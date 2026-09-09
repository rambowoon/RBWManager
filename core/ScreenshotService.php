<?php
namespace RamboWoon;

class ScreenshotService
{
    private $baseDir;
    private $screenshotsDir;
    private $cacheDir;

    public function __construct($baseDir = null)
    {
        $this->baseDir = $baseDir ?: dirname(__DIR__);
        $this->screenshotsDir = $this->baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'screenshots';
        $this->cacheDir = $this->baseDir . DIRECTORY_SEPARATOR . 'cache';

        if (!is_dir($this->screenshotsDir)) {
            @mkdir($this->screenshotsDir, 0777, true);
        }
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    public function getScreenshotKey($category, $projectName)
    {
        $catKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($category ?: '', '/\\'));
        $projKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($projectName));
        return ($catKey !== '' ? $catKey . '__' : '') . $projKey;
    }

    public function getScreenshotPath($category, $projectName)
    {
        return $this->screenshotsDir . DIRECTORY_SEPARATOR . $this->getScreenshotKey($category, $projectName) . '.webp';
    }

    public function getScreenshotUrl($category, $projectName)
    {
        $path = $this->getScreenshotPath($category, $projectName);
        if (file_exists($path) && filesize($path) > 500) {
            $key = $this->getScreenshotKey($category, $projectName);
            return 'data/screenshots/' . $key . '.webp?v=' . filemtime($path);
        }
        return null;
    }

    public function findBrowserExecutable()
    {
        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                return $cand;
            }
        }
        return null;
    }

    public function determineBestUrl($project, $config = [])
    {
        // 1. Check Production
        $prod = $config['prod'] ?? [];
        if (!empty($prod['web_domain'])) {
            $domain = $prod['web_domain'];
            $ssl = !empty($prod['ssl']);
            if (!preg_match('/^https?:\/\//i', $domain)) {
                $domain = ($ssl ? 'https://' : 'http://') . $domain;
            }
            return rtrim($domain, '/') . '/';
        }

        // 2. Check Demo
        if (!empty($config['demo_url'])) {
            $dUrl = $config['demo_url'];
            if (!preg_match('/^https?:\/\//i', $dUrl)) {
                $dUrl = 'http://' . $dUrl;
            }
            return rtrim($dUrl, '/') . '/';
        }
        $demoDeployed = $config['deployed']['demo'] ?? null;
        if (!empty($demoDeployed['url'])) {
            $dUrl = $demoDeployed['url'];
            if (!preg_match('/^https?:\/\//i', $dUrl)) {
                $dUrl = 'http://' . $dUrl;
            }
            return rtrim($dUrl, '/') . '/';
        }

        // 3. Fallback Localhost
        if (!empty($project['relPath'])) {
            $rel = str_replace('\\', '/', $project['relPath']);
            return 'http://localhost/' . trim($rel, '/') . '/';
        }

        return null;
    }

    public function capture($category, $projectName, $customUrl = null, $project = null, $config = [])
    {
        $targetUrl = $customUrl ?: $this->determineBestUrl($project, $config);
        if (!$targetUrl) {
            return ['status' => 'error', 'message' => 'Không tìm thấy đường dẫn website hợp lệ để chụp ảnh.'];
        }

        // Kiểm tra website có hoạt động bình thường không (tránh 404, 500, lỗi máy chủ, chưa cấu hình)
        $health = $this->checkWebsiteHealth($targetUrl);
        if (!$health['ok']) {
            return [
                'status' => 'error',
                'message' => $health['message'],
                'targetUrl' => $targetUrl,
                'httpCode' => $health['code'] ?? 0
            ];
        }

        $browserPath = $this->findBrowserExecutable();
        if (!$browserPath) {
            return ['status' => 'error', 'message' => 'Không tìm thấy trình duyệt Chrome hoặc Edge trên hệ thống để chụp ảnh màn hình.'];
        }

        $tempPng = $this->cacheDir . DIRECTORY_SEPARATOR . 'shot_' . uniqid() . '.png';
        $finalWebp = $this->getScreenshotPath($category, $projectName);
        $nodeScript = __DIR__ . DIRECTORY_SEPARATOR . 'capture_fullpage.js';

        @set_time_limit(90);

        // 1. Ưu tiên sử dụng Node.js CDP script để chụp Full Page (từ Header đến Footer)
        if (file_exists($nodeScript)) {
            $cmd = sprintf('node "%s" "%s" "%s" 2>&1', $nodeScript, $targetUrl, $finalWebp);
            $output = shell_exec($cmd);

            if (file_exists($finalWebp) && filesize($finalWebp) > 1000) {
                // Tối ưu chiều rộng 768px giữ nguyên tỷ lệ chiều cao full-page
                $this->resizeFullPageWebp($finalWebp, 768, 80);

                return [
                    'status' => 'success',
                    'message' => 'Chụp ảnh full page website thành công!',
                    'url' => $this->getScreenshotUrl($category, $projectName),
                    'targetUrl' => $targetUrl
                ];
            }
        }

        // 2. Fallback: Sử dụng Chrome CLI với chiều cao dài
        $browserPath = $this->findBrowserExecutable();
        if (!$browserPath) {
            return ['status' => 'error', 'message' => 'Không tìm thấy trình duyệt Chrome hoặc Edge trên hệ thống để chụp ảnh màn hình.'];
        }

        $tempPng = $this->cacheDir . DIRECTORY_SEPARATOR . 'shot_' . uniqid() . '.png';
        $cmdFallback = sprintf(
            '"%s" --headless=new --disable-gpu --no-sandbox --hide-scrollbars --window-size=1280,3200 --screenshot="%s" "%s" 2>&1',
            $browserPath,
            $tempPng,
            $targetUrl
        );

        $output = shell_exec($cmdFallback);

        if (!file_exists($tempPng) || filesize($tempPng) < 1000) {
            @unlink($tempPng);
            return [
                'status' => 'error',
                'message' => 'Trình duyệt không thể chụp được ảnh trang web. Vui lòng kiểm tra lại đường dẫn: ' . $targetUrl,
                'debug' => substr((string)$output, 0, 300)
            ];
        }

        $converted = $this->optimizeAndSaveWebp($tempPng, $finalWebp, 768, 80);
        @unlink($tempPng);

        if (!$converted) {
            return ['status' => 'error', 'message' => 'Lỗi khi nén ảnh thumbnail sang định dạng WebP.'];
        }

        return [
            'status' => 'success',
            'message' => 'Chụp ảnh full page website thành công!',
            'url' => $this->getScreenshotUrl($category, $projectName),
            'targetUrl' => $targetUrl
        ];
    }

    private function resizeFullPageWebp($srcWebpPath, $maxWidth = 768, $quality = 80)
    {
        if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
            return true;
        }

        $srcImg = @imagecreatefromwebp($srcWebpPath);
        if (!$srcImg) return true;

        $origW = imagesx($srcImg);
        $origH = imagesy($srcImg);

        if ($origW <= $maxWidth) {
            imagedestroy($srcImg);
            return true;
        }

        $newW = $maxWidth;
        $newH = (int) round(($origH * $newW) / $origW);

        $dstImg = imagecreatetruecolor($newW, $newH);
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        @unlink($srcWebpPath);
        $res = imagewebp($dstImg, $srcWebpPath, $quality);

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return $res;
    }

    private function optimizeAndSaveWebp($srcPngPath, $destWebpPath, $maxWidth = 768, $quality = 80)
    {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagewebp')) {
            return @copy($srcPngPath, str_replace('.webp', '.png', $destWebpPath));
        }

        $srcImg = @imagecreatefrompng($srcPngPath);
        if (!$srcImg) {
            return false;
        }

        $origW = imagesx($srcImg);
        $origH = imagesy($srcImg);

        $newW = min($origW, $maxWidth);
        $newH = (int) round(($origH * $newW) / $origW);

        $dstImg = imagecreatetruecolor($newW, $newH);
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $res = imagewebp($dstImg, $destWebpPath, $quality);

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return $res;
    }

    public function deleteScreenshot($category, $projectName)
    {
        $path = $this->getScreenshotPath($category, $projectName);
        if (file_exists($path)) {
            @unlink($path);
            return true;
        }
        return false;
    }

    public function checkWebsiteHealth($url)
    {
        if (!function_exists('curl_init')) {
            return ['ok' => true, 'code' => 200];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HEADER => false
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return [
                'ok' => false,
                'code' => $httpCode ?: 0,
                'message' => 'Không thể kết nối đến website (' . $curlErr . '). Vui lòng kiểm tra lại cấu hình domain hoặc DNS.'
            ];
        }

        if ($httpCode >= 400) {
            $msg = ($httpCode == 404) 
                ? 'Website trả về lỗi 404 (Trang không tồn tại).'
                : (($httpCode >= 500) 
                    ? "Website trả về lỗi Server $httpCode (500/502/503)."
                    : "Website trả về mã lỗi HTTP $httpCode.");
            return [
                'ok' => false,
                'code' => $httpCode,
                'message' => $msg . ' Đã hủy chụp ảnh để tránh lưu trang lỗi.'
            ];
        }

        if (is_string($body) && strlen($body) > 0) {
            $bodySample = substr(strip_tags($body), 0, 2000);
            $errorPatterns = [
                '/Apache is functioning normally/i' => 'Trang mặc định của Apache (chưa cấu hình source code)',
                '/Welcome to nginx!/i' => 'Trang mặc định của Nginx (chưa deploy source code)',
                '/Default Web Site Page/i' => 'Trang mặc định của Hosting/Web Server',
                '/Error establishing a database connection/i' => 'Lỗi kết nối cơ sở dữ liệu (Database Error)',
                '/Database Error/i' => 'Lỗi cơ sở dữ liệu (Database Error)',
                '/500 Internal Server Error/i' => 'Lỗi 500 Internal Server Error',
                '/404 Not Found/i' => 'Lỗi 404 Not Found',
                '/Site under construction/i' => 'Trang web chưa hoàn thiện (Site under construction)'
            ];

            foreach ($errorPatterns as $pattern => $reason) {
                if (preg_match($pattern, $bodySample)) {
                    return [
                        'ok' => false,
                        'code' => $httpCode,
                        'message' => "Website đang ở trạng thái: $reason. Đã hủy chụp ảnh."
                    ];
                }
            }
        }

        return ['ok' => true, 'code' => $httpCode];
    }
}

