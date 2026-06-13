<?php
/**
 * RamboWoon Manager API - Full Version
 */

error_reporting(0);
ini_set('display_errors', 0);
@set_time_limit(0);
@ini_set('max_execution_time', 0);
@ini_set('memory_limit', '512M');
ignore_user_abort(true);

$cliInputData = null;
if (PHP_SAPI === 'cli') {
    $cliArgs = [];
    foreach ($argv ?? [] as $arg) {
        if (strpos($arg, '--') !== 0) continue;
        $parts = explode('=', substr($arg, 2), 2);
        $cliArgs[$parts[0]] = $parts[1] ?? '1';
    }
    if (!empty($cliArgs['action'])) {
        $_GET['action'] = $cliArgs['action'];
    }
    if (!empty($cliArgs['payload']) && is_file($cliArgs['payload'])) {
        $payloadRaw = file_get_contents($cliArgs['payload']);
        $cliInputData = json_decode($payloadRaw, true) ?: [];
        @unlink($cliArgs['payload']);
    }
}

register_shutdown_function(function () use (&$jobId) {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR || $error['type'] === E_CORE_ERROR)) {
        writeJobLog($jobId, [
            'status' => 'error',
            'message' => '🔴 Lỗi Hệ Thống (Fatal Error): ' . $error['message'] . ' trong file ' . basename($error['file']) . ' dòng ' . $error['line']
        ]);
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
    if ($base === 'php-cgi.exe' || $base === 'php-win.exe') {
        $candidate = dirname($php) . DIRECTORY_SEPARATOR . 'php.exe';
        if (is_file($candidate)) return $candidate;
    }
    return $php;
}

function startApiBackgroundJob($action, array $data, $jobId) {
    $safeJobId = preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId ?: ('job_' . time()));
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);

    $payloadFile = $logDir . DIRECTORY_SEPARATOR . $safeJobId . '.payload.json';
    $data['jobId'] = $safeJobId;
    if (file_put_contents($payloadFile, json_encode($data)) === false) {
        throw new Exception('Khong the tao payload cho background job.');
    }

    $php = getPhpCliBinary();
    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $cmd = 'start "" /B ' . escapeshellarg($php)
            . ' ' . escapeshellarg(__FILE__)
            . ' --action=' . escapeshellarg($action)
            . ' --payload=' . escapeshellarg($payloadFile)
            . ' > NUL 2>&1';
        $handle = @popen($cmd, 'r');
        if (!$handle) {
            @unlink($payloadFile);
            throw new Exception('Khong the khoi dong background job.');
        }
        pclose($handle);
    } else {
        $cmd = escapeshellarg($php)
            . ' ' . escapeshellarg(__FILE__)
            . ' --action=' . escapeshellarg($action)
            . ' --payload=' . escapeshellarg($payloadFile)
            . ' > /dev/null 2>&1 &';
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
    elseif (preg_match('/(bold|700)/i', $f)) $weight = '700';
    elseif (preg_match('/(extrabold|800)/i', $f)) $weight = '800';
    elseif (preg_match('/(black|900)/i', $f)) $weight = '900';
    
    $style = preg_match('/italic/i', $f) ? 'italic' : 'normal';
    
    $cleanFamily = preg_replace('/[-_]?(thin|100|extralight|200|light|300|medium|500|semibold|demibold|600|bold|700|extrabold|800|black|heavy|900|regular|italic|normal|it|rg)/i', '', $f);
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

require_once __DIR__ . '/core/ProjectScanner.php';
require_once __DIR__ . '/core/ConfigManager.php';
require_once __DIR__ . '/core/DeploymentService.php';
require_once __DIR__ . '/core/PackagingService.php';
require_once __DIR__ . '/core/SchemaManager.php';
require_once __DIR__ . '/core/RemoteClient.php';
require_once __DIR__ . '/core/ProjectDeployer.php';
require_once __DIR__ . '/core/ImageTrimService.php';

use RamboWoon\ProjectScanner;
use RamboWoon\ConfigManager;
use RamboWoon\DeploymentService;
use RamboWoon\PackagingService;
use RamboWoon\RemoteClient;
use RamboWoon\ProjectDeployer;
use RamboWoon\ImageTrimService;

$baseDir = dirname(__DIR__); 
$configPath = __DIR__ . '/data/projects.json';

$scanner = new ProjectScanner($baseDir);
$configManager = new ConfigManager($configPath);
$deployService = new DeploymentService($baseDir);
$packagingService = new PackagingService($scanner, $deployService, $configManager);
$projectDeployer = new ProjectDeployer($baseDir);

$action = $_GET['action'] ?? '';
$jobId = $_GET['jobId'] ?? ($_POST['jobId'] ?? null);

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
        echo json_encode(['status' => 'success', 'data' => $scanner->getCategories($strict)]);
        break;

    case 'listProjects':
        $category = $_GET['category'] ?? '';
        $projects = $scanner->getProjects($category);
        $configs = $configManager->getAll();
        foreach ($projects as &$p) { $p['config'] = $configs[$p['name']] ?? null; }
        echo json_encode(['status' => 'success', 'data' => $projects]);
        break;

    case 'saveConfig':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($configManager->save($data['name'], $data['config']) ? ['status' => 'success'] : ['status' => 'error']);
        break;

    case 'saveGlobalConfig':
        $data = json_decode(file_get_contents('php://input'), true);
        $globalPath = __DIR__ . '/data/demo_config.json';
        echo json_encode(file_put_contents($globalPath, json_encode($data, JSON_PRETTY_PRINT)) ? ['status' => 'success'] : ['status' => 'error']);
        break;

    case 'getGlobalConfig':
        $globalPath = __DIR__ . '/data/demo_config.json';
        $config = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
        echo json_encode(['status' => 'success', 'data' => $config]);
        break;

    case 'deleteConfig':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($configManager->delete($data['name']) ? ['status' => 'success'] : ['status' => 'error']);
        break;

    case 'getProjectConfig':
        $name = $_GET['name'] ?? '';
        echo json_encode(['status' => 'success', 'data' => $configManager->getForProject($name)]);
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
        
        // Fix: Use $baseDir directly to place categories inside it
        $targetDir = $baseDir . DIRECTORY_SEPARATOR . $category . DIRECTORY_SEPARATOR . $projectName;
        
        // Final DB Name: category + project name (e.g. 2026_05_ranzilla_0765526w)
        $cleanProjectName = preg_replace('/[^a-z0-9_]/', '', strtolower($projectName));
        $dbName = $category . '_' . $cleanProjectName;

        try {
            writeJobLog($jobId, ['status' => 'info', 'log' => "Bắt đầu triển khai dự án: $projectName"]);
            writeJobLog($jobId, ['status' => 'info', 'log' => "Đường dẫn đích: $targetDir"]);
            
            // 1. Determine Source & Target Paths
            $parentDir = $baseDir . DIRECTORY_SEPARATOR . $category;
            if (!is_dir($parentDir)) @mkdir($parentDir, 0777, true);

            $finalSourceFolder = null;
            $finalSourceZip = null;
            $sourceBaseName = $sourceDbName; // e.g. source_nasani_2026

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
            $envUpdates = [
                'SITE_PATH' => '/' . $category . '/' . $projectName . '/', // e.g. 2026_05/ranzilla_0765526w
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => 'root',
                'DB_PASSWORD' => ''
            ];
            $projectDeployer->updateEnv($targetDir . '/.env', $envUpdates);

            // Cleanup SQL file
            @unlink($sqlFile);

            writeJobLog($jobId, ['status' => 'success', 'message' => "Triển khai dự án $projectName thành công!"]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            writeJobLog($jobId, ['status' => 'error', 'message' => $e->getMessage()]);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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

        $projectConfig = $configManager->getForProject($projectName);
        if (!$projectConfig) {
            echo json_encode(['status' => 'error', 'message' => 'Dự án chưa cấu hình']);
            break;
        }

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

        // Database đã tồn tại!
        // 2. Kiểm tra xem có file env trên demo không
        $envExists = false;
        $relPath = $project['relPath'];
        try {
            $envExists = $deployService->remoteFileExists($config, $relPath . '/.env');
        } catch (\Exception $e) {}

        $dbPass = null;
        if ($envExists) {
            // 3. Đọc pass từ file env trên demo
            try {
                $remoteEnvContent = $deployService->downloadRemoteEnv($config, $relPath);
                $dbPass = $deployService->getDbPassFromEnvContent($remoteEnvContent);
            } catch (\Exception $e) {}
        }

        // Lấy pass dự phòng
        $targetPass = '';
        $localEnvPath = $project['path'] . '/.env';
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
        $configManager->save($projectName, $projectConfig);

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
                    'env_exists' => $envExists,
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
                    'env_exists' => $envExists,
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
        $jobId = $data['jobId'] ?? null;
        $isDemo = ($action === 'deployDemo');
        if (PHP_SAPI !== 'cli' && empty($data['_background'])) {
            try {
                $queuedJobId = startApiBackgroundJob($action, array_merge($data, ['_background' => true]), $jobId);
                writeJobLog($queuedJobId, ['status' => 'info', 'log' => 'Da khoi dong job deploy nen...']);
                echo json_encode(['status' => 'queued', 'jobId' => $queuedJobId]);
            } catch (Throwable $e) {
                writeJobLog($jobId, ['status' => 'error', 'message' => 'Khong the khoi dong job nen: ' . $e->getMessage()]);
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;
        }

        $projectConfig = $configManager->getForProject($projectName);
        
        if ($isDemo && !empty($projectConfig['lock_demo'])) { writeJobLog($jobId, ['status' => 'error', 'message' => 'Deploy Demo bị khóa']); exit; }

        $config = $isDemo ? (file_exists(__DIR__ . '/data/demo_config.json') ? json_decode(file_get_contents(__DIR__ . '/data/demo_config.json'), true) : null) : ($projectConfig['prod'] ?? null);
        if (!$config) { writeJobLog($jobId, ['status' => 'error', 'message' => 'Host chưa cấu hình']); exit; }

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
            
            // Read local .env DB_PASSWORD if exists
            $localEnvPass = '';
            $localEnvPath = $project['path'] . '/.env';
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

        $zipFile = __DIR__ . '/dist.zip'; $sqlFile = __DIR__ . '/dist.sql';
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
                'deploy_time' => date('Y-m-d H:i:s')
            ];
            
            if ($isDemo) $projectConfig['lock_demo'] = true;
            if (!empty($data['password_updated'])) {
                $configManager->addHistory($projectName, 'Đồng bộ mật khẩu DB', 'Tự động cập nhật mật khẩu mới thành công');
            }
            $configManager->save($projectName, $projectConfig);
            $configManager->addHistory($projectName, 'Deploy ' . ($isDemo ? 'Demo' : 'Production'), 'Thành công');

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
            $configManager->addHistory($data['name'], 'Sync Tools', 'Đã tải lên Bridge');
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) { writeJobLog($jobId, ['status' => 'error', 'message' => $e->getMessage()]); echo json_encode(['status' => 'error']); }
        break;

    case 'publishToProduction':
        $data = json_decode(file_get_contents('php://input'), true);
        $jobId = $data['jobId'] ?? null;
        $projectName = $data['name'] ?? '';
        $projectConfig = $configManager->getForProject($projectName);
        
        // Load Global Config for Cloudflare Credentials
        $globalPath = __DIR__ . '/data/demo_config.json';
        $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
        
        $prodConfig = array_merge($projectConfig['prod'] ?? [], $projectConfig['deployed']['production'] ?? []);
        $demoConfig = array_merge($gConfig, $projectConfig['deployed']['demo'] ?? []);
        
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
                $configManager->save($projectName, $projectConfig);
                $configManager->addHistory($projectName, 'Publish Production', 'Full Setup hoàn tất');
                
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
            $configManager->addHistory($projectName, 'Download Package', 'Tải mã nguồn thành công');
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
        $projectConfig = $configManager->getForProject($data['name']);
        $config = ($data['type'] === 'demo') ? json_decode(file_get_contents(__DIR__ . '/data/demo_config.json'), true) : ($projectConfig['prod'] ?? []);
        $projects = $scanner->getProjects($data['category'] ?? null);
        $project = null;
        foreach ($projects as $p) { if ($p['name'] === $data['name']) { $project = $p; break; } }
        $res = $deployService->cleanupBridge($config, ($data['type'] === 'demo' ? $project['relPath'] : ''));
        $decoded = json_decode($res, true);
        if ($decoded && $decoded['status'] === 'success') {
            $configManager->addHistory($data['name'], 'Dọn dẹp Bridge (' . $data['type'] . ')', 'Hoàn tất');
        }
        echo $res;
        break;

    case 'integrateAMP':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectName = $data['name'] ?? '';
        $jobId = $data['jobId'] ?? null;

        $project = $scanner->getProjectByName($projectName);
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
            $configManager->addHistory($projectName, 'Tích hợp AMP NASANI', 'Hoàn tất');
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

    case 'installSSL':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectConfig = $configManager->getForProject($data['name']);
        $res = RemoteClient::requestSSLViaDA($projectConfig['prod']);
        
        // Cải tiến kiểm tra: Chấp nhận error=0 (text) HOẶC có chứa từ khóa thành công trong JSON
        $isSuccess = (strpos($res, 'error=0') !== false) || 
                     (strpos($res, '"success":') !== false) || 
                     (strpos($res, '"error":"0"') !== false);
                     
        $status = $isSuccess ? 'success' : 'error';
        if ($status === 'success') {
            $configManager->addHistory($data['name'], 'Cài đặt SSL', 'Gửi yêu cầu thành công');
        }
        echo json_encode(['status' => $status, 'message' => $res]);
        break;

    case 'getAvailablePhpVersions':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectName = $data['name'] ?? '';
        
        $projectConfig = $configManager->getForProject($projectName);
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
        $projectName = $data['name'] ?? '';
        $phpVersionIndex = $data['php_version_index'] ?? '1'; // 1, 2, 3, etc.
        
        $projectConfig = $configManager->getForProject($projectName);
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
            $configManager->addHistory($projectName, 'Thay đổi PHP Version', "Thành công (Index: $phpVersionIndex)");
        }
        echo json_encode(['status' => $status, 'message' => $res]);
        break;

    case 'changeDatabaseType':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectName = $data['name'];
        $module = $data['module'] ?? 'product';
        $old = $data['old_type'] ?? '';
        $new = $data['new_type'] ?? '';

        if (!$old || !$new) {
            echo json_encode(['status' => 'error', 'message' => 'Missing old_type or new_type']);
            break;
        }

        // 1. Tìm đường dẫn project local
        $project = $scanner->getProjectByName($projectName);
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

            $configManager->addHistory($projectName, 'Đổi Type DB (Local)', "Từ $old -> $new ($module)");
            echo json_encode(['status' => 'success', 'message' => "Đã cập nhật $totalAffected dòng tại Local Database.", 'details' => $details]);

        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối DB local: ' . $e->getMessage()]);
        }
        break;

    case 'toggleActionLock':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectConfig = $configManager->getForProject($data['name']);
        $key = ($data['type'] === 'demo') ? 'lock_demo' : 'lock_production';
        $projectConfig[$key] = !empty($projectConfig[$key]) ? false : true;
        $configManager->save($data['name'], $projectConfig);
        $actionName = $projectConfig[$key] ? 'Khóa' : 'Mở khóa';
        $configManager->addHistory($data['name'], $actionName . ' ' . ($data['type'] === 'demo' ? 'Demo' : 'Production'), 'Thành công');
        echo json_encode(['status' => 'success', 'locked' => $projectConfig[$key]]);
        break;

    case 'getProjectSchemaList':
        $projectName = $_GET['name'] ?? '';
        $project = $scanner->getProjectByName($projectName);
        if (!$project) {
            echo json_encode(['status' => 'error', 'message' => 'Project not found']);
            break;
        }
        $files = SchemaManager::listConfigFiles($project['path']);
        echo json_encode(['status' => 'success', 'data' => $files]);
        break;

    case 'loadModuleSchema':
        $projectName = $_GET['name'] ?? '';
        $file = $_GET['file'] ?? '';
        $project = $scanner->getProjectByName($projectName);
        if (!$project || !$file) {
            echo json_encode(['status' => 'error', 'message' => 'Project or file not found']);
            break;
        }
        $data = SchemaManager::load($project['path'], $file);
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    case 'saveModuleSchema':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectName = $data['name'] ?? '';
        $file = $data['file'] ?? '';
        $configData = $data['config'] ?? [];

        $project = $scanner->getProjectByName($projectName);
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

        $project = $scanner->getProjectByName($projectName);
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
        $name = $_GET['name'] ?? '';
        $project = $scanner->getProjectByName($name);
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

    case 'searchFonts':
        $query = $_GET['query'] ?? '';
        $globalPath = __DIR__ . '/data/demo_config.json';
        $gConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : [];
        $fontSource = $gConfig['font_source_path'] ?? '';
        if (empty($fontSource) || !is_dir($fontSource)) {
            $fontSource = $baseDir . DIRECTORY_SEPARATOR . 'fonts';
        }

        $results = [];

        // 1. Search Local Library
        if ($fontSource && is_dir($fontSource)) {
            $fontSource = rtrim(str_replace('\\', '/', $fontSource), '/');
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fontSource, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                $grouped = [];

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
                            
                            $filename = pathinfo($f, PATHINFO_FILENAME);
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
                                    'variants' => [],
                                    'convert_variants' => [],
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

                $normalizedQuery = str_replace(['_', '-', ' '], '', strtolower($query));

                foreach ($grouped as $fontId => $font) {
                    $normalizedFamily = str_replace(['_', '-', ' '], '', strtolower($font['family']));
                    
                    if ($query === '' || strpos($normalizedFamily, $normalizedQuery) !== false) {
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
                        
                        $score = 0;
                        $familyLower = strtolower($font['family']);
                        $queryLower = strtolower($query);
                        if ($familyLower === $queryLower) {
                            $score = 2000;
                        } elseif (strpos($familyLower, $queryLower) === 0) {
                            $score = 1000 - strlen($font['family']);
                        } else {
                            $score = 500 - strlen($font['family']);
                        }
                        $font['score'] = $score;
                        
                        $results[] = $font;
                        
                        if (count($results) >= 20) break;
                    }
                }
            } catch (Exception $e) {}
        }

        // 2. Search Google Fonts
        if ($query !== '') {
            ini_set('memory_limit', '256M'); // Increase memory for large JSON
            $cacheFile = __DIR__ . '/data/google_fonts_cache.json';
            $googleFonts = [];
            $cacheValid = false;
            
            if (file_exists($cacheFile)) {
                $cacheData = @file_get_contents($cacheFile);
                if ($cacheData) {
                    $googleFonts = json_decode($cacheData, true);
                    if (json_last_error() === JSON_ERROR_NONE && !empty($googleFonts)) {
                        $cacheValid = true;
                    }
                }
            }

            if (!$cacheValid || (time() - filemtime($cacheFile) > 86400 * 7)) {
                // Fetch from Google metadata with aggressive settings
                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 30,
                        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n"
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
                $data = @file_get_contents('https://fonts.google.com/metadata/fonts', false, $ctx);
                if ($data) {
                    $json = json_decode($data, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($json['familyMetadataList'])) {
                        $googleFonts = [];
                        foreach ($json['familyMetadataList'] as $item) {
                            $googleFonts[] = [
                                'family' => $item['family'],
                                'category' => $item['category'],
                                'variants' => array_keys($item['fonts'] ?? [])
                            ];
                        }
                        @file_put_contents($cacheFile, json_encode($googleFonts));
                        $cacheValid = true;
                    }
                }
            }

            if (!empty($googleFonts)) {
                $count = 0;
                $queryLower = strtolower($query);
                foreach ($googleFonts as $font) {
                    $familyLower = strtolower($font['family']);
                    if (strpos($familyLower, $queryLower) !== false) {
                        $score = 0;
                        if ($familyLower === $queryLower) {
                            $score = 2000;
                        } elseif (strpos($familyLower, $queryLower) === 0) {
                            $score = 1000 - strlen($font['family']);
                        } else {
                            $score = 500 - strlen($font['family']);
                        }

                        $results[] = [
                            'id' => $font['family'],
                            'family' => $font['family'],
                            'category' => $font['category'],
                            'variants' => $font['variants'],
                            'source' => 'google',
                            'score' => $score
                        ];
                        $count++;
                    }
                    if (count($results) >= 150 || $count >= 100) break;
                }
            }
        }

        // Final sorting: Exact matches first, then partials. Google Fonts has a HUGE boost over Local.
        usort($results, function($a, $b) {
            $scoreA = ($a['score'] ?? 0) + ($a['source'] === 'google' ? 5000 : 0);
            $scoreB = ($b['score'] ?? 0) + ($b['source'] === 'google' ? 5000 : 0);
            if ($scoreA !== $scoreB) return $scoreB - $scoreA;
            return strcmp($a['family'], $b['family']);
        });

        echo json_encode([
            'status' => 'success', 
            'data' => $results, 
            'google_search' => !empty($googleFonts)
        ]);
        break;

    case 'installFont':
        $data = json_decode(file_get_contents('php://input'), true);
        $projectName = $data['name'] ?? '';
        $fontId = $data['fontId'] ?? ''; 
        $selectedVariants = $data['variants'] ?? [];

        $project = $scanner->getProjectByName($projectName);
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
                    $weight = '400';
                    if (preg_match('/(thin|100)/i', $f)) $weight = '100';
                    elseif (preg_match('/(extralight|200)/i', $f)) $weight = '200';
                    elseif (preg_match('/(light|300)/i', $f)) $weight = '300';
                    elseif (preg_match('/(medium|500)/i', $f)) $weight = '500';
                    elseif (preg_match('/(semibold|600)/i', $f)) $weight = '600';
                    elseif (preg_match('/(bold|700)/i', $f)) $weight = '700';
                    elseif (preg_match('/(extrabold|800)/i', $f)) $weight = '800';
                    elseif (preg_match('/(black|900)/i', $f)) $weight = '900';
                    $style = preg_match('/italic/i', $f) ? 'italic' : 'normal';
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
        $projectName = $_GET['name'] ?? '';
        $project = $scanner->getProjectByName($projectName);
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
        $projectName = $data['name'] ?? '';
        $fontName = $data['fontName'] ?? '';

        $project = $scanner->getProjectByName($projectName);
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
        $projectName = $data['name'] ?? '';
        $importUrl = trim($data['importUrl'] ?? '');

        if (!empty($importUrl)) {
            if (preg_match('/url\s*\(\s*[\'"]?([^\'"\)]+)[\'"]?\s*\)/i', $importUrl, $urlMatch)) {
                $rawUrl = trim($urlMatch[1]);
                $importUrl = "@import url(" . $rawUrl . ");";
            } else if (strpos($importUrl, 'http') === 0) {
                $importUrl = "@import url(" . $importUrl . ");";
            }
        }

        $project = $scanner->getProjectByName($projectName);
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
        $prefix = (empty($existing) || substr($existing, -1) === "\n") ? "" : "\n";
        $cssContent = $prefix . $importUrl . "\n";
        file_put_contents($globalCssPath, $cssContent, FILE_APPEND);

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
                    $weight = '400';
                    if (preg_match('/(thin|100)/i', $f)) $weight = '100';
                    elseif (preg_match('/(extralight|200)/i', $f)) $weight = '200';
                    elseif (preg_match('/(light|300)/i', $f)) $weight = '300';
                    elseif (preg_match('/(medium|500)/i', $f)) $weight = '500';
                    elseif (preg_match('/(semibold|600)/i', $f)) $weight = '600';
                    elseif (preg_match('/(bold|700)/i', $f)) $weight = '700';
                    elseif (preg_match('/(extrabold|800)/i', $f)) $weight = '800';
                    elseif (preg_match('/(black|900)/i', $f)) $weight = '900';
                    $style = preg_match('/italic/i', $f) ? 'italic' : 'normal';
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
        $projectName = $data['name'] ?? '';
        $fontFamily = $data['fontFamily'] ?? '';
        $files = $data['files'] ?? []; // Array of { fileName, data (base64), weight, style, ext }

        $project = $scanner->getProjectByName($projectName);
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

            // If already WebP, do nothing
            if ($ext === 'webp') continue;

            $nameOnly = pathinfo($file, PATHINFO_FILENAME);
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
                    $isKeep = (stripos($file, 'logo') !== false || stripos($file, 'favicon') !== false);
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

        if ($ext === 'webp') {
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
            $isKeep = (stripos($fileName, 'logo') !== false || stripos($fileName, 'favicon') !== false);
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
