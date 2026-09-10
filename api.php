<?php
/**
 * RamboWoon Manager API - Full Version
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');
error_reporting(0);
ini_set('display_errors', 0);
@set_time_limit(0);
@ini_set('max_execution_time', 0);
@ini_set('memory_limit', '512M');
ignore_user_abort(true);

function getDemoConfigForProject($projectConfig = []) {
    $globalPath = __DIR__ . '/data/demo_config.json';
    $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
    $demoId = $projectConfig['deployed']['demo']['demo_server_id'] ?? (!empty($projectConfig['deployed']['demo']['url']) ? 'legacy' : ($gConfig['default_demo_id'] ?? 'legacy'));
    $config = $gConfig;
    if (!empty($gConfig['demo_list'])) {
        foreach ($gConfig['demo_list'] as $d) {
            if ($d['id'] === $demoId) {
                $config = array_merge($gConfig, $d);
                break;
            }
        }
    }
    return $config;
}

function getProjectTargetHostConfig($projectConfig, $env = 'demo') {
    $env = strtolower($env ?: 'demo');
    if ($env === 'prod' || $env === 'production') {
        $prod = $projectConfig['prod'] ?? [];
        if (!empty($projectConfig['deployed']['production'])) {
            $prod = array_merge($prod, $projectConfig['deployed']['production']);
        }
        return ['env' => 'prod', 'config' => $prod];
    }
    $demo = getDemoConfigForProject($projectConfig);
    if (!empty($projectConfig['deployed']['demo'])) {
        $demo = array_merge($demo, $projectConfig['deployed']['demo']);
    }
    return ['env' => 'demo', 'config' => $demo];
}

function autoCleanOldCacheFiles($days = 7) {
    $cacheBase = __DIR__ . '/cache/remote_edit';
    if (!is_dir($cacheBase)) return;
    
    $lockFile = __DIR__ . '/cache/.last_cleanup';
    if (file_exists($lockFile) && (time() - filemtime($lockFile) < 86400)) {
        return; // Only run once every 24 hours to keep requests ultra fast
    }
    @file_put_contents($lockFile, (string)time());
    
    $cutoff = time() - ($days * 86400);
    
    $cleanDir = function($dir) use (&$cleanDir, $cutoff) {
        $items = @scandir($dir);
        if ($items === false) return true;
        $isEmpty = true;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                if ($cleanDir($path)) {
                    @rmdir($path);
                } else {
                    $isEmpty = false;
                }
            } else {
                if (filemtime($path) < $cutoff) {
                    @unlink($path);
                } else {
                    $isEmpty = false;
                }
            }
        }
        return $isEmpty;
    };
    
    $cleanDir($cacheBase);
}

// Auto clean cache files older than 7 days
autoCleanOldCacheFiles(7);

function saveSyncAutoBackup($projectName, $category, $subFolder, $cleanPath, $content, $env = 'demo') {
    if ($content === null || $content === false) return;
    $dateFolder = date('Y-m-d');
    $timePrefix = date('His');
    $safeProj = preg_replace('/[^a-zA-Z0-9_\-]/', '_', "{$category}_{$projectName}");
    $envKey = (strtolower($env) === 'prod' || strtolower($env) === 'production') ? 'prod' : 'demo';
    
    // Tách riêng biệt theo môi trường để không bao giờ bị trùng lặp file backup giữa demo và production
    $backupDir = __DIR__ . "/backups/sync_snapshots/{$safeProj}/{$envKey}/{$dateFolder}/{$subFolder}/" . dirname($cleanPath);
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0777, true);
    }
    $fileName = basename($cleanPath);
    $backupFile = "{$backupDir}/[{$timePrefix}]_{$fileName}";
    @file_put_contents($backupFile, $content);

    $metaFile = "{$backupDir}/[{$timePrefix}]_{$fileName}.meta.json";
    @file_put_contents($metaFile, json_encode([
        'original_rel_path' => $cleanPath,
        'category' => $category,
        'project_name' => $projectName,
        'env' => $envKey,
        'type' => $subFolder,
        'date' => $dateFolder,
        'time' => date('H:i:s'),
        'timestamp' => time(),
        'size' => strlen($content),
        'backup_file' => "{$envKey}/{$dateFolder}/{$subFolder}/" . dirname($cleanPath) . "/[{$timePrefix}]_{$fileName}"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$cliInputData = null;
if (PHP_SAPI === 'cli') {
    $debugLogFile = __DIR__ . '/logs/debug_bg_job.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($debugLogFile, "[$timestamp] ⚡ CLI ENTRY POINT: Đã vào PHP CLI mode!\n", FILE_APPEND);

    $cliArgs = [];
    foreach ($argv ?? [] as $arg) {
        if (strpos($arg, '--') !== 0) continue;
        $parts = explode('=', substr($arg, 2), 2);
        $cliArgs[$parts[0]] = $parts[1] ?? '1';
    }
    @file_put_contents($debugLogFile, "[$timestamp] CLI Args: " . json_encode($cliArgs) . "\n", FILE_APPEND);

    if (!empty($cliArgs['action'])) {
        $_GET['action'] = $cliArgs['action'];
    }
    if (!empty($cliArgs['payload']) && is_file($cliArgs['payload'])) {
        $payloadRaw = file_get_contents($cliArgs['payload']);
        $cliInputData = json_decode($payloadRaw, true) ?: [];
        @file_put_contents($debugLogFile, "[$timestamp] Payload JSON decoded successfully (Keys: " . implode(', ', array_keys($cliInputData)) . ")\n", FILE_APPEND);
        if (!empty($cliInputData['jobId'])) {
            $jobId = $cliInputData['jobId'];
        }
        @unlink($cliArgs['payload']);
    } else {
        @file_put_contents($debugLogFile, "[$timestamp] ❌ LỖI CLI: Payload file không tồn tại hoặc rỗng!\n", FILE_APPEND);
    }
} elseif (!empty($_POST['_background']) && !empty($_POST['payloadFile'])) {
    $debugLogFile = __DIR__ . '/logs/debug_bg_job.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($debugLogFile, "[$timestamp] 🌐 HTTP BACKGROUND ENTRY POINT: Đã nhận luồng chạy nền HTTP!\n", FILE_APPEND);
    
    if (is_file($_POST['payloadFile'])) {
        $payloadRaw = file_get_contents($_POST['payloadFile']);
        $cliInputData = json_decode($payloadRaw, true) ?: [];
        $cliInputData['_background'] = true; // Ensure it skips re-queueing
        
        @file_put_contents($debugLogFile, "[$timestamp] HTTP Payload JSON decoded successfully (Keys: " . implode(', ', array_keys($cliInputData)) . ")\n", FILE_APPEND);
        if (!empty($cliInputData['jobId'])) {
            $jobId = $cliInputData['jobId'];
        }
        @unlink($_POST['payloadFile']);
        
        // TRICK TO CLOSE CONNECTION IN HTTP BACKGROUND
        while (ob_get_level()) ob_end_clean();
        header("Connection: close\r\n");
        header("Content-Encoding: none\r\n");
        ignore_user_abort(true);
        ob_start();
        echo "Background job started.";
        $size = ob_get_length();
        header("Content-Length: $size");
        ob_end_flush();     
        flush();            
        if (session_id()) session_write_close();
    } else {
        @file_put_contents($debugLogFile, "[$timestamp] ❌ LỖI HTTP BACKGROUND: Payload file không tồn tại: " . $_POST['payloadFile'] . "\n", FILE_APPEND);
    }
}

register_shutdown_function(function () use (&$jobId) {
    global $jobId;
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR || $error['type'] === E_CORE_ERROR)) {
        if ($jobId) {
            writeJobLog($jobId, [
                'status' => 'error',
                'message' => '🔴 Lỗi Hệ Thống (Fatal Error): ' . $error['message'] . ' trong file ' . basename($error['file']) . ' dòng ' . $error['line']
            ]);
        } else {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'status' => 'error',
                'message' => '🔴 Lỗi Hệ Thống (Fatal Error): ' . $error['message'] . ' trong ' . basename($error['file']) . ' dòng ' . $error['line']
            ]);
        }
    }
});

function writeJobLog($jobId, $data) {
    if (!$jobId) return;
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
    $logFile = $logDir . "/{$jobId}.log";
    file_put_contents($logFile, json_encode($data) . "\n", FILE_APPEND);
}

function readJsonInput() {
    global $cliInputData;
    if (is_array($cliInputData)) return $cliInputData;

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function getPhpCliBinary() {
    $php = PHP_BINARY;
    $base = strtolower(basename($php));
    if ($base === 'php.exe') {
        return $php;
    }
    if ($base === 'php-cgi.exe' || $base === 'php-win.exe') {
        $candidate = dirname($php) . DIRECTORY_SEPARATOR . 'php.exe';
        if (is_file($candidate)) return $candidate;
    }
    // Detect under Apache / RBWStack
    $parentDir = dirname($php);
    if (is_file($parentDir . DIRECTORY_SEPARATOR . 'php.exe')) {
        return $parentDir . DIRECTORY_SEPARATOR . 'php.exe';
    }
    $stackBinPhp = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php';
    if (is_dir($stackBinPhp)) {
        $dirs = glob($stackBinPhp . DIRECTORY_SEPARATOR . 'php*');
        if ($dirs) {
            rsort($dirs);
            foreach ($dirs as $d) {
                if (is_file($d . DIRECTORY_SEPARATOR . 'php.exe')) {
                    return $d . DIRECTORY_SEPARATOR . 'php.exe';
                }
            }
        }
    }
    return 'php';
}

function startApiBackgroundJob($action, array $data, $jobId) {
    $safeJobId = preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId ?: ('job_' . time()));
    $logDir = __DIR__ . '/logs';
    if (substr($logDir, 0, 4) === '\\\\.\\' || substr($logDir, 0, 4) === '\\\\?\\') {
        $logDir = substr($logDir, 4);
    }
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);

    $debugLogFile = $logDir . '/debug_bg_job.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($debugLogFile, "[$timestamp] --- BẮT ĐẦU startApiBackgroundJob (Job: $safeJobId, Action: $action) ---\n", FILE_APPEND);

    $payloadFile = $logDir . DIRECTORY_SEPARATOR . $safeJobId . '.payload.json';
    $data['jobId'] = $safeJobId;
    if (file_put_contents($payloadFile, json_encode($data)) === false) {
        @file_put_contents($debugLogFile, "[$timestamp] ❌ LỖI: Không thể tạo payloadFile: $payloadFile\n", FILE_APPEND);
        throw new Exception('Không thể tạo file dữ liệu (payload) cho tiến trình chạy nền.');
    }
    @file_put_contents($debugLogFile, "[$timestamp] ✅ Đã tạo payloadFile thành công.\n", FILE_APPEND);

    $php = getPhpCliBinary();
    @file_put_contents($debugLogFile, "[$timestamp] PHP Binary detected: $php (Exists: " . (file_exists($php) ? 'YES' : 'NO') . ")\n", FILE_APPEND);
    
    // Remove Windows Device Namespace prefix (\\.\) which breaks CMD and PHP CLI path resolution
    $scriptFile = __FILE__;
    if (substr($scriptFile, 0, 4) === '\\\\.\\') $scriptFile = substr($scriptFile, 4);
    if (substr($payloadFile, 0, 4) === '\\\\.\\') $payloadFile = substr($payloadFile, 4);

    @file_put_contents($debugLogFile, "[$timestamp] ScriptFile: $scriptFile (Exists: " . (file_exists($scriptFile) ? 'YES' : 'NO') . ")\n", FILE_APPEND);
    @file_put_contents($debugLogFile, "[$timestamp] PayloadFile: $payloadFile (Exists: " . (file_exists($payloadFile) ? 'YES' : 'NO') . ")\n", FILE_APPEND);

    $cmdOutLog = $logDir . DIRECTORY_SEPARATOR . $safeJobId . '_cmd_exec.log';
    if (substr($cmdOutLog, 0, 4) === '\\\\.\\') $cmdOutLog = substr($cmdOutLog, 4);

    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        @file_put_contents($debugLogFile, "[$timestamp] Gọi curl nội bộ tới: $url\n", FILE_APPEND);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'action' => $action, 
            'payloadFile' => $payloadFile, 
            '_background' => 1
        ]);
        
        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        @file_put_contents($debugLogFile, "[$timestamp] ✅ Đã đẩy luồng qua HTTP cURL (Err: $err)\n", FILE_APPEND);
    } else {
        $cmd = escapeshellarg($php)
            . ' ' . escapeshellarg($scriptFile)
            . ' --action=' . escapeshellarg($action)
            . ' --payload=' . escapeshellarg($payloadFile)
            . ' > /dev/null 2>&1 &';
        @file_put_contents($debugLogFile, "[$timestamp] Lệnh cmd Linux sẽ chạy: $cmd\n", FILE_APPEND);
        exec($cmd);
    }

    return $safeJobId;
}

function directAdminResponseHasError($response) {
    $decoded = urldecode((string)$response);
    if (stripos($decoded, 'error=1') !== false) return true;

    $json = json_decode((string)$response, true);
    return is_array($json) && (($json['status'] ?? '') === 'error');
}

function parseFontFilename($filename) {
    $f = $filename;
    $weight = '400';
    if (preg_match('/(thin|100)/i', $f)) $weight = '100';
    elseif (preg_match('/(extralight|200)/i', $f)) $weight = '200';
    elseif (preg_match('/(light|300)/i', $f)) $weight = '300';
    elseif (preg_match('/(medium|500)/i', $f)) $weight = '500';
    elseif (preg_match('/(semibold|600)/i', $f)) $weight = '600';
    elseif (preg_match('/(extrabold|800|heavy)/i', $f)) $weight = '800';
    elseif (preg_match('/(bold|700)/i', $f)) $weight = '700';
    elseif (preg_match('/(black|900)/i', $f)) $weight = '900';
    
    $style = preg_match('/italic/i', $f) ? 'italic' : 'normal';
    
    $cleanFamily = preg_replace('/[-_]?\b(extrabold|800|semibold|demibold|600|bold|700|extralight|200|light|300|thin|100|medium|500|black|heavy|900|regular|italic|normal|it|rg)\b/i', '', $f);
    $cleanFamily = trim($cleanFamily, '-_ ');
    if (empty($cleanFamily)) {
        $cleanFamily = $f;
    }
    
    return [
        'family' => $cleanFamily,
        'weight' => $weight,
        'style' => $style
    ];
}

function removeVietnameseDiacritics($str) {
    $unicode = array(
        'a' => 'á|à|ả|ã|ạ|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ',
        'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ằ|Ẳ|Ẵ|Ặ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
        'd' => 'đ',
        'D' => 'Đ',
        'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
        'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
        'i' => 'í|ì|ỉ|ĩ|ị',
        'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
        'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
        'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
        'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
        'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
        'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
        'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
    );
    foreach($unicode as $nonUnicode => $uni){
        $str = preg_replace("/($uni)/", $nonUnicode, $str);
    }
    // Remove all whitespace and special characters, keep a-z, A-Z, 0-9
    $str = preg_replace('/[^a-zA-Z0-9]/', '', $str);
    return $str;
}

function buildLocalFontCacheFromTree($fontSource) {
    $fontSource = rtrim(str_replace('\\', '/', $fontSource), '/');
    $treeFile = $fontSource . '/tree.md';
    $cacheDir = __DIR__ . '/data';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    $cacheFile = $cacheDir . '/local_fonts_cache.json';

    $grouped = [];

    if (file_exists($treeFile)) {
        $handle = @fopen($treeFile, 'r');
        if ($handle) {
            $dirStack = [];
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if (trim($line) === '') continue;

                if (preg_match('/^([│├└─\s]+)(.*)$/u', $line, $m)) {
                    $prefix = $m[1];
                    $name = trim($m[2]);
                    $depth = (int)floor(mb_strlen($prefix, 'UTF-8') / 4);
                } else {
                    $name = trim($line);
                    $depth = 0;
                }

                $isDir = (substr($name, -1) === '/');
                $nameClean = rtrim($name, '/');

                if ($isDir) {
                    $dirStack[$depth] = $nameClean;
                    $dirStack = array_slice($dirStack, 0, $depth + 1, true);
                } else {
                    $ext = strtolower(pathinfo($nameClean, PATHINFO_EXTENSION));
                    $isWoff = ($ext === 'woff' || $ext === 'woff2');
                    $isConvert = ($ext === 'otf' || $ext === 'ttf');

                    if ($isWoff || $isConvert) {
                        $relParts = [];
                        for ($i = 1; $i < $depth; $i++) {
                            if (isset($dirStack[$i]) && $dirStack[$i] !== '') {
                                $relParts[] = $dirStack[$i];
                            }
                        }
                        $parentFolder = implode('/', $relParts);
                        $filename = pathinfo($nameClean, PATHINFO_FILENAME);
                        $parsed = parseFontFilename($filename);
                        $familyPrefix = $parsed['family'];
                        $weight = $parsed['weight'];
                        $style = $parsed['style'];
                        $vKey = $weight . ($style === 'italic' ? 'i' : '');

                        $fontId = ($parentFolder === '.' || $parentFolder === '') ? $familyPrefix : ($parentFolder . '/' . $familyPrefix);

                        if (!isset($grouped[$fontId])) {
                            $grouped[$fontId] = [
                                'id' => $fontId,
                                'family' => str_replace(['/', '-', '_'], [' > ', ' ', ' '], $fontId),
                                'category' => 'Local Library',
                                'source' => 'local',
                                'files' => []
                            ];
                        }

                        $grouped[$fontId]['files'][] = [
                            'file' => $nameClean,
                            'filename' => $filename,
                            'ext' => $ext,
                            'weight' => $weight,
                            'style' => $style,
                            'vKey' => $vKey,
                            'isWoff' => $isWoff
                        ];
                    }
                }
            }
            fclose($handle);
        }
    }

    if (empty($grouped) && is_dir($fontSource)) {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fontSource, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile()) {
                    $f = $fileInfo->getFilename();
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    $isWoff = ($ext === 'woff' || $ext === 'woff2');
                    $isConvert = ($ext === 'otf' || $ext === 'ttf');

                    if ($isWoff || $isConvert) {
                        $fullPath = str_replace('\\', '/', $fileInfo->getRealPath());
                        $relPath = ltrim(str_replace($fontSource, '', $fullPath), '/');
                        $parentFolder = dirname($relPath);
                        if ($parentFolder === '.') $parentFolder = '';

                        $filename = pathinfo($f, PATHINFO_FILENAME);
                        $parsed = parseFontFilename($filename);
                        $familyPrefix = $parsed['family'];
                        $weight = $parsed['weight'];
                        $style = $parsed['style'];
                        $vKey = $weight . ($style === 'italic' ? 'i' : '');

                        $fontId = ($parentFolder === '' ? $familyPrefix : ($parentFolder . '/' . $familyPrefix));

                        if (!isset($grouped[$fontId])) {
                            $grouped[$fontId] = [
                                'id' => $fontId,
                                'family' => str_replace(['/', '-', '_'], [' > ', ' ', ' '], $fontId),
                                'category' => 'Local Library',
                                'source' => 'local',
                                'files' => []
                            ];
                        }

                        $grouped[$fontId]['files'][] = [
                            'file' => $f,
                            'filename' => $filename,
                            'ext' => $ext,
                            'weight' => $weight,
                            'style' => $style,
                            'vKey' => $vKey,
                            'isWoff' => $isWoff
                        ];
                    }
                }
            }
        } catch (Exception $e) {}
    }

    $finalFonts = [];
    foreach ($grouped as $fontId => $font) {
        $variants = [];
        $convert_variants = [];
        foreach ($font['files'] as $file) {
            $vKey = $file['vKey'];
            if ($file['isWoff']) {
                if (!in_array($vKey, $variants)) $variants[] = $vKey;
            } else {
                $exists = false;
                foreach ($convert_variants as $cv) {
                    if ($cv['variant'] === $vKey) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $convert_variants[] = ['variant' => $vKey, 'ext' => $file['ext'], 'filename' => $file['filename']];
                }
            }
        }
        sort($variants);
        usort($convert_variants, function($a, $b) {
            return strcmp($a['variant'], $b['variant']);
        });

        $font['variants'] = $variants;
        $font['convert_variants'] = $convert_variants;
        unset($font['files']);

        $finalFonts[$fontId] = $font;
    }

    file_put_contents($cacheFile, json_encode($finalFonts));
    return $finalFonts;
}

function getLocalFontData($fontSource, $forceReindex = false) {
    $cacheFile = __DIR__ . '/data/local_fonts_cache.json';
    $treeFile = rtrim(str_replace('\\', '/', $fontSource), '/') . '/tree.md';

    $needBuild = $forceReindex || !file_exists($cacheFile);
    if (!$needBuild && file_exists($treeFile)) {
        if (filemtime($treeFile) > filemtime($cacheFile)) {
            $needBuild = true;
        }
    }

    if ($needBuild) {
        return buildLocalFontCacheFromTree($fontSource);
    }

    $raw = @file_get_contents($cacheFile);
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data)) {
        return buildLocalFontCacheFromTree($fontSource);
    }

    return $data;
}

function cleanAllOldBackups($dir, $ttl = 86400) {
    if (!is_dir($dir)) return;
    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            if ($fileinfo->isFile()) {
                $path = $fileinfo->getRealPath();
                // Avoid unlinking the tracking file itself
                if (basename($path) === '.last_clean') continue;
                if (time() - filemtime($path) > $ttl) {
                    @unlink($path);
                }
            }
        }
    } catch (Exception $e) {
        // Fail silently to prevent interrupting API requests
    }
}

// Auto clean backups older than 24 hours (run at most once per hour)
$backupsRootDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupsRootDir)) {
    @mkdir($backupsRootDir, 0777, true);
}
$lastCleanFile = $backupsRootDir . DIRECTORY_SEPARATOR . '.last_clean';
if (!file_exists($lastCleanFile) || (time() - filemtime($lastCleanFile) > 3600)) {
    cleanAllOldBackups($backupsRootDir);
    @touch($lastCleanFile);
}

require_once __DIR__ . '/core/ProjectScanner.php';
require_once __DIR__ . '/core/ConfigManager.php';
require_once __DIR__ . '/core/DeploymentService.php';
require_once __DIR__ . '/core/PackagingService.php';
require_once __DIR__ . '/core/SchemaManager.php';
require_once __DIR__ . '/core/RemoteClient.php';
require_once __DIR__ . '/core/ProjectDeployer.php';
require_once __DIR__ . '/core/ImageTrimService.php';
require_once __DIR__ . '/core/ScreenshotService.php';

use RamboWoon\ProjectScanner;
use RamboWoon\ConfigManager;
use RamboWoon\DeploymentService;
use RamboWoon\PackagingService;
use RamboWoon\RemoteClient;
use RamboWoon\ProjectDeployer;
use RamboWoon\ImageTrimService;
use RamboWoon\ScreenshotService;

$baseDir = dirname(__DIR__);
if (strpos($baseDir, '\\\\.\\') === 0 || strpos($baseDir, '\\\\?\\') === 0) {
    $baseDir = substr($baseDir, 4);
}
$configPath = __DIR__ . '/data/projects.json';

$scanner = new ProjectScanner($baseDir);
$configManager = new ConfigManager($configPath);
$deployService = new DeploymentService($baseDir);
$packagingService = new PackagingService($scanner, $deployService, $configManager);
$appDir = __DIR__;
if (strpos($appDir, '\\\\.\\') === 0 || strpos($appDir, '\\\\?\\') === 0) {
    $appDir = substr($appDir, 4);
}
$screenshotService = new ScreenshotService($appDir);
$projectDeployer = new ProjectDeployer($baseDir);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$jobId = $_POST['jobId'] ?? $_GET['jobId'] ?? null;

header('Content-Type: application/json');

switch ($action) {
    case 'getLogs':
        if (!$jobId) die(json_encode(['status' => 'error', 'message' => 'Missing jobId']));
        $logFile = __DIR__ . "/logs/{$jobId}.log";
        $logs = [];
        if (file_exists($logFile)) {
            $lines = explode("\n", trim(file_get_contents($logFile)));
            foreach ($lines as $line) { if ($line) $logs[] = json_decode($line, true); }
        }
        echo json_encode(['status' => 'success', 'logs' => $logs]);
        break;

    case 'deleteLog':
        if ($jobId) {
            $logFile = __DIR__ . "/logs/{$jobId}.log";
            if (file_exists($logFile)) @unlink($logFile);
        }
        echo json_encode(['status' => 'success']);
        break;

    case 'listCategories':
        $strict = isset($_GET['strict']) && $_GET['strict'] === 'true';
        $refresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';
        echo json_encode(['status' => 'success', 'data' => $scanner->getCategories($strict, $refresh)]);
        break;

    case 'listProjects':
        $category = $_GET['category'] ?? '';
        $refresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';
        $projects = $scanner->getProjects($category, $refresh);
        $sitesConfig = $scanner->getPhpSitesConfig();
        $systemPhp = $scanner->getSystemPhpVersion();

        foreach ($projects as &$p) { 
            $p['config'] = $configManager->getForProject($p['name'], $p['category'] ?? null); 
            $p['screenshot'] = $screenshotService->getScreenshotUrl($p['category'] ?? '', $p['name']);

            // Đọc phiên bản PHP từ sites.json (nếu có) hoặc mặc định hệ thống
            $phpInfo = $scanner->resolveProjectPhp($p, $sitesConfig);
            $p['php_version'] = $phpInfo['php_version'];
            $p['php_display'] = $phpInfo['php_display'];
            $p['is_custom_php'] = $phpInfo['is_custom_php'];
        }
        echo json_encode([
            'status' => 'success', 
            'data' => $projects,
            'system_php' => $systemPhp
        ]);
        break;

    case 'captureScreenshot':
        $rawInput = file_get_contents('php://input');
        $jsonInput = json_decode($rawInput, true) ?: [];

        $projectName = $jsonInput['name'] ?? ($_POST['name'] ?? ($_GET['name'] ?? ''));
        $category = $jsonInput['category'] ?? ($_POST['category'] ?? ($_GET['category'] ?? ''));
        $url = $jsonInput['url'] ?? ($_POST['url'] ?? ($_GET['url'] ?? null));

        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { 
            if ($p['name'] === $projectName) { 
                $project = $p; 
                if (empty($category) && !empty($p['category'])) {
                    $category = $p['category'];
                }
                break; 
            } 
        }

        if (!$project) {
            // Fallback: Tìm trong tất cả danh mục nếu không thấy trong category chỉ định
            $allProjects = $scanner->getProjects('');
            foreach ($allProjects as $p) {
                if ($p['name'] === $projectName) {
                    $project = $p;
                    $category = $p['category'] ?? $category;
                    break;
                }
            }
        }

        $projectConfig = $configManager->getForProject($projectName, $category) ?: [];

        $res = $screenshotService->capture($category, $projectName, $url, $project, $projectConfig);
        echo json_encode($res);
        break;

    case 'batchCaptureScreenshots':
        $rawInput = file_get_contents('php://input');
        $jsonInput = json_decode($rawInput, true) ?: [];

        $category = $jsonInput['category'] ?? ($_POST['category'] ?? ($_GET['category'] ?? ''));
        $projects = $scanner->getProjects($category);
        $results = [];
        $successCount = 0;
        foreach ($projects as $p) {
            $pConfig = $configManager->getForProject($p['name'], $p['category'] ?? null) ?: [];
            $res = $screenshotService->capture($p['category'] ?? '', $p['name'], null, $p, $pConfig);
            if (($res['status'] ?? '') === 'success') $successCount++;
            $results[] = [
                'name' => $p['name'],
                'category' => $p['category'] ?? '',
                'status' => $res['status'],
                'message' => $res['message'] ?? '',
                'url' => $res['url'] ?? null
            ];
        }
        echo json_encode(['status' => 'success', 'results' => $results, 'successCount' => $successCount, 'total' => count($projects)]);
        break;

    case 'deleteScreenshot':
        $rawInput = file_get_contents('php://input');
        $jsonInput = json_decode($rawInput, true) ?: [];

        $projectName = $jsonInput['name'] ?? ($_POST['name'] ?? ($_GET['name'] ?? ''));
        $category = $jsonInput['category'] ?? ($_POST['category'] ?? ($_GET['category'] ?? ''));
        $screenshotService->deleteScreenshot($category, $projectName);
        echo json_encode(['status' => 'success']);
        break;

    case 'reindexProjects':
        $cache = $scanner->buildCache();
        $catCount = count($cache['categories_all'] ?? []);
        $projCount = count($cache['projects']['all'] ?? []);
        echo json_encode([
            'status' => 'success',
            'message' => "Đã đồng bộ lại chỉ mục danh mục và dự án thành công ($catCount thư mục, $projCount dự án)!",
            'data' => $cache
        ]);
        break;

    case 'checkCategoryDeployStatus':
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?: [];
        $category = $data['category'] ?? ($_GET['category'] ?? '');
        $specificName = $data['name'] ?? null;

        if (!$category) {
            echo json_encode(['status' => 'error', 'message' => 'Vui lòng chọn danh mục/tháng để kiểm tra']);
            break;
        }

        $globalPath = __DIR__ . '/data/demo_config.json';
        $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
        $demoList = $gConfig['demo_list'] ?? [];

        if (empty($demoList)) {
            echo json_encode(['status' => 'error', 'message' => 'Chưa cấu hình danh sách phân vùng Demo']);
            break;
        }

        $projects = $scanner->getProjects($category);
        $sitesConfig = $scanner->getPhpSitesConfig();

        // 1. Quét danh sách thư mục từ tất cả phân vùng Demo
        $serverFolders = [];
        foreach ($demoList as $demo) {
            $ftpRoot = !empty($demo['ftp_root']) ? $demo['ftp_root'] : '/public_html';
            $relativeRoot = ltrim(rtrim($ftpRoot, '/'), '/');
            $parentUrl = "ftp://{$demo['ftp_host']}/" . ($relativeRoot ? $relativeRoot . '/' : '') . trim($category, '/') . '/';
            $userPwd = "{$demo['ftp_user']}:{$demo['ftp_pass']}";

            $items = RemoteClient::listFtpDirectory($parentUrl, $userPwd);
            $items = array_filter($items, function($it) {
                return $it !== '.' && $it !== '..' && !empty($it);
            });
            $serverFolders[$demo['id']] = [
                'demo' => $demo,
                'items' => array_values($items)
            ];
        }

        $results = [];
        $counts = ['local' => 0, 'demo' => 0, 'production' => 0];

        foreach ($projects as $p) {
            $pName = $p['name'];
            if ($specificName && $pName !== $specificName) continue;

            $pConfig = $configManager->getForProject($pName, $category) ?: [];
            $phpInfo = $scanner->resolveProjectPhp($p, $sitesConfig);
            $pPhp = (float)preg_replace('/[^0-9.]/', '', $phpInfo['php_version'] ?? '');

            // Sắp xếp thứ tự ưu tiên kiểm tra server cho dự án
            $orderedServers = [];

            // Ưu tiên 1: Server đã từng lưu trong cấu hình
            $savedServerId = $pConfig['deployed']['demo']['demo_server_id'] ?? ($pConfig['demo_server_id'] ?? null);
            if ($savedServerId && isset($serverFolders[$savedServerId])) {
                $orderedServers[] = $savedServerId;
            }

            // Ưu tiên 2: Phân vùng theo phiên bản PHP
            foreach ($demoList as $d) {
                if (in_array($d['id'], $orderedServers)) continue;
                $dName = strtolower($d['name']);
                if ($pPhp >= 8.4 && strpos($dName, '8.4') !== false) {
                    $orderedServers[] = $d['id'];
                } elseif ($pPhp >= 8.2 && $pPhp < 8.4 && strpos($dName, '8.3') !== false) {
                    $orderedServers[] = $d['id'];
                } elseif ($pPhp < 8.0 && $pPhp > 0 && strpos($dName, '7.4') !== false) {
                    $orderedServers[] = $d['id'];
                }
            }

            // Ưu tiên 3: Các server còn lại (quét dự phòng toàn diện)
            foreach ($demoList as $d) {
                if (!in_array($d['id'], $orderedServers)) {
                    $orderedServers[] = $d['id'];
                }
            }

            $detectedStatus = 'local';
            $detectedServer = null;
            $matchedFolder = null;

            foreach ($orderedServers as $sId) {
                if (!isset($serverFolders[$sId])) continue;
                $items = $serverFolders[$sId]['items'];
                $demo = $serverFolders[$sId]['demo'];

                $pNameLower = strtolower($pName);

                // 1. Kiểm tra dấu hiệu Production (có tiền/hậu tố _old hoặc _xxx)
                foreach ($items as $item) {
                    if ($item === $pName) continue;
                    $itemLower = strtolower($item);

                    $isProd = (
                        strpos($itemLower, $pNameLower . '_') === 0 || 
                        strpos($itemLower, $pNameLower . '-') === 0 || 
                        strpos($itemLower, '_old_' . $pNameLower) === 0 || 
                        strpos($itemLower, '_old' . $pNameLower) === 0 ||
                        strpos($itemLower, 'old_' . $pNameLower) === 0
                    );

                    if ($isProd) {
                        $detectedStatus = 'production';
                        $detectedServer = $demo;
                        $matchedFolder = $item;
                        break;
                    }
                }

                if ($detectedStatus === 'production') break;

                // 2. Kiểm tra Demo (tồn tại thư mục trùng tên dự án)
                foreach ($items as $item) {
                    if (strtolower($item) === $pNameLower) {
                        $detectedStatus = 'demo';
                        $detectedServer = $demo;
                        $matchedFolder = $item;
                        break;
                    }
                }

                if ($detectedStatus === 'demo') break;
            }

            // Cập nhật cấu hình dự án
            $updated = false;
            if ($detectedStatus === 'production') {
                $counts['production']++;
                if (!isset($pConfig['deployed']['production'])) {
                    $pConfig['deployed']['production'] = [];
                }
                if (empty($pConfig['deployed']['production']['deploy_time'])) {
                    $pConfig['deployed']['production']['deploy_time'] = date('Y-m-d H:i:s');
                }
                $pConfig['deployed']['production']['matched_folder'] = $matchedFolder;
                if ($detectedServer) {
                    $pConfig['deployed']['production']['server_name'] = $detectedServer['name'];
                }
                $pConfig['lock_production'] = true;
                $updated = true;
            } elseif ($detectedStatus === 'demo') {
                $counts['demo']++;
                if (!isset($pConfig['deployed']['demo'])) {
                    $pConfig['deployed']['demo'] = [];
                }
                if (empty($pConfig['deployed']['demo']['deploy_time'])) {
                    $pConfig['deployed']['demo']['deploy_time'] = date('Y-m-d H:i:s');
                }
                if ($detectedServer) {
                    $pConfig['deployed']['demo']['demo_server_id'] = $detectedServer['id'];
                    $pConfig['deployed']['demo']['server_name'] = $detectedServer['name'];
                    $domain = $detectedServer['web_domain'];
                    $protocol = (!empty($detectedServer['ssl']) ? 'https://' : 'http://');
                    $pConfig['deployed']['demo']['url'] = $protocol . $domain . '/' . $category . '/' . $pName . '/';
                }
                $pConfig['deployed']['demo']['matched_folder'] = $matchedFolder;
                $updated = true;
            } else {
                $counts['local']++;
            }

            if ($updated) {
                $configManager->save($pName, $pConfig, $category);
            }

            $results[] = [
                'name' => $pName,
                'status' => $detectedStatus,
                'server_name' => $detectedServer['name'] ?? null,
                'server_id' => $detectedServer['id'] ?? null,
                'matched_folder' => $matchedFolder
            ];
        }

        echo json_encode([
            'status' => 'success',
            'category' => $category,
            'counts' => $counts,
            'total' => count($results),
            'results' => $results
        ]);
        break;

    case 'saveConfig':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? null;
        echo json_encode($configManager->save($data['name'], $data['config'], $category) ? ['status' => 'success'] : ['status' => 'error']);
        break;

    case 'saveGlobalConfig':
        $data = json_decode(file_get_contents('php://input'), true);
        $globalPath = __DIR__ . '/data/demo_config.json';
        echo json_encode(file_put_contents($globalPath, json_encode($data, JSON_PRETTY_PRINT)) ? ['status' => 'success'] : ['status' => 'error']);
        break;

    case 'getGlobalConfig':
        $globalPath = __DIR__ . '/data/demo_config.json';
        $config = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : null;
        if (empty($config)) $config = new stdClass();
        echo json_encode(['status' => 'success', 'data' => $config]);
        break;

    case 'createMonthCategory':
        $data = json_decode(file_get_contents('php://input'), true);
        $monthFolder = trim($data['monthFolder'] ?? '');
        if (!$monthFolder) {
            echo json_encode(['status' => 'error', 'message' => 'Tên thư mục tháng không được để trống']);
            break;
        }
        $monthPath = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $monthFolder);
        if (!is_dir($monthPath)) {
            if (@mkdir($monthPath, 0777, true)) {
                $scanner->buildCache();
                echo json_encode(['status' => 'success', 'message' => "Đã tạo thư mục tháng '$monthFolder' thành công!"]);
            } else {
                echo json_encode(['status' => 'error', 'message' => "Không thể tạo thư mục '$monthFolder'"]);
            }
        } else {
            echo json_encode(['status' => 'success', 'message' => "Thư mục '$monthFolder' đã tồn tại."]);
        }
        break;

    case 'deleteConfig':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? null;
        echo json_encode($configManager->delete($data['name'], $category) ? ['status' => 'success'] : ['status' => 'error']);
        break;

    case 'getProjectConfig':
        $name = $_GET['name'] ?? '';
        $category = $_GET['category'] ?? null;
        $config = $configManager->getForProject($name, $category) ?: [];
        $project = $scanner->getProjectByName($name, $category);
        if ($project) {
            $phpInfo = $scanner->resolveProjectPhp($project);
            $config['php_version'] = $phpInfo['php_version'];
            $config['php_display'] = $phpInfo['php_display'];
            $config['is_custom_php'] = $phpInfo['is_custom_php'];
            $config['system_php'] = $phpInfo['system_php'];
        }
        echo json_encode(['status' => 'success', 'data' => $config]);
        break;

    case 'deployNewProject':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectName = $data['projectName'] ?? '';
        $category = $data['category'] ?? ''; // e.g. "2026_05"
        $sourceKey = $data['sourceKey'] ?? 'default'; // For multiple sources if needed
        $jobId = $data['jobId'] ?? null;

        if (!$projectName || !$category) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin dự án hoặc tháng năm']);
            break;
        }

        // Load settings
        $globalPath = __DIR__ . '/data/demo_config.json';
        $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
        
        $sourcePath = $gConfig['source_path'] ?? '';
        if (empty($sourcePath) || !is_dir($sourcePath)) {
            $sourcePath = $baseDir . DIRECTORY_SEPARATOR . 'source_laravel';
        }
        $sourceDbName = $gConfig['source_db_name'] ?? 'source_nasani_2026';
        
        // Normalize category path for filesystem
        $categoryPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $category);
        $targetDir = $baseDir . DIRECTORY_SEPARATOR . $categoryPath . DIRECTORY_SEPARATOR . $projectName;
        
        // Final DB Name: category + project name (e.g. 2026_05_ranzilla_0765526w)
        $cleanProjectName = preg_replace('/[^a-z0-9_]/', '', strtolower($projectName));
        $dbName = str_replace(['/', '\\'], '_', $category) . '_' . $cleanProjectName;

        try {
            writeJobLog($jobId, ['status' => 'info', 'log' => "Bắt đầu triển khai dự án: $projectName"]);
            writeJobLog($jobId, ['status' => 'info', 'log' => "Đường dẫn đích: $targetDir"]);
            
            // 1. Determine Source & Target Paths
            $parentDir = $baseDir . DIRECTORY_SEPARATOR . $categoryPath;
            if (!is_dir($parentDir)) @mkdir($parentDir, 0777, true);

            $finalSourceFolder = null;
            $finalSourceZip = null;
            $sourceFolderName = $gConfig['source_folder_name'] ?? '';
            $sourceBaseName = !empty($sourceFolderName) ? $sourceFolderName : $sourceDbName;

            // Check if sourcePath itself is the project root
            if (is_dir($sourcePath) && (file_exists($sourcePath . DIRECTORY_SEPARATOR . '.env') || is_dir($sourcePath . DIRECTORY_SEPARATOR . 'app'))) {
                $finalSourceFolder = rtrim($sourcePath, DIRECTORY_SEPARATOR);
                $finalSourceZip = $finalSourceFolder . '.zip';
            } else {
                $finalSourceFolder = rtrim($sourcePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $sourceBaseName;
                $finalSourceZip = $finalSourceFolder . '.zip';
            }

            // Check if target project already exists
            if (is_dir($targetDir)) {
                throw new Exception("Thư mục dự án đã tồn tại: $targetDir. Vui lòng chọn tên khác.");
            }

            if (file_exists($finalSourceZip)) {
                writeJobLog($jobId, ['status' => 'info', 'log' => "Tìm thấy file ZIP mẫu. Đang giải nén và đổi tên..."]);
                // Extract into parent category folder
                $projectDeployer->extractZip($finalSourceZip, $parentDir);
                
                // The extracted folder will have the name of the zip (usually)
                $extractedPath = $parentDir . DIRECTORY_SEPARATOR . $sourceBaseName;
                if (is_dir($extractedPath)) {
                    rename($extractedPath, $targetDir);
                } else {
                    // Fallback: If zip doesn't contain a folder, it was extracted directly into parentDir
                    // This is complex, so we assume zip contains the folder.
                    // If not, we might need to create targetDir first and extract inside.
                }
            } elseif (is_dir($finalSourceFolder)) {
                writeJobLog($jobId, ['status' => 'info', 'log' => "Đang copy từ nguồn: $finalSourceFolder"]);
                $projectDeployer->copyRecursive($finalSourceFolder, $targetDir);
            } else {
                throw new Exception("Không tìm thấy source mẫu.");
            }

            // 1b. Copy .agents folder/files into new project
            $agentsDir = __DIR__ . DIRECTORY_SEPARATOR . '.agents';
            if (is_dir($agentsDir)) {
                writeJobLog($jobId, ['status' => 'info', 'log' => "Đang copy thư mục .agents vào dự án..."]);
                $projectDeployer->copyRecursive($agentsDir, $targetDir . DIRECTORY_SEPARATOR . '.agents');
                writeJobLog($jobId, ['status' => 'info', 'log' => "✅ Đã copy .agents vào dự án."]);
            }

            // 2. Find SQL file to import
            writeJobLog($jobId, ['status' => 'info', 'log' => "Đang kiểm tra file SQL trong thư mục dự án..."]);
            $sqlFile = null;
            $filesInTarget = scandir($targetDir);
            foreach ($filesInTarget as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $sqlFile = $targetDir . DIRECTORY_SEPARATOR . $file;
                    writeJobLog($jobId, ['status' => 'info', 'log' => "Tìm thấy file SQL: $file"]);
                    break;
                }
            }

            // Fallback: Export Source DB if no SQL file found in folder
            if (!$sqlFile) {
                $sqlFile = $targetDir . DIRECTORY_SEPARATOR . 'database_dump.sql';
                writeJobLog($jobId, ['status' => 'info', 'log' => "Không tìm thấy file SQL trong thư mục, đang trích xuất từ database nguồn ($sourceDbName)..."]);
                $deployService->exportLocalDatabase([
                    'host' => 'localhost',
                    'name' => $sourceDbName,
                    'user' => 'root',
                    'pass' => ''
                ], $sqlFile);
            }

            // 3. Create Target DB
            writeJobLog($jobId, ['status' => 'info', 'log' => "Đang tạo database mới: $dbName"]);
            $projectDeployer->createDatabase($dbName);

            // 4. Import SQL
            writeJobLog($jobId, ['status' => 'info', 'log' => "Đang import dữ liệu vào database mới..."]);
            $projectDeployer->importSql($dbName, $sqlFile);

            // 5. Update .env
            writeJobLog($jobId, ['status' => 'info', 'log' => "Đang cấu hình file .env..."]);
            $sitePath = '/' . str_replace('\\', '/', $category) . '/' . $projectName . '/';
            $envUpdates = [
                'SITE_PATH' => $sitePath,
                'APP_URL' => '"http://localhost${SITE_PATH}"',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => '3306',
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => 'root',
                'DB_PASSWORD' => ''
            ];
            $projectDeployer->updateEnv($targetDir . '/.env', $envUpdates);

            // Cleanup SQL file
            @unlink($sqlFile);

            $projectConfig = $configManager->getForProject($projectName, $category) ?: [];
            $projectConfig['configured_local'] = true;
            $projectConfig['lock_demo'] = false;
            $projectConfig['lock_production'] = false;

            $configManager->save($projectName, $projectConfig, $category);
            $configManager->addHistory($projectName, 'Triển khai dự án mới', "Khởi tạo thành công từ Source mẫu (Database: $dbName).", $category);

            writeJobLog($jobId, ['status' => 'success', 'message' => "Triển khai dự án $projectName thành công!"]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            writeJobLog($jobId, ['status' => 'error', 'message' => $e->getMessage()]);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'fmList':
    case 'fmGet':
    case 'fmSave':
    case 'fmGetDiff':
    case 'fmListBackups':
    case 'fmGetBackupContent':
    case 'fmRestoreBackup':
    case 'fmDeleteBackups':
    case 'fmDelete':
    case 'fmUpload':
    case 'fmCreateDir':
    case 'fmOpenInEditor':
    case 'fmSyncLocalFile':
    case 'fmWatchFile':
    case 'fmCheckSyncStatus':
    case 'fmSyncCenterCompare':
    case 'fmSyncCenterExecute':
        $data = readJsonInput();
        if (!$data) $data = $_POST;
        
        $projectName = $data['name'] ?? $_GET['name'] ?? '';
        $category = $data['category'] ?? $_GET['category'] ?? '';
        $path = $data['path'] ?? $_GET['path'] ?? '/';
        
        $projectConfig = $configManager->getForProject($projectName, $category) ?: [];
        $requestEnv = $data['env'] ?? $_GET['env'] ?? 'demo';
        $hostConfigInfo = getProjectTargetHostConfig($projectConfig, $requestEnv);
        $currentEnv = $hostConfigInfo['env'];
        $config = $hostConfigInfo['config'];
        $envLabel = ($currentEnv === 'prod') ? 'Production Hosting' : 'Demo Hosting';

        if (!$config || empty($config['ftp_host'])) {
            echo json_encode(['status' => 'error', 'message' => "Môi trường {$envLabel} chưa được cấu hình FTP trong Cấu hình dự án."]);
            break;
        }
        
        $host = $config['ftp_host'];
        $user = $config['ftp_user'];
        $pass = $config['ftp_pass'];
        $userPwd = "$user:$pass";

        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        
        if ($currentEnv === 'prod') {
            $baseFtpRoot = !empty($config['ftp_root']) ? $config['ftp_root'] : '/public_html';
            $ftpRoot = '/' . ltrim(rtrim($baseFtpRoot, '/'), '/');
            $customDomain = $config['web_domain'] ?? ($config['domain'] ?? '');
            if (!empty($customDomain)) {
                $cleanHost = str_replace(['https://', 'http://', '/'], '', $customDomain);
            } else {
                $cleanHost = str_replace(['ftp.', 'www.'], '', $config['ftp_host']);
            }
            $remoteBaseUrl = "http" . (!empty($config['ssl']) ? 's' : '') . "://$cleanHost";
        } else {
            $customDomain = $projectConfig['deployed']['demo']['custom_domain'] ?? '';
            if (empty($customDomain)) {
                $webDomain = str_replace(['https://', 'http://', '/'], '', $config['web_domain']);
                $folderName = $project ? str_replace('\\', '/', trim($project['relPath'], '/\\')) : ($category . '/' . $projectName);
                $ftpRoot = '/domains/' . $webDomain . '/public_html/' . $folderName;
                $remoteBaseUrl = "http" . (!empty($config['ssl']) ? 's' : '') . "://$webDomain/$folderName";
            } else {
                $ftpRoot = '/domains/' . $customDomain . '/public_html';
                $remoteBaseUrl = "http" . (!empty($config['ssl']) ? 's' : '') . "://$customDomain";
            }
        }
        
        $cleanPath = ltrim($path, '/');
        // Ensure path starts from the project's root on the target server
        $remotePath = rtrim($ftpRoot, '/') . '/' . $cleanPath;
        $url = "ftp://$host$remotePath";
        
        if ($action === 'fmList') {
            $files = RemoteClient::listFtpDirectoryDetailed($url, $userPwd);
            if (empty($files) && $currentEnv === 'demo' && $cleanPath === '') {
                $deploySubPath = $project['relPath'] ?? '';
                if ($deploySubPath && !$deployService->remoteDirExists($config, $deploySubPath)) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Dự án chưa tồn tại trên Demo Server (thư mục '{$deploySubPath}' chưa được triển khai). Vui lòng Deploy Demo trước!"
                    ]);
                    break;
                }
            }
            if ($project && !empty($project['path'])) {
                $localBasePath = rtrim(str_replace('\\', '/', $project['path']), '/') . ($cleanPath ? "/$cleanPath" : '');
                foreach ($files as &$f) {
                    if (!empty($f['is_dir'])) {
                        $localSub = $localBasePath . '/' . $f['name'];
                        if (is_dir($localSub)) {
                            $subItems = @scandir($localSub);
                            $hasSub = false;
                            if ($subItems !== false) {
                                foreach ($subItems as $si) {
                                    if ($si === '.' || $si === '..') continue;
                                    if (is_dir($localSub . '/' . $si)) {
                                        $hasSub = true;
                                        break;
                                    }
                                }
                            }
                            $f['has_subdirs'] = $hasSub;
                        }
                    }
                }
                unset($f);
            }
            echo json_encode(['status' => 'success', 'data' => $files, 'baseUrl' => rtrim($remoteBaseUrl, '/'), 'env' => $currentEnv, 'env_label' => $envLabel]);
        } 
        elseif ($action === 'fmGet') {
            $res = RemoteClient::getFtpFileContent($url, $userPwd);
            echo json_encode($res);
        }
        elseif ($action === 'fmSave') {
            $content = $data['content'] ?? '';
            // Auto Backup before saving remote file
            $existingRemote = RemoteClient::getFtpFileContent($url, $userPwd);
            if (($existingRemote['status'] ?? '') === 'success' && isset($existingRemote['content'])) {
                saveSyncAutoBackup($projectName, $category, 'remote_before_edit', $cleanPath, $existingRemote['content'], $currentEnv);
            }
            $res = RemoteClient::saveFtpFileContent($url, $userPwd, $content);
            if ($res === true) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $res]);
            }
        }
        elseif ($action === 'fmGetDiff') {
            if (!$project) {
                echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại ở Local']);
                break;
            }
            $localFile = rtrim(str_replace('\\', '/', $project['path']), '/') . '/' . $cleanPath;
            
            $localExists = file_exists($localFile);
            $localContent = $localExists ? @file_get_contents($localFile) : null;
            $localMtime = $localExists ? @filemtime($localFile) : null;
            $localSize = $localExists ? @filesize($localFile) : 0;
            
            $remoteRes = RemoteClient::getFtpFileContent($url, $userPwd);
            $remoteExists = ($remoteRes['status'] ?? '') === 'success';
            $remoteContent = $remoteExists ? $remoteRes['content'] : null;
            $remoteSize = $remoteExists ? strlen($remoteContent) : 0;
            
            echo json_encode([
                'status' => 'success',
                'path' => $cleanPath,
                'local' => [
                    'exists' => $localExists,
                    'content' => $localContent,
                    'mtime' => $localMtime,
                    'size' => $localSize
                ],
                'remote' => [
                    'exists' => $remoteExists,
                    'content' => $remoteContent,
                    'size' => $remoteSize
                ]
            ]);
        }
        elseif ($action === 'fmListBackups') {
            $safeProj = preg_replace('/[^a-zA-Z0-9_\-]/', '_', "{$category}_{$projectName}");
            $backupBase = __DIR__ . "/backups/sync_snapshots/{$safeProj}";
            $backups = [];
            $filterEnv = $data['env'] ?? $_GET['env'] ?? 'all';
            
            if (is_dir($backupBase)) {
                $scanMeta = function($dir) use (&$scanMeta, &$backups, $backupBase, $filterEnv) {
                    $items = @scandir($dir);
                    if ($items === false) return;
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $path = $dir . '/' . $item;
                        if (is_dir($path)) {
                            $scanMeta($path);
                        } elseif (substr($item, -10) === '.meta.json') {
                            $raw = @file_get_contents($path);
                            if ($raw) {
                                $meta = json_decode($raw, true);
                                if ($meta) {
                                    if (empty($meta['env'])) {
                                        $relFromBase = ltrim(str_replace(['\\', $backupBase], ['/', ''], $path), '/');
                                        if (strpos($relFromBase, 'prod/') === 0) {
                                            $meta['env'] = 'prod';
                                        } else {
                                            $meta['env'] = 'demo';
                                        }
                                    }
                                    $relBackupFile = ltrim(str_replace(['\\', $backupBase], ['/', ''], substr($path, 0, -10)), '/');
                                    $meta['backup_file'] = $relBackupFile;

                                    if ($filterEnv === 'all' || $meta['env'] === $filterEnv) {
                                        $backups[] = $meta;
                                    }
                                }
                            }
                        }
                    }
                };
                $scanMeta($backupBase);
                
                // Sort newest first
                usort($backups, function($a, $b) {
                    return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
                });
            }
            
            echo json_encode(['status' => 'success', 'backups' => $backups]);
        }
        elseif ($action === 'fmGetBackupContent') {
            $backupFile = $data['backup_file'] ?? $_GET['backup_file'] ?? '';
            $safeProj = preg_replace('/[^a-zA-Z0-9_\-]/', '_', "{$category}_{$projectName}");
            $fullBackupPath = __DIR__ . "/backups/sync_snapshots/{$safeProj}/" . ltrim(str_replace(['..', '\\'], ['', '/'], $backupFile), '/');
            
            if (file_exists($fullBackupPath)) {
                $content = file_get_contents($fullBackupPath);
                echo json_encode(['status' => 'success', 'content' => $content]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'File backup không tồn tại hoặc đã bị xóa']);
            }
        }
        elseif ($action === 'fmRestoreBackup') {
            $backupFile = $data['backup_file'] ?? '';
            $target = $data['target'] ?? 'local'; // 'local' or 'remote'
            $relPath = $data['path'] ?? '';
            $restoreEnv = $data['env'] ?? 'demo';
            
            if (!$project) {
                echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại ở Local']);
                break;
            }
            if (empty($backupFile) || empty($relPath)) {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin file khôi phục']);
                break;
            }
            
            $safeProj = preg_replace('/[^a-zA-Z0-9_\-]/', '_', "{$category}_{$projectName}");
            $fullBackupPath = __DIR__ . "/backups/sync_snapshots/{$safeProj}/" . ltrim(str_replace(['..', '\\'], ['', '/'], $backupFile), '/');
            $metaPath = $fullBackupPath . '.meta.json';
            if (file_exists($metaPath)) {
                $metaRaw = @file_get_contents($metaPath);
                $metaJson = json_decode($metaRaw, true);
                if (!empty($metaJson['env'])) {
                    $restoreEnv = $metaJson['env'];
                }
            }

            if (!file_exists($fullBackupPath)) {
                echo json_encode(['status' => 'error', 'message' => 'File backup không tồn tại']);
                break;
            }
            
            $backupContent = file_get_contents($fullBackupPath);
            $cleanRelPath = ltrim(str_replace('\\', '/', $relPath), '/');
            
            if ($target === 'local') {
                $localFile = rtrim(str_replace('\\', '/', $project['path']), '/') . '/' . $cleanRelPath;
                // Safety backup current local file
                if (file_exists($localFile)) {
                    saveSyncAutoBackup($projectName, $category, 'local_before_restore', $cleanRelPath, @file_get_contents($localFile), $restoreEnv);
                }
                $dir = dirname($localFile);
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                @file_put_contents($localFile, $backupContent);
                echo json_encode(['status' => 'success', 'message' => "Đã khôi phục file {$cleanRelPath} về Local thành công!"]);
            } else {
                // Restore to Remote Hosting (Demo or Production depending on backup source)
                $hostConfigInfo = getProjectTargetHostConfig($projectConfig, $restoreEnv);
                $tConfig = $hostConfigInfo['config'];
                $tEnv = $hostConfigInfo['env'];
                $targetLabel = ($tEnv === 'prod') ? 'Production Hosting' : 'Demo Hosting';

                if (!$tConfig || empty($tConfig['ftp_host'])) {
                    echo json_encode(['status' => 'error', 'message' => "Chưa cấu hình FTP cho {$targetLabel} để khôi phục"]);
                    break;
                }
                
                $rHost = $tConfig['ftp_host'];
                $rUser = $tConfig['ftp_user'];
                $rPass = $tConfig['ftp_pass'];
                $rUserPwd = "$rUser:$rPass";

                if ($tEnv === 'prod') {
                    $baseFtpRoot = !empty($tConfig['ftp_root']) ? $tConfig['ftp_root'] : '/public_html';
                    $rFtpRoot = '/' . ltrim(rtrim($baseFtpRoot, '/'), '/');
                } else {
                    $customDomain = $projectConfig['deployed']['demo']['custom_domain'] ?? '';
                    if (empty($customDomain)) {
                        $webDomain = str_replace(['https://', 'http://', '/'], '', $tConfig['web_domain']);
                        $folderName = $project ? str_replace('\\', '/', trim($project['relPath'], '/\\')) : ($category . '/' . $projectName);
                        $rFtpRoot = '/domains/' . $webDomain . '/public_html/' . $folderName;
                    } else {
                        $rFtpRoot = '/domains/' . $customDomain . '/public_html';
                    }
                }

                $restoreRemotePath = rtrim($rFtpRoot, '/') . '/' . $cleanRelPath;
                $restoreUrl = "ftp://$rHost$restoreRemotePath";
                
                // Safety backup current remote file with target environment
                $existingRemote = RemoteClient::getFtpFileContent($restoreUrl, $rUserPwd);
                if (($existingRemote['status'] ?? '') === 'success' && isset($existingRemote['content'])) {
                    saveSyncAutoBackup($projectName, $category, 'remote_before_restore', $cleanRelPath, $existingRemote['content'], $tEnv);
                }
                
                $res = RemoteClient::saveFtpFileContent($restoreUrl, $rUserPwd, $backupContent);
                if ($res === true) {
                    echo json_encode(['status' => 'success', 'message' => "Đã khôi phục file {$cleanRelPath} lên {$targetLabel} thành công!"]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => "Lỗi FTP khi khôi phục lên {$targetLabel}: " . $res]);
                }
            }
        }
        elseif ($action === 'fmDeleteBackups') {
            $safeProj = preg_replace('/[^a-zA-Z0-9_\-]/', '_', "{$category}_{$projectName}");
            $backupBase = __DIR__ . "/backups/sync_snapshots/{$safeProj}";
            
            if (!is_dir($backupBase)) {
                echo json_encode(['status' => 'success', 'deleted' => 0, 'message' => 'Không có bản sao lưu nào để xóa']);
                break;
            }
            
            $deleteAll = !empty($data['all']);
            $backupFiles = $data['backup_files'] ?? [];
            if (!is_array($backupFiles) && !empty($backupFiles)) {
                $backupFiles = [$backupFiles];
            }
            
            $deletedCount = 0;
            
            if ($deleteAll) {
                // Delete all files in project's sync_snapshots folder recursively
                $rrmdir = function($dir) use (&$rrmdir, &$deletedCount) {
                    $items = @scandir($dir);
                    if ($items === false) return;
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $p = $dir . '/' . $item;
                        if (is_dir($p)) {
                            $rrmdir($p);
                            @rmdir($p);
                        } else {
                            if (substr($item, -10) !== '.meta.json') {
                                $deletedCount++;
                            }
                            @unlink($p);
                        }
                    }
                };
                $rrmdir($backupBase);
                @rmdir($backupBase);
            } else {
                foreach ($backupFiles as $bf) {
                    $cleanBf = ltrim(str_replace(['..', '\\'], ['', '/'], $bf), '/');
                    $fullPath = $backupBase . '/' . $cleanBf;
                    $metaPath = $fullPath . '.meta.json';
                    
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                        $deletedCount++;
                    }
                    if (file_exists($metaPath)) {
                        @unlink($metaPath);
                    }
                    
                    // Clean parent dirs if empty
                    $parentDir = dirname($fullPath);
                    while ($parentDir !== $backupBase && is_dir($parentDir)) {
                        $remaining = @scandir($parentDir);
                        if ($remaining !== false && count($remaining) <= 2) {
                            @rmdir($parentDir);
                            $parentDir = dirname($parentDir);
                        } else {
                            break;
                        }
                    }
                }
            }
            
            echo json_encode([
                'status' => 'success',
                'deleted' => $deletedCount,
                'message' => "Đã xóa {$deletedCount} bản sao lưu thành công!"
            ]);
        }
        elseif ($action === 'fmDelete') {
            $isDir = !empty($data['isDir']);
            $res = RemoteClient::deleteViaFTP($url, $userPwd, $isDir);
            if ($res === true) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $res]);
            }
        }
        elseif ($action === 'fmCreateDir') {
            $config['ftp_pass'] = $pass; // pass to makeDirViaDA / makeDirViaFTP
            $res = RemoteClient::makeDirViaFTP($config, $remotePath);
            echo $res;
        }
        elseif ($action === 'fmUpload') {
            if (!empty($_FILES['file'])) {
                $tmpFile = $_FILES['file']['tmp_name'];
                $res = RemoteClient::uploadFtp($url, $userPwd, $tmpFile);
                if ($res === true) {
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => $res]);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
            }
        }
        elseif ($action === 'fmOpenInEditor') {
            $res = RemoteClient::getFtpFileContent($url, $userPwd);
            if ($res['status'] !== 'success') {
                echo json_encode($res);
                break;
            }
            
            $cacheDir = __DIR__ . '/cache/remote_edit/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $category) . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $projectName);
            $localFile = $cacheDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);
            $localParent = dirname($localFile);
            if (!is_dir($localParent)) {
                @mkdir($localParent, 0777, true);
            }
            file_put_contents($localFile, $res['content']);
            
            try {
                $jobData = [
                    'name' => $projectName,
                    'category' => $category,
                    'path' => $cleanPath,
                    'url' => $url,
                    'userPwd' => $userPwd,
                    'localFile' => $localFile,
                    '_background' => true
                ];
                startApiBackgroundJob('fmWatchFile', $jobData, null);
            } catch (Throwable $e) {}
            
            $urlSafePath = str_replace('\\', '/', $localFile);
            $ideUrl = 'antigravity://file/' . ltrim($urlSafePath, '/');
            
            echo json_encode(['status' => 'success', 'localPath' => $localFile, 'ideUrl' => $ideUrl]);
        }
        elseif ($action === 'fmSyncLocalFile') {
            $cacheDir = __DIR__ . '/cache/remote_edit/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $category) . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $projectName);
            $localFile = $cacheDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);
            if (!file_exists($localFile)) {
                echo json_encode(['status' => 'error', 'message' => 'File cục bộ không tồn tại để đồng bộ']);
                break;
            }
            $content = file_get_contents($localFile);
            $res = RemoteClient::saveFtpFileContent($url, $userPwd, $content);
            if ($res === true) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $res]);
            }
        }
        elseif ($action === 'fmWatchFile') {
            $data = readJsonInput();
            $path = $data['path'] ?? '';
            $url = $data['url'] ?? '';
            $userPwd = $data['userPwd'] ?? '';
            $localFile = $data['localFile'] ?? '';
            
            if (empty($localFile) || !file_exists($localFile)) exit;
            
            $timeout = time() + (2 * 3600); // 2 hours
            $mtime = filemtime($localFile);
            
            while(time() < $timeout) {
                clearstatcache();
                if (!file_exists($localFile)) break;
                
                $new = filemtime($localFile);
                if ($new > $mtime) {
                    $mtime = $new;
                    $content = file_get_contents($localFile);
                    RemoteClient::saveFtpFileContent($url, $userPwd, $content);
                    file_put_contents($localFile . '.sync', microtime(true));
                }
                sleep(2);
            }
            exit;
        }
        elseif ($action === 'fmCheckSyncStatus') {
            $data = readJsonInput();
            if (!$data) $data = $_POST;
            $path = $data['path'] ?? $_GET['path'] ?? '/';
            $cleanPath = ltrim($path, '/');
            $cacheDir = __DIR__ . '/cache/remote_edit/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $category) . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $projectName);
            $localFile = $cacheDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);
            $syncFile = $localFile . '.sync';
            if (file_exists($syncFile)) {
                echo json_encode(['status' => 'success', 'time' => file_get_contents($syncFile)]);
            } else {
                echo json_encode(['status' => 'success', 'time' => 0]);
            }
        }
        elseif ($action === 'fmSyncCenterCompare') {
            $data = readJsonInput();
            if (!$data) $data = $_POST;
            
            $projectName = $data['name'] ?? '';
            $category = $data['category'] ?? '';
            $allowUploadBridge = !empty($data['allow_upload_bridge']);
            $customExcludes = $data['excludes'] ?? [];
            $defaultExcludes = [
                'bootstrap', 'caches', 'compiled', 'config_contents', 'thumbs', 'upload', 'vendor', 'watermarks', 'watermark', 'logs',
                '.agents', '.git', '.idea', '.vscode', 'tools', 'docs', 'graphify-out',
                'assets/caches', 'assets/images/images', 'src/views/templates/layout/backup', 'assets/css/backup',
                'assets/admin/json', 'libraries/config.php'
            ];
            if (!empty($customExcludes) && is_array($customExcludes)) {
                $defaultExcludes = array_unique(array_merge($defaultExcludes, $customExcludes));
            }

            $isPathExcluded = function($relPath) use ($defaultExcludes) {
                $clean = strtolower(trim(str_replace('\\', '/', $relPath), '/'));
                if ($clean === '') return false;
                $firstPart = explode('/', $clean)[0];
                
                foreach ($defaultExcludes as $ex) {
                    $exNorm = strtolower(trim(str_replace('\\', '/', $ex), '/'));
                    if ($exNorm === '') continue;
                    if (strpos($exNorm, '/') === false) {
                        if ($firstPart === $exNorm) return true;
                    } else {
                        if ($clean === $exNorm || strpos($clean, $exNorm . '/') === 0) {
                            return true;
                        }
                    }
                }
                return false;
            };

            $includeClearData = !empty($data['include_cleardata']);
            $isClearDataFile = function($path, $localDir = '') {
                $clean = str_replace('\\', '/', $path);
                if (stripos($clean, 'cleardata') !== false) {
                    return true;
                }
                if (strtolower($clean) === 'src/routes/web.php' || preg_match('#^src/Routes/#i', $clean)) {
                    if ($localDir) {
                        $fullLocal = rtrim($localDir, '/\\') . '/' . ltrim($clean, '/');
                        if (file_exists($fullLocal)) {
                            $c = @file_get_contents($fullLocal);
                            if (stripos($c, 'cleardata') !== false) {
                                return true;
                            }
                        }
                    } else {
                        return true;
                    }
                }
                return false;
            };
            
            $projectConfig = $configManager->getForProject($projectName, $category) ?: [];
            $syncEnv = $data['env'] ?? 'demo';
            $hostConfigInfo = getProjectTargetHostConfig($projectConfig, $syncEnv);
            $currentEnv = $hostConfigInfo['env'];
            $config = $hostConfigInfo['config'];
            $envLabel = ($currentEnv === 'prod') ? 'Production Hosting' : 'Demo Hosting';

            if (!$config || empty($config['ftp_host'])) {
                echo json_encode(['status' => 'error', 'message' => "Chưa cấu hình FTP cho {$envLabel}. Vui lòng kiểm tra lại trong Cấu hình dự án."]);
                break;
            }
            
            $projects = $scanner->getProjects($category);
            $project = null;
            foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
            if (!$project) {
                $allProjects = $scanner->getProjects('all');
                foreach ($allProjects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
            }
            if (!$project) {
                $rawProjects = $scanner->scanProjectsRaw($category);
                foreach ($rawProjects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
            }
            if (!$project) {
                echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại ở Local']);
                break;
            }
            
            // 1. Kiểm tra Bridge trên Remote Host trước tiên (Fast ping: < 0.2s)
            $cleanHost = !empty($config['web_domain']) ? str_replace(['https://', 'http://', '/'], '', $config['web_domain']) : str_replace(['ftp.', 'www.'], '', $config['ftp_host']);
            if ($currentEnv === 'prod') {
                $bridgePingUrls = [
                    'https://' . $cleanHost . '/bridge.php?action=ping',
                    'http://' . $cleanHost . '/bridge.php?action=ping'
                ];
                $bridgeUrls = [
                    'https://' . $cleanHost . '/bridge.php?action=scanFiles',
                    'http://' . $cleanHost . '/bridge.php?action=scanFiles'
                ];
                $deploySubPath = '';
            } else {
                $webSub = $deployService->getWebSubPath($config['ftp_root'] ?? '');
                $fullSubPath = rtrim($webSub, '/') . '/' . trim($project['relPath'], '/');
                $bridgePingUrls = [
                    'https://' . $cleanHost . '/' . ltrim($fullSubPath, '/') . '/bridge.php?action=ping',
                    'http://' . $cleanHost . '/' . ltrim($fullSubPath, '/') . '/bridge.php?action=ping'
                ];
                $bridgeUrls = [
                    'https://' . $cleanHost . '/' . ltrim($fullSubPath, '/') . '/bridge.php?action=scanFiles',
                    'http://' . $cleanHost . '/' . ltrim($fullSubPath, '/') . '/bridge.php?action=scanFiles'
                ];
                $deploySubPath = $project['relPath'];
            }

            // Ping nhanh bridge để kiểm tra sự tồn tại (timeout 2.5s)
            $pingBridge = function() use (&$bridgePingUrls) {
                foreach ($bridgePingUrls as $url) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $res = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode === 200) {
                        $data = json_decode($res, true);
                        if (is_array($data) && ($data['status'] ?? '') === 'success') {
                            return true;
                        }
                    }
                }
                return false;
            };

            $bridgeReady = $pingBridge();

            // Nếu Bridge chưa sẵn sàng trên Host
            if (!$bridgeReady) {
                if ($currentEnv === 'demo') {
                    if (!$deployService->remoteDirExists($config, $deploySubPath)) {
                        echo json_encode([
                            'status' => 'error',
                            'message' => "Dự án chưa tồn tại trên Demo Server (thư mục '{$deploySubPath}' chưa được triển khai trên hosting). Vui lòng Deploy Demo trước khi thực hiện Đồng bộ hoặc Quản lý File!"
                        ]);
                        break;
                    }
                }

                // BẢO MẬT: Kiểm tra xem người dùng đã đồng ý cho tải bridge.php lên server chưa
                if (!$allowUploadBridge) {
                    echo json_encode([
                        'status' => 'bridge_missing',
                        'code' => 'BRIDGE_MISSING',
                        'env' => $currentEnv,
                        'env_label' => $envLabel,
                        'message' => "Chưa có tệp kết nối bridge.php trên máy chủ {$envLabel}."
                    ]);
                    break;
                }

                // Người dùng đã xác nhận đồng ý -> tiến hành tải bridge.php lên host
                try {
                    $deployService->upload($config, ['bridge.php' => __DIR__ . '/bridge.php'], $deploySubPath);
                } catch (\Exception $e) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Không thể tải tệp bridge.php lên {$envLabel}: " . $e->getMessage()
                    ]);
                    break;
                }
            }

            // 2. Khi Bridge đã sẵn sàng -> Bắt đầu Scan Local
            $localFiles = [];
            $localRoot = rtrim($project['path'], '/\\');
            $scanLocal = function($dir, $relPrefix = '') use (&$scanLocal, &$localFiles, $isPathExcluded, $localRoot, $includeClearData, $isClearDataFile) {
                $items = @scandir($dir);
                if ($items === false) return;
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $path = $dir . '/' . $item;
                    $relPath = $relPrefix . $item;
                    if ($isPathExcluded($relPath)) continue;
                    if ($relPath === 'bridge.php' || $relPath === 'dist.zip' || $relPath === 'dist.sql') continue;
                    if (!$includeClearData && $isClearDataFile($relPath, $localRoot)) continue;
                    $excludedFiles = ['readme.md', 'vite.config.js', '.env', '.htaccess', 'data.dat'];
                    if (in_array(strtolower($item), $excludedFiles)) continue;
                    if (is_dir($path)) {
                        $scanLocal($path, $relPath . '/');
                    } else {
                        $size = filesize($path);
                        $localFiles[$relPath] = [
                            'mtime' => filemtime($path),
                            'size' => $size,
                            'md5' => ($size < 2097152) ? md5_file($path) : 'sz_' . $size
                        ];
                    }
                }
            };
            $scanLocal($localRoot);

            // 3. Gọi Bridge scan Remote Files
            $callBridge = function($targetUrl = null) use (&$bridgeUrls, $defaultExcludes, $includeClearData) {
                $urls = $targetUrl ? [$targetUrl] : $bridgeUrls;
                $lastRes = null;
                foreach ($urls as $url) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['excludes' => $defaultExcludes, 'include_cleardata' => $includeClearData]));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_POSTREDIR, 3);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $res = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlErr = curl_error($ch);
                    curl_close($ch);
                    
                    $data = json_decode($res, true);
                    if (is_array($data) && ($data['status'] ?? '') === 'success') {
                        return $data;
                    }
                    $lastRes = is_array($data) ? $data : [
                        'status' => 'error', 
                        'http_code' => $httpCode, 
                        'curl_err' => $curlErr, 
                        'url' => $url,
                        'raw' => substr((string)$res, 0, 200)
                    ];
                }
                return $lastRes;
            };

            $remoteData = $callBridge();

            // Nếu bridge vừa ping được nhưng scanFiles báo phiên bản cũ -> cập nhật lại
            if (!$remoteData || ($remoteData['status'] ?? '') !== 'success' || ($remoteData['version'] ?? '') !== 'v6_sub_excludes') {
                if ($allowUploadBridge) {
                    try {
                        $deployService->upload($config, ['bridge.php' => __DIR__ . '/bridge.php'], $deploySubPath);
                        $remoteData = $callBridge();
                    } catch (\Exception $e) {}
                }
            }

            if (!$remoteData || ($remoteData['status'] ?? '') !== 'success') {
                $detail = !empty($remoteData['message']) ? $remoteData['message'] : (!empty($remoteData['curl_err']) ? $remoteData['curl_err'] : ('HTTP ' . ($remoteData['http_code'] ?? 'Unknown')));
                echo json_encode(['status' => 'error', 'message' => "Bridge {$envLabel} không phản hồi ($detail). Vui lòng kiểm tra lại cấu hình Hosting."]);
                break;
            }
            
            $remoteFiles = $remoteData['files'] ?? [];
            foreach ($remoteFiles as $rPath => $rVal) {
                if ($isPathExcluded($rPath)) {
                    unset($remoteFiles[$rPath]);
                    continue;
                }
                if (!$includeClearData && $isClearDataFile($rPath, $localRoot)) {
                    unset($remoteFiles[$rPath]);
                }
            }
            
            // 3. Compare with MD5 Hash
            $comparison = [
                'local_newer' => [],
                'remote_newer' => [],
                'local_only' => [],
                'remote_only' => [],
                'conflict' => []
            ];
            
            foreach ($localFiles as $path => $lData) {
                if (isset($remoteFiles[$path])) {
                    $rData = $remoteFiles[$path];
                    
                    // 1. If MD5 matches, content is 100% identical -> Skip!
                    if (isset($lData['md5']) && isset($rData['md5']) && $lData['md5'] === $rData['md5']) {
                        continue;
                    }
                    
                    // 2. MD5 differs -> Content changed, compare mtime
                    $diff = $lData['mtime'] - $rData['mtime'];
                    if ($diff > 0) {
                        $comparison['local_newer'][] = [
                            'path' => $path,
                            'local_mtime' => $lData['mtime'],
                            'remote_mtime' => $rData['mtime']
                        ];
                    } elseif ($diff < 0) {
                        $comparison['remote_newer'][] = [
                            'path' => $path,
                            'local_mtime' => $lData['mtime'],
                            'remote_mtime' => $rData['mtime']
                        ];
                    } else {
                        $comparison['conflict'][] = [
                            'path' => $path,
                            'local_mtime' => $lData['mtime'],
                            'remote_mtime' => $rData['mtime'],
                            'local_size' => $lData['size'],
                            'remote_size' => $rData['size']
                        ];
                    }
                } else {
                    $comparison['local_only'][] = [
                        'path' => $path,
                        'local_mtime' => $lData['mtime'],
                        'remote_mtime' => null
                    ];
                }
            }
            
            foreach ($remoteFiles as $path => $rData) {
                if (!isset($localFiles[$path])) {
                    $comparison['remote_only'][] = [
                        'path' => $path,
                        'local_mtime' => null,
                        'remote_mtime' => $rData['mtime']
                    ];
                }
            }
            
            echo json_encode([
                'status' => 'success', 
                'env' => $currentEnv, 
                'env_label' => $envLabel,
                'comparison' => $comparison
            ]);
        }
        elseif ($action === 'fmSyncCenterExecute') {
            @set_time_limit(300);
            $data = readJsonInput();
            if (!$data) $data = $_POST;
            
            $projectName = $data['name'] ?? '';
            $category = $data['category'] ?? '';
            $actions = $data['actions'] ?? []; // ['upload' => [...paths], 'download' => [...paths]]
            $includeClearData = !empty($data['include_cleardata']);
            $defaultExcludes = [
                'bootstrap', 'caches', 'compiled', 'config_contents', 'thumbs', 'upload', 'vendor', 'watermarks', 'watermark', 'logs',
                '.agents', '.git', '.idea', '.vscode', 'tools', 'docs', 'graphify-out',
                'assets/caches', 'assets/images/images', 'src/views/templates/layout/backup', 'assets/css/backup',
                'assets/admin/json', 'libraries/config.php'
            ];
            $isPathExcluded = function($relPath) use ($defaultExcludes) {
                $clean = strtolower(trim(str_replace('\\', '/', $relPath), '/'));
                if ($clean === '') return false;
                $firstPart = explode('/', $clean)[0];
                
                foreach ($defaultExcludes as $ex) {
                    $exNorm = strtolower(trim(str_replace('\\', '/', $ex), '/'));
                    if ($exNorm === '') continue;
                    if (strpos($exNorm, '/') === false) {
                        if ($firstPart === $exNorm) return true;
                    } else {
                        if ($clean === $exNorm || strpos($clean, $exNorm . '/') === 0) {
                            return true;
                        }
                    }
                }
                return false;
            };

            $isClearDataFile = function($path, $localDir = '') {
                $clean = str_replace('\\', '/', $path);
                if (stripos($clean, 'cleardata') !== false) {
                    return true;
                }
                if (strtolower($clean) === 'src/routes/web.php' || preg_match('#^src/Routes/#i', $clean)) {
                    if ($localDir) {
                        $fullLocal = rtrim($localDir, '/\\') . '/' . ltrim($clean, '/');
                        if (file_exists($fullLocal)) {
                            $c = @file_get_contents($fullLocal);
                            if (stripos($c, 'cleardata') !== false) {
                                return true;
                            }
                        }
                    } else {
                        return true;
                    }
                }
                return false;
            };
            
            $projectConfig = $configManager->getForProject($projectName, $category) ?: [];
            $syncEnv = $data['env'] ?? 'demo';
            $hostConfigInfo = getProjectTargetHostConfig($projectConfig, $syncEnv);
            $currentEnv = $hostConfigInfo['env'];
            $config = $hostConfigInfo['config'];
            $envLabel = ($currentEnv === 'prod') ? 'Production Hosting' : 'Demo Hosting';

            if (!$config || empty($config['ftp_host'])) {
                echo json_encode(['status' => 'error', 'message' => "Chưa cấu hình FTP cho {$envLabel}"]);
                break;
            }
            
            $projects = $scanner->getProjects($category);
            $project = null;
            foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
            if (!$project) {
                $allProjects = $scanner->getProjects('all');
                foreach ($allProjects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
            }
            if (!$project) {
                $rawProjects = $scanner->scanProjectsRaw($category);
                foreach ($rawProjects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
            }
            if (!$project) {
                echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại ở Local']);
                break;
            }
            
            $host = $config['ftp_host'];
            $user = $config['ftp_user'];
            $pass = $config['ftp_pass'];
            $userPwd = "$user:$pass";
            
            if ($currentEnv === 'prod') {
                $baseFtpRoot = !empty($config['ftp_root']) ? $config['ftp_root'] : '/public_html';
                $ftpRoot = '/' . ltrim(rtrim($baseFtpRoot, '/'), '/');
            } else {
                $baseFtpRoot = !empty($config['ftp_root']) ? $config['ftp_root'] : '/public_html';
                $subPath = $project ? str_replace('\\', '/', trim($project['relPath'], '/\\')) : ($category . '/' . $projectName);
                $customDomain = $projectConfig['deployed']['demo']['custom_domain'] ?? '';
                if (!empty($customDomain)) {
                    $ftpRoot = '/domains/' . $customDomain . '/public_html';
                } else {
                    $ftpRoot = '/' . ltrim(rtrim($baseFtpRoot, '/'), '/') . '/' . trim($subPath, '/');
                }
            }
            
            $localRoot = rtrim(str_replace('\\', '/', $project['path']), '/');
            $results = [];
            
            // Handle Uploads
            if (!empty($actions['upload'])) {
                foreach ($actions['upload'] as $path) {
                    $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
                    if ($isPathExcluded($cleanPath)) {
                        continue;
                    }
                    if (!$includeClearData && $isClearDataFile($cleanPath, $localRoot)) {
                        continue;
                    }
                    $localFile = $localRoot . '/' . $cleanPath;
                    $remotePath = rtrim($ftpRoot, '/') . '/' . $cleanPath;
                    $url = "ftp://$host$remotePath";
                    if (file_exists($localFile)) {
                        $content = file_get_contents($localFile);
                        // Auto Backup: get current remote file before overwriting (segregated by env)
                        $existingRemote = RemoteClient::getFtpFileContent($url, $userPwd);
                        if (($existingRemote['status'] ?? '') === 'success' && isset($existingRemote['content'])) {
                            saveSyncAutoBackup($projectName, $category, 'remote_before_upload', $cleanPath, $existingRemote['content'], $currentEnv);
                        }
                        $res = RemoteClient::saveFtpFileContent($url, $userPwd, $content);
                        $results[] = ['path' => $cleanPath, 'action' => 'upload', 'status' => $res === true ? 'success' : 'error', 'message' => $res];
                    }
                }
            }
            
            // Handle Downloads
            if (!empty($actions['download'])) {
                foreach ($actions['download'] as $path) {
                    $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
                    if ($isPathExcluded($cleanPath)) {
                        continue;
                    }
                    if (!$includeClearData && $isClearDataFile($cleanPath, $localRoot)) {
                        continue;
                    }
                    $localFile = $localRoot . '/' . $cleanPath;
                    $remotePath = rtrim($ftpRoot, '/') . '/' . $cleanPath;
                    $url = "ftp://$host$remotePath";
                    
                    $res = RemoteClient::getFtpFileContent($url, $userPwd);
                    if ($res['status'] === 'success') {
                        // Auto Backup: backup current local file before overwriting
                        if (file_exists($localFile)) {
                            $existingLocal = @file_get_contents($localFile);
                            saveSyncAutoBackup($projectName, $category, 'local_before_download', $cleanPath, $existingLocal, $currentEnv);
                        }
                        $dir = dirname($localFile);
                        if (!is_dir($dir)) mkdir($dir, 0777, true);
                        file_put_contents($localFile, $res['content']);
                        $results[] = ['path' => $cleanPath, 'action' => 'download', 'status' => 'success'];
                    } else {
                        $results[] = ['path' => $cleanPath, 'action' => 'download', 'status' => 'error', 'message' => $res['message']];
                    }
                }
            }
            
            echo json_encode(['status' => 'success', 'results' => $results]);
        }
        break;

    case 'preCheckDeployDemo':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectName = $data['name'] ?? '';
        $category = $data['category'] ?? '';
        $manualSuffix = $data['manual_db_suffix'] ?? null;

        if (!$projectName || !$category) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin dự án hoặc danh mục']);
            break;
        }

        $projectConfig = $configManager->getForProject($projectName, $category) ?: [];

        $config = getDemoConfigForProject($projectConfig);
        if (!$config) {
            echo json_encode(['status' => 'error', 'message' => 'Cấu hình chung chưa thiết lập']);
            break;
        }

        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại']);
            break;
        }

        $dbSuffix = $deployService->generateDemoDbName($project['category'], $projectName, $manualSuffix);
        $mainUser = !empty($config['da_user']) ? $config['da_user'] : $config['ftp_user'];
        $dbName = $mainUser . '_' . $dbSuffix;

        // 1. Kiểm tra database, user có tồn tại không
        $dbExists = false;
        try {
            $dbExists = $deployService->directAdminDbExists($config, $dbSuffix);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối DirectAdmin: ' . $e->getMessage()]);
            break;
        }

        if (!$dbExists) {
            echo json_encode([
                'status' => 'success',
                'action' => 'proceed',
                'db_pass' => '',
                'message' => 'Database chưa tồn tại, tiến hành tạo mới.',
                'debug' => [
                    'db_exists' => $dbExists,
                    'db_name' => $dbName
                ]
            ]);
            break;
        }

        $isDemo = true;
        // Database đã tồn tại!
        // 2. Kiểm tra xem dự án đã tồn tại trên demo chưa (check file index.php)
        $relPath = $project['relPath'];
        $indexExists = false;
        try {
            $indexExists = $deployService->remoteFileExists($config, $relPath . '/index.php');
        } catch (\Exception $e) {}

        $dbPass = null;
        // 3. Đọc pass từ file .env hoặc libraries/config.php trên demo
        try {
            $remoteEnvContent = $deployService->downloadRemoteFile($config, $relPath . '/.env');
            if ($remoteEnvContent) {
                $dbPass = $deployService->getDbPassFromEnvContent($remoteEnvContent);
            }
        } catch (\Exception $e) {}

        if (empty($dbPass)) {
            try {
                $remoteConfigContent = $deployService->downloadRemoteFile($config, $relPath . '/libraries/config.php');
                if ($remoteConfigContent) {
                    $dbPass = $deployService->getDbPassFromConfigContent($remoteConfigContent);
                }
            } catch (\Exception $e) {}
        }

        // Lấy pass dự phòng từ local (.env hoặc libraries/config.php)
        $targetPass = '';
        $localEnvPath = $project['path'] . '/.env';
        $localConfigPath = $project['path'] . '/libraries/config.php';
        if (file_exists($localEnvPath)) {
            $lines = file($localEnvPath);
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if (strpos($trimmedLine, 'DB_PASSWORD=') === 0) {
                    $targetPass = trim(substr($trimmedLine, 12));
                } elseif (strpos($trimmedLine, 'DB_PASS=') === 0) {
                    $targetPass = trim(substr($trimmedLine, 8));
                }
            }
            $targetPass = trim($targetPass, " \t\n\r\0\x0B\"'");
        } elseif (file_exists($localConfigPath)) {
            $cfgDb = $projectDeployer->extractConfigPhpDb($localConfigPath);
            if (!empty($cfgDb['password'])) {
                $targetPass = $cfgDb['password'];
            }
        }

        if (empty($targetPass)) {
            $targetPass = $projectConfig['deployed']['demo']['db_pass'] ?? '';
        }

        if (empty($targetPass)) {
            $targetPass = 'Pw' . substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 10) . substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 2);
        }

        if (empty($dbPass)) {
            $dbPass = $targetPass;
        }

        // Kiểm tra xem dự án có tồn tại trên Demo trước khi upload bridge để kiểm tra Database
        if ($isDemo && !$indexExists && !$deployService->remoteDirExists($config, $relPath)) {
            echo json_encode(['status' => 'error', 'message' => "Dự án chưa tồn tại trên Demo Server (chưa có '{$relPath}/index.php'). Vui lòng Deploy Demo trước!"]);
            break;
        }

        // Tải bridge.php lên trước để kiểm tra kết nối localhost
        try {
            $deployService->upload($config, ['bridge.php' => __DIR__ . '/bridge.php'], $relPath);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Không thể upload Bridge để kiểm tra Database: ' . $e->getMessage()]);
            break;
        }

        // 4. Check xem đăng nhập bằng $dbPass được không
        $checkRes = $deployService->checkRemoteDbStatus($config, $dbName, $dbName, $dbPass, $relPath);
        $connected = ($checkRes['status'] === 'success');
        $passwordUpdated = false;

        if (!$connected) {
            // 5. Nếu không đăng nhập được, đổi mật khẩu trên DirectAdmin thành $targetPass
            if (!empty($targetPass)) {
                try {
                    $deployService->changeDirectAdminDbPassword($config, $dbSuffix, $targetPass);
                    $dbPass = $targetPass;
                    // Chờ DirectAdmin cập nhật và thử kết nối lại
                    sleep(2);
                    $checkRes = $deployService->checkRemoteDbStatus($config, $dbName, $dbName, $dbPass, $relPath);
                    $connected = ($checkRes['status'] === 'success');
                    if ($connected) {
                        $passwordUpdated = true;
                    }
                } catch (\Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Đổi mật khẩu Database thất bại: ' . $e->getMessage()]);
                    break;
                }
            }
        }

        if (!$connected) {
            echo json_encode(['status' => 'error', 'message' => 'Database đã tồn tại nhưng không thể kết nối hoặc cập nhật mật khẩu mới: ' . ($checkRes['message'] ?? 'Lỗi không xác định')]);
            break;
        }

        // Cập nhật lại mật khẩu đúng vào project config để lưu vết
        if (!isset($projectConfig['deployed']['demo'])) $projectConfig['deployed']['demo'] = [];
        $projectConfig['deployed']['demo']['db_pass'] = $dbPass;
        if ($isDemo && isset($demoId)) {
            $projectConfig['deployed']['demo']['demo_server_id'] = $demoId;
        }
        $configManager->save($projectName, $projectConfig, $category);

        // 6. Kiểm tra xem database đã có dữ liệu hay chưa
        $hasData = !empty($checkRes['has_data']);

        if ($hasData) {
            echo json_encode([
                'status' => 'success',
                'action' => 'prompt_confirm',
                'db_pass' => $dbPass,
                'password_updated' => $passwordUpdated,
                'message' => 'Database đã tồn tại và đang chứa dữ liệu.',
                'debug' => [
                    'db_exists' => $dbExists,
                    'index_exists' => $indexExists,
                    'db_name' => $dbName,
                    'connected' => $connected,
                    'has_data' => $hasData,
                    'check_res' => $checkRes
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'action' => 'proceed',
                'db_pass' => $dbPass,
                'password_updated' => $passwordUpdated,
                'message' => 'Database đã kết nối thành công và chưa có dữ liệu.',
                'debug' => [
                    'db_exists' => $dbExists,
                    'index_exists' => $indexExists,
                    'db_name' => $dbName,
                    'connected' => $connected,
                    'has_data' => $hasData,
                    'check_res' => $checkRes
                ]
            ]);
        }
        break;

    case 'deploy':
    case 'deployDemo':
        $data = readJsonInput();
        $projectName = $data['name'] ?? '';
        $category = $data['category'] ?? '';
        $jobId = $data['jobId'] ?? null;
        $isDemo = ($action === 'deployDemo');
        if (PHP_SAPI !== 'cli' && empty($data['_background'])) {
            try {
                $queuedJobId = startApiBackgroundJob($action, array_merge($data, ['_background' => true]), $jobId);
                writeJobLog($queuedJobId, ['status' => 'info', 'log' => '🚀 Đã khởi động luồng xuất bản (Deploy) chạy nền...']);
                echo json_encode(['status' => 'queued', 'jobId' => $queuedJobId]);
            } catch (Throwable $e) {
                writeJobLog($jobId, ['status' => 'error', 'message' => 'Không thể khởi động luồng chạy nền: ' . $e->getMessage()]);
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;
        }

        $projectConfig = $configManager->getForProject($projectName, $category);
        
        if ($isDemo && !empty($projectConfig['lock_demo'])) { writeJobLog($jobId, ['status' => 'error', 'message' => 'Deploy Demo bị khóa']); exit; }

        if ($isDemo && !empty($data['demo_server_id'])) {
            $demoId = $data['demo_server_id'];
            $globalPath = __DIR__ . '/data/demo_config.json';
            $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
            $config = $gConfig;
            if (!empty($gConfig['demo_list'])) {
                foreach ($gConfig['demo_list'] as $d) {
                    if ($d['id'] === $demoId) {
                        $config = array_merge($gConfig, $d);
                        break;
                    }
                }
            }
        } else {
            $config = $isDemo ? getDemoConfigForProject($projectConfig) : ($projectConfig['prod'] ?? null);
        }
        if (!$config) { writeJobLog($jobId, ['status' => 'error', 'message' => 'Host chưa cấu hình']); exit; }

        if ($isDemo && !empty($data['custom_domain'])) {
            $customDomain = trim($data['custom_domain']);
            $config['web_domain'] = $customDomain;
            // Trên DirectAdmin, Addon Domain sẽ nằm trong thư mục domains/domain.com/public_html
            $config['ftp_root'] = '/domains/' . $customDomain . '/public_html';
            writeJobLog($jobId, ['status' => 'info', 'log' => '🌐 Đã kích hoạt upload lên tên miền tùy chỉnh: ' . $customDomain]);
        }

        // Cập nhật cấu hình SSL từ tham số truyền lên hoặc từ cấu hình cũ
        $useSSL = isset($data['use_ssl']) ? (bool)$data['use_ssl'] : (!empty($projectConfig['demo']['ssl']) || !empty($projectConfig['prod']['ssl']));
        $config['ssl'] = $useSSL;
        $config['clear_db'] = !empty($data['clear_db']) ? 1 : 0;
        
        // Đồng bộ SSL vào cấu hình dự án
        if (!isset($projectConfig['demo'])) $projectConfig['demo'] = [];
        if (!isset($projectConfig['prod'])) $projectConfig['prod'] = [];
        $projectConfig['demo']['ssl'] = $useSSL;
        $projectConfig['prod']['ssl'] = $useSSL;

        $projects = $scanner->getProjects($data['category'] ?? null);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) { writeJobLog($jobId, ['status' => 'error', 'message' => 'Dự án không tồn tại']); exit; }
        
        $daLogString = "";
        $packUpload = isset($data['pack_upload']) ? (bool)$data['pack_upload'] : true;
        $exportUpload = isset($data['export_upload']) ? (bool)$data['export_upload'] : true;
        $createDb = isset($data['create_db']) ? (bool)$data['create_db'] : true;
        $extractSetup = isset($data['extract_setup']) ? (bool)$data['extract_setup'] : true;

        $skipSource = !$packUpload;

        if ($isDemo) {
            $dbSuffix = $deployService->generateDemoDbName($project['category'], $projectName, $data['manual_db_suffix'] ?? null);
            
            // Read local .env or libraries/config.php DB_PASSWORD if exists
            $localEnvPass = '';
            $localEnvPath = $project['path'] . '/.env';
            $localConfigPath = $project['path'] . '/libraries/config.php';
            if (file_exists($localEnvPath)) {
                $lines = file($localEnvPath);
                foreach ($lines as $line) {
                    $trimmedLine = trim($line);
                    if (strpos($trimmedLine, 'DB_PASSWORD=') === 0) {
                        $localEnvPass = trim(substr($trimmedLine, 12));
                    } elseif (strpos($trimmedLine, 'DB_PASS=') === 0) {
                        $localEnvPass = trim(substr($trimmedLine, 8));
                    }
                }
                $localEnvPass = trim($localEnvPass, " \t\n\r\0\x0B\"'");
            } elseif (file_exists($localConfigPath)) {
                $cfgDb = $projectDeployer->extractConfigPhpDb($localConfigPath);
                if (isset($cfgDb['password'])) {
                    $localEnvPass = $cfgDb['password'];
                }
            }

            if (!empty($data['db_pass'])) {
                $dbPass = trim($data['db_pass']);
            } elseif (!empty($localEnvPass)) {
                $dbPass = $localEnvPass;
            } else {
                $dbPass = $projectConfig['deployed']['demo']['db_pass'] ?? ('Pw' . substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 18) . substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 2));
            }
            $config['db_pass'] = $dbPass;

            if (!empty($data['password_updated'])) {
                writeJobLog($jobId, ['status' => 'info', 'log' => '🔄 Đã tự động đồng bộ lại mật khẩu mới của Database trên DirectAdmin.']);
            }

            if ($createDb) {
                writeJobLog($jobId, ['status' => 'info', 'log' => '🛠️ Khởi tạo Database trên DirectAdmin...']);
                try { $daRes = $deployService->createDirectAdminDb($config, $dbSuffix, $dbPass); $daLogString = "DA API: " . $daRes; } catch (Exception $e) {}
                if (empty($daRes)) {
                    writeJobLog($jobId, ['status' => 'error', 'message' => 'DirectAdmin khong phan hoi khi tao database.']);
                    exit;
                }
                if (directAdminResponseHasError($daRes)) {
                    writeJobLog($jobId, ['status' => 'error', 'message' => 'DirectAdmin tao database that bai: ' . strip_tags(urldecode((string)$daRes))]);
                    exit;
                }
            } else {
                writeJobLog($jobId, ['status' => 'info', 'log' => 'ℹ️ Bỏ qua bước tạo Database trên DirectAdmin theo yêu cầu.']);
            }
        }

        $zipFile = __DIR__ . DIRECTORY_SEPARATOR . 'dist.zip'; 
        $sqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'dist.sql';
        if (substr($zipFile, 0, 4) === '\\\\.\\') $zipFile = substr($zipFile, 4);
        if (substr($sqlFile, 0, 4) === '\\\\.\\') $sqlFile = substr($sqlFile, 4);
        if (!$skipSource) {
            $use7zip = !empty($data['use_7zip']);
            $msg = $use7zip ? '📦 Đang nén mã nguồn bằng 7-Zip...' : '📦 Đang nén mã nguồn...';
            writeJobLog($jobId, ['status' => 'info', 'log' => $msg]);
            if (!$deployService->pack($project['path'], $zipFile, $use7zip, $jobId)) { writeJobLog($jobId, ['status' => 'error', 'message' => 'Nén thất bại']); exit; }
        } else {
            writeJobLog($jobId, ['status' => 'info', 'log' => 'ℹ️ Bỏ qua bước nén mã nguồn theo yêu cầu.']);
        }

        if ($exportUpload) {
            writeJobLog($jobId, ['status' => 'info', 'log' => '🗄️ Đang xuất SQL...']);
            if ($deployService->exportDb($project['path'], $sqlFile) !== true) { writeJobLog($jobId, ['status' => 'error', 'message' => 'Export SQL lỗi']); exit; }
        } else {
            writeJobLog($jobId, ['status' => 'info', 'log' => 'ℹ️ Bỏ qua bước xuất SQL theo yêu cầu.']);
        }

        $files = [];
        if ($extractSetup) {
            $files['bridge.php'] = __DIR__ . '/bridge.php';
        }
        if (!$skipSource) {
            $files['dist.zip'] = $zipFile;
        }
        if ($exportUpload) {
            $files['dist.sql'] = $sqlFile;
        }

        if (!empty($files)) {
            writeJobLog($jobId, ['status' => 'info', 'log' => '🚀 Đang tải dữ liệu lên server...']);
            try {
                $deployService->upload($config, $files, $project['relPath']);
            } catch (Exception $e) { writeJobLog($jobId, ['status' => 'error', 'message' => $e->getMessage()]); exit; }
        } else {
            writeJobLog($jobId, ['status' => 'info', 'log' => 'ℹ️ Không có file nào cần tải lên.']);
        }

        $decoded = null;
        if ($extractSetup) {
            writeJobLog($jobId, ['status' => 'info', 'log' => '⚡ Đang kích hoạt Bridge xử lý...']);
            $res = $deployService->triggerBridge($config, $isDemo ? $dbSuffix : null, $project['relPath'], $isDemo, 'bridge.php');
            $decoded = json_decode($res, true);
            
            // Auto-heal DB password mismatch
            if ($isDemo && (!$decoded || $decoded['status'] !== 'success')) {
                $errMsg = $decoded['message'] ?? $res;
                $isAccessDenied = (stripos($errMsg, 'Access denied') !== false || stripos($errMsg, '1045') !== false || stripos($errMsg, 'DB_CONNECTION_FAIL') !== false);
                if ($isAccessDenied) {
                    writeJobLog($jobId, ['status' => 'info', 'log' => '⚠️ Sai mật khẩu Database! Đang tự động cập nhật lại mật khẩu trên DirectAdmin theo file env...']);
                    try {
                        $daRes = $deployService->changeDirectAdminDbPassword($config, $dbSuffix, $dbPass);
                        writeJobLog($jobId, ['status' => 'info', 'log' => '🔄 DirectAdmin API phản hồi: ' . strip_tags(urldecode((string)$daRes))]);
                        writeJobLog($jobId, ['status' => 'info', 'log' => '🔄 Đang thử kết nối lại...']);
                        
                        // Sleep to allow DirectAdmin propagation
                        sleep(2);
                        
                        $res = $deployService->triggerBridge($config, $isDemo ? $dbSuffix : null, $project['relPath'], $isDemo, 'bridge.php');
                        $decoded = json_decode($res, true);
                    } catch (Exception $e) {
                        writeJobLog($jobId, ['status' => 'info', 'log' => '❌ Tự động đổi mật khẩu thất bại: ' . $e->getMessage()]);
                    }
                }
            }
        } else {
            writeJobLog($jobId, ['status' => 'info', 'log' => 'ℹ️ Bỏ qua kích hoạt Bridge xử lý.']);
            $decoded = ['status' => 'success', 'message' => 'Đã hoàn tất tiến trình (Bỏ qua kích hoạt Bridge).', 'logs' => ['Skip Bridge activation.']];
        }
        
        if ($decoded && $decoded['status'] === 'success') {
            if (!empty($decoded['logs']) && is_array($decoded['logs'])) {
                foreach ($decoded['logs'] as $logLine) {
                    writeJobLog($jobId, ['status' => 'info', 'log' => '✈️ [Bridge] ' . $logLine]);
                }
            }
            $mainUser = $config['da_user'] ?? $config['ftp_user'];
            $dbName = $isDemo ? ($mainUser . '_' . (isset($dbSuffix) ? $dbSuffix : '')) : ($config['db_name'] ?? '');
            
            if (!isset($projectConfig['deployed'])) $projectConfig['deployed'] = [];
            $projectConfig['deployed'][$isDemo ? 'demo' : 'production'] = [
                'db_name' => $dbName, 
                'db_user' => $dbName, 
                'db_pass' => $config['db_pass'] ?? '', 
                'deploy_time' => date('Y-m-d H:i:s'),
                'demo_server_id' => $config['id'] ?? ($config['default_demo_id'] ?? 'legacy')
            ];
            
            if ($isDemo) $projectConfig['lock_demo'] = true;
            if (!empty($data['password_updated'])) {
                $configManager->addHistory($projectName, 'Đồng bộ mật khẩu DB', 'Tự động cập nhật mật khẩu mới thành công', $category);
            }
            $configManager->save($projectName, $projectConfig, $category);
            $configManager->addHistory($projectName, 'Deploy ' . ($isDemo ? 'Demo' : 'Production'), 'Thành công', $category);

            // Tự động chụp ảnh màn hình website sau khi Deploy thành công
            try {
                $screenshotService->capture($category, $projectName, null, $project, $projectConfig);
            } catch (\Throwable $e) {}

            writeJobLog($jobId, ['status' => 'success', 'message' => 'Deployment thành công!', 'logs' => [$daLogString]]);
            echo json_encode(['status' => 'success']);
        } else {
            if ($decoded && !empty($decoded['logs']) && is_array($decoded['logs'])) {
                foreach ($decoded['logs'] as $logLine) {
                    writeJobLog($jobId, ['status' => 'info', 'log' => '✈️ [Bridge] ' . $logLine]);
                }
            }
            $errMsg = $decoded['message'] ?? 'Bridge lỗi';
            if (empty($decoded)) {
                $errMsg = 'Bridge không phản hồi hoặc phản hồi không đúng định dạng JSON. Có thể do lỗi kết nối hoặc hosting chặn request.';
                $rawDecoded = json_decode($res, true);
                if ($rawDecoded && isset($rawDecoded['message'])) {
                    $errMsg = 'Lỗi kết nối Bridge: ' . $rawDecoded['message'];
                    if (!empty($rawDecoded['body'])) {
                        $errMsg .= ' | Chi tiết: ' . strip_tags($rawDecoded['body']);
                    }
                } else if ($res) {
                    $errMsg = 'Lỗi Bridge (Raw Response): ' . strip_tags(substr($res, 0, 500));
                }
            }
            writeJobLog($jobId, ['status' => 'error', 'message' => $errMsg]);
            echo json_encode(['status' => 'error', 'message' => $errMsg]);
        }
        @unlink($zipFile); @unlink($sqlFile);
        break;

    case 'pushTools':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $jobId = $data['jobId'] ?? null;
        $globalPath = __DIR__ . '/data/demo_config.json';
        $config = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : null;
        $projects = $scanner->getProjects($data['category'] ?? null);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $data['name']) { $project = $p; break; } }
        writeJobLog($jobId, ['status' => 'info', 'log' => '🚀 Đang đồng bộ Bridge.php...']);
        try {
            $deployService->upload($config, ['bridge.php' => __DIR__ . '/bridge.php'], $project['relPath']);
            writeJobLog($jobId, ['status' => 'success', 'message' => 'Tools synced successfully.']);
            $configManager->addHistory($data['name'], 'Sync Tools', 'Đã tải lên Bridge', $category);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) { writeJobLog($jobId, ['status' => 'error', 'message' => $e->getMessage()]); echo json_encode(['status' => 'error']); }
        break;

    case 'publishToProduction':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $jobId = $data['jobId'] ?? null;
        $projectName = $data['name'] ?? '';
        $projectConfig = $configManager->getForProject($projectName, $category);
        
        // Load Global Config for Cloudflare Credentials
        $globalPath = __DIR__ . '/data/demo_config.json';
        $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
        
        $prodConfig = array_merge($projectConfig['prod'] ?? [], $projectConfig['deployed']['production'] ?? []);
        $demoConfig = array_merge(getDemoConfigForProject($projectConfig), $projectConfig['deployed']['demo'] ?? []);
        
        $projects = $scanner->getProjects($data['category'] ?? null);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) { writeJobLog($jobId, ['status' => 'error', 'message' => 'Dự án không tồn tại']); exit; }

        writeJobLog($jobId, ['status' => 'info', 'log' => '🛠️ Khởi tạo cấu hình bảo mật...']);
        
        // 1. Generate RANDOMKEY based on formula: md5($salt1 . $db_name . $salt2)
        if (empty($prodConfig['random_key'])) {
            $mainUser = $prodConfig['da_user'] ?? $prodConfig['ftp_user'] ?? 'user';
            $dbName = $mainUser . '_nasani';
            $salt1 = '$$#*d*934FD546';
            $salt2 = '$$#fdsDFDsfd84348fDF8f*d*';
            $prodConfig['random_key'] = md5($salt1 . $dbName . $salt2);
            writeJobLog($jobId, ['status' => 'info', 'log' => '🔐 Đã tạo RANDOMKEY theo công thức bảo mật (DB: ' . $dbName . ')']);
        }


        // 2. Create Cloudflare Turnstile Widget automatically
        $domain = $prodConfig['web_domain'] ?? '';
        if (empty($domain)) {
            writeJobLog($jobId, ['status' => 'info', 'log' => '⚠️ Bỏ qua Cloudflare: Dự án chưa cấu hình Web Domain.']);
        } elseif (empty($gConfig['cf_api_token']) || empty($gConfig['cf_account_id'])) {
            writeJobLog($jobId, ['status' => 'info', 'log' => '⚠️ Bỏ qua Cloudflare: Thiếu API Token hoặc Account ID trong cấu hình chung.']);
        } else {
            $isGlobal = !empty($gConfig['cf_auth_email']);
            writeJobLog($jobId, ['status' => 'info', 'log' => '☁️ Đang khởi tạo Cloudflare Turnstile (' . ($isGlobal ? 'Global Key' : 'API Token') . ') cho ' . $domain]);
            $cfRes = $deployService->createTurnstileWidget($domain, $gConfig['cf_account_id'], $gConfig['cf_api_token'], $gConfig['cf_auth_email'] ?? '');
            if (is_array($cfRes)) {
                $prodConfig['turnstile_sitekey'] = $cfRes['sitekey'];
                $prodConfig['turnstile_secretkey'] = $cfRes['secret'];
                writeJobLog($jobId, ['status' => 'info', 'log' => '✅ Khởi tạo Turnstile thành công. Sitekey: ' . $cfRes['sitekey']]);
            } else {
                writeJobLog($jobId, ['status' => 'info', 'log' => '❌ Lỗi Cloudflare: ' . $cfRes]);
                writeJobLog($jobId, ['status' => 'info', 'log' => '💡 Gợi ý: Bạn hãy vào Cloudflare Dashboard, ID tài khoản nằm ở trang Overview chính (phía dưới bên phải). Đảm bảo không copy nhầm Zone ID.']);
            }
        }

        writeJobLog($jobId, ['status' => 'info', 'log' => '🛠️ Cấu hình DB/Email trên DirectAdmin...']);
        
        $generatePass = function() {
            $p = substr(str_shuffle('ABCDEFGHJKMNPQRSTUVWXYZ'), 0, 2) . 
                 substr(str_shuffle('abcdefghjkmnpqrstuvwxyz'), 0, 4) . 
                 substr(str_shuffle('23456789'), 0, 4);
            return str_shuffle($p);
        };

        // 1. Database Password
        $dbPass = $prodConfig['db_pass'] ?? '';
        $isStrongDb = !empty($dbPass) && preg_match('/[A-Z]/', $dbPass) && preg_match('/[a-z]/', $dbPass) && preg_match('/[0-9]/', $dbPass);
        if (!$isStrongDb) {
            $dbPass = $generatePass();
            writeJobLog($jobId, ['status' => 'info', 'log' => '🔄 Đã tạo mật khẩu Database mới mạnh hơn.']);
        }
        $prodConfig['db_pass'] = $dbPass;
        $mainUser = $prodConfig['da_user'] ?? $prodConfig['ftp_user'] ?? 'user';
        $prodConfig['db_name'] = $mainUser . '_nasani';
        $prodConfig['db_user'] = $mainUser . '_nasani';

        // 2. Email Password (Lấy từ cấu hình cũ hoặc tạo mới riêng biệt nếu chưa có)
        $emailPass = $prodConfig['email_pass'] ?? '';
        $isStrongEmail = !empty($emailPass) && preg_match('/[A-Z]/', $emailPass) && preg_match('/[a-z]/', $emailPass) && preg_match('/[0-9]/', $emailPass);
        if (!$isStrongEmail) {
            $emailPass = $generatePass();
            writeJobLog($jobId, ['status' => 'info', 'log' => '🔄 Đã tạo mật khẩu Email mới riêng biệt.']);
        }
        $prodConfig['email_user'] = 'noreply@' . $domain;
        $prodConfig['email_pass'] = $emailPass;
        
        // Cung cấp mapping Domain để Bridge thay thế link trong Database
        $prodConfig['demo_domain'] = $demoConfig['web_domain'] ?? '';
        $prodConfig['prod_domain'] = $prodConfig['web_domain'] ?? '';
        $prodConfig['is_production'] = true;

        
        try {
            $dbRes = $deployService->createDirectAdminDb($prodConfig, 'nasani', $dbPass);
            writeJobLog($jobId, ['status' => 'info', 'log' => '> Database: ' . (strpos($dbRes, 'error=0') !== false || $dbRes === 'ok_already_exists' ? 'OK' : $dbRes)]);
            
            $mailRes = $deployService->createDirectAdminEmail($prodConfig, 'noreply', $emailPass);
            writeJobLog($jobId, ['status' => 'info', 'log' => '> Email noreply: ' . (strpos($mailRes, 'error=0') !== false || $mailRes === 'ok_already_exists' ? 'OK' : $mailRes)]);
        } catch (Exception $e) { 
            writeJobLog($jobId, ['status' => 'error', 'message' => 'Lỗi kết nối DirectAdmin: ' . $e->getMessage()]); 
            exit; 
        }
        
        writeJobLog($jobId, ['status' => 'info', 'log' => '🚀 Đồng bộ Bridge và Cloud Transfer...']);
        try {
            // Đồng bộ bridge.php lên cả Demo và Production trước khi chuyển giao
            $deployService->upload($demoConfig, ['bridge.php' => __DIR__ . '/bridge.php'], $project['relPath']);
            $deployService->upload($prodConfig, ['bridge.php' => __DIR__ . '/bridge.php']);
            $res = $deployService->triggerCloudDeploy($demoConfig, $prodConfig, $project['relPath']);
            $decoded = json_decode($res, true);
            
            if ($decoded && $decoded['status'] === 'success') {
                if (!empty($decoded['logs']) && is_array($decoded['logs'])) {
                    foreach ($decoded['logs'] as $logLine) {
                        writeJobLog($jobId, ['status' => 'info', 'log' => '☁️ [Demo Server] ' . $logLine]);
                    }
                }
                if (!empty($decoded['final']['logs']) && is_array($decoded['final']['logs'])) {
                    foreach ($decoded['final']['logs'] as $logLine) {
                        writeJobLog($jobId, ['status' => 'info', 'log' => '🚀 [Prod Server] ' . $logLine]);
                    }
                }
                $mainUser = $prodConfig['da_user'] ?? $prodConfig['ftp_user'];
                $finalDb = $mainUser . '_nasani';
                
                if (!isset($projectConfig['deployed'])) $projectConfig['deployed'] = [];
                $projectConfig['deployed']['production'] = [
                    'db_name' => $finalDb, 
                    'db_user' => $finalDb, 
                    'db_pass' => $dbPass,
                    'email_user' => 'noreply@' . $domain,
                    'email_pass' => $emailPass,
                    'random_key' => $prodConfig['random_key'],
                    'turnstile_sitekey' => $prodConfig['turnstile_sitekey'] ?? '',
                    'turnstile_secretkey' => $prodConfig['turnstile_secretkey'] ?? '',
                    'deploy_time' => date('Y-m-d H:i:s')
                ];
                
                $projectConfig['lock_production'] = true; 
                $configManager->save($projectName, $projectConfig, $category);
                $configManager->addHistory($projectName, 'Publish Production', 'Full Setup hoàn tất', $category);

                // Tự động chụp ảnh website production
                try {
                    $prodUrl = (!empty($prodConfig['ssl']) ? 'https://' : 'http://') . $domain;
                    $screenshotService->capture($category, $projectName, $prodUrl, null, $projectConfig);
                } catch (\Throwable $e) {}
                
                writeJobLog($jobId, ['status' => 'success', 'message' => 'Cloud transfer & Full Setup hoàn tất!']);
                echo json_encode(['status' => 'success']);
            } else {
                if ($decoded) {
                    if (!empty($decoded['logs']) && is_array($decoded['logs'])) {
                        foreach ($decoded['logs'] as $logLine) {
                            writeJobLog($jobId, ['status' => 'info', 'log' => '☁️ [Demo Server] ' . $logLine]);
                        }
                    }
                    if (!empty($decoded['final']['logs']) && is_array($decoded['final']['logs'])) {
                        foreach ($decoded['final']['logs'] as $logLine) {
                            writeJobLog($jobId, ['status' => 'info', 'log' => '🚀 [Prod Server] ' . $logLine]);
                        }
                    }
                }
                $errMsg = $decoded['message'] ?? 'Unknown Error';
                if (!empty($decoded['final']['message'])) {
                    $errMsg = $decoded['final']['message'];
                }
                if (empty($decoded)) {
                    $errMsg = 'Bridge không phản hồi hoặc phản hồi không đúng định dạng JSON. Có thể do lỗi kết nối hoặc hosting chặn request.';
                    $rawDecoded = json_decode($res, true);
                    if ($rawDecoded && isset($rawDecoded['message'])) {
                        $errMsg = 'Lỗi kết nối Cloud Transfer: ' . $rawDecoded['message'];
                        if (!empty($rawDecoded['body'])) {
                            $errMsg .= ' | Chi tiết: ' . strip_tags($rawDecoded['body']);
                        }
                    } else if ($res) {
                        $errMsg = 'Lỗi Cloud Transfer (Raw Response): ' . strip_tags(substr($res, 0, 500));
                    }
                }
                writeJobLog($jobId, ['status' => 'error', 'message' => 'Transfer thất bại: ' . $errMsg]);
                echo json_encode(['status' => 'error', 'message' => 'Transfer thất bại: ' . $errMsg]);
            }
        } catch (Exception $e) { writeJobLog($jobId, ['status' => 'error', 'message' => $e->getMessage()]); }
        break;

    case 'downloadPackage':
        $data = readJsonInput();
        $category = $data['category'] ?? '';
        $projectName = $data['name'] ?? '';
        $jobId = $data['jobId'] ?? null;
        
        if (PHP_SAPI !== 'cli' && empty($data['_background'])) {
            try {
                $queuedJobId = startApiBackgroundJob($action, array_merge($data, ['_background' => true]), $jobId);
                writeJobLog($queuedJobId, ['status' => 'info', 'log' => 'Bắt đầu tiến trình tải mã nguồn từ Demo...']);
                echo json_encode(['status' => 'queued', 'jobId' => $queuedJobId]);
            } catch (Throwable $e) {
                writeJobLog($jobId, ['status' => 'error', 'message' => 'Không thể khởi động tiến trình nền: ' . $e->getMessage()]);
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;
        }

        $res = $packagingService->downloadFromDemo($projectName, $data['category'], $jobId);
        if ($res['status'] === 'success') {
            $configManager->addHistory($projectName, 'Download Package', 'Tải mã nguồn thành công', $category);
            writeJobLog($jobId, ['status' => 'success', 'message' => 'Tải package thành công', 'url' => $res['url'] ?? '']);
        } else {
            writeJobLog($jobId, ['status' => 'error', 'message' => $res['message'] ?? 'Thất bại']);
        }
        if (PHP_SAPI === 'cli') {
            exit;
        }
        echo json_encode($res);
        break;

    case 'cleanupTools':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? null;
        $projectConfig = $configManager->getForProject($data['name'], $category);
        $config = ($data['type'] === 'demo') ? getDemoConfigForProject($projectConfig) : ($projectConfig['prod'] ?? []);
        $projects = $scanner->getProjects($data['category'] ?? null);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $data['name']) { $project = $p; break; } }
        $res = $deployService->cleanupBridge($config, ($data['type'] === 'demo' ? $project['relPath'] : ''), ($data['type'] === 'demo'));
        $decoded = json_decode($res, true);
        if ($decoded && $decoded['status'] === 'success') {
            $configManager->addHistory($data['name'], 'Dọn dẹp Bridge (' . $data['type'] . ')', 'Hoàn tất', $category);
        }
        echo $res;
        break;

    case 'integrateAMP':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $projectName = $data['name'] ?? '';
        $jobId = $data['jobId'] ?? null;

        $project = $scanner->getProjectByName($projectName, $category ?? null);
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy dự án: ' . $projectName]);
            break;
        }

        $projectPath = $project['path'];
        $ampSourceDir = __DIR__ . '/data/AMP_NASANI';

        if (!is_dir($ampSourceDir)) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thư mục data/AMP_NASANI trong Manager.']);
            break;
        }

        // Run the integrate script (returns ['status' => ..., 'logs' => [...]])
        $integrateResult = (function() use ($projectPath, $ampSourceDir) {
            return require __DIR__ . '/core/AmpIntegrator.php';
        })();

        if (is_array($integrateResult) && ($integrateResult['status'] ?? '') === 'success') {
            $configManager->addHistory($projectName, 'Tích hợp AMP NASANI', 'Hoàn tất', $category);
            echo json_encode([
                'status'  => 'success',
                'message' => 'Tích hợp AMP thành công!',
                'logs'    => $integrateResult['logs'] ?? []
            ]);
        } else {
            $errMsg = $integrateResult['message'] ?? 'Tích hợp thất bại';
            echo json_encode(['status' => 'error', 'message' => $errMsg]);
        }
        break;

    case 'clearProjectCache':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectName = $data['name'] ?? '';
        $category = $data['category'] ?? '';
        
        $project = $projectManager->getProject($projectName, $category);
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy dự án!']);
            break;
        }
        
        $projectPath = rtrim(str_replace('\\', '/', $project['path']), '/');
        $clearedDirs = [];
        $filesDeleted = 0;
        
        // Potential cache directories in Nasanic / Laravel / PHP projects
        $cacheDirs = [
            $projectPath . '/storage/framework/views',
            $projectPath . '/storage/framework/cache',
            $projectPath . '/storage/framework/cache/data',
            $projectPath . '/storage/cache',
            $projectPath . '/cache',
            $projectPath . '/var/cache'
        ];
        
        foreach ($cacheDirs as $dir) {
            if (is_dir($dir)) {
                $items = @scandir($dir);
                if ($items) {
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..' || $item === '.gitignore') continue;
                        $itemPath = $dir . '/' . $item;
                        if (is_file($itemPath)) {
                            @unlink($itemPath);
                            $filesDeleted++;
                        }
                    }
                }
                $clearedDirs[] = str_replace($projectPath . '/', '', $dir);
            }
        }
        
        $configManager->addHistory($projectName, 'Xóa cache dự án & trình duyệt', 'Hoàn tất', $category);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Đã dọn dẹp cache của dự án thành công!',
            'files_deleted' => $filesDeleted,
            'cleared_dirs' => $clearedDirs
        ]);
        break;

    case 'installSSL':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $projectConfig = $configManager->getForProject($data['name'], $category);
        $res = RemoteClient::requestSSLViaDA($projectConfig['prod']);
        
        // Cải tiến kiểm tra: Chấp nhận error=0 (text) HOẶC có chứa từ khóa thành công trong JSON
        $isSuccess = (strpos($res, 'error=0') !== false) || 
                     (strpos($res, '"success":') !== false) || 
                     (strpos($res, '"error":"0"') !== false);
                     
        $status = $isSuccess ? 'success' : 'error';
        if ($status === 'success') {
            $configManager->addHistory($data['name'], 'Cài đặt SSL', 'Gửi yêu cầu thành công', $category);
        }
        echo json_encode(['status' => $status, 'message' => $res]);
        break;

    case 'getAvailablePhpVersions':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectName = $data['name'] ?? '';
        
        $projectConfig = $configManager->getForProject($projectName, $category);
        $config = $projectConfig['prod'] ?? [];
        
        if (empty($config)) {
            echo json_encode(['status' => 'error', 'message' => 'Dự án chưa cấu hình Production.']);
            break;
        }
        
        $res = RemoteClient::getAvailablePhpVersionsViaDA($config);
        echo json_encode($res);
        break;

    case 'changePhpVersion':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $projectName = $data['name'] ?? '';
        $phpVersionIndex = $data['php_version_index'] ?? '1'; // 1, 2, 3, etc.
        
        $projectConfig = $configManager->getForProject($projectName, $category);
        $config = $projectConfig['prod'] ?? [];
        
        if (empty($config)) {
            echo json_encode(['status' => 'error', 'message' => 'Dự án chưa cấu hình Production.']);
            break;
        }
        
        $res = RemoteClient::changePhpVersionViaDA($config, $phpVersionIndex);
        
        $isSuccess = (strpos($res, 'error=0') !== false) || 
                     (strpos($res, '"success":') !== false) || 
                     (strpos($res, '"error":"0"') !== false) ||
                     (stripos($res, 'PHP version') !== false) ||
                     (stripos($res, 'success') !== false);
                     
        $status = $isSuccess ? 'success' : 'error';
        if ($status === 'success') {
            $configManager->addHistory($projectName, 'Thay đổi PHP Version', "Thành công (Index: $phpVersionIndex)", $category);
        }
        echo json_encode(['status' => $status, 'message' => $res]);
        break;

    case 'changeDatabaseType':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $projectName = $data['name'];
        $module = $data['module'] ?? 'product';
        $old = $data['old_type'] ?? '';
        $new = $data['new_type'] ?? '';

        if (!$old || !$new) {
            echo json_encode(['status' => 'error', 'message' => 'Missing old_type or new_type']);
            break;
        }

        // 1. Tìm đường dẫn project local
        $project = $scanner->getProjectByName($projectName, $category ?? null);
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Project not found locally.']);
            break;
        }

        $envPath = $project['path'] . DIRECTORY_SEPARATOR . '.env';
        if (!file_exists($envPath)) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy file .env tại project local.']);
            break;
        }

        // 2. Parse .env
        $env = [];
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $env[trim($name)] = trim($value, ' "');
        }

        $dbHost = $env['DB_HOST'] ?? '127.0.0.1';
        $dbName = $env['DB_DATABASE'] ?? '';
        $dbUser = $env['DB_USERNAME'] ?? '';
        $dbPass = $env['DB_PASSWORD'] ?? '';

        if (!$dbName) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy DB_DATABASE trong .env']);
            break;
        }

        // 3. Kết nối DB local
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $tables = [];
            if ($module === 'product' || $module === 'all') {
                $tables = array_merge($tables, ['table_product_list', 'table_product_cat', 'table_product_item', 'table_product_sub', 'table_product']);
            }
            if ($module === 'news' || $module === 'all') {
                $tables = array_merge($tables, ['table_news_list', 'table_news_cat', 'table_news']);
            }
            $commonTables = ['table_gallery', 'table_seo', 'table_slug'];
            $allTables = array_unique(array_merge($tables, $commonTables));

            $totalAffected = 0;
            $details = [];

            foreach ($allTables as $table) {
                try {
                    // Kiểm tra bảng có tồn tại không
                    $stmtCheck = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmtCheck->rowCount() == 0) continue;

                    if ($table === 'table_gallery') {
                        $sql = "UPDATE `$table` SET `type` = ?, `type_parent` = ? WHERE `type` = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$new, $new, $old]);
                    } else {
                        $sql = "UPDATE `$table` SET `type` = ? WHERE `type` = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$new, $old]);
                    }
                    $affected = $stmt->rowCount();
                    $details[$table] = $affected;
                    $totalAffected += $affected;
                } catch (Exception $e) {
                    $details[$table] = "Error: " . $e->getMessage();
                }
            }

            $configManager->addHistory($projectName, 'Đổi Type DB (Local)', "Từ $old -> $new ($module)", $category);
            echo json_encode(['status' => 'success', 'message' => "Đã cập nhật $totalAffected dòng tại Local Database.", 'details' => $details]);

        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối DB local: ' . $e->getMessage()]);
        }
        break;

    case 'toggleActionLock':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $projectConfig = $configManager->getForProject($data['name'], $category);
        $key = ($data['type'] === 'demo') ? 'lock_demo' : 'lock_production';
        $projectConfig[$key] = !empty($projectConfig[$key]) ? false : true;
        $configManager->save($data['name'], $projectConfig, $category);
        $actionName = $projectConfig[$key] ? 'Khóa' : 'Mở khóa';
        $configManager->addHistory($data['name'], $actionName . ' ' . ($data['type'] === 'demo' ? 'Demo' : 'Production'), 'Thành công', $category);
        echo json_encode(['status' => 'success', 'locked' => $projectConfig[$key]]);
        break;

    case 'getProjectSchemaList':
        $category = $_GET['category'] ?? '';
        $projectName = $_GET['name'] ?? '';
        $project = $scanner->getProjectByName($projectName, $category ?? null);
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Project not found']);
            break;
        }
        $files = SchemaManager::listConfigFiles($project['path']);
        echo json_encode(['status' => 'success', 'data' => $files]);
        break;

    case 'loadModuleSchema':
        $category = $_GET['category'] ?? '';
        $projectName = $_GET['name'] ?? '';
        $file = $_GET['file'] ?? '';
        $project = $scanner->getProjectByName($projectName, $category ?? null);
        if (!$project || !$file) {
            echo json_encode(['status' => 'error', 'message' => 'Project or file not found']);
            break;
        }
        $data = SchemaManager::load($project['path'], $file);
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    case 'saveModuleSchema':
        $category = $data['category'] ?? '';
        $data = json_decode(file_get_contents('php://input'), true);
        $projectName = $data['name'] ?? '';
        $file = $data['file'] ?? '';
        $configData = $data['config'] ?? [];

        $project = $scanner->getProjectByName($projectName, $category ?? null);
        if (!$project || !$file) {
            echo json_encode(['status' => 'error', 'message' => 'Project or file not found']);
            break;
        }

        if (SchemaManager::save($project['path'], $file, $configData)) {
            // Log history
            $configManager = new ConfigManager($project['path']);
            $configManager->addHistory("Cập nhật Schema: $file", "success", "manager");
            echo json_encode(['status' => 'success', 'message' => 'Đã lưu cấu hình thành công!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể lưu file cấu hình']);
        }
        break;

    case 'getSchemaPresets':
        $presetsPath = __DIR__ . '/data/schema_presets.json';
        $presets = file_exists($presetsPath) ? json_decode(file_get_contents($presetsPath), true) : [];
        echo json_encode(['status' => 'success', 'data' => $presets]);
        break;

    case 'getTypeImageSize':
        $projectName = $_GET['name'] ?? '';
        $typeName = trim($_GET['type'] ?? '');

        $project = $scanner->getProjectByName($projectName, $category ?? null);
        if (!$project || $typeName === '') {
            echo json_encode(['status' => 'error', 'message' => 'Project or type not found']);
            break;
        }

        $imagesDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'images';
        if (!is_dir($imagesDir)) {
            echo json_encode(['status' => 'error', 'message' => 'Images folder not found: assets/images/images']);
            break;
        }

        $normalizeTypeKey = static function ($value) {
            $value = strtolower((string)$value);
            return preg_replace('/[^a-z0-9]/', '', $value);
        };
        $targetKey = $normalizeTypeKey($typeName);
        $matched = [];
        $entries = scandir($imagesDir);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $fullPath = $imagesDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($fullPath)) continue;

            $baseName = strtolower(pathinfo($entry, PATHINFO_FILENAME));
            $baseNameNormalized = $normalizeTypeKey($baseName);
            if ($baseNameNormalized !== $targetKey) continue;

            $matched[] = ['name' => $entry, 'path' => $fullPath];
        }

        if (empty($matched)) {
            echo json_encode(['status' => 'error', 'message' => "No image found for type '{$typeName}'"]);
            break;
        }

        foreach ($matched as $item) {
            $dim = @getimagesize($item['path']);
            if ($dim && !empty($dim[0]) && !empty($dim[1])) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'file' => $item['name'],
                        'width' => (int)$dim[0],
                        'height' => (int)$dim[1],
                    ],
                ]);
                break 2;
            }
        }

        echo json_encode(['status' => 'error', 'message' => "Found '{$typeName}.*' but cannot read image size"]);
        break;

    case 'saveSchemaPreset':
        $data = json_decode(file_get_contents('php://input'), true);
        $presetsPath = __DIR__ . '/data/schema_presets.json';
        if (file_put_contents($presetsPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Cannot save presets file']);
        }
        break;

    case 'openProject':
        $category = $_GET['category'] ?? '';
        $name = $_GET['name'] ?? '';
        $project = $scanner->getProjectByName($name, $category ?? null);
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Project not found']);
            break;
        }
        $path = $project['path'];
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $globalPath = __DIR__ . '/data/demo_config.json';
            $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
            $codeCmd = $gConfig['editor_path'] ?? 'code';
            
            // If configured editor path does not exist on disk, reset to 'code' to trigger auto-detection
            $cleanCodeCmd = trim($codeCmd, '"\' ');
            if ($codeCmd !== 'code' && !empty($cleanCodeCmd) && !file_exists($cleanCodeCmd)) {
                $codeCmd = 'code';
            }
            
            // If editor_path is default 'code', try to find standard paths
            if ($codeCmd === 'code') {
                $localAppData = getenv('LOCALAPPDATA');
                $progFiles = getenv('ProgramFiles');
                $searchPaths = [
                    $localAppData . '\Programs\Microsoft VS Code\bin\code.cmd',
                    $progFiles . '\Microsoft VS Code\bin\code.cmd',
                    $localAppData . '\Programs\cursor\resources\app\bin\cursor',
                    $localAppData . '\Programs\Cursor\resources\app\bin\cursor.cmd'
                ];

                foreach ($searchPaths as $sp) {
                    if (file_exists($sp)) {
                        $codeCmd = '"' . $sp . '"';
                        break;
                    }
                }
            } else {
                // Ensure custom path is quoted
                $codeCmd = '"' . $codeCmd . '"';
            }

            @exec("start \"\" /B $codeCmd \"" . $path . "\"");
        } else {
            @exec("code \"" . $path . "\" > /dev/null 2>&1 &");
        }
        echo json_encode(['status' => 'success']);
        break;

    case 'setupLocalSource':
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $projectName = $data['name'] ?? '';
        $category = $data['category'] ?? '';
        $forceOverwriteDb = isset($data['forceOverwriteDb']) ? $data['forceOverwriteDb'] : null;

        $project = $scanner->getProjectByName($projectName, $category);
        if (!$project) {
            $catPath = $category ? str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $category) . DIRECTORY_SEPARATOR : '';
            $projectDir = $baseDir . DIRECTORY_SEPARATOR . $catPath . $projectName;
            if (is_dir($projectDir)) {
                $project = [
                    'name' => $projectName,
                    'path' => $projectDir,
                    'category' => $category,
                    'relPath' => ($category ? $category . '/' : '') . $projectName,
                    'type' => 'project'
                ];
            }
        }

        if (!$project || !is_dir($project['path'])) {
            echo json_encode(['status' => 'error', 'message' => 'Thư mục dự án không tồn tại trên hệ thống local!']);
            break;
        }

        $projectPath = $project['path'];

        // Tên Database chuẩn hóa: e.g. 2026_08_ngocanhclinic_0553526w
        $cleanProjectName = preg_replace('/[^a-z0-9_]/', '', strtolower($projectName));
        if (!empty($category)) {
            $dbName = str_replace(['/', '\\'], '_', $category) . '_' . $cleanProjectName;
        } else {
            $dbName = $cleanProjectName;
        }

        // 1. Quét tìm file .sql trong thư mục dự án
        $sqlFiles = [];
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($projectPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'sql') {
                    $rel = str_replace('\\', '/', ltrim(str_replace($projectPath, '', $fileInfo->getRealPath()), '\\/'));
                    if (strpos($rel, 'vendor/') === 0 || strpos($rel, 'node_modules/') === 0 || strpos($rel, 'backups/') === 0) continue;
                    $sqlFiles[] = $fileInfo->getRealPath();
                }
            }
        } catch (Exception $e) {}

        $targetSqlFile = null;
        if (!empty($sqlFiles)) {
            $folderName = strtolower(basename($projectPath));
            foreach ($sqlFiles as $sf) {
                $baseName = strtolower(pathinfo($sf, PATHINFO_FILENAME));
                $cleanBase = preg_replace('/[^a-z0-9]/', '', $baseName);
                if ($cleanBase === $cleanProjectName || $baseName === $folderName || strpos($cleanBase, $cleanProjectName) !== false) {
                    $targetSqlFile = $sf;
                    break;
                }
            }
            if (!$targetSqlFile) {
                usort($sqlFiles, function($a, $b) { return strlen($a) <=> strlen($b); });
                $targetSqlFile = $sqlFiles[0];
            }
        }

        // 2. Kiểm tra DB đã tồn tại chưa bằng MySQLi
        $dbHost = 'localhost';
        $dbUser = 'root';
        $dbPass = '';

        $mysqli = @new \mysqli($dbHost, $dbUser, $dbPass);
        if ($mysqli->connect_error) {
            $dbHost = '127.0.0.1';
            $mysqli = @new \mysqli($dbHost, $dbUser, $dbPass);
        }

        if ($mysqli->connect_error) {
            echo json_encode(['status' => 'error', 'message' => 'Kết nối MySQL/phpMyAdmin thất bại: ' . $mysqli->connect_error]);
            break;
        }

        $resDb = $mysqli->query("SHOW DATABASES LIKE '" . $mysqli->real_escape_string($dbName) . "'");
        $dbExists = ($resDb && $resDb->num_rows > 0);
        $mysqli->close();

        if ($dbExists && $forceOverwriteDb === null) {
            echo json_encode([
                'status' => 'db_exists_prompt',
                'db_name' => $dbName,
                'sql_file' => $targetSqlFile ? basename($targetSqlFile) : 'Không có file .sql',
                'message' => "Database '$dbName' đã tồn tại trên phpMyAdmin."
            ]);
            break;
        }

        $importLog = '';

        try {
            // 3. Nếu ghi đè -> Drop DB cũ
            if ($dbExists && $forceOverwriteDb === true) {
                $mDrop = new \mysqli($dbHost, $dbUser, $dbPass);
                @$mDrop->query("DROP DATABASE IF EXISTS `" . $mDrop->real_escape_string($dbName) . "`");
                $mDrop->close();
            }

            // 4. Tạo Database
            if (!$dbExists || $forceOverwriteDb === true) {
                $projectDeployer->createDatabase($dbName, $dbHost, $dbUser, $dbPass);
                if ($targetSqlFile && file_exists($targetSqlFile)) {
                    $projectDeployer->importSql($dbName, $targetSqlFile, $dbHost, $dbUser, $dbPass);
                    $importLog = "Tạo & nạp DB '$dbName' từ " . basename($targetSqlFile);
                } else {
                    $importLog = "Tạo mới DB '$dbName' (không file SQL)";
                }
            } else {
                $importLog = "Bỏ qua DB (Giữ nguyên Database '$dbName' hiện tại)";
            }

            // 5. Copy .agents folder vào dự án (nếu có)
            $agentsDir = __DIR__ . DIRECTORY_SEPARATOR . '.agents';
            if (is_dir($agentsDir)) {
                try {
                    $projectDeployer->copyRecursive($agentsDir, $projectPath . DIRECTORY_SEPARATOR . '.agents');
                } catch (Exception $e) {}
            }

            // 6. Cấu hình file .env (Laravel) hoặc libraries/config.php (Source tự viết)
            $configPhpPath = $projectPath . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . 'config.php';
            $envPath = $projectPath . DIRECTORY_SEPARATOR . '.env';
            $envExamplePath = $projectPath . DIRECTORY_SEPARATOR . '.env.example';
            $sitePath = '/' . str_replace('\\', '/', trim($project['relPath'], '/\\')) . '/';

            if (file_exists($configPhpPath)) {
                // Source tự viết: Cấu hình thông qua libraries/config.php
                $projectConfig = $configManager->getForProject($projectName, $category) ?: [];
                $hasSsl = !empty($projectConfig['local_ssl']) || !empty($projectConfig['ssl']);
                $configUpdates = [
                    'host' => 'localhost',
                    'username' => 'root',
                    'password' => '',
                    'dbname' => $dbName,
                    'url' => $sitePath,
                    'port' => 3306,
                    'debug-developer' => true,
                    'ssl' => $hasSsl
                ];
                $projectDeployer->updateConfigFile($configPhpPath, $configUpdates);
            } else {
                // Laravel: Cấu hình thông qua .env
                if (!file_exists($envPath) && file_exists($envExamplePath)) {
                    @copy($envExamplePath, $envPath);
                }

                $envUpdates = [
                    'SITE_PATH' => $sitePath,
                    'APP_URL' => '"http://localhost${SITE_PATH}"',
                    'DB_HOST' => '127.0.0.1',
                    'DB_PORT' => '3306',
                    'DB_DATABASE' => $dbName,
                    'DB_USERNAME' => 'root',
                    'DB_PASSWORD' => ''
                ];

                if (file_exists($envPath)) {
                    $projectDeployer->updateEnv($envPath, $envUpdates);
                } else {
                    $newEnvContent = "";
                    foreach ($envUpdates as $k => $val) {
                        $newEnvContent .= "$k=$val\n";
                    }
                    file_put_contents($envPath, $newEnvContent);
                }
            }

            // 7. Lưu trạng thái đã cấu hình & khóa nút
            $projectConfig = $configManager->getForProject($projectName, $category);
            $projectConfig['configured_local'] = true;
            $configManager->save($projectName, $projectConfig, $category);
            $configManager->addHistory($projectName, 'Cấu hình Source Local', $importLog, $category);

            echo json_encode([
                'status' => 'success',
                'message' => '✅ Cấu hình Source Local thành công! DB: ' . $dbName . ' (' . $importLog . ')'
            ]);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi cấu hình Source Local: ' . $e->getMessage()]);
        }
        break;

    case 'searchFonts':
        $query = $_GET['query'] ?? '';
        $globalPath = __DIR__ . '/data/demo_config.json';
        $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
        $fontSource = $gConfig['font_source_path'] ?? '';
        if (empty($fontSource) || !is_dir($fontSource)) {
            $fontSource = $baseDir . DIRECTORY_SEPARATOR . 'fonts';
        }

        $results = [];

        // 1. Search Local Library (Layer 1: Tree Index Cache)
        if ($fontSource && is_dir($fontSource)) {
            $fontSource = rtrim(str_replace('\\', '/', $fontSource), '/');
            $localFonts = getLocalFontData($fontSource);

            $cleanQuery = removeVietnameseDiacritics($query);
            $normalizedQuery = str_replace(['_', '-', ' '], '', strtolower($cleanQuery));

            foreach ($localFonts as $fontId => $font) {
                $cleanFamily = removeVietnameseDiacritics($font['family']);
                $normalizedFamily = str_replace(['_', '-', ' '], '', strtolower($cleanFamily));

                if ($query === '' || strpos($normalizedFamily, $normalizedQuery) !== false) {
                    $familyLower = strtolower($cleanFamily);
                    $queryLower = strtolower($cleanQuery);

                    if ($familyLower === $queryLower) {
                        $score = 3000;
                    } elseif (strpos($familyLower, $queryLower) === 0) {
                        $score = 2000 - strlen($font['family']);
                    } else {
                        $score = 1000 - strlen($font['family']);
                    }
                    $font['score'] = $score;
                    $font['source'] = 'local';
                    $font['category'] = 'Local Library';

                    $results[] = $font;
                    if (count($results) >= 100) break;
                }
            }

            // Layer 2: Real Disk Fallback Search if empty and query provided
            if (empty($results) && $query !== '') {
                try {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($fontSource, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    $diskGrouped = [];
                    foreach ($iterator as $fileInfo) {
                        if ($fileInfo->isFile()) {
                            $f = $fileInfo->getFilename();
                            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                            $isWoff = ($ext === 'woff' || $ext === 'woff2');
                            $isConvert = ($ext === 'otf' || $ext === 'ttf');

                            if ($isWoff || $isConvert) {
                                $fullPath = str_replace('\\', '/', $fileInfo->getRealPath());
                                $relPath = ltrim(str_replace($fontSource, '', $fullPath), '/');
                                $parentFolder = dirname($relPath);
                                if ($parentFolder === '.') $parentFolder = '';

                                $filename = pathinfo($f, PATHINFO_FILENAME);
                                $parsed = parseFontFilename($filename);
                                $familyPrefix = $parsed['family'];

                                $cleanFam = removeVietnameseDiacritics($familyPrefix);
                                $normalizedFam = str_replace(['_', '-', ' '], '', strtolower($cleanFam));
                                if (strpos($normalizedFam, $normalizedQuery) !== false) {
                                    $fontId = ($parentFolder === '' ? $familyPrefix : ($parentFolder . '/' . $familyPrefix));
                                    $weight = $parsed['weight'];
                                    $style = $parsed['style'];
                                    $vKey = $weight . ($style === 'italic' ? 'i' : '');

                                    if (!isset($diskGrouped[$fontId])) {
                                        $diskGrouped[$fontId] = [
                                            'id' => $fontId,
                                            'family' => str_replace(['/', '-', '_'], [' > ', ' ', ' '], $fontId),
                                            'category' => 'Local Library',
                                            'source' => 'local',
                                            'files' => []
                                        ];
                                    }
                                    $diskGrouped[$fontId]['files'][] = [
                                        'file' => $f, 'filename' => $filename, 'ext' => $ext,
                                        'weight' => $weight, 'style' => $style, 'vKey' => $vKey, 'isWoff' => $isWoff
                                    ];
                                }
                            }
                        }
                    }

                    if (!empty($diskGrouped)) {
                        foreach ($diskGrouped as $fontId => $font) {
                            $variants = []; $convert_variants = [];
                            foreach ($font['files'] as $file) {
                                $vKey = $file['vKey'];
                                if ($file['isWoff']) {
                                    if (!in_array($vKey, $variants)) $variants[] = $vKey;
                                } else {
                                    $exists = false;
                                    foreach ($convert_variants as $cv) {
                                        if ($cv['variant'] === $vKey) { $exists = true; break; }
                                    }
                                    if (!$exists) $convert_variants[] = ['variant' => $vKey, 'ext' => $file['ext'], 'filename' => $file['filename']];
                                }
                            }
                            sort($variants);
                            $font['variants'] = $variants;
                            $font['convert_variants'] = $convert_variants;
                            unset($font['files']);
                            $font['score'] = 2500;
                            $results[] = $font;

                            $localFonts[$fontId] = $font;
                        }
                        @file_put_contents(__DIR__ . '/data/local_fonts_cache.json', json_encode($localFonts));
                    }
                } catch (Exception $e) {}
            }
        }

        // 2. Search Google Fonts
        if ($query !== '') {
            ini_set('memory_limit', '256M');
            $cacheFile = __DIR__ . '/data/google_fonts_cache.json';
            $googleFonts = [];
            
            if (file_exists($cacheFile)) {
                $cacheData = @file_get_contents($cacheFile);
                if ($cacheData) {
                    $googleFonts = json_decode($cacheData, true) ?: [];
                }
            }
            
            if (empty($googleFonts)) {
                $ctx = stream_context_create(['http' => ['timeout' => 5]]);
                $data = @file_get_contents('https://fonts.google.com/metadata/fonts', false, $ctx);
                if ($data) {
                    $json = json_decode($data, true);
                    if (isset($json['familyMetadataList'])) {
                        foreach ($json['familyMetadataList'] as $item) {
                            $googleFonts[] = [
                                'family' => $item['family'] ?? '',
                                'category' => $item['category'] ?? 'Google Fonts',
                                'variants' => array_keys($item['fonts'] ?? [])
                            ];
                        }
                        @file_put_contents($cacheFile, json_encode($googleFonts));
                    }
                }
            }

            if (!empty($googleFonts)) {
                $cleanQuery = removeVietnameseDiacritics($query);
                $queryLower = strtolower($cleanQuery);
                $count = 0;
                foreach ($googleFonts as $font) {
                    $familyLower = strtolower($font['family']);
                    if ($queryLower === '' || strpos($familyLower, $queryLower) !== false) {
                        $score = 0;
                        if ($familyLower === $queryLower) {
                            $score = 2500;
                        } elseif (strpos($familyLower, $queryLower) === 0) {
                            $score = 1500 - strlen($font['family']);
                        } else {
                            $score = 500 - strlen($font['family']);
                        }

                        $results[] = [
                            'id' => $font['family'],
                            'family' => $font['family'],
                            'category' => $font['category'],
                            'variants' => $font['variants'],
                            'convert_variants' => [],
                            'source' => 'google',
                            'score' => $score
                        ];
                        $count++;
                    }
                    if (count($results) >= 150 || $count >= 100) break;
                }
            }
        }

        // Final sorting: Balanced score sorting
        usort($results, function($a, $b) {
            $scoreA = $a['score'] ?? 0;
            $scoreB = $b['score'] ?? 0;
            if ($scoreA !== $scoreB) return $scoreB - $scoreA;
            return strcmp($a['family'], $b['family']);
        });

        echo json_encode([
            'status' => 'success', 
            'data' => $results, 
            'google_search' => !empty($googleFonts)
        ]);
        break;

    case 'reindexFonts':
        $globalPath = __DIR__ . '/data/demo_config.json';
        $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
        $fontSource = $gConfig['font_source_path'] ?? '';
        if (empty($fontSource) || !is_dir($fontSource)) {
            $fontSource = $baseDir . DIRECTORY_SEPARATOR . 'fonts';
        }

        $fonts = buildLocalFontCacheFromTree($fontSource);
        $count = count($fonts);
        echo json_encode([
            'status' => 'success',
            'message' => "Đã đồng bộ thành công $count họ font từ chỉ mục tree.md!",
            'count' => $count
        ]);
        break;

    case 'installFont':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $projectName = $data['name'] ?? '';
        $fontId = $data['fontId'] ?? ''; 
        $selectedVariants = $data['variants'] ?? [];

        $project = $scanner->getProjectByName($projectName, $category ?? null);
        $globalPath = __DIR__ . '/data/demo_config.json';
        $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
        $fontSource = $gConfig['font_source_path'] ?? '';
        if (empty($fontSource) || !is_dir($fontSource)) {
            $fontSource = $baseDir . DIRECTORY_SEPARATOR . 'fonts';
        }

        if (!$project || !$fontSource) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin dự án hoặc thư viện font']);
            break;
        }

        $parentFolder = dirname($fontId);
        $familyPrefix = basename($fontId);

        $srcDir = $fontSource;
        if ($parentFolder !== '.' && $parentFolder !== '') {
            $srcDir .= DIRECTORY_SEPARATOR . $parentFolder;
        }
        
        $cleanFolderName = removeVietnameseDiacritics($familyPrefix);
        $destDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . $cleanFolderName;

        if (!is_dir($srcDir)) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thư mục font gốc']);
            break;
        }

        if (!is_dir($destDir)) @mkdir($destDir, 0777, true);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $copiedFiles = [];
        $fontName = $cleanFolderName;

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $f = $fileInfo->getFilename();
                
                $filename = pathinfo($f, PATHINFO_FILENAME);
                $parsed = parseFontFilename($filename);
                if (strtolower($parsed['family']) !== strtolower($familyPrefix)) {
                    continue;
                }

                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if ($ext === 'woff' || $ext === 'woff2') {
                    $weight = $parsed['weight'];
                    $style = $parsed['style'];
                    $vKey = $weight . ($style === 'italic' ? 'i' : '');

                    if (in_array($vKey, $selectedVariants)) {
                        copy($fileInfo->getRealPath(), $destDir . DIRECTORY_SEPARATOR . $f);
                        $copiedFiles[$vKey][] = ['file' => $f, 'ext' => $ext, 'weight' => $weight, 'style' => $style];
                    }
                }
            }
        }

        if (empty($copiedFiles)) {
            echo json_encode(['status' => 'error', 'message' => 'Không có file font nào phù hợp với lựa chọn.']);
            break;
        }

        // Generate CSS
        $globalCssPath = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'fonts.css';
        $globalCssDir = dirname($globalCssPath);
        if (!is_dir($globalCssDir)) @mkdir($globalCssDir, 0777, true);

        $cssContent = "";
        foreach ($copiedFiles as $vKey => $vFiles) {
            $weight = $vFiles[0]['weight'];
            $style = $vFiles[0]['style'];
            $cssContent .= "@font-face {\n";
            $cssContent .= "  font-family: '$fontName';\n";
            $cssContent .= "  font-style: $style;\n";
            $cssContent .= "  font-weight: $weight;\n";
            $cssContent .= "  font-display: swap;\n"; // SEO & Speed Optimization
            
            $srcs = [];
            
            // Sort to ensure woff2 is prioritized
            usort($vFiles, function($a, $b) {
                if ($a['ext'] === 'woff2') return -1;
                if ($b['ext'] === 'woff2') return 1;
                return 0;
            });

            foreach ($vFiles as $vf) {
                $srcs[] = "url('../fonts/" . $cleanFolderName . "/{$vf['file']}') format('{$vf['ext']}')";
            }
            $cssContent .= "  src: " . implode(",\n       ", $srcs) . ";\n";
            $cssContent .= "}\n";
        }

        $existing = file_exists($globalCssPath) ? file_get_contents($globalCssPath) : '';
        $prefix = (empty($existing) || substr($existing, -1) === "\n") ? "" : "\n";
        file_put_contents($globalCssPath, $prefix . $cssContent, FILE_APPEND);
        
        echo json_encode(['status' => 'success', 'message' => "Đã cài đặt font $fontName và cập nhật vào assets/css/fonts.css"]);
        break;

    case 'getFontsCss':
        $category = $_GET['category'] ?? '';
        $projectName = $_GET['name'] ?? '';
        $project = $scanner->getProjectByName($projectName, $category ?? null);
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Project not found']);
            break;
        }
        $cssPath = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'fonts.css';
        $content = file_exists($cssPath) ? file_get_contents($cssPath) : '';
        echo json_encode(['status' => 'success', 'data' => $content]);
        break;

    case 'removeFont':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $projectName = $data['name'] ?? '';
        $fontName = $data['fontName'] ?? '';

        $project = $scanner->getProjectByName($projectName, $category ?? null);
        if (!$project || empty($fontName)) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin dự án hoặc tên font']);
            break;
        }

        $fontsCssPath = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'fonts.css';
        if (file_exists($fontsCssPath)) {
            $cssContent = file_get_contents($fontsCssPath);

            // 1. Remove @font-face blocks
            $escaped = preg_quote($fontName, '/');
            $pattern = '/@font-face\s*\{[^}]*font-family:\s*[\'"]' . $escaped . '[\'"][^}]*\}/i';
            $cssContent = preg_replace($pattern, '', $cssContent);

            // 2. Remove @import Google Fonts
            $cleanFamily = str_replace(' ', '+', $fontName);
            $lines = explode("\n", $cssContent);
            $newLines = [];
            foreach ($lines as $line) {
                if (stripos($line, "family=" . $cleanFamily) !== false || 
                    stripos($line, "family=" . urlencode($fontName)) !== false) {
                    continue;
                }
                $newLines[] = $line;
            }
            $cssContent = implode("\n", $newLines);
            $cssContent = preg_replace("/^\s*[\r\n]/m", "", $cssContent); // remove empty lines

            file_put_contents($fontsCssPath, trim($cssContent) . "\n");
        }

        // 3. Delete directory under assets/fonts/fontName
        $cleanFolderName = removeVietnameseDiacritics($fontName);
        $destDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . $cleanFolderName;
        if (is_dir($destDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($destDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                @$todo($fileinfo->getRealPath());
            }
            @rmdir($destDir);
        }

        echo json_encode(['status' => 'success', 'message' => "Đã gỡ bỏ font $fontName ra khỏi dự án"]);
        break;

    case 'addGoogleFont':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $projectName = $data['name'] ?? '';
        $importUrl = trim($data['importUrl'] ?? '');

        if (!empty($importUrl)) {
            if (preg_match('/url\s*\(\s*[\'"]?([^\'"\)]+)[\'"]?\s*\)/i', $importUrl, $urlMatch)) {
                $rawUrl = trim($urlMatch[1]);
                $importUrl = "@import url('" . $rawUrl . "');";
            } else if (strpos($importUrl, 'http') === 0) {
                $importUrl = "@import url('" . $importUrl . "');";
            }
        }

        $project = $scanner->getProjectByName($projectName, $category ?? null);
        if (!$project || !$importUrl) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin dự án hoặc URL']);
            break;
        }

        $globalCssPath = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'fonts.css';
        $globalCssDir = dirname($globalCssPath);
        if (!is_dir($globalCssDir)) @mkdir($globalCssDir, 0777, true);

        // Duplicate check
        $existingContent = file_exists($globalCssPath) ? file_get_contents($globalCssPath) : '';
        
        // Extract family name from URL to check
        preg_match('/family=([^&:]+)/', $importUrl, $matches);
        if (isset($matches[1])) {
            $familyName = str_replace('+', ' ', urldecode($matches[1]));
            // Check if this family is already imported or defined
            if (stripos($existingContent, "family=" . $matches[1]) !== false || 
                stripos($existingContent, "font-family: '" . $familyName . "'") !== false ||
                stripos($existingContent, "font-family: \"" . $familyName . "\"") !== false) {
                echo json_encode(['status' => 'error', 'message' => "Font '$familyName' đã tồn tại trong file fonts.css"]);
                break;
            }
        }

        $existing = file_exists($globalCssPath) ? file_get_contents($globalCssPath) : '';
        $cssContent = $importUrl . "\n" . $existing;
        file_put_contents($globalCssPath, $cssContent);

        echo json_encode(['status' => 'success', 'message' => "Đã thêm Google Font vào assets/css/fonts.css"]);
        break;

    case 'getFontFile':
        $fontId = $_GET['fontId'] ?? '';
        $variant = $_GET['variant'] ?? '';
        $ext = $_GET['ext'] ?? ''; // 'otf' or 'ttf'
        
        $globalPath = __DIR__ . '/data/demo_config.json';
        $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
        $fontSource = $gConfig['font_source_path'] ?? '';
        if (empty($fontSource) || !is_dir($fontSource)) {
            $fontSource = $baseDir . DIRECTORY_SEPARATOR . 'fonts';
        }
        
        if (!$fontSource || !is_dir($fontSource) || !$fontId || !$variant || !$ext) {
            die("Invalid parameters");
        }
        
        $parentFolder = dirname($fontId);
        $familyPrefix = basename($fontId);

        $srcDir = $fontSource;
        if ($parentFolder !== '.' && $parentFolder !== '') {
            $srcDir .= DIRECTORY_SEPARATOR . $parentFolder;
        }
        
        if (!is_dir($srcDir)) {
            die("Font directory not found");
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        $targetFile = null;
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $f = $fileInfo->getFilename();
                
                $filename = pathinfo($f, PATHINFO_FILENAME);
                $parsed = parseFontFilename($filename);
                if (strtolower($parsed['family']) !== strtolower($familyPrefix)) {
                    continue;
                }

                $fExt = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if ($fExt === strtolower($ext)) {
                    $weight = $parsed['weight'];
                    $style = $parsed['style'];
                    $vKey = $weight . ($style === 'italic' ? 'i' : '');
                    
                    if ($vKey === $variant) {
                        $targetFile = $fileInfo->getRealPath();
                        break;
                    }
                }
            }
        }
        
        if ($targetFile && file_exists($targetFile)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($targetFile) . '"');
            header('Content-Length: ' . filesize($targetFile));
            readfile($targetFile);
            exit;
        }
        
        die("Font file not found");
        break;

    case 'installConvertedFonts':
        $data = json_decode(file_get_contents('php://input'), true);
        $category = $data['category'] ?? '';
        $projectName = $data['name'] ?? '';
        $fontFamily = $data['fontFamily'] ?? '';
        $files = $data['files'] ?? []; // Array of { fileName, data (base64), weight, style, ext }

        $project = $scanner->getProjectByName($projectName, $category ?? null);
        if (!$project || empty($files)) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin dự án hoặc dữ liệu font']);
            break;
        }

        $parts = explode(' > ', $fontFamily);
        $fontName = removeVietnameseDiacritics(end($parts));
        $cleanFolderName = $fontName;
        $destDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . $cleanFolderName;

        if (!is_dir($destDir)) @mkdir($destDir, 0777, true);

        $copiedFiles = [];
        foreach ($files as $f) {
            $fileName = $f['fileName'];
            $base64Data = $f['data'];
            $weight = $f['weight'];
            $style = $f['style'];
            $ext = $f['ext'];

            $bin = base64_decode($base64Data);
            if (!$bin) continue;

            $destPath = $destDir . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($destPath, $bin);

            $vKey = $weight . ($style === 'italic' ? 'i' : '');
            $copiedFiles[$vKey][] = ['file' => $fileName, 'ext' => $ext, 'weight' => $weight, 'style' => $style];
        }

        if (empty($copiedFiles)) {
            echo json_encode(['status' => 'error', 'message' => 'Không thể lưu các file font đã chuyển đổi']);
            break;
        }

        $globalCssPath = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'fonts.css';
        $globalCssDir = dirname($globalCssPath);
        if (!is_dir($globalCssDir)) @mkdir($globalCssDir, 0777, true);

        $cssContent = "";
        foreach ($copiedFiles as $vKey => $vFiles) {
            $weight = $vFiles[0]['weight'];
            $style = $vFiles[0]['style'];
            $cssContent .= "@font-face {\n";
            $cssContent .= "  font-family: '$fontName';\n";
            $cssContent .= "  font-style: $style;\n";
            $cssContent .= "  font-weight: $weight;\n";
            $cssContent .= "  font-display: swap;\n";
            
            // Sort to ensure woff2 is prioritized
            usort($vFiles, function($a, $b) {
                if ($a['ext'] === 'woff2') return -1;
                if ($b['ext'] === 'woff2') return 1;
                return 0;
            });

            $srcs = [];
            foreach ($vFiles as $vf) {
                $srcs[] = "url('../fonts/" . $cleanFolderName . "/{$vf['file']}') format('{$vf['ext']}')";
            }
            $cssContent .= "  src: " . implode(",\n       ", $srcs) . ";\n";
            $cssContent .= "}\n";
        }

        $existing = file_exists($globalCssPath) ? file_get_contents($globalCssPath) : '';
        $prefix = (empty($existing) || substr($existing, -1) === "\n") ? "" : "\n";
        file_put_contents($globalCssPath, $prefix . $cssContent, FILE_APPEND);

        echo json_encode(['status' => 'success', 'message' => "Đã cài đặt font $fontName (convert) và cập nhật vào assets/css/fonts.css"]);
        break;

    case 'listProjectImages':
        $projectName = $_GET['name'] ?? '';
        $category = $_GET['category'] ?? '';
        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại']);
            break;
        }

        $imagesDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'images';
        $backupsDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $projectName;

        // 1. Run Garbage Collector on backups older than 24h
        if (is_dir($backupsDir)) {
            $backupFiles = scandir($backupsDir);
            foreach ($backupFiles as $bf) {
                if ($bf === '.' || $bf === '..') continue;
                $bfPath = $backupsDir . DIRECTORY_SEPARATOR . $bf;
                if (is_file($bfPath)) {
                    if (time() - filemtime($bfPath) > 86400) { // 24 hours
                        @unlink($bfPath);
                    }
                }
            }
        }

        // 2. Read current images
        $images = [];
        if (is_dir($imagesDir)) {
            $files = scandir($imagesDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $filePath = $imagesDir . DIRECTORY_SEPARATOR . $file;
                if (is_file($filePath)) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                        $size = filesize($filePath);
                        $width = 0;
                        $height = 0;
                        $dimensions = @getimagesize($filePath);
                        if ($dimensions) {
                            $width = $dimensions[0];
                            $height = $dimensions[1];
                        }

                        // Check if backup exists
                        $hasBackup = false;
                        $backupFile = null;
                        $timeLeft = 0;
                        if (is_dir($backupsDir)) {
                            $nameOnly = pathinfo($file, PATHINFO_FILENAME);
                            $backupFiles = scandir($backupsDir);
                            foreach ($backupFiles as $bf) {
                                if ($bf === '.' || $bf === '..') continue;
                                $bfNameOnly = pathinfo($bf, PATHINFO_FILENAME);
                                if ($bfNameOnly === $nameOnly) {
                                    $bfPath = $backupsDir . DIRECTORY_SEPARATOR . $bf;
                                    $hasBackup = true;
                                    $backupFile = $bf;
                                    $timeLeft = (filemtime($bfPath) + 86400) - time();
                                    break;
                                }
                            }
                        }

                        $images[] = [
                            'name' => $file,
                            'ext' => $ext,
                            'size' => $size,
                            'width' => $width,
                            'height' => $height,
                            'hasBackup' => $hasBackup,
                            'backupFile' => $backupFile,
                            'timeLeft' => $timeLeft > 0 ? $timeLeft : 0
                        ];
                    }
                }
            }
        }

        echo json_encode(['status' => 'success', 'data' => $images]);
        break;

    case 'convertProjectImages':
        $projectName = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $quality = (int)($_POST['quality'] ?? 80);
        $deep = (bool)($_POST['deep'] ?? 0);
        
        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại']);
            break;
        }

        $imagesDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'images';
        if (!is_dir($imagesDir)) {
            echo json_encode(['status' => 'error', 'message' => 'Thư mục hình ảnh không tồn tại']);
            break;
        }

        $backupsDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $projectName;
        if (!is_dir($backupsDir)) {
            @mkdir($backupsDir, 0777, true);
        }

        $files = scandir($imagesDir);
        $convertedCount = 0;
        $backupCount = 0;
        $errors = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $filePath = $imagesDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($filePath)) continue;

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) continue;

            $nameOnly = pathinfo($file, PATHINFO_FILENAME);
            $nameOnlyLower = strtolower($nameOnly);
            $isLogo = ($nameOnlyLower === 'logo');
            $isFavicon = ($nameOnlyLower === 'favicon' || stripos($file, 'favicon') !== false);

            if ($isFavicon) continue;

            // If already WebP and not a logo, do nothing
            if ($ext === 'webp' && !$isLogo) continue;

            $webpName = $nameOnly . '.webp';
            $webpPath = $imagesDir . DIRECTORY_SEPARATOR . $webpName;

            $img = null;
            $info = @getimagesize($filePath);
            if (!$info) continue;

            switch ($info[2]) {
                case IMAGETYPE_JPEG:
                    $img = @imagecreatefromjpeg($filePath);
                    break;
                case IMAGETYPE_PNG:
                    $img = @imagecreatefrompng($filePath);
                    if ($img) {
                        imagepalettetotruecolor($img);
                        imagealphablending($img, true);
                        imagesavealpha($img, true);
                    }
                    break;
                case IMAGETYPE_GIF:
                    $img = @imagecreatefromgif($filePath);
                    if ($img) {
                        imagepalettetotruecolor($img);
                    }
                    break;
                case IMAGETYPE_WEBP:
                    $img = @imagecreatefromwebp($filePath);
                    break;
            }

            if ($img) {
                if ($isLogo) {
                    $src_w = $info[0];
                    $src_h = $info[1];
                    $target_size = min(180, max($src_w, $src_h));
                    if ($target_size <= 0) $target_size = 180;

                    $squareImg = imagecreatetruecolor($target_size, $target_size);
                    imagealphablending($squareImg, false);
                    imagesavealpha($squareImg, true);

                    $transparent = imagecolorallocatealpha($squareImg, 0, 0, 0, 127);
                    imagefill($squareImg, 0, 0, $transparent);

                    $ratio = $src_w / $src_h;
                    if ($src_w > $src_h) {
                        $dst_w = $target_size;
                        $dst_h = (int)round($target_size / $ratio);
                    } else {
                        $dst_h = $target_size;
                        $dst_w = (int)round($target_size * $ratio);
                    }
                    $dst_x = (int)floor(($target_size - $dst_w) / 2);
                    $dst_y = (int)floor(($target_size - $dst_h) / 2);

                    imagecopyresampled($squareImg, $img, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $src_w, $src_h);
                    
                    $faviconPngPath = $imagesDir . DIRECTORY_SEPARATOR . 'favicon.png';
                    @imagepng($squareImg, $faviconPngPath);
                    imagedestroy($squareImg);
                }

                if ($deep) {
                    @imagefilter($img, IMG_FILTER_SMOOTH, 5);
                }
                $qVal = $quality;
                if ($deep) {
                    $qVal = min(80, $quality);
                }
                if ($quality === 101) {
                    $qVal = defined('IMG_WEBP_LOSSLESS') ? IMG_WEBP_LOSSLESS : 101;
                }
                if (@imagewebp($img, $webpPath, $qVal)) {
                    $convertedCount++;
                    imagedestroy($img);

                    // Move original to backups directory
                    $isKeep = $isFavicon || ($ext === 'webp');
                    if (!$isKeep) {
                        $backupPath = $backupsDir . DIRECTORY_SEPARATOR . $file;
                        if (@rename($filePath, $backupPath)) {
                            @touch($backupPath);
                            $backupCount++;
                        } else {
                            if (@copy($filePath, $backupPath)) {
                                @unlink($filePath);
                                @touch($backupPath);
                                $backupCount++;
                            } else {
                                $errors[] = "Không thể sao lưu file gốc: " . $file;
                            }
                        }
                    }
                } else {
                    $errors[] = "Không thể tạo file WebP cho: " . $file;
                    imagedestroy($img);
                }
            } else {
                $errors[] = "Không thể đọc ảnh: " . $file;
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Đã chuyển đổi thành công $convertedCount ảnh sang WebP và sao lưu $backupCount ảnh gốc để hoàn tác.",
            'converted' => $convertedCount,
            'backups' => $backupCount,
            'errors' => $errors
        ]);
        break;

    case 'convertSingleImage':
        $projectName = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $fileName = $_POST['file'] ?? '';
        $quality = (int)($_POST['quality'] ?? 80);
        $deep = (bool)($_POST['deep'] ?? 0);

        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại']);
            break;
        }

        $imagesDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'images';
        $filePath = $imagesDir . DIRECTORY_SEPARATOR . $fileName;

        if (!file_exists($filePath) || !is_file($filePath)) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy tệp tin hình ảnh']);
            break;
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $nameOnly = pathinfo($fileName, PATHINFO_FILENAME);
        $nameOnlyLower = strtolower($nameOnly);

        $isLogo = ($nameOnlyLower === 'logo');
        $isFavicon = ($nameOnlyLower === 'favicon' || stripos($fileName, 'favicon') !== false);

        if ($isFavicon) {
            echo json_encode(['status' => 'error', 'message' => 'Tệp favicon không được phép chuyển đổi sang WebP']);
            break;
        }

        if ($ext === 'webp' && !$isLogo) {
            echo json_encode(['status' => 'error', 'message' => 'Tệp đã ở định dạng WebP']);
            break;
        }

        $img = null;
        $info = @getimagesize($filePath);
        if (!$info) {
            echo json_encode(['status' => 'error', 'message' => 'Không thể đọc thông tin hình ảnh']);
            break;
        }

        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $img = @imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($filePath);
                if ($img) {
                    imagepalettetotruecolor($img);
                    imagealphablending($img, true);
                    imagesavealpha($img, true);
                }
                break;
            case IMAGETYPE_GIF:
                $img = @imagecreatefromgif($filePath);
                if ($img) {
                    imagepalettetotruecolor($img);
                }
                break;
            case IMAGETYPE_WEBP:
                $img = @imagecreatefromwebp($filePath);
                break;
        }

        if (!$img) {
            echo json_encode(['status' => 'error', 'message' => 'Không thể tải hình ảnh vào bộ nhớ']);
            break;
        }

        $webpName = $nameOnly . '.webp';
        $webpPath = $imagesDir . DIRECTORY_SEPARATOR . $webpName;

        if ($isLogo) {
            $src_w = $info[0];
            $src_h = $info[1];
            $target_size = min(180, max($src_w, $src_h));
            if ($target_size <= 0) $target_size = 180;

            $squareImg = imagecreatetruecolor($target_size, $target_size);
            imagealphablending($squareImg, false);
            imagesavealpha($squareImg, true);

            $transparent = imagecolorallocatealpha($squareImg, 0, 0, 0, 127);
            imagefill($squareImg, 0, 0, $transparent);

            $ratio = $src_w / $src_h;
            if ($src_w > $src_h) {
                $dst_w = $target_size;
                $dst_h = (int)round($target_size / $ratio);
            } else {
                $dst_h = $target_size;
                $dst_w = (int)round($target_size * $ratio);
            }
            $dst_x = (int)floor(($target_size - $dst_w) / 2);
            $dst_y = (int)floor(($target_size - $dst_h) / 2);

            imagecopyresampled($squareImg, $img, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $src_w, $src_h);
            
            $faviconPngPath = $imagesDir . DIRECTORY_SEPARATOR . 'favicon.png';
            @imagepng($squareImg, $faviconPngPath);
            imagedestroy($squareImg);
        }
        
        if ($deep) {
            @imagefilter($img, IMG_FILTER_SMOOTH, 5);
        }
        $qVal = $quality;
        if ($deep) {
            $qVal = min(80, $quality);
        }
        if ($quality === 101) {
            $qVal = defined('IMG_WEBP_LOSSLESS') ? IMG_WEBP_LOSSLESS : 101;
        }
        $success = @imagewebp($img, $webpPath, $qVal);

        imagedestroy($img);

        if ($success) {
            $isKeep = $isFavicon || ($ext === 'webp');
            if (!$isKeep) {
                $backupsDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $projectName;
                if (!is_dir($backupsDir)) {
                    @mkdir($backupsDir, 0777, true);
                }
                $backupPath = $backupsDir . DIRECTORY_SEPARATOR . $fileName;
                if (!@rename($filePath, $backupPath)) {
                    if (@copy($filePath, $backupPath)) {
                        @unlink($filePath);
                    }
                }
                @touch($backupPath);
            }
            echo json_encode([
                'status' => 'success',
                'message' => "Đã chuyển đổi thành công sang WebP."
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Lỗi trong quá trình ghi tệp tin WebP.'
            ]);
        }
        break;

    case 'undoSingleImage':
        $projectName = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $fileName = $_POST['file'] ?? ''; // e.g. banner.webp

        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại']);
            break;
        }

        $imagesDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'images';
        $backupsDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $projectName;

        $nameOnly = pathinfo($fileName, PATHINFO_FILENAME);
        
        $backupFile = null;
        if (is_dir($backupsDir)) {
            $backupFiles = scandir($backupsDir);
            foreach ($backupFiles as $bf) {
                if ($bf === '.' || $bf === '..') continue;
                $bfNameOnly = pathinfo($bf, PATHINFO_FILENAME);
                if ($bfNameOnly === $nameOnly) {
                    $backupFile = $bf;
                    break;
                }
            }
        }

        if (!$backupFile) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy hình ảnh gốc hoặc đã quá hạn 24 giờ.']);
            break;
        }

        $backupPath = $backupsDir . DIRECTORY_SEPARATOR . $backupFile;
        $restorePath = $imagesDir . DIRECTORY_SEPARATOR . $backupFile;

        if (@rename($backupPath, $restorePath) || (@copy($backupPath, $restorePath) && @unlink($backupPath))) {
            $webpPath = $imagesDir . DIRECTORY_SEPARATOR . $fileName;
            if (file_exists($webpPath)) {
                @unlink($webpPath);
            }
            echo json_encode([
                'status' => 'success',
                'message' => "Đã hoàn tác thành công hình ảnh gốc: $backupFile"
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể phục hồi hình ảnh gốc.']);
        }
        break;

    case 'undoAllImages':
        $projectName = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';

        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại']);
            break;
        }

        $imagesDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'images';
        $backupsDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $projectName;

        if (!is_dir($backupsDir)) {
            echo json_encode(['status' => 'error', 'message' => 'Không có hình ảnh nào có thể hoàn tác.']);
            break;
        }

        $backupFiles = scandir($backupsDir);
        $restoredCount = 0;
        $errors = [];

        foreach ($backupFiles as $bf) {
            if ($bf === '.' || $bf === '..') continue;
            $backupPath = $backupsDir . DIRECTORY_SEPARATOR . $bf;
            $restorePath = $imagesDir . DIRECTORY_SEPARATOR . $bf;

            if (is_file($backupPath)) {
                if (@rename($backupPath, $restorePath) || (@copy($backupPath, $restorePath) && @unlink($backupPath))) {
                    $restoredCount++;
                    $nameOnly = pathinfo($bf, PATHINFO_FILENAME);
                    $webpPath = $imagesDir . DIRECTORY_SEPARATOR . $nameOnly . '.webp';
                    if (file_exists($webpPath)) {
                        @unlink($webpPath);
                    }
                } else {
                    $errors[] = "Không thể phục hồi: " . $bf;
                }
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Đã hoàn tác thành công $restoredCount hình ảnh về định dạng gốc.",
            'errors' => $errors
        ]);
        break;

    case 'clearProjectImages':
        $projectName = $_POST['name'] ?? ($_GET['name'] ?? '');
        $category = $_POST['category'] ?? ($_GET['category'] ?? '');
        $projectConfig = $configManager->getForProject($projectName, $category);

        $config = file_exists(__DIR__ . '/data/demo_config.json') ? json_decode(file_get_contents(__DIR__ . '/data/demo_config.json'), true) : null;
        if (!$config) {
            echo json_encode(['status' => 'error', 'message' => 'Cấu hình chung chưa thiết lập']);
            break;
        }

        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Dự án không tồn tại']);
            break;
        }

        // Kiểm tra xem thư mục dự án có tồn tại trên Demo trước khi upload bridge
        if (!$deployService->remoteDirExists($config, $project['relPath'])) {
            echo json_encode(['status' => 'error', 'message' => "Dự án chưa tồn tại trên Demo Server. Không có dữ liệu để dọn dẹp."]);
            break;
        }

        // 1. Upload bridge.php to demo
        try {
            $deployService->upload($config, ['bridge.php' => __DIR__ . '/bridge.php'], $project['relPath']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Không thể upload Bridge để dọn dẹp: ' . $e->getMessage()]);
            break;
        }

        // 2. Call action=clearImages
        $cleanHost = !empty($config['web_domain'])
            ? str_replace(['https://', 'http://', '/'], '', $config['web_domain'])
            : str_replace(['ftp.', 'www.'], '', $config['ftp_host']);

        $useSSL = !empty($config['ssl']) || (isset($config['web_domain']) && strpos($config['web_domain'], 'https://') === 0);
        $schemes = $useSSL ? ['https://', 'http://'] : ['http://', 'https://'];
        $res = null;
        $webSub = '';
        if (isset($config['ftp_root'])) {
            $parts = explode('/public_html', $config['ftp_root']);
            if (count($parts) > 1) $webSub = $parts[1];
        }
        $fullSubPath = rtrim($webSub, '/') . '/' . trim($project['relPath'], '/');

        $success = false;
        $resMessage = '';
        foreach ($schemes as $scheme) {
            $pathPart = trim($fullSubPath, '/');
            $url = $scheme . $cleanHost . ($pathPart ? '/' . $pathPart : '') . "/bridge.php?action=clearImages";
            
            $res = RemoteClient::get($url);
            $decoded = json_decode($res, true);
            if ($decoded && isset($decoded['status'])) {
                $success = true;
                $resMessage = $res;
                break;
            }
        }

        // 3. Remove bridge.php from demo
        try {
            $ftpRoot = !empty($config['ftp_root']) ? $config['ftp_root'] : '/public_html';
            $remoteDir = rtrim($ftpRoot, '/');
            if ($project['relPath']) $remoteDir = $remoteDir . '/' . trim($project['relPath'], '/');
            RemoteClient::deleteViaDA($config, $remoteDir, 'bridge.php');
        } catch (\Exception $e) {}

        if ($success) {
            echo $resMessage;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể dọn dẹp hình ảnh trên demo: ' . strip_tags((string)$res)]);
        }
        break;

    case 'listProjectTrimImages':
        $projectName = $_GET['name'] ?? '';
        $category = $_GET['category'] ?? '';
        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Du an khong ton tai']);
            break;
        }

        $imagesDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'images';
        $backupsDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $projectName . DIRECTORY_SEPARATOR . 'trim';
        $ttl = 86400;

        if (is_dir($backupsDir)) {
            foreach (scandir($backupsDir) as $bf) {
                if ($bf === '.' || $bf === '..') continue;
                $bfPath = $backupsDir . DIRECTORY_SEPARATOR . $bf;
                if (is_file($bfPath) && time() - filemtime($bfPath) > $ttl) {
                    @unlink($bfPath);
                }
            }
        }

        $images = [];
        if (is_dir($imagesDir)) {
            foreach (scandir($imagesDir) as $file) {
                if ($file === '.' || $file === '..') continue;
                $filePath = $imagesDir . DIRECTORY_SEPARATOR . $file;
                if (!is_file($filePath) || !ImageTrimService::isSupportedFile($file)) continue;

                $dimensions = @getimagesize($filePath);
                $width = $dimensions ? $dimensions[0] : 0;
                $height = $dimensions ? $dimensions[1] : 0;

                $nameOnly = pathinfo($file, PATHINFO_FILENAME);
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $hasTrimBackup = false;
                $trimTimeLeft = 0;
                $latestBackupTime = 0;

                if (is_dir($backupsDir)) {
                    foreach (scandir($backupsDir) as $bf) {
                        if ($bf === '.' || $bf === '..') continue;
                        $bfPath = $backupsDir . DIRECTORY_SEPARATOR . $bf;
                        if (!is_file($bfPath)) continue;
                        $bfExt = strtolower(pathinfo($bf, PATHINFO_EXTENSION));
                        if ($bfExt !== $ext) continue;
                        if (strpos(pathinfo($bf, PATHINFO_FILENAME), $nameOnly . '__') !== 0) continue;

                        $mtime = filemtime($bfPath);
                        if ($mtime > $latestBackupTime) {
                            $latestBackupTime = $mtime;
                            $hasTrimBackup = true;
                            $trimTimeLeft = ($mtime + $ttl) - time();
                        }
                    }
                }

                $previewBase = '/' . trim(str_replace('\\', '/', $project['relPath']), '/') . '/assets/images/images/';
                $images[] = [
                    'name' => $file,
                    'ext' => $ext,
                    'size' => @filesize($filePath) ?: 0,
                    'width' => $width,
                    'height' => $height,
                    'previewUrl' => $previewBase . rawurlencode($file) . '?v=' . (@filemtime($filePath) ?: time()),
                    'hasTrimBackup' => $hasTrimBackup,
                    'trimTimeLeft' => $trimTimeLeft > 0 ? $trimTimeLeft : 0,
                ];
            }
        }

        echo json_encode(['status' => 'success', 'data' => $images]);
        break;

    case 'trimProjectImages':
        $projectName = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $tolerance = (int)($_POST['tolerance'] ?? 12);
        $filesInput = $_POST['files'] ?? '[]';
        $selectedFiles = is_array($filesInput) ? $filesInput : json_decode($filesInput, true);
        if (!is_array($selectedFiles)) $selectedFiles = [];

        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Du an khong ton tai']);
            break;
        }

        if (empty($selectedFiles)) {
            echo json_encode(['status' => 'error', 'message' => 'Chua chon anh de trim']);
            break;
        }

        $imagesDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'images';
        if (!is_dir($imagesDir)) {
            echo json_encode(['status' => 'error', 'message' => 'Thu muc hinh anh khong ton tai']);
            break;
        }

        $backupsDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $projectName . DIRECTORY_SEPARATOR . 'trim';
        if (!is_dir($backupsDir)) {
            @mkdir($backupsDir, 0777, true);
        }

        $trimmedCount = 0;
        $skippedCount = 0;
        $errors = [];
        $details = [];

        foreach ($selectedFiles as $fileName) {
            $fileName = basename((string)$fileName);
            if ($fileName === '' || !ImageTrimService::isSupportedFile($fileName)) {
                $errors[] = "Bo qua file khong hop le: " . $fileName;
                continue;
            }

            $filePath = $imagesDir . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($filePath)) {
                $errors[] = "Khong tim thay anh: " . $fileName;
                continue;
            }

            $backupName = pathinfo($fileName, PATHINFO_FILENAME) . '__' . date('Ymd_His') . '__' . bin2hex(random_bytes(3)) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
            $backupPath = $backupsDir . DIRECTORY_SEPARATOR . $backupName;
            if (!@copy($filePath, $backupPath)) {
                $errors[] = "Khong the sao luu anh truoc khi trim: " . $fileName;
                continue;
            }

            $result = ImageTrimService::trimFile($filePath, $tolerance);
            if ($result['status'] === 'success') {
                $trimmedCount++;
                $details[] = [
                    'file' => $fileName,
                    'oldWidth' => $result['oldWidth'],
                    'oldHeight' => $result['oldHeight'],
                    'newWidth' => $result['newWidth'],
                    'newHeight' => $result['newHeight'],
                    'removedX' => $result['removedX'],
                    'removedY' => $result['removedY'],
                ];
            } else {
                $skippedCount++;
                if ($result['status'] === 'error') {
                    @copy($backupPath, $filePath);
                }
                @unlink($backupPath);
                if ($result['status'] === 'error') {
                    $errors[] = $fileName . ': ' . ($result['message'] ?? 'Trim failed');
                }
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Da trim $trimmedCount anh. Bo qua $skippedCount anh khong co pixel thua.",
            'trimmed' => $trimmedCount,
            'skipped' => $skippedCount,
            'details' => $details,
            'errors' => $errors
        ]);
        break;

    case 'undoTrimImage':
        $projectName = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $fileName = basename((string)($_POST['file'] ?? ''));

        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Du an khong ton tai']);
            break;
        }

        if ($fileName === '' || !ImageTrimService::isSupportedFile($fileName)) {
            echo json_encode(['status' => 'error', 'message' => 'File khong hop le']);
            break;
        }

        $imagesDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'images';
        $backupsDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $projectName . DIRECTORY_SEPARATOR . 'trim';
        if (!is_dir($backupsDir)) {
            echo json_encode(['status' => 'error', 'message' => 'Khong co backup trim']);
            break;
        }

        $nameOnly = pathinfo($fileName, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $latestBackup = null;
        $latestTime = 0;

        foreach (scandir($backupsDir) as $bf) {
            if ($bf === '.' || $bf === '..') continue;
            $bfPath = $backupsDir . DIRECTORY_SEPARATOR . $bf;
            if (!is_file($bfPath)) continue;
            if (strtolower(pathinfo($bf, PATHINFO_EXTENSION)) !== $ext) continue;
            if (strpos(pathinfo($bf, PATHINFO_FILENAME), $nameOnly . '__') !== 0) continue;
            $mtime = filemtime($bfPath);
            if ($mtime > $latestTime) {
                $latestTime = $mtime;
                $latestBackup = $bfPath;
            }
        }

        if (!$latestBackup) {
            echo json_encode(['status' => 'error', 'message' => 'Khong tim thay backup trim cho anh nay']);
            break;
        }

        $restorePath = $imagesDir . DIRECTORY_SEPARATOR . $fileName;
        if (@copy($latestBackup, $restorePath)) {
            @unlink($latestBackup);
            echo json_encode(['status' => 'success', 'message' => 'Da hoan tac trim anh: ' . $fileName]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Khong the phuc hoi anh tu backup']);
        }
        break;

    case 'undoAllTrimImages':
        $projectName = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';

        $projects = $scanner->getProjects($category);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $projectName) { $project = $p; break; } }
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Du an khong ton tai']);
            break;
        }

        $imagesDir = $project['path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'images';
        $backupsDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $projectName . DIRECTORY_SEPARATOR . 'trim';
        if (!is_dir($backupsDir)) {
            echo json_encode(['status' => 'error', 'message' => 'Khong co backup trim']);
            break;
        }

        $latestByFile = [];
        foreach (scandir($backupsDir) as $bf) {
            if ($bf === '.' || $bf === '..') continue;
            $bfPath = $backupsDir . DIRECTORY_SEPARATOR . $bf;
            if (!is_file($bfPath)) continue;

            $parts = explode('__', pathinfo($bf, PATHINFO_FILENAME));
            if (count($parts) < 3) continue;
            array_pop($parts);
            array_pop($parts);
            $originalFile = implode('__', $parts) . '.' . pathinfo($bf, PATHINFO_EXTENSION);
            $mtime = filemtime($bfPath);
            if (!isset($latestByFile[$originalFile]) || $mtime > $latestByFile[$originalFile]['time']) {
                $latestByFile[$originalFile] = ['path' => $bfPath, 'time' => $mtime];
            }
        }

        $restoredCount = 0;
        $errors = [];
        foreach ($latestByFile as $originalFile => $backup) {
            $restorePath = $imagesDir . DIRECTORY_SEPARATOR . $originalFile;
            if (@copy($backup['path'], $restorePath)) {
                @unlink($backup['path']);
                $restoredCount++;
            } else {
                $errors[] = 'Khong the phuc hoi: ' . $originalFile;
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Da hoan tac trim $restoredCount anh.",
            'restored' => $restoredCount,
            'errors' => $errors
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
