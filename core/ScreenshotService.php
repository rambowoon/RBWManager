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

        $browserPath = $this->findBrowserExecutable();
        if (!$browserPath) {
            return ['status' => 'error', 'message' => 'Không tìm thấy trình duyệt Chrome hoặc Edge trên hệ thống để chụp ảnh màn hình.'];
        }

        $tempPng = $this->cacheDir . DIRECTORY_SEPARATOR . 'shot_' . uniqid() . '.png';
        $finalWebp = $this->getScreenshotPath($category, $projectName);

        // Command to capture 1280x720 screenshot via headless Chrome/Edge
        $cmd = sprintf(
            '"%s" --headless=new --disable-gpu --no-sandbox --hide-scrollbars --window-size=1280,720 --screenshot="%s" "%s" 2>&1',
            $browserPath,
            $tempPng,
            $targetUrl
        );

        @set_time_limit(60);
        $output = shell_exec($cmd);

        if (!file_exists($tempPng) || filesize($tempPng) < 1000) {
            @unlink($tempPng);
            return [
                'status' => 'error',
                'message' => 'Trình duyệt không thể chụp được ảnh trang web. Vui lòng kiểm tra lại đường dẫn: ' . $targetUrl,
                'debug' => substr((string)$output, 0, 300)
            ];
        }

        // Resize and optimize to WebP (640x360 for high-density crisp preview)
        $converted = $this->optimizeAndSaveWebp($tempPng, $finalWebp, 640, 360, 82);
        @unlink($tempPng);

        if (!$converted) {
            return ['status' => 'error', 'message' => 'Lỗi khi nén ảnh thumbnail sang định dạng WebP.'];
        }

        return [
            'status' => 'success',
            'message' => 'Chụp ảnh màn hình website thành công!',
            'url' => $this->getScreenshotUrl($category, $projectName),
            'targetUrl' => $targetUrl
        ];
    }

    private function optimizeAndSaveWebp($srcPngPath, $destWebpPath, $targetW = 640, $targetH = 360, $quality = 82)
    {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagewebp')) {
            // Fallback if GD is missing: just copy PNG
            return @copy($srcPngPath, str_replace('.webp', '.png', $destWebpPath));
        }

        $srcImg = @imagecreatefrompng($srcPngPath);
        if (!$srcImg) {
            return false;
        }

        $origW = imagesx($srcImg);
        $origH = imagesy($srcImg);

        $dstImg = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);

        // Resample with high quality interpolation
        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

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
}
