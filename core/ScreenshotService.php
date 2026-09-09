<?php
namespace RamboWoon;

class ScreenshotService
{
    private $baseDir;
    private $screenshotsDir;
    private $cacheDir;

    public function __construct($baseDir = null)
    {
        $dir = $baseDir ?: dirname(__DIR__);
        if (strpos($dir, '\\\\.\\') === 0 || strpos($dir, '\\\\?\\') === 0) {
            $dir = substr($dir, 4);
        }
        $this->baseDir = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
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

    public function findNodeExecutable()
    {
        $candidates = [
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            'D:\\RBWStack\\bin\\node\\node.exe',
            'node'
        ];

        foreach ($candidates as $cand) {
            if ($cand !== 'node' && file_exists($cand)) {
                return $cand;
            }
        }
        return 'node';
    }

    public function determineBestUrl($project, $config = [])
    {
        // 1. Ưu tiên Production
        $prod = $config['prod'] ?? [];
        if (!empty($prod['web_domain'])) {
            $domain = $prod['web_domain'];
            $ssl = !empty($prod['ssl']);
            if (!preg_match('/^https?:\/\//i', $domain)) {
                $domain = ($ssl ? 'https://' : 'http://') . $domain;
            }
            return rtrim($domain, '/') . '/';
        }

        // 2. Kế đến Demo
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

        // 2b. Nếu đã deploy Demo (có deployed['demo'] hoặc lock_demo) -> Tự động tìm demo server và ghép relPath
        if (!empty($demoDeployed) || !empty($config['lock_demo'])) {
            $demoId = $demoDeployed['demo_server_id'] ?? 'legacy';
            $globalPath = $this->baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'demo_config.json';
            $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
            
            $demoDomain = 'demo92.nasanivietnam.net';
            if (!empty($gConfig['demo_list']) && is_array($gConfig['demo_list'])) {
                foreach ($gConfig['demo_list'] as $d) {
                    if (($d['id'] ?? '') === $demoId) {
                        $demoDomain = $d['web_domain'] ?? $demoDomain;
                        break;
                    }
                }
            } elseif (!empty($gConfig['web_domain'])) {
                $demoDomain = $gConfig['web_domain'];
            }

            $demoDomain = preg_replace('/^https?:\/\//i', '', rtrim($demoDomain, '/'));
            
            $rel = '';
            if (!empty($project['relPath'])) {
                $rel = str_replace('\\', '/', $project['relPath']);
            } elseif (!empty($project['name'])) {
                $cat = !empty($project['category']) ? $project['category'] : '';
                $rel = ($cat ? $cat . '/' : '') . $project['name'];
            }

            if (!empty($rel)) {
                $ssl = !empty($config['demo']['ssl']);
                return ($ssl ? 'https://' : 'http://') . $demoDomain . '/' . trim($rel, '/') . '/';
            }
        }

        // Tuyệt đối không fallback về localhost
        return null;
    }

    public function capture($category, $projectName, $customUrl = null, $project = null, $config = [])
    {
        // Tự động tìm config và danh mục thực tế nếu category truyền vào rỗng hoặc sai
        if (empty($config)) {
            $configPath = $this->baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'projects.json';
            if (file_exists($configPath)) {
                $allConfigs = json_decode(file_get_contents($configPath), true) ?: [];
                if (!empty($category) && isset($allConfigs[$category][$projectName])) {
                    $config = $allConfigs[$category][$projectName];
                } else {
                    foreach ($allConfigs as $catName => $catProjects) {
                        if (is_array($catProjects) && isset($catProjects[$projectName])) {
                            $category = $catName;
                            $config = $catProjects[$projectName];
                            break;
                        }
                    }
                }
            }
        }

        if (!$project) {
            $project = [
                'name' => $projectName,
                'category' => $category,
                'relPath' => ($category ? $category . '/' : '') . $projectName
            ];
        } elseif (empty($project['category']) && !empty($category)) {
            $project['category'] = $category;
            $project['relPath'] = $category . '/' . $projectName;
        }

        $targetUrl = $customUrl ?: $this->determineBestUrl($project, $config);
        if (!$targetUrl) {
            return [
                'status' => 'error',
                'message' => 'Dự án chưa có thông tin tên miền Production hoặc Demo.'
            ];
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
            return ['status' => 'error', 'message' => 'Không tìm thấy trình duyệt Chrome hoặc Edge trên hệ thống.'];
        }

        $tempPng = $this->cacheDir . DIRECTORY_SEPARATOR . 'shot_' . uniqid() . '.png';
        $finalWebp = $this->getScreenshotPath($category, $projectName);
        $nodeScript = $this->baseDir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'capture_fullpage.js';

        @set_time_limit(90);

        // 1. Ưu tiên sử dụng Node.js CDP script để chụp Full Page (từ Header đến Footer)
        if (file_exists($nodeScript)) {
            $nodeExec = $this->findNodeExecutable();
            $cmd = sprintf('"%s" "%s" "%s" "%s"', $nodeExec, $nodeScript, $targetUrl, $finalWebp);
            $procRes = $this->executeCommand($cmd);
            $output = $procRes['stdout'] ?: $procRes['output'];

            if (file_exists($finalWebp) && filesize($finalWebp) > 1000) {
                // Tối ưu chiều rộng 768px giữ nguyên tỷ lệ chiều cao full-page
                $this->resizeFullPageWebp($finalWebp, 768, 80);

                $shotUrl = $this->getScreenshotUrl($category, $projectName);
                return [
                    'status' => 'success',
                    'message' => 'Chụp ảnh full page website thành công!',
                    'url' => $shotUrl,
                    'screenshot' => $shotUrl,
                    'targetUrl' => $targetUrl
                ];
            }

            // Nếu Node script đã bắt được lỗi cụ thể (ví dụ Laravel ErrorException, 404, 500, etc.)
            $decoded = json_decode((string)$output, true);
            if ($decoded && ($decoded['status'] ?? '') === 'error') {
                return [
                    'status' => 'error',
                    'message' => $decoded['message'] ?? 'Lỗi khi chụp trang web',
                    'targetUrl' => $targetUrl
                ];
            }
        }

        // 2. Fallback: Sử dụng Chrome CLI với chiều cao dài
        $browserPath = $this->findBrowserExecutable();
        if (!$browserPath) {
            return ['status' => 'error', 'message' => 'Không tìm thấy trình duyệt Chrome hoặc Edge trên hệ thống.'];
        }

        $tempPng = $this->cacheDir . DIRECTORY_SEPARATOR . 'shot_' . uniqid() . '.png';
        $cmdFallback = sprintf(
            '"%s" --headless=new --disable-gpu --no-sandbox --disable-setuid-sandbox --ignore-certificate-errors --allow-running-insecure-content --hide-scrollbars --window-size=1280,3200 --screenshot="%s" "%s"',
            $browserPath,
            $tempPng,
            $targetUrl
        );

        $procFallback = $this->executeCommand($cmdFallback);
        $output = $procFallback['output'];

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

        $shotUrl = $this->getScreenshotUrl($category, $projectName);
        return [
            'status' => 'success',
            'message' => 'Chụp ảnh full page website thành công!',
            'url' => $shotUrl,
            'screenshot' => $shotUrl,
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
            $rawSample = substr($body, 0, 5000);
            $textSample = substr(strip_tags($body), 0, 3000);

            $errorPatterns = [
                // Laravel / Framework Exceptions
                '/ErrorException/i' => 'Lỗi Laravel ErrorException (Property/Variable/Method không tồn tại)',
                '/FatalErrorException/i' => 'Lỗi Laravel FatalErrorException',
                '/FatalThrowableError/i' => 'Lỗi Laravel FatalThrowableError',
                '/Undefined (property|variable|index|offset)/i' => 'Lỗi PHP: Undefined property/variable/array index',
                '/Attempt to read property .* on (null|bool|string|array)/i' => 'Lỗi PHP: Attempt to read property on null',
                '/Trying to get property .* of non-object/i' => 'Lỗi PHP: Trying to get property of non-object',
                '/Call to undefined (function|method)/i' => 'Lỗi PHP: Call to undefined function/method',
                '/Whoops!/i' => 'Lỗi giao diện debug Whoops/Laravel',
                '/Whoops, looks like something went wrong/i' => 'Lỗi trang mặc định Laravel Whoops',
                '/View \[.*?\] not found/i' => 'Lỗi View Blade template không tìm thấy',
                '/Uncaught (Exception|Error)/i' => 'Lỗi Uncaught Exception/Error',
                '/Fatal error:/i' => 'Lỗi PHP Fatal error',
                '/Parse error:/i' => 'Lỗi cú pháp PHP (Parse error)',

                // Database Errors
                '/Error establishing a database connection/i' => 'Lỗi kết nối cơ sở dữ liệu (Database Error)',
                '/Database Error/i' => 'Lỗi cơ sở dữ liệu (Database Error)',
                '/SQLSTATE\[/i' => 'Lỗi truy vấn cơ sở dữ liệu (SQLSTATE Error)',

                // Web Server Defaults / Not Configured
                '/Apache is functioning normally/i' => 'Trang mặc định của Apache (chưa cấu hình source code)',
                '/Welcome to nginx!/i' => 'Trang mặc định của Nginx (chưa deploy source code)',
                '/Default Web Site Page/i' => 'Trang mặc định của Hosting/Web Server',
                '/Site under construction/i' => 'Trang web chưa hoàn thiện (Site under construction)',

                // Generic HTTP Errors
                '/500 Internal Server Error/i' => 'Lỗi 500 Internal Server Error',
                '/404 Not Found/i' => 'Lỗi 404 Not Found'
            ];

            foreach ($errorPatterns as $pattern => $reason) {
                if (preg_match($pattern, $rawSample) || preg_match($pattern, $textSample)) {
                    return [
                        'ok' => false,
                        'code' => $httpCode,
                        'message' => "Website đang gặp sự cố: $reason. Đã hủy chụp ảnh."
                    ];
                }
            }
        }

        return ['ok' => true, 'code' => $httpCode];
    }

    private function executeCommand($cmd, $cwd = null)
    {
        $cleanCwd = $cwd ?: $this->baseDir;
        if (strpos($cleanCwd, '\\\\.\\') === 0 || strpos($cleanCwd, '\\\\?\\') === 0) {
            $cleanCwd = substr($cleanCwd, 4);
        }

        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];

        $process = proc_open($cmd, $descriptorspec, $pipes, $cleanCwd);
        if (!is_resource($process)) {
            return ['code' => -1, 'stdout' => '', 'stderr' => 'proc_open failed', 'output' => 'proc_open failed'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $code = proc_close($process);
        return [
            'code' => $code,
            'stdout' => trim((string)$stdout),
            'stderr' => trim((string)$stderr),
            'output' => trim($stdout . "\n" . $stderr)
        ];
    }
}

