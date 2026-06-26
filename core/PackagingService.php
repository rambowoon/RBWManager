<?php
namespace RamboWoon;

class PackagingService {
    private $scanner;
    private $deploymentService;
    private $configManager;

    public function __construct($scanner, $deploymentService, $configManager) {
        $this->scanner = $scanner;
        $this->deploymentService = $deploymentService;
        $this->configManager = $configManager;
    }

    public function downloadFromDemo($projectName, $category, $jobId = null) {
        $project = $this->scanner->getProjectByName($projectName, $category);
        if (!$project) return ['status' => 'error', 'message' => 'Project not found'];

        writeJobLog($jobId, ['status' => 'info', 'log' => '📦 Bắt đầu tiến trình tải mã nguồn từ Demo...']);

        $globalPath = __DIR__ . '/../data/demo_config.json';
        $demoConfig = file_exists($globalPath) ? json_decode(file_get_contents($globalPath), true) : null;
        if (!$demoConfig) return ['status' => 'error', 'message' => 'Demo global config not found'];

        // Trigger 'package' action on Demo Bridge
        $demoWebDomain = $demoConfig['web_domain'] ?? '';
        $scheme = (strpos($demoWebDomain, 'https://') !== false) ? 'https://' : 'http://';
        $demoDomain = str_replace(['https://', 'http://', '/'], '', $demoWebDomain);
        if (empty($demoDomain)) {
            $demoDomain = str_replace(['ftp.', 'www.'], '', $demoConfig['ftp_host']);
        }
        
        $subPath = $project['relPath'];
        $webSub = '';
        if (isset($demoConfig['ftp_root']) && strpos($demoConfig['ftp_root'], '/public_html') !== false) {
            $webSub = str_replace('/public_html', '', $demoConfig['ftp_root']);
        }
        $fullSubPath = rtrim($webSub, '/') . '/' . trim($subPath, '/');
        
        $demoBridgeUrl = $scheme . $demoDomain . "/" . ltrim($fullSubPath, '/') . "/bridge.php?action=package";

        writeJobLog($jobId, ['status' => 'info', 'log' => '🔗 Đang kết nối tới Bridge tại Demo: ' . $demoDomain]);

        // Đồng bộ bridge.php mới nhất lên Demo trước khi đóng gói
        try {
            $this->deploymentService->upload($demoConfig, ['bridge.php' => __DIR__ . '/../bridge.php'], $project['relPath']);
        } catch (\Exception $e) {
            writeJobLog($jobId, ['status' => 'info', 'log' => '⚠️ Cảnh báo: Không thể tự động cập nhật bridge.php lên Demo: ' . $e->getMessage()]);
        }

        // Use ConfigManager to get the actual deployed DB credentials
        $projectConfig = $this->configManager->getForProject($projectName);
        $demoDeployed = $projectConfig['deployed']['demo'] ?? null;
        
        if ($demoDeployed && !empty($demoDeployed['db_name'])) {
            $dbConfig = [
                'host' => 'localhost',
                'name' => $demoDeployed['db_name'],
                'user' => $demoDeployed['db_user'],
                'pass' => $demoDeployed['db_pass']
            ];
        } else {
            // Fallback
            $dbName = $this->deploymentService->generateDemoDbName($category, $projectName);
            $dbConfig = [
                'host' => 'localhost',
                'name' => $demoConfig['ftp_user'] . '_' . $dbName,
                'user' => $demoConfig['ftp_user'] . '_' . $dbName,
                'pass' => $demoConfig['ftp_pass']
            ];
        }

        writeJobLog($jobId, ['status' => 'info', 'log' => '☁️ Đang yêu cầu Demo đóng gói Source + Database...']);
        $res = RemoteClient::post($demoBridgeUrl, [
            'db_config' => json_encode($dbConfig),
            'project_name' => $projectName
        ]);

        $decoded = json_decode($res, true);
        if (!$decoded) {
            writeJobLog($jobId, ['status' => 'error', 'message' => 'Lỗi phản hồi từ Demo: ' . (strlen($res) > 100 ? substr($res, 0, 100) . '...' : $res)]);
            return ['status' => 'error', 'message' => 'Demo trả về dữ liệu không hợp lệ.'];
        }

        if ($decoded && $decoded['status'] === 'success') {
            $localParentDir = dirname($project['path']);
            $downloadDir = $localParentDir . DIRECTORY_SEPARATOR . 'download';
            
            // Kiểm tra thư mục đích
            if (!is_dir($downloadDir)) {
                @mkdir($downloadDir, 0777, true);
            }
            if (!is_writable($downloadDir)) {
                $err = 'Lỗi: Thư mục local không có quyền ghi: ' . $downloadDir;
                writeJobLog($jobId, ['status' => 'error', 'message' => $err]);
                return ['status' => 'error', 'message' => $err];
            }

            $zipUrl = $decoded['url'];
            
            // Extract the original ZIP name from URL query parameter
            $parsedUrl = parse_url($zipUrl);
            $queryParams = [];
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
            }
            $zipName = $queryParams['file'] ?? ($projectName . '_' . time() . '.zip');
            
            $localFilePath = $downloadDir . DIRECTORY_SEPARATOR . $zipName;

            // Sử dụng Curl để tải package
            $zipRes = RemoteClient::downloadFile($zipUrl, $localFilePath);

            if ($zipRes !== true) {
                $msg = "Lỗi tải file: " . (is_string($zipRes) ? $zipRes : 'Không rõ nguyên nhân');
                writeJobLog($jobId, ['status' => 'error', 'message' => $msg]);
                return ['status' => 'error', 'message' => $msg];
            }

            $webPath = '../' . ($category ? str_replace('\\', '/', $category) . '/' : '') . 'download/' . $zipName;
            writeJobLog($jobId, ['status' => 'info', 'log' => '🚚 Đã tải xong Package về Local: ' . realpath($localFilePath)]);
            writeJobLog($jobId, ['status' => 'success', 'message' => 'Tải package thành công. File lưu tại: ' . realpath($localFilePath), 'url' => $webPath]);

            return ['status' => 'success', 'message' => 'Tải package thành công. File lưu tại: ' . realpath($localFilePath), 'url' => $webPath];
        }

        $err = $decoded['message'] ?? 'Invalid response from Demo host: ' . $res;
        writeJobLog($jobId, ['status' => 'error', 'message' => $err]);
        return ['status' => 'error', 'message' => $err];
    }
}
