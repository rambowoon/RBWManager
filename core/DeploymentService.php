<?php

namespace RamboWoon;

class DeploymentService
{
    private $baseDir;

    public function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    public function generateDemoDbName($category, $projectName, $manualSuffix = null)
    {
        // Category suffix: Extract YYMM or similar numbers
        // e.g. "2026_06" -> year 2026, month 06
        // e.g. "2026/thang6" -> year 2026, month 06
        // e.g. "2026/26t6" -> year 2026, month 06
        $categoryNormalized = str_replace('\\', '/', $category);
        $year = '2026';
        $month = '06';

        if (preg_match('/^(\d{4})_(\d{2})$/', $categoryNormalized, $m)) {
            $year = $m[1];
            $month = $m[2];
        } elseif (preg_match('/^(\d{4})\/thang(\d{1,2})$/i', $categoryNormalized, $m)) {
            $year = $m[1];
            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        } elseif (preg_match('/^(\d{4})\/(\d{2})t(\d{1,2})$/i', $categoryNormalized, $m)) {
            $year = $m[1];
            $month = str_pad($m[3], 2, '0', STR_PAD_LEFT);
        } else {
            // Fallback: extract any digits
            $clean = preg_replace('/[^0-9]/', '', $categoryNormalized);
            if (strlen($clean) >= 6) {
                $year = substr($clean, 0, 4);
                $month = substr($clean, 4, 2);
            } elseif (strlen($clean) >= 2) {
                $month = substr($clean, -2);
            }
        }

        $yearDigit = substr($year, -1);
        $prefix_cat = $yearDigit . $month;

        if (!empty($manualSuffix)) {
            $name = preg_replace('/[^a-z0-9]/', '', strtolower($manualSuffix));
        } else {
            // Take only the part before underscores to keep it clean (e.g. rambowoon_0123 -> rambowoon)
            $parts = explode('_', strtolower($projectName));
            $cleanBase = preg_replace('/[^a-z]/', '', $parts[0]);
            $name = substr($cleanBase, 0, 10);
        }

        $suffix = $prefix_cat . $name;
        // DirectAdmin 24-char limit check: prefix (11) + suffix (13)
        return substr($suffix, 0, 13);
    }

    public function pack($projectPath, $zipFile, $use7zip = false, $jobId = null)
    {
        if (file_exists($zipFile)) @unlink($zipFile);

        if ($use7zip) {
            $exe7z = $this->get7zExecutable();
            if ($exe7z) {
                $exclude = '-xr!".agents" -xr!"thumbs" -xr!"watermarks" -xr!"caches" -xr!"dist.zip" -xr!"dist.sql" -xr!"vite.config.js" -xr!"README.md" -xr!".gitignore"';
                $cmd = "cd /d \"$projectPath\" && $exe7z a -tzip -mx=9 -bsp1 \"$zipFile\" $exclude .";
                $returnVar = $this->runCommandWithProgress($cmd, $zipFile, $jobId);
                if ($returnVar === 0 && is_file($zipFile) && filesize($zipFile) > 0) {
                    $finalSize = $this->formatBytes(filesize($zipFile));
                    if ($jobId && function_exists('writeJobLog')) {
                        writeJobLog($jobId, ['status' => 'info', 'log' => "✅ Nén thành công! Dung lượng cuối cùng: $finalSize"]);
                    }
                    return true;
                }
            } else {
                if ($jobId && function_exists('writeJobLog')) {
                    writeJobLog($jobId, ['status' => 'info', 'log' => "⚠️ Không tìm thấy lệnh 7z trong PATH hoặc Program Files."]);
                }
            }
        }

        // Fallback to PHP ZipArchive
        if (class_exists('ZipArchive')) {
            if ($jobId && function_exists('writeJobLog')) {
                writeJobLog($jobId, ['status' => 'info', 'log' => "⚠️ Không sử dụng hoặc lỗi 7-Zip. Đang nén bằng PHP ZipArchive..."]);
            }
            $zip = new \ZipArchive();
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($projectPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                
                $excludes = ['.agents', 'thumbs', 'watermarks', 'caches', 'dist.zip', 'dist.sql', 'vite.config.js', 'README.md', '.gitignore'];
                $count = 0;
                
                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($projectPath) + 1);
                        $relativePathNormalized = str_replace('\\', '/', $relativePath);
                        
                        $skip = false;
                        foreach ($excludes as $ex) {
                            if (strpos($relativePathNormalized, $ex) === 0 || strpos($relativePathNormalized, '/' . $ex) !== false) {
                                $skip = true;
                                break;
                            }
                        }
                        if ($skip) continue;
                        
                        $zip->addFile($filePath, $relativePathNormalized);
                        $count++;
                        if ($count % 500 === 0) {
                            if ($jobId && function_exists('writeJobLog')) {
                                writeJobLog($jobId, ['status' => 'info', 'log' => "📦 Đang nén bằng PHP... Đã thêm $count tệp..."]);
                            }
                        }
                    }
                }
                
                $zip->close();
                if (is_file($zipFile) && filesize($zipFile) > 0) {
                    $finalSize = $this->formatBytes(filesize($zipFile));
                    if ($jobId && function_exists('writeJobLog')) {
                        writeJobLog($jobId, ['status' => 'info', 'log' => "✅ Nén thành công bằng PHP ZipArchive! Dung lượng cuối cùng: $finalSize"]);
                    }
                    return true;
                }
            }
        }
        
        return false;
    }

    public function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function runCommandWithProgress($cmd, $zipFile, $jobId)
    {
        $stdoutFile = tempnam(sys_get_temp_dir(), 'cmd_out_');
        $stderrFile = tempnam(sys_get_temp_dir(), 'cmd_err_');

        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["file", $stdoutFile, "w"],
            2 => ["file", $stderrFile, "w"]
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (is_resource($process)) {
            $terminated = false;
            register_shutdown_function(function() use ($process, &$terminated) {
                if (!$terminated) {
                    $status = proc_get_status($process);
                    if ($status && $status['running']) {
                        @proc_terminate($process);
                    }
                }
            });

            $lastSize = 0;
            $lastLogTime = 0;
            $lastPercent = '';
            $stdout = '';
            $stderr = '';

            while (true) {
                $status = proc_get_status($process);

                if (file_exists($stdoutFile)) {
                    $stdout = (string)file_get_contents($stdoutFile);
                }
                if (file_exists($stderrFile)) {
                    $stderr = (string)file_get_contents($stderrFile);
                }

                if (!$status['running']) {
                    break;
                }

                $now = time();
                
                // 1. Try to get progress from 7z stdout
                $progressPercent = null;
                if (preg_match_all('/([0-9]{1,3})%/', $stdout, $matches)) {
                    $progressPercent = end($matches[1]) . '%';
                }

                if ($progressPercent !== null) {
                    if ($progressPercent !== $lastPercent && ($now - $lastLogTime) >= 1) {
                        if ($jobId && function_exists('writeJobLog')) {
                            writeJobLog($jobId, ['status' => 'info', 'log' => "📦 Đang nén... Tiến trình: $progressPercent"]);
                        }
                        $lastPercent = $progressPercent;
                        $lastLogTime = $now;
                    }
                } else {
                    // 2. Fallback to file size (e.g. for tar)
                    clearstatcache(true, $zipFile);
                    if (file_exists($zipFile)) {
                        $currentSize = filesize($zipFile);
                        if ($currentSize !== $lastSize && ($now - $lastLogTime) >= 1) {
                            $sizeStr = $this->formatBytes($currentSize);
                            if ($jobId && function_exists('writeJobLog')) {
                                writeJobLog($jobId, ['status' => 'info', 'log' => "📦 Đang nén... Dung lượng hiện tại: $sizeStr"]);
                            }
                            $lastSize = $currentSize;
                            $lastLogTime = $now;
                        }
                    }
                }
                usleep(200000); // 0.2 seconds
            }

            fclose($pipes[0]);
            $terminated = true;
            $exitCode = proc_close($process);

            if (file_exists($stdoutFile)) {
                $stdout = (string)file_get_contents($stdoutFile);
                @unlink($stdoutFile);
            }
            if (file_exists($stderrFile)) {
                $stderr = (string)file_get_contents($stderrFile);
                @unlink($stderrFile);
            }

            if ($exitCode !== 0 && $jobId && function_exists('writeJobLog')) {
                writeJobLog($jobId, ['status' => 'info', 'log' => "❌ Lệnh lỗi (Exit Code: $exitCode). Cmd: $cmd"]);
                if (!empty($stdout)) {
                    writeJobLog($jobId, ['status' => 'info', 'log' => "STDOUT: " . substr(trim($stdout), 0, 500)]);
                }
                if (!empty($stderr)) {
                    writeJobLog($jobId, ['status' => 'info', 'log' => "STDERR: " . substr(trim($stderr), 0, 500)]);
                }
            }
            return $exitCode;
        }

        @unlink($stdoutFile);
        @unlink($stderrFile);
        return -1;
    }

    private function get7zExecutable()
    {
        @exec("7z --help", $out, $res);
        if ($res === 0) return "7z";

        $paths = [
            'C:\\Program Files\\7-Zip\\7z.exe',
            'C:\\Program Files (x86)\\7-Zip\\7z.exe'
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return '"' . $path . '"';
            }
        }
        return false;
    }

    public function exportDb($projectPath, $sqlFile)
    {
        $dbLocal = ['host' => 'localhost', 'name' => '', 'user' => 'root', 'pass' => ''];
        $envPath = $projectPath . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath);
            foreach ($lines as $line) {
                if (strpos($line, 'DB_DATABASE=') === 0) $dbLocal['name'] = trim(substr($line, 12));
                if (strpos($line, 'DB_USERNAME=') === 0) $dbLocal['user'] = trim(substr($line, 12));
                if (strpos($line, 'DB_PASSWORD=') === 0) $dbLocal['pass'] = trim(substr($line, 12));
                if (strpos($line, 'DB_HOST=') === 0) $dbLocal['host'] = trim(substr($line, 8));
            }
        }
        if (!$dbLocal['name']) return "Khong tim thay DB_DATABASE trong file .env";

        // Override mysqldump with the robust native PDO exporter to avoid PATH issues
        $res = $this->exportLocalDatabase($dbLocal, $sqlFile);
        return $res;
    }

    public function upload($config, $files, $subPath = '')
    {
        file_put_contents(__DIR__ . '/debug_upload.txt', "ARG1 (Config): " . print_r($config, true) . "\n" . "ARG2 (Files): " . print_r($files, true) . "\n", FILE_APPEND);

        $ftpRoot = !empty($config['ftp_root']) ? $config['ftp_root'] : '/public_html';
        $ftpUrlBase = "ftp://{$config['ftp_host']}/" . ltrim(rtrim($ftpRoot, '/'), '/') . '/';
        if ($subPath) $ftpUrlBase .= trim($subPath, '/') . '/';
        $userPwd = "{$config['ftp_user']}:{$config['ftp_pass']}";

        // Step 0: Ensure the project directory exists on server before any upload
        if ($subPath) {
            $parts = array_filter(explode('/', str_replace('\\', '/', $subPath)));
            $currentParent = rtrim($config['ftp_root'], '/');
            $numParts = count($parts);
            $i = 0;

            foreach ($parts as $part) {
                $i++;
                $isLast = ($i === $numParts);

                // Fetch existing items in $currentParent
                $relativeRoot = ltrim(rtrim($currentParent, '/'), '/');
                $parentUrl = "ftp://{$config['ftp_host']}/" . ($relativeRoot ? $relativeRoot : '');
                $existingItems = RemoteClient::listFtpDirectory($parentUrl, $userPwd);

                if ($isLast) {
                    $dirName = $part;
                    $hasOld = false;
                    $oldPatterns = [
                        "_old_" . $dirName,
                        "_old" . $dirName,
                        $dirName . "_old",
                        $dirName . "-old"
                    ];

                    foreach ($existingItems as $item) {
                        if (in_array($item, $oldPatterns)) {
                            $hasOld = true;
                            break;
                        }
                    }

                    if ($hasOld) {
                        throw new \Exception("❌ TỪ CHỐI TRIỂN KHAI: Dự án này đã được triển khai (Publish) từ trước cho bên khách hàng (Tìm thấy thư mục có tiền/hậu tố _old trên host).");
                    }

                    // Auto-create directory if not exists
                    if (!in_array($dirName, $existingItems)) {
                        $daRes = RemoteClient::makeDirViaDA($config, $dirName, $currentParent);
                        if (strpos($daRes, 'error=1') !== false) {
                            throw new \Exception("Không thể tạo thư mục dự án mới: " . $daRes);
                        }
                    }
                } else {
                    // Ensure intermediate directory exists
                    if (!in_array($part, $existingItems)) {
                        $daRes = RemoteClient::makeDirViaDA($config, $part, $currentParent);
                        if (strpos($daRes, 'error=1') !== false) {
                            throw new \Exception("Không thể tạo thư mục trung gian: " . $daRes);
                        }
                    }
                    $currentParent = rtrim($currentParent, '/') . '/' . $part;
                }
            }
        }

        if (isset($files['bridge.php'])) {
            $remoteDir = !empty($config['ftp_root']) ? $config['ftp_root'] : '/public_html';
            if ($subPath) $remoteDir = rtrim($remoteDir, '/') . '/' . trim($subPath, '/');
            RemoteClient::deleteViaDA($config, $remoteDir, 'bridge.php');
        }

        foreach ($files as $remoteName => $localPath) {
            try {
                $status = RemoteClient::uploadFtp($ftpUrlBase . $remoteName, $userPwd, $localPath);
                if ($status !== true) {
                    throw new \Exception(is_string($status) ? $status : "FTP Fail");
                }
            } catch (\Exception $e) {
                // FALLBACK: Try via DirectAdmin API for ALL files
                $remoteDir = !empty($config['ftp_root']) ? $config['ftp_root'] : '/public_html';
                if ($subPath) $remoteDir = rtrim($remoteDir, '/') . '/' . trim($subPath, '/');

                $daRes = RemoteClient::uploadViaDA($config, $localPath, $remoteDir);
                $decodedDaRes = urldecode($daRes);
                
                // DirectAdmin success indicators: error=0 OR specific text
                if (strpos($daRes, 'error=0') !== false || 
                    stripos($decodedDaRes, 'Upload successful') !== false || 
                    strpos($daRes, 'file1=') !== false) {
                    continue; // Success via DA
                }
                throw new \Exception("Cả FTP và DA đều thất bại cho file $remoteName. FTP: " . $e->getMessage() . " | DA: " . $daRes);
            }
        }
    }

    public function remoteFileExists($config, $remotePath)
    {
        $ftpUrl = "ftp://{$config['ftp_host']}" . rtrim($config['ftp_root'], '/') . '/' . ltrim($remotePath, '/');
        $userPwd = "{$config['ftp_user']}:{$config['ftp_pass']}";
        return RemoteClient::checkFileExistsFtp($ftpUrl, $userPwd);
    }

    public function createDirectAdminDb($config, $dbSuffix, $dbPass)
    {
        $daPort = $config['da_port'] ?? '1111';
        $daHost = $config['ftp_host'];
        $mainUser = !empty($config['da_user']) ? $config['da_user'] : $config['ftp_user'];
        $auth = "{$mainUser}:{$config['ftp_pass']}";

        $data = [
            'action' => 'create',
            'name' => $dbSuffix,
            'user' => $dbSuffix,
            'passwd' => $dbPass,
            'passwd2' => $dbPass,
            'create' => 'Create'
        ];

        // Thử HTTPS trước
        $daUrl = "https://{$daHost}:{$daPort}/CMD_API_DATABASES";
        $res = RemoteClient::post($daUrl, $data, $auth);

        // Nếu lỗi SSL (Wrong version number) thì thử lại với HTTP
        if (strpos($res, 'error:0A00010B') !== false || strpos($res, 'wrong version number') !== false) {
            $daUrl = "http://{$daHost}:{$daPort}/CMD_API_DATABASES";
            $res = RemoteClient::post($daUrl, $data, $auth);
        }

        // CẢI TIẾN: Giải mã URL trước khi kiểm tra để bắt được %20, +, vv.
        $decodedRes = urldecode($res);
        $isAlreadyExists = (stripos($decodedRes, 'already exists') !== false || stripos($decodedRes, 'already user') !== false);

        if (strpos($res, '"status":"error"') !== false && !$isAlreadyExists) {
            $decoded = json_decode($res, true);
            // Ignore if the actual DA message says it already exists
            if (isset($decoded['body']) && (stripos($decoded['body'], 'exists') !== false || stripos($decoded['body'], 'already') !== false)) {
                $isAlreadyExists = true;
            } else {
                $decoded['message'] .= " (Target URL: $daUrl)";
                return json_encode($decoded);
            }
        }

        // Nếu DA báo đã tồn tại, thực hiện cập nhật password để đảm bảo đồng bộ
        if ($isAlreadyExists) {
            $daUserUrl = "https://{$daHost}:{$daPort}/CMD_API_DB_USER";
            $modifyData = [
                'action' => 'modify',
                'name' => $mainUser . '_' . $dbSuffix,
                'user' => $mainUser . '_' . $dbSuffix,
                'passwd' => $dbPass,
                'passwd2' => $dbPass
            ];
            $res = RemoteClient::post($daUserUrl, $modifyData, $auth);
            if (strpos($res, 'error:0A00010B') !== false || strpos($res, 'wrong version number') !== false) {
                $daUserUrl = "http://{$daHost}:{$daPort}/CMD_API_DB_USER";
                $res = RemoteClient::post($daUserUrl, $modifyData, $auth);
            }
            return "ok_already_exists"; 
        }

        return $res;
    }

    public function createDirectAdminEmail($config, $emailUser, $emailPass)
    {
        $cleanHost = !empty($config['web_domain'])
            ? str_replace(['https://', 'http://', '/'], '', $config['web_domain'])
            : str_replace(['ftp.', 'www.'], '', $config['ftp_host']);

        $daPort = $config['da_port'] ?? '1111';
        $daHost = $config['ftp_host'];
        $mainUser = !empty($config['da_user']) ? $config['da_user'] : $config['ftp_user'];
        $auth = "{$mainUser}:{$config['ftp_pass']}";

        $data = [
            'action' => 'create',
            'domain' => $cleanHost,
            'user' => $emailUser,
            'passwd' => $emailPass,
            'quota' => '0'
        ];

        // Thử HTTPS trước
        $daUrl = "https://{$daHost}:{$daPort}/CMD_API_POP";
        $res = RemoteClient::post($daUrl, $data, $auth);

        // Nếu lỗi SSL (Wrong version number) thì thử lại với HTTP
        if (strpos($res, 'error:0A00010B') !== false || strpos($res, 'wrong version number') !== false) {
            $daUrl = "http://{$daHost}:{$daPort}/CMD_API_POP";
            $res = RemoteClient::post($daUrl, $data, $auth);
        }

        // Kiểm tra nếu đã tồn tại (Giải mã URL trước)
        $decodedRes = urldecode($res);
        if (stripos($decodedRes, 'already exists') !== false) {
            // Cập nhật mật khẩu nếu đã tồn tại
            $modifyData = [
                'action' => 'passwd',
                'domain' => $cleanHost,
                'user' => $emailUser,
                'passwd' => $emailPass,
                'passwd2' => $emailPass
            ];
            $res = RemoteClient::post($daUrl, $modifyData, $auth);
            return "ok_already_exists";
        }

        return $res;
    }

    public function triggerBridge($config, $dbName = null, $subPath = '', $skipLock = false, $bridgeName = 'bridge.php')
    {
        $cleanHost = !empty($config['web_domain'])
            ? str_replace(['https://', 'http://', '/'], '', $config['web_domain'])
            : str_replace(['ftp.', 'www.'], '', $config['ftp_host']);

        $useSSL = !empty($config['ssl']) || (isset($config['web_domain']) && strpos($config['web_domain'], 'https://') === 0);
        $schemes = $useSSL ? ['https://', 'http://'] : ['http://', 'https://'];
        $res = null;
        $webSub = (isset($config['ftp_root']) && strpos($config['ftp_root'], '/public_html') !== false) ? str_replace('/public_html', '', $config['ftp_root']) : '';
        $fullSubPath = rtrim($webSub, '/') . '/' . trim($subPath, '/');

        foreach ($schemes as $scheme) {
            $pathPart = trim($fullSubPath, '/');
            $url = $scheme . $cleanHost . ($pathPart ? '/' . $pathPart : '') . "/" . $bridgeName . "?action=deploy";
            $mainUser = !empty($config['da_user']) ? $config['da_user'] : ($config['ftp_user'] ?? '');
            $finalDbName = ($dbName && strpos($dbName, $mainUser . '_') !== 0) ? $mainUser . '_' . $dbName : $dbName;

            $dbToPass = [
                'host' => 'localhost',
                'name' => $finalDbName ?: ($config['db_name'] ?? ''),
                'user' => $finalDbName ?: ($config['db_user'] ?? ''),
                'pass' => $config['db_pass'] ?? ($config['ftp_pass'] ?? '')
            ];
            $appConfigInfo = [
                'app_url' => (!empty($config['ssl']) ? 'https://' : 'http://') . $cleanHost . '/' . ltrim($fullSubPath, '/'),
                'ssl' => !empty($config['ssl']),
                'skip_lock' => $skipLock,
                'is_production' => !$skipLock,
                'turnstile_sitekey' => $config['turnstile_sitekey'] ?? '',
                'turnstile_secretkey' => $config['turnstile_secretkey'] ?? '',
                'email_user' => $config['email_user'] ?? '',
                'email_pass' => $config['email_pass'] ?? '',
                'demo_domain' => $config['demo_domain'] ?? '',
                'prod_domain' => $config['prod_domain'] ?? '',
                'is_production' => true
            ];
            $res = RemoteClient::post($url, [
                'db_config' => json_encode($dbToPass),
                'app_config' => json_encode($appConfigInfo),
                'clear_db' => !empty($config['clear_db']) ? 1 : 0
            ]);
            $decoded = json_decode($res, true);
            if ($decoded && isset($decoded['status']) && $decoded['status'] !== 'error') return $res;
        }
        return $res;
    }

    public function triggerCloudDeploy($demoConfig, $prodConfig, $subPath = '')
    {
        $demoDomain = !empty($demoConfig['web_domain']) ? str_replace(['https://', 'http://', '/'], '', $demoConfig['web_domain']) : str_replace(['ftp.', 'www.'], '', $demoConfig['ftp_host']);
        $prodDomain = !empty($prodConfig['web_domain']) ? str_replace(['https://', 'http://', '/'], '', $prodConfig['web_domain']) : str_replace(['ftp.', 'www.'], '', $prodConfig['ftp_host']);

        $prodWebSub = ltrim(str_replace('/public_html', '', $prodConfig['ftp_root'] ?? ''), '/');
        $prodConfig['app_url'] = ($prodConfig['ssl'] ? 'https://' : 'http://') . $prodDomain . ($prodWebSub ? '/' . $prodWebSub : '');

        $demoSSL = !empty($demoConfig['ssl']) || (isset($demoConfig['web_domain']) && strpos($demoConfig['web_domain'], 'https://') === 0);
        $schemes = $demoSSL ? ['https://', 'http://'] : ['http://', 'https://'];
        $res = null;
        
        $jsonPayload = json_encode($prodConfig);
        $encodedPayload = base64_encode($jsonPayload);
        
        foreach ($schemes as $scheme) {
            $demoBridgeUrl = $scheme . $demoDomain . "/" . ($subPath ? trim($subPath, '/') . "/" : "") . "bridge.php?action=cloudDeploy";
            $res = RemoteClient::post($demoBridgeUrl, ['prod_config_b64' => $encodedPayload]);
            $decoded = json_decode($res, true);
            if ($decoded && isset($decoded['status'])) break;
        }
        return $res;
    }

    public function exportLocalDatabase($dbConfig, $outputFile)
    {
        $host = $dbConfig['host'] ?? 'localhost';
        $dbname = $dbConfig['name'] ?? '';
        $user = $dbConfig['user'] ?? 'root';
        $pass = $dbConfig['pass'] ?? '';

        if (empty($dbname)) return "No database name provided";

        // Method 1: Try system mysqldump (Highest integrity, like phpMyAdmin)
        $passPart = !empty($pass) ? "-p\"$pass\"" : "";
        $cmd = "mysqldump --host={$host} --user={$user} {$passPart} --default-character-set=utf8mb4 --result-file=\"{$outputFile}\" {$dbname} 2>&1";

        exec($cmd, $output, $returnVar);

        if ($returnVar === 0) {
            return true;
        }

        // Method 2: Fallback to PHP-based export if mysqldump fails
        try {
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'"
            ];
            $pdo = new \PDO($dsn, $user, $pass, $options);

            $outputSql = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $outputSql .= "DROP TABLE IF EXISTS `$table`;\n";
                $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_ASSOC);
                $outputSql .= $create['Create Table'] . ";\n\n";

                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $outputSql .= "INSERT INTO `$table` VALUES (";
                    $vals = array_map(function ($v) use ($pdo) {
                        return is_null($v) ? "NULL" : $pdo->quote($v);
                    }, array_values($row));
                    $outputSql .= implode(', ', $vals) . ");\n";
                }
                $outputSql .= "\n";
            }
            $outputSql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($outputFile, $outputSql);
            return true;
        } catch (\Exception $e) {
            return "Export Error: " . $e->getMessage() . " (mysqldump failure: " . implode("\n", $output) . ")";
        }
    }
    public function createTurnstileWidget($domain, $cfAccountId, $cfApiToken, $cfAuthEmail = '')
    {
        $url = "https://api.cloudflare.com/client/v4/accounts/" . trim($cfAccountId) . "/challenges/widgets";
        $data = [
            "name" => "RamboWoon - " . $domain,
            "domains" => [$domain],
            "mode" => "managed"
        ];
        
        $headers = ["Content-Type: application/json"];
        $token = trim(str_replace('Bearer ', '', $cfApiToken));
        
        if (!empty($cfAuthEmail)) {
            $headers[] = "X-Auth-Email: " . trim($cfAuthEmail);
            $headers[] = "X-Auth-Key: {$token}";
        } else {
            $headers[] = "Authorization: Bearer {$token}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $res = json_decode($response, true);
        if ($res && isset($res['success']) && $res['success'] && !empty($res['result'])) {
            return [
                'sitekey' => $res['result']['sitekey'] ?? '',
                'secret' => $res['result']['secret'] ?? ''
            ];
        }
        
        $errorMsg = "HTTP {$httpCode}";
        if (isset($res['errors'][0]['message'])) {
            $errorMsg .= ": " . $res['errors'][0]['message'];
        } elseif (!$response) {
            $errorMsg = "Connection failed";
        } else {
            $errorMsg .= ": " . $response;
        }
        
        return $errorMsg;
    }

    public function cleanupBridge($config, $subPath = '')
    {
        $ftpHost = $config['ftp_host'] ?? '';
        $ftpRoot = !empty($config['ftp_root']) ? $config['ftp_root'] : '/public_html';
        $webDomain = $config['web_domain'] ?? '';

        if (empty($ftpHost)) return json_encode(['status' => 'error', 'message' => 'Thiếu FTP Host trong cấu hình.']);

        $cleanHost = !empty($webDomain)
            ? str_replace(['https://', 'http://', '/'], '', $webDomain)
            : str_replace(['ftp.', 'www.'], '', $ftpHost);

        $useSSL = !empty($config['ssl']) || (isset($config['web_domain']) && strpos($config['web_domain'], 'https://') === 0);
        $schemes = $useSSL ? ['https://', 'http://'] : ['http://', 'https://'];
        $res = null;
        $webSub = (strpos($ftpRoot, '/public_html') !== false) ? str_replace('/public_html', '', $ftpRoot) : '';
        $fullSubPath = rtrim($webSub, '/') . '/' . trim($subPath, '/');

        foreach ($schemes as $scheme) {
            $pathPart = trim($fullSubPath, '/');
            $url = $scheme . $cleanHost . ($pathPart ? '/' . $pathPart : '') . "/bridge.php?action=cleanup";
            $raw = RemoteClient::get($url);
            $decoded = json_decode($raw, true);
            if ($decoded && isset($decoded['status'])) {
                $res = $raw;
                break;
            }
        }
        return $res;
    }

    public function changeDirectAdminDbPassword($config, $dbSuffix, $dbPass)
    {
        $daPort = $config['da_port'] ?? '1111';
        $daHost = $config['ftp_host'];
        $mainUser = !empty($config['da_user']) ? $config['da_user'] : $config['ftp_user'];
        $auth = "{$mainUser}:{$config['ftp_pass']}";

        $daUrl = "https://{$daHost}:{$daPort}/CMD_API_DB_USER";
        $modifyData = [
            'action' => 'modify',
            'name' => $mainUser . '_' . $dbSuffix,
            'user' => $mainUser . '_' . $dbSuffix,
            'passwd' => $dbPass,
            'passwd2' => $dbPass
        ];
        
        $res = RemoteClient::post($daUrl, $modifyData, $auth);
        
        if (strpos($res, 'error:0A00010B') !== false || strpos($res, 'wrong version number') !== false) {
            $daUrl = "http://{$daHost}:{$daPort}/CMD_API_DB_USER";
            $res = RemoteClient::post($daUrl, $modifyData, $auth);
        }
        
        return $res;
    }

    public function directAdminDbExists($config, $dbSuffix)
    {
        $daPort = $config['da_port'] ?? '1111';
        $daHost = $config['ftp_host'];
        $mainUser = !empty($config['da_user']) ? $config['da_user'] : $config['ftp_user'];
        $auth = "{$mainUser}:{$config['ftp_pass']}";

        $daUrl = "https://{$daHost}:{$daPort}/CMD_API_DATABASES";
        $res = RemoteClient::get($daUrl, $auth);
        
        $isError = ($res === false || strpos((string)$res, 'error:0A00010B') !== false || strpos((string)$res, 'wrong version number') !== false || strpos((string)$res, 'TLS') !== false);
        if ($isError) {
            $daUrl = "http://{$daHost}:{$daPort}/CMD_API_DATABASES";
            $res = RemoteClient::get($daUrl, $auth);
        }

        $dbName = strtolower($mainUser . '_' . $dbSuffix);
        
        $databases = [];
        if (is_string($res) && !empty($res)) {
            // Method 1: Use regex to parse list[] values to avoid max_input_vars limitations with parse_str
            preg_match_all('/list(?:%5B%5D|\[\])=([^&]+)/i', $res, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $db) {
                    $databases[] = strtolower(urldecode(trim($db)));
                }
            }
            // Method 2: Fallback parse_str in case the response format is different
            if (empty($databases)) {
                parse_str($res, $output);
                if (isset($output['list']) && is_array($output['list'])) {
                    $databases = array_map('strtolower', $output['list']);
                }
            }
        }
        
        return in_array($dbName, $databases);
    }

    public function downloadRemoteEnv($config, $remoteProjectFolder)
    {
        $ftpRoot = !empty($config['ftp_root']) ? $config['ftp_root'] : '/public_html';
        $ftpUrl = "ftp://{$config['ftp_host']}" . rtrim($ftpRoot, '/') . '/' . ltrim($remoteProjectFolder, '/') . '/.env';
        $userPwd = "{$config['ftp_user']}:{$config['ftp_pass']}";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'env_');
        
        $fp = fopen($tempFile, 'w');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ftpUrl);
        curl_setopt($ch, CURLOPT_USERPWD, $userPwd);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        
        if ($res && $httpCode === 200) {
            $content = file_get_contents($tempFile);
            @unlink($tempFile);
            return $content;
        }
        @unlink($tempFile);
        return false;
    }

    public function getDbPassFromEnvContent($content)
    {
        if (empty($content)) return null;
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            $parts = explode('=', $line, 2);
            $key = strtoupper(trim($parts[0]));
            $val = trim($parts[1]);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (in_array($key, ['DB_PASSWORD', 'DB_PASS', 'DATABASE_PASS', 'DATABASE_PASSWORD'])) {
                return $val;
            }
        }
        return null;
    }

    public function checkRemoteDbStatus($config, $dbName, $dbUser, $dbPass, $subPath = '', $bridgeName = 'bridge.php')
    {
        $cleanHost = !empty($config['web_domain'])
            ? str_replace(['https://', 'http://', '/'], '', $config['web_domain'])
            : str_replace(['ftp.', 'www.'], '', $config['ftp_host']);

        $useSSL = !empty($config['ssl']) || (isset($config['web_domain']) && strpos($config['web_domain'], 'https://') === 0);
        $schemes = $useSSL ? ['https://', 'http://'] : ['http://', 'https://'];
        $res = null;
        $webSub = (isset($config['ftp_root']) && strpos($config['ftp_root'], '/public_html') !== false) ? str_replace('/public_html', '', $config['ftp_root']) : '';
        $fullSubPath = rtrim($webSub, '/') . '/' . trim($subPath, '/');

        foreach ($schemes as $scheme) {
            $pathPart = trim($fullSubPath, '/');
            $url = $scheme . $cleanHost . ($pathPart ? '/' . $pathPart : '') . "/" . $bridgeName . "?action=checkDb";
            
            $dbToPass = [
                'host' => 'localhost',
                'name' => $dbName,
                'user' => $dbUser,
                'pass' => $dbPass
            ];
            
            $res = RemoteClient::post($url, [
                'db_config' => json_encode($dbToPass)
            ]);
            
            $decoded = json_decode($res, true);
            if ($decoded && isset($decoded['status'])) return $decoded;
        }
        return ['status' => 'error', 'message' => 'Không thể kết nối tới Bridge hoặc phản hồi không hợp lệ: ' . strip_tags((string)$res)];
    }
}
