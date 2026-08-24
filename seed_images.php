<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
/**
 * seed_images.php - Scan thư mục ảnh để dùng trong Tạo Dữ Liệu Mẫu
 * GET ?action=scan_folder&project_name=&folder=project_images|custom_pool
 * GET ?action=load_types&project_name=&main_key=
 * POST ?action=seed - tạo dữ liệu mẫu
 */

require_once __DIR__ . '/core/ProjectScanner.php';
require_once __DIR__ . '/core/AutoMediaPipeline.php';

use RamboWoon\AutoMediaPipeline;
use RamboWoon\ProjectScanner;

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    $baseDir = dirname(__DIR__);
    $scanner = new ProjectScanner($baseDir);

    // ── Đọc demo_config để lấy images_pool_path ──
    $globalConfig = [];
    $globalPath = __DIR__ . '/data/demo_config.json';
    if (is_file($globalPath)) {
        $globalConfig = json_decode(file_get_contents($globalPath), true) ?: [];
    }

    // ─────────────────────────────────────────────
    // 1. SCAN FOLDER ảnh
    // ─────────────────────────────────────────────
    if ($action === 'scan_folder') {
        $projectName = trim($_GET['project_name'] ?? '');
        $folderKey   = trim($_GET['folder'] ?? 'project_images'); // project_images | custom_pool
        $subdir      = trim($_GET['subdir'] ?? '');               // sub path trong custom pool

        if ($projectName === '') throw new Exception('Thiếu project_name');

        $project = $scanner->getProjectByName($projectName);
        if (!$project || empty($project['path'])) throw new Exception("Không tìm thấy dự án: $projectName");

        // Xác định thư mục
        if ($folderKey === 'project_images') {
            $scanDir = AutoMediaPipeline::normalizePath($project['path'] . '/assets/images/images');
            $previewBase = '/' . trim(str_replace('\\', '/', $project['relPath']), '/') . '/assets/images/images/';
        } else {
            // custom_pool: lấy từ global config
            $poolPath = rtrim(str_replace('\\', '/', $globalConfig['images_pool_path'] ?? ($baseDir . '/images')), '/');
            if ($subdir !== '') {
                $poolPath .= '/' . ltrim($subdir, '/');
            }
            $scanDir = AutoMediaPipeline::normalizePath($poolPath);
            $previewBase = null; // sẽ cần API riêng để serve ảnh từ path tuyệt đối
        }

        if (!is_dir($scanDir)) {
            throw new Exception("Thư mục không tồn tại: $scanDir");
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $files = [];

        // Scan đệ quy 1 cấp con
        $scanFn = function(string $dir, string $relPrefix = '') use ($allowedExt, $scanDir, $previewBase, $folderKey, $globalConfig, &$files) {
            $items = @scandir($dir);
            if (!$items) return;
            foreach ($items as $f) {
                if ($f === '.' || $f === '..') continue;
                $abs = $dir . DIRECTORY_SEPARATOR . $f;
                if (is_dir($abs)) continue; // bỏ qua sub-folder ở cấp này
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) continue;

                $size = @filesize($abs) ?: 0;
                $dim  = @getimagesize($abs);
                $w = $dim ? (int)$dim[0] : 0;
                $h = $dim ? (int)$dim[1] : 0;

                if ($folderKey === 'project_images') {
                    $preview = $previewBase . rawurlencode($f);
                } else {
                    // Encode path để PHP serve qua api
                    $relPath = ltrim($relPrefix . '/' . $f, '/');
                    $preview = 'seed_images.php?action=serve_image&path=' . urlencode($abs);
                }

                $files[] = [
                    'file'    => $relPrefix ? ($relPrefix . '/' . $f) : $f,
                    'abs'     => $abs,
                    'name'    => $f,
                    'ext'     => $ext,
                    'size'    => $size,
                    'width'   => $w,
                    'height'  => $h,
                    'preview' => $preview,
                ];
            }
        };

        $scanFn($scanDir, $subdir);

        // Liệt kê sub-folders của custom_pool để user có thể chọn
        $subfolders = [];
        if ($folderKey === 'custom_pool') {
            $items = @scandir($scanDir);
            if ($items) {
                foreach ($items as $f) {
                    if ($f === '.' || $f === '..') continue;
                    if (is_dir($scanDir . DIRECTORY_SEPARATOR . $f)) {
                        $subfolders[] = $f;
                    }
                }
            }
        }

        echo json_encode([
            'status'     => 'success',
            'folder'     => $folderKey,
            'scan_dir'   => AutoMediaPipeline::toForwardSlash($scanDir),
            'images'     => $files,
            'subfolders' => $subfolders,
            'total'      => count($files),
        ]);
        exit;
    }

    // ─────────────────────────────────────────────
    // 2. SERVE IMAGE từ path tuyệt đối (custom_pool)
    // ─────────────────────────────────────────────
    if ($action === 'serve_image') {
        $absPath = $_GET['path'] ?? '';
        $absPath = AutoMediaPipeline::normalizePath($absPath);

        // Bảo mật: chỉ cho phép từ images_pool_path
        $poolPath = AutoMediaPipeline::normalizePath($globalConfig['images_pool_path'] ?? ($baseDir . '/images'));
        if (!str_starts_with($absPath, $poolPath)) {
            http_response_code(403);
            exit;
        }
        if (!is_file($absPath)) {
            http_response_code(404);
            exit;
        }
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $mimeMap = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp'];
        header('Content-Type: ' . ($mimeMap[$ext] ?? 'image/jpeg'));
        header('Cache-Control: public, max-age=3600');
        readfile($absPath);
        exit;
    }

    // ─────────────────────────────────────────────
    // 3. LOAD TYPES config của dự án
    // ─────────────────────────────────────────────
    if ($action === 'load_types') {
        $projectName = trim($_GET['project_name'] ?? '');
        $mainKey     = trim($_GET['main_key'] ?? 'type-photo');
        if ($projectName === '') throw new Exception('Thiếu project_name');

        $project = $scanner->getProjectByName($projectName);
        if (!$project) throw new Exception("Không tìm thấy dự án: $projectName");

        $mainCfg = AutoMediaPipeline::resolveMainConfig($project['path'], $mainKey);
        if (!$mainCfg) throw new Exception("Không đọc được config/{$mainKey}.php");

        // Đọc raw config để lấy thêm thông tin (title_main, number, kind...)
        $configFilePath = AutoMediaPipeline::normalizePath($project['path'] . '/config/' . $mainKey . '.php');
        $rawConfig = is_file($configFilePath) ? (require $configFilePath) : [];

        $subTypes = [];
        foreach ($mainCfg['sub_types'] as $subKey => $sub) {
            $raw = $rawConfig[$subKey] ?? [];
            $ratio = AutoMediaPipeline::getSubtypeRatio($project['path'], $mainKey, $subKey);

            // Đọc số lượng record hiện có trong DB
            $recordCount = 0;
            try {
                $laravelBase = AutoMediaPipeline::normalizePath($project['path']);
                $pdo = AutoMediaPipeline::createPdoFromLaravelEnv($laravelBase);
                $stCheck = $pdo->query("SHOW TABLES LIKE '{$sub['table']}'");
                if ($stCheck->rowCount() > 0) {
                    $stCount = $pdo->prepare("SELECT COUNT(*) FROM `{$sub['table']}` WHERE `{$sub['type_column']}` = ?");
                    $stCount->execute([$subKey]);
                    $recordCount = (int)$stCount->fetchColumn();
                }
            } catch (\Throwable $e) {}

            $subTypes[$subKey] = [
                'key'          => $subKey,
                'title'        => $raw['title_main'] ?? $subKey,
                'kind'         => $raw['kind'] ?? 'static',
                'number'       => isset($raw['number']) ? (int)$raw['number'] : null,
                'has_images'   => !empty($raw['images']),
                'has_title'    => !empty($raw['title']),
                'has_desc'     => !empty($raw['desc']) || !empty($raw['desc_cke']),
                'has_link'     => !empty($raw['link']),
                'has_name'     => !empty($raw['name']),
                'ratio'        => $ratio,
                'table'        => $sub['table'],
                'type_column'  => $sub['type_column'],
                'type_value'   => $sub['type_value'],
                'record_count' => $recordCount,
            ];
        }

        echo json_encode([
            'status'    => 'success',
            'main_key'  => $mainKey,
            'target_dir'=> $mainCfg['target_dir'],
            'sub_types' => $subTypes,
        ]);
        exit;
    }

    // ─────────────────────────────────────────────
    // 4. SEED DỮ LIỆU MẪU
    // ─────────────────────────────────────────────
    if ($action === 'seed') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) throw new Exception('Payload không hợp lệ');

        $projectName = trim($payload['project_name'] ?? '');
        $mainKey     = trim($payload['main_key'] ?? '');
        $selectedSubs = $payload['sub_types'] ?? []; // [subKey => [file1, file2, ...]]
        $folderKey   = $payload['folder'] ?? 'project_images';
        $seedCount   = max(1, min(200, (int)($payload['seed_count'] ?? 5)));
        $seedCatCount = max(1, min(50, (int)($payload['seed_cat_count'] ?? 3)));
        $useAiText   = !empty($payload['use_ai']);
        $aiPromptExtra = trim($payload['ai_prompt'] ?? '');
        $aiModel     = trim($payload['ai_model'] ?? 'gemini-3.5-flash');

        if ($projectName === '' || $mainKey === '') throw new Exception('Thiếu project_name hoặc main_key');
        if (empty($selectedSubs)) throw new Exception('Chưa chọn sub-type và ảnh');

        $project = $scanner->getProjectByName($projectName);
        if (!$project) throw new Exception("Không tìm thấy dự án: $projectName");

        $laravelBase = AutoMediaPipeline::normalizePath($project['path']);
        $mainCfg = AutoMediaPipeline::resolveMainConfig($project['path'], $mainKey);
        if (!$mainCfg) throw new Exception("Không đọc được config/{$mainKey}.php");

        $pdo = AutoMediaPipeline::createPdoFromLaravelEnv($laravelBase);

        // Target dir
        $targetDirNorm = ltrim(str_replace('\\', '/', $mainCfg['target_dir']), '/');
        if (str_starts_with($targetDirNorm, 'public/')) {
            $targetDirNorm = substr($targetDirNorm, 7);
        }

        // Config file raw
        $configFilePath = AutoMediaPipeline::normalizePath($project['path'] . '/config/' . $mainKey . '.php');
        $rawConfig = is_file($configFilePath) ? (require $configFilePath) : [];

        $report = ['status' => 'success', 'details' => [], 'errors' => []];

        // AI text generators (nội dung mẫu đơn giản)
        $loremWords  = ['Thiết kế', 'Nội thất', 'Sang trọng', 'Hiện đại', 'Đẳng cấp', 'Tinh tế', 'Phong cách', 'Đặc biệt', 'Chất lượng', 'Ưu việt', 'Bền vững', 'Sáng tạo', 'Độc đáo', 'Cao cấp', 'Chuyên nghiệp'];
        $randomTitle = function(int $words = 4) use ($loremWords): string {
            shuffle($loremWords);
            return implode(' ', array_slice($loremWords, 0, $words));
        };
        $randomDesc  = function() use ($randomTitle): string {
            return $randomTitle(6) . '. ' . $randomTitle(8) . '. ' . $randomTitle(5) . '.';
        };
        $randomPhone = function(): string {
            $prefixes = ['090', '091', '093', '094', '096', '097', '098', '032', '033', '034', '035'];
            return $prefixes[array_rand($prefixes)] . rand(1000000, 9999999);
        };
        $randomLink  = function(): string {
            return '#';
        };
        $makeSlug = function($text) {
            if ($text != '') {
                $utf8 = array(
                    'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ|Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
                    'd' => 'đ|Đ',
                    'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ|É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
                    'i' => 'í|ì|ỉ|ĩ|ị|Í|Ì|Ỉ|Ĩ|Ị',
                    'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ|Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
                    'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự|Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
                    'y' => 'ý|ỳ|ỷ|ỹ|ỵ|Ý|Ỳ|Ỷ|Ỹ|Ỵ',
                    '' => '`|\~|\!|\@|\#|\||\$|\%|\^|\&|\*|\(|\)|\+|\=|\,|\.|\/|\?|\>|\<|\'|\"|\“|\”|\:|\;|_',
                );
                foreach ($utf8 as $ascii => $uni) {
                    $text = preg_replace("/($uni)/i", $ascii, $text);
                }
                $text = strtolower($text);
                $text = preg_replace("/[^\\p{L}0-9-\s]/u", "", $text);
                $text = preg_replace('/([\s]+)/', '-', $text);
                $text = str_replace(array('%20', ' '), '-', $text);
                $text = preg_replace("/-+/", "-", $text);
                $text = trim($text, '-');
                $text = trim($text);
            }
            return $text;
        };

        $designImage = $payload['design_image'] ?? null; // ['data' => base64, 'mime' => 'image/png']

        // Helper function to fetch from Gemini (Supports Vision Multimodal & Design Context Expansion)
        $getAiContentFromGemini = function(string $apiKey, string $model, string $prompt, string $subTypeTitle, int $count, bool $needsContent = false, ?array $designImg = null) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . urlencode($apiKey);
            
            $contentRequirement = $needsContent ? "\n- 'content': bài viết chi tiết, dài khoảng 2-3 đoạn văn, mô tả đầy đủ thông tin. Có thể dùng thẻ HTML cơ bản (ví dụ <br>, <strong>)." : "";

            if (!empty($designImg['data']) && !empty($designImg['mime'])) {
                $promptText = "Bạn là AI chuyên gia phân tích bản thiết kế UI/UX và trích xuất dữ liệu website thực tế từ ảnh chụp Figma/Mockup.
Dưới đây là ảnh chụp màn hình thiết kế giao diện của dự án.

NHIỆM VỤ CỦA BẠN:
1. Quan sát kỹ ảnh thiết kế đính kèm, nhận diện ngành nghề, tên thương hiệu và khối giao diện đang hiển thị liên quan đến loại mục: '$subTypeTitle' (hoặc mô tả: '$prompt').
2. Trích xuất chính xác các bài viết / danh mục / mục dữ liệu đang xuất hiện trên bản thiết kế (tiêu đề, tên, mô tả).
3. Nếu số lượng bài trích xuất được từ ảnh ít hơn $count phần tử, bạn hãy tự động hiểu ngữ cảnh/pattern của thiết kế và SÁNG TẠO THÊM các bài viết mới có cùng phong cách, cùng chủ đề để đủ đúng $count phần tử.
4. Trả về đúng một JSON array chứa đúng $count phần tử với các thuộc tính:
- 'name': tiêu đề/tên ngắn gọn (Ví dụ theo thiết kế: 'Follow Fanpage', 'Follow tiktok', 'Youtube', 'Follow Instagram'...).
- 'desc': đoạn mô tả ngắn gọn, chi tiết và hấp dẫn dài từ 1 đến 3 câu.{$contentRequirement}

YÊU CẦU QUAN TRỌNG:
1. Bạn CHỈ phản hồi nội dung là một chuỗi JSON array hợp lệ.
2. KHÔNG giải thích, KHÔNG viết bất kỳ chữ nào khác ngoài JSON array. KHÔNG đặt trong block ```json ... ```.";
            } else {
                $promptText = "Hãy tạo một JSON array chứa đúng $count phần tử dữ liệu mẫu tiếng Việt phù hợp với ngành nghề/mô tả sau: '$prompt' (dành cho loại danh mục: '$subTypeTitle').
Mỗi phần tử trong mảng phải chứa các thuộc tính sau:
- 'name': tiêu đề/tên ngắn gọn của sản phẩm/bài viết/hình ảnh (ví dụ: 'Nồi chiên không dầu Philips HD9252'). Tên phải mang tính thực tế, đa dạng, KHÔNG trùng lặp.
- 'desc': đoạn mô tả ngắn gọn, chi tiết và hấp dẫn dài từ 2 đến 3 câu.{$contentRequirement}

YÊU CẦU QUAN TRỌNG:
1. Bạn CHỈ phản hồi nội dung là một chuỗi JSON array hợp lệ.
2. KHÔNG giải thích, KHÔNG viết bất kỳ chữ nào khác ngoài JSON array. KHÔNG đặt trong block ```json ... ``` nếu có thể.";
            }

            $parts = [["text" => $promptText]];
            if (!empty($designImg['data']) && !empty($designImg['mime'])) {
                $parts[] = [
                    "inline_data" => [
                        "mime_type" => $designImg['mime'],
                        "data" => $designImg['data']
                    ]
                ];
            }

            $payload = json_encode([
                "contents" => [
                    ["parts" => $parts]
                ],
                "generationConfig" => [
                    "responseMimeType" => "application/json"
                ]
            ]);

            $executeGeminiCurl = function(string $targetModel) use ($apiKey, $payload): array {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/$targetModel:generateContent?key=" . urlencode($apiKey);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 45);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_close($ch);

                return ['code' => $httpCode, 'body' => $response, 'error' => $err];
            };

            $res = $executeGeminiCurl($model);
            // Auto Fallback nếu model được chọn trả về lỗi (ví dụ preview endpoint chưa mở)
            if ($res['code'] !== 200 && ($model === 'gemini-3.5-flash' || $model === 'gemini-3.1-flash-lite')) {
                $res = $executeGeminiCurl('gemini-2.5-flash');
                if ($res['code'] !== 200) {
                    $res = $executeGeminiCurl('gemini-1.5-flash');
                }
            }

            if ($res['error']) {
                throw new Exception("Lỗi kết nối cURL tới Gemini: " . $res['error']);
            }

            if ($res['code'] !== 200) {
                $resObj = json_decode($res['body'], true);
                $errMsg = $resObj['error']['message'] ?? "HTTP Code " . $res['code'];
                throw new Exception("Lỗi từ Gemini API ($model): " . $errMsg);
            }

            $response = $res['body'];

            $resObj = json_decode($response, true);
            $text = $resObj['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = trim($text);
            
            if (str_starts_with($text, '```')) {
                $text = preg_replace('/^```(?:json)?\s+|\s+```$/', '', $text);
                $text = trim($text);
            }
            
            $data = json_decode($text, true);
            if (!is_array($data)) {
                throw new Exception("Phản hồi từ Gemini không phải là JSON array hợp lệ.");
            }
            
            return $data;
        };

        // Helper function to fetch category names from Gemini
        $getAiCategoriesFromGemini = function(string $apiKey, string $model, string $prompt, string $subTypeTitle, string $level, int $count, array $excludeNames = [], ?array $designImg = null) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . urlencode($apiKey);
            
            $excludeStr = '';
            if (!empty($excludeNames)) {
                $excludeStr = "\n5. TUYỆT ĐỐI KHÔNG sử dụng các tên sau (vì đã bị trùng): " . implode(', ', array_slice($excludeNames, 0, 50));
            }

            $designInstruction = (!empty($designImg['data']) && !empty($designImg['mime'])) ? " Hãy quan sát ảnh thiết kế đính kèm để trích xuất hoặc suy luận tên danh mục cho chuẩn xác với giao diện thực tế." : "";

            $promptText = "Hãy tạo một JSON array chứa đúng $count tên danh mục cấp '$level' bằng tiếng Việt, phù hợp với ngành nghề/mô tả sau: '$prompt' (dành cho loại đối tượng: '$subTypeTitle').{$designInstruction}
Yêu cầu:
1. Các tên danh mục phải là TỪ KHÓA BAO QUÁT, mang tính phân loại chung (Ví dụ: 'Điện thoại', 'Tin thể thao', 'Dịch vụ tư vấn').
2. TUYỆT ĐỐI KHÔNG đặt tên danh mục giống với tên sản phẩm/bài viết cụ thể (Ví dụ: KHÔNG dùng 'iPhone 14 Pro', KHÔNG dùng 'Tư vấn pháp luật lao động ngày 14/2').
3. Ngắn gọn (2-5 từ), đa dạng và KHÔNG trùng lặp.
4. Chỉ phản hồi nội dung là một chuỗi JSON array các chuỗi. Ví dụ: [\"Danh mục A\", \"Danh mục B\"]. KHÔNG giải thích thêm.{$excludeStr}";

            $parts = [["text" => $promptText]];
            if (!empty($designImg['data']) && !empty($designImg['mime'])) {
                $parts[] = [
                    "inline_data" => [
                        "mime_type" => $designImg['mime'],
                        "data" => $designImg['data']
                    ]
                ];
            }

            $payload = json_encode([
                "contents" => [
                    ["parts" => $parts]
                ],
                "generationConfig" => [
                    "responseMimeType" => "application/json"
                ]
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 45);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                throw new Exception("Lỗi kết nối cURL tới Gemini: " . $err);
            }

            if ($httpCode !== 200) {
                $resObj = json_decode($response, true);
                $errMsg = $resObj['error']['message'] ?? "HTTP Code $httpCode";
                throw new Exception("Lỗi từ Gemini API khi tạo danh mục ($model): " . $errMsg);
            }

            $resObj = json_decode($response, true);
            $text = $resObj['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = trim($text);
            
            if (str_starts_with($text, '```')) {
                $text = preg_replace('/^```(?:json)?\s+|\s+```$/', '', $text);
                $text = trim($text);
            }
            
            $data = json_decode($text, true);
            if (!is_array($data)) {
                throw new Exception("Phản hồi từ Gemini cho danh mục không phải là JSON array.");
            }
            
            return $data;
        };

        $pdo->beginTransaction();
        try {
            foreach ($selectedSubs as $subKey => $imageFiles) {
                $subCfg = $mainCfg['sub_types'][$subKey] ?? null;
                if (!$subCfg) continue;

                $raw        = $rawConfig[$subKey] ?? [];
                $table      = $subCfg['table'];
                $typeCol    = $subCfg['type_column'];
                $typeVal    = $subCfg['type_value'];
                $imgCol     = $subCfg['column'] ?? 'photo';

                // kind=static: chỉ seed 1 record (hoặc update nếu đã có)
                $kind = $raw['kind'] ?? 'dynamic';
                $count = ($kind === 'static') ? 1 : $seedCount;
                if (isset($raw['number'])) $count = (int)$raw['number'];

                $aiData = [];
                if ($useAiText) {
                    $apiKey = trim($globalConfig['gemini_key'] ?? '');
                    if ($apiKey === '' || $apiKey === 'YOUR_GEMINI_API_KEY') {
                        throw new Exception("Vui lòng cấu hình Gemini API Key chính xác trong Cài đặt (demo_config.json).");
                    }
                    $prompt = $aiPromptExtra !== '' ? $aiPromptExtra : ($raw['title_main'] ?? $subKey);
                    $subTypeTitle = $raw['title_main'] ?? $subKey;
                    
                    try {
                        $needsContent = !empty($raw['content']) || !empty($raw['content_cke']);
                        $aiData = $getAiContentFromGemini($apiKey, $aiModel, $prompt, $subTypeTitle, $count, $needsContent, $designImage);
                    } catch (\Throwable $aiErr) {
                        throw new Exception("Lỗi tạo dữ liệu bằng AI cho loại '{$subTypeTitle}': " . $aiErr->getMessage());
                    }
                }

                // Check table exists
                $stCheck = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stCheck->rowCount() === 0) {
                    $report['errors'][] = "Bảng $table không tồn tại - bỏ qua $subKey";
                    continue;
                }

                // Fetch table columns to avoid column-not-found errors
                $columns = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), 'Field');

                $images = is_array($imageFiles) ? array_values(array_filter($imageFiles)) : [];
                $imgTotal = count($images);

                // Tự động kiểm tra và tạo danh mục (categories: list, cat, item, sub)
                $generated = [
                    'list' => [],
                    'cat' => [],
                    'item' => [],
                    'sub' => [],
                ];
                $categoryLevels = ['list', 'cat', 'item', 'sub'];
                $categoriesConfig = $raw['categories'] ?? [];

                $allGeneratedNames = [];
                if (!empty($aiData)) {
                    foreach ($aiData as $aiItem) {
                        if (!empty($aiItem['name'])) $allGeneratedNames[] = trim($aiItem['name']);
                    }
                }

                foreach ($categoryLevels as $lvlIdx => $level) {
                    if (!isset($categoriesConfig[$level])) {
                        continue;
                    }
                    $catTable = $table . '_' . $level;
                    $stCheck = $pdo->query("SHOW TABLES LIKE '$catTable'");
                    if ($stCheck->rowCount() === 0) {
                        continue;
                    }

                    $catColumns = array_column($pdo->query("SHOW COLUMNS FROM `$catTable`")->fetchAll(PDO::FETCH_ASSOC), 'Field');

                    // Lấy danh sách ID đã có hoặc tạo mới
                    $stExist = $pdo->prepare("SELECT id FROM `$catTable` WHERE `$typeCol` = ?");
                    $stExist->execute([$typeVal]);
                    $existingIds = $stExist->fetchAll(PDO::FETCH_COLUMN);

                    // Đảm bảo slug cho các categories đã tồn tại trong table_slug
                    $stSlugCheckTable = $pdo->query("SHOW TABLES LIKE 'table_slug'");
                    $hasSlugTable = ($stSlugCheckTable->rowCount() > 0);

                    if ($hasSlugTable && in_array('slugvi', $catColumns)) {
                        $nameCol = in_array('namevi', $catColumns) ? 'namevi' : (in_array('name', $catColumns) ? 'name' : '');
                        $slugCol = 'slugvi';

                        if ($nameCol && !empty($existingIds)) {
                            $stExistRows = $pdo->prepare("SELECT id, `$nameCol` as name_val, `$slugCol` as slug_val FROM `$catTable` WHERE id IN (" . implode(',', array_map('intval', $existingIds)) . ")");
                            $stExistRows->execute();
                            $existRows = $stExistRows->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($existRows as $row) {
                                $cId = $row['id'];
                                $cName = $row['name_val'];
                                $cSlug = $row['slug_val'];

                                if (!empty($cSlug)) {
                                    $stSlugCheck = $pdo->prepare("SELECT COUNT(*) FROM `table_slug` WHERE `id_parent` = ? AND `type` = ? AND `act` = ?");
                                    $stSlugCheck->execute([$cId, $typeVal, $level]);
                                    $slugExists = (int)$stSlugCheck->fetchColumn();

                                    if (!$slugExists) {
                                        $controller = '\\NASANICORE\\Controllers\\Web\\NewsController';
                                        $model = '\\NASANICORE\\Models\\News' . ucfirst($level) . 'Model';
                                        $prefix = 'news';
                                        if ($mainKey === 'type-products') {
                                            $controller = '\\NASANICORE\\Controllers\\Web\\ProductController';
                                            $model = '\\NASANICORE\\Models\\Product' . ucfirst($level) . 'Model';
                                            $prefix = 'product';
                                        } elseif ($mainKey === 'type-photo') {
                                            $controller = '\\NASANICORE\\Controllers\\Web\\PhotoController';
                                            $model = '\\NASANICORE\\Models\\Photo' . ucfirst($level) . 'Model';
                                            $prefix = 'photo';
                                        }
                                        $comVal = $prefix . '-' . $level;

                                        $stSlug = $pdo->prepare("INSERT INTO `table_slug` (`slugvi`, `namevi`, `controller`, `model`, `id_parent`, `com`, `act`, `type`, `created_at`, `updated_at`) VALUES (:slugvi, :namevi, :controller, :model, :id_parent, :com, :act, :type, :created_at, :updated_at)");
                                        $stSlug->execute([
                                            ':slugvi' => $cSlug,
                                            ':namevi' => $cName ?: $cSlug,
                                            ':controller' => $controller,
                                            ':model' => $model,
                                            ':id_parent' => $cId,
                                            ':com' => $comVal,
                                            ':act' => 'save',
                                            ':type' => $typeVal,
                                            ':created_at' => date('Y-m-d H:i:s'),
                                            ':updated_at' => date('Y-m-d H:i:s'),
                                        ]);
                                    }
                                }
                            }
                        }
                    }

                    $parentLevel = $lvlIdx > 0 ? $categoryLevels[$lvlIdx - 1] : null;
                    $parents = $parentLevel ? $generated[$parentLevel] : [0];

                    $targetCount = 0;
                    if ($level === 'list') {
                        $targetCount = $seedCatCount;
                    } elseif ($level === 'cat') {
                        $targetCount = count($parents) * 4;
                    } else { // item or sub
                        $targetCount = count($parents) * 2;
                    }

                    if (count($existingIds) >= $targetCount) {
                        $generated[$level] = $existingIds;
                    } else {
                        $newIds = [];
                        $needed = $targetCount - count($existingIds);

                        $aiCatData = [];
                        if ($useAiText && $needed > 0) {
                            $apiKey = trim($globalConfig['gemini_key'] ?? '');
                            if ($apiKey !== '' && $apiKey !== 'YOUR_GEMINI_API_KEY') {
                                $prompt = $aiPromptExtra !== '' ? $aiPromptExtra : ($raw['title_main'] ?? $subKey);
                                $subTypeTitle = $raw['title_main'] ?? $subKey;
                                // Giới hạn gọi AI tối đa 40 để tránh đứt gãy JSON
                                $aiNeeded = min($needed, 40);
                                try {
                                    $aiCatData = $getAiCategoriesFromGemini($apiKey, $aiModel, $prompt, $subTypeTitle, $level, $aiNeeded, $allGeneratedNames, $designImage);
                                    if (is_array($aiCatData)) {
                                        foreach ($aiCatData as $catName) {
                                            $allGeneratedNames[] = trim($catName);
                                        }
                                    }
                                } catch (\Throwable $catErr) {
                                    $report['errors'][] = "Lỗi sinh danh mục AI cấp {$level}: " . $catErr->getMessage();
                                }
                            }
                        }

                        for ($cIdx = 0; $cIdx < $needed; $cIdx++) {
                            $parentVal = !empty($parents) ? $parents[$cIdx % count($parents)] : 0;
                            
                            $nameVal = '';
                            if (!empty($aiCatData) && isset($aiCatData[$cIdx]) && trim($aiCatData[$cIdx]) !== '') {
                                $nameVal = trim($aiCatData[$cIdx]);
                            }
                            if ($nameVal === '') {
                                $catPrompt = ($useAiText && $aiPromptExtra !== '') ? $aiPromptExtra : ($raw['title_main'] ?? $subKey);
                                $nameVal = $catPrompt . ' Danh Mục ' . ucfirst($level) . ' ' . (count($existingIds) + $cIdx + 1);
                            }
                            $slugVal = $makeSlug($nameVal);
                            if ($useAiText && !empty($aiCatData) && isset($aiCatData[$cIdx]) && trim($aiCatData[$cIdx]) !== '') {
                                $slugVal .= '-' . (count($existingIds) + $cIdx + 1);
                            }

                            $cols = [$typeCol];
                            $vals = [$typeVal];

                            if (in_array('namevi', $catColumns)) { $cols[] = 'namevi'; $vals[] = $nameVal; }
                            if (in_array('nameen', $catColumns)) { $cols[] = 'nameen'; $vals[] = $nameVal; }
                            if (in_array('name', $catColumns))   { $cols[] = 'name';   $vals[] = $nameVal; }

                            if (in_array('slugvi', $catColumns)) { $cols[] = 'slugvi'; $vals[] = $slugVal; }
                            if (in_array('slugen', $catColumns)) { $cols[] = 'slugen'; $vals[] = $slugVal; }

                            if (in_array('status', $catColumns)) { $cols[] = 'status'; $vals[] = 'hienthi'; }
                            if (in_array('numb', $catColumns)) { $cols[] = 'numb'; $vals[] = $cIdx + 1; }
                            if (in_array('date_created', $catColumns)) { $cols[] = 'date_created'; $vals[] = time(); }
                            if (in_array('created_at', $catColumns)) { $cols[] = 'created_at'; $vals[] = date('Y-m-d H:i:s'); }
                            if (in_array('updated_at', $catColumns)) { $cols[] = 'updated_at'; $vals[] = date('Y-m-d H:i:s'); }

                            // Gán quan hệ parent
                            if ($level === 'cat') {
                                if (in_array('id_list', $catColumns)) { $cols[] = 'id_list'; $vals[] = $parentVal; }
                            } elseif ($level === 'item') {
                                if (in_array('id_cat', $catColumns)) { $cols[] = 'id_cat'; $vals[] = $parentVal; }
                                if (in_array('id_list', $catColumns) && $parentVal) {
                                    $stParent = $pdo->prepare("SELECT id_list FROM `{$table}_cat` WHERE id = ?");
                                    $stParent->execute([$parentVal]);
                                    $cols[] = 'id_list'; $vals[] = (int)$stParent->fetchColumn();
                                }
                            } elseif ($level === 'sub') {
                                if (in_array('id_item', $catColumns)) { $cols[] = 'id_item'; $vals[] = $parentVal; }
                                if ($parentVal) {
                                    $stParent = $pdo->prepare("SELECT id_list, id_cat FROM `{$table}_item` WHERE id = ?");
                                    $stParent->execute([$parentVal]);
                                    $pInfo = $stParent->fetch();
                                    if (in_array('id_list', $catColumns)) { $cols[] = 'id_list'; $vals[] = (int)($pInfo['id_list'] ?? 0); }
                                    if (in_array('id_cat', $catColumns)) { $cols[] = 'id_cat'; $vals[] = (int)($pInfo['id_cat'] ?? 0); }
                                }
                            }

                            $ph = array_fill(0, count($cols), '?');
                            $st = $pdo->prepare("INSERT INTO `$catTable` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $ph) . ")");
                            $st->execute($vals);
                            $newId = $pdo->lastInsertId();
                            $newIds[] = $newId;

                            if (in_array('slugvi', $catColumns) && $newId && !empty($slugVal)) {
                                if ($hasSlugTable) {
                                    $controller = '\\NASANICORE\\Controllers\\Web\\NewsController';
                                    $model = '\\NASANICORE\\Models\\News' . ucfirst($level) . 'Model';
                                    $prefix = 'news';
                                    if ($mainKey === 'type-products') {
                                        $controller = '\\NASANICORE\\Controllers\\Web\\ProductController';
                                        $model = '\\NASANICORE\\Models\\Product' . ucfirst($level) . 'Model';
                                        $prefix = 'product';
                                    } elseif ($mainKey === 'type-photo') {
                                        $controller = '\\NASANICORE\\Controllers\\Web\\PhotoController';
                                        $model = '\\NASANICORE\\Models\\Photo' . ucfirst($level) . 'Model';
                                        $prefix = 'photo';
                                    }

                                    $comVal = $prefix . '-' . $level;
                                    $stSlug = $pdo->prepare("INSERT INTO `table_slug` (`slugvi`, `namevi`, `controller`, `model`, `id_parent`, `com`, `act`, `type`, `created_at`, `updated_at`) VALUES (:slugvi, :namevi, :controller, :model, :id_parent, :com, :act, :type, :created_at, :updated_at)");
                                    $stSlug->execute([
                                        ':slugvi' => $slugVal,
                                        ':namevi' => $nameVal,
                                        ':controller' => $controller,
                                        ':model' => $model,
                                        ':id_parent' => $newId,
                                        ':com' => $comVal,
                                        ':act' => 'save',
                                        ':type' => $typeVal,
                                        ':created_at' => date('Y-m-d H:i:s'),
                                        ':updated_at' => date('Y-m-d H:i:s'),
                                    ]);
                                }
                            }
                        }
                        $generated[$level] = array_merge($existingIds, $newIds);
                    }
                }

                $existingCount = 0;
                try {
                    $stCount = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$typeCol` = ?");
                    $stCount->execute([$typeVal]);
                    $existingCount = (int)$stCount->fetchColumn();
                } catch (\Throwable $e) {}

                $inserted = 0;
                for ($i = 0; $i < $count; $i++) {
                    // Chọn ảnh random từ danh sách
                    $chosenFile = '';
                    $newImagePath = '';
                    if ($imgTotal > 0) {
                        $imgFile = $images[$i % $imgTotal];
                        if ($folderKey === 'project_images') {
                            $srcAbs = AutoMediaPipeline::normalizePath($laravelBase . '/assets/images/images/' . $imgFile);
                        } else {
                            $poolPath = AutoMediaPipeline::normalizePath($globalConfig['images_pool_path'] ?? ($baseDir . '/images'));
                            $srcAbs   = AutoMediaPipeline::normalizePath($poolPath . '/' . $imgFile);
                        }

                        if (is_file($srcAbs)) {
                            $today      = date('Y/m/d');
                            $ext        = strtolower(pathinfo($imgFile, PATHINFO_EXTENSION));
                            $base       = pathinfo($imgFile, PATHINFO_FILENAME);
                            $newName    = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $base) . ($ext ? ".$ext" : '');
                            $publicPath = $laravelBase . DIRECTORY_SEPARATOR . 'public';
                            if (is_dir($publicPath)) {
                                $destFolder = AutoMediaPipeline::normalizePath(
                                    $publicPath . '/' . str_replace('/', DIRECTORY_SEPARATOR, $targetDirNorm) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $today)
                                );
                            } else {
                                $destFolder = AutoMediaPipeline::normalizePath(
                                    $laravelBase . '/' . str_replace('/', DIRECTORY_SEPARATOR, $targetDirNorm) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $today)
                                );
                            }
                            if (!is_dir($destFolder)) @mkdir($destFolder, 0777, true);
                            $destAbs = $destFolder . DIRECTORY_SEPARATOR . $newName;
                            if (@copy($srcAbs, $destAbs)) {
                                $dbPath = $targetDirNorm;
                                if (str_starts_with($dbPath, 'upload/')) {
                                    $dbPath = substr($dbPath, 7);
                                }
                                $newImagePath = AutoMediaPipeline::toForwardSlash($dbPath . '/' . $today . '/' . $newName);
                            }
                            usleep(1000);
                        }
                    }

                    // Build insert data
                    $cols = [$typeCol];
                    $vals = [$typeVal];

                    $idListVal = 0;
                    $idCatVal  = 0;
                    $idItemVal = 0;
                    $idSubVal  = 0;

                    if (!empty($generated['list'])) {
                        $idListVal = (int)$generated['list'][$i % count($generated['list'])];
                    }
                    if (!empty($generated['cat']) && $idListVal) {
                        $stCat = $pdo->prepare("SELECT id FROM `{$table}_cat` WHERE id_list = ?");
                        $stCat->execute([$idListVal]);
                        $catIds = $stCat->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($catIds)) {
                            $idCatVal = (int)$catIds[$i % count($catIds)];
                        } else {
                            $idCatVal = (int)$generated['cat'][$i % count($generated['cat'])];
                        }
                    }
                    if (!empty($generated['item']) && $idCatVal) {
                        $stItem = $pdo->prepare("SELECT id FROM `{$table}_item` WHERE id_cat = ?");
                        $stItem->execute([$idCatVal]);
                        $itemIds = $stItem->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($itemIds)) {
                            $idItemVal = (int)$itemIds[$i % count($itemIds)];
                        } else {
                            $idItemVal = (int)$generated['item'][$i % count($generated['item'])];
                        }
                    }
                    if (!empty($generated['sub']) && $idItemVal) {
                        $stSub = $pdo->prepare("SELECT id FROM `{$table}_sub` WHERE id_item = ?");
                        $stSub->execute([$idItemVal]);
                        $subIds = $stSub->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($subIds)) {
                            $idSubVal = (int)$subIds[$i % count($subIds)];
                        } else {
                            $idSubVal = (int)$generated['sub'][$i % count($generated['sub'])];
                        }
                    }

                    if ($idListVal && in_array('id_list', $columns)) { $cols[] = 'id_list'; $vals[] = $idListVal; }
                    if ($idCatVal && in_array('id_cat', $columns)) { $cols[] = 'id_cat'; $vals[] = $idCatVal; }
                    if ($idItemVal && in_array('id_item', $columns)) { $cols[] = 'id_item'; $vals[] = $idItemVal; }
                    if ($idSubVal && in_array('id_sub', $columns)) { $cols[] = 'id_sub'; $vals[] = $idSubVal; }

                    if ($newImagePath !== '') {
                        if (in_array($imgCol, $columns)) {
                            $cols[] = $imgCol;
                            $vals[] = $newImagePath;
                        }
                    }

                    $nameViVal = '';
                    $slugViVal = '';
                    $hasSlug = !empty($raw['slug']);

                    $aiItem = null;
                    if (!empty($aiData)) {
                        $aiItem = $aiData[$i % count($aiData)];
                    }
                    $aiName = ($aiItem && !empty($aiItem['name'])) ? trim($aiItem['name']) : '';

                    if (!empty($raw['name'])) {
                        $nameVal = ($aiName !== '') ? $aiName : ($randomTitle(2) . ' ' . ($existingCount + $i + 1));
                        $nameViVal = $nameVal;
                        if (in_array('namevi', $columns)) { $cols[] = 'namevi'; $vals[] = $nameVal; }
                        if (in_array('nameen', $columns)) { $cols[] = 'nameen'; $vals[] = $nameVal; }
                        if (in_array('name', $columns))   { $cols[] = 'name';   $vals[] = $nameVal; }
                    }

                    if (!empty($raw['title'])) {
                        $titleVal = ($aiName !== '') ? $aiName : ($randomTitle(3 + ($i % 3)) . ' ' . ($existingCount + $i + 1));
                        if ($nameViVal === '') {
                            $nameViVal = $titleVal;
                        }
                        if (in_array('titlevi', $columns)) { $cols[] = 'titlevi'; $vals[] = $titleVal; }
                        if (in_array('titleen', $columns)) { $cols[] = 'titleen'; $vals[] = $titleVal; }
                        if (in_array('title', $columns))   { $cols[] = 'title';   $vals[] = $titleVal; }
                    }

                    if ($hasSlug && $nameViVal !== '') {
                        $slugViVal = $makeSlug($nameViVal);
                        if ($useAiText && $aiName !== '') {
                            $slugViVal .= '-' . ($existingCount + $i + 1);
                        }
                        if (in_array('slugvi', $columns)) { $cols[] = 'slugvi'; $vals[] = $slugViVal; }
                        if (in_array('slugen', $columns)) { $cols[] = 'slugen'; $vals[] = $slugViVal; }
                    }

                    // desc & desc_cke
                    $descVal = '';
                    if ($aiItem && !empty($aiItem['desc'])) {
                        $descVal = trim($aiItem['desc']);
                        if (!empty($raw['desc_cke']) && !str_starts_with($descVal, '<p>')) {
                            $descVal = '<p>' . $descVal . '</p>';
                        }
                    } else {
                        if (!empty($raw['desc'])) {
                            $descVal = $randomDesc();
                        } elseif (!empty($raw['desc_cke'])) {
                            $descVal = '<p>' . $randomDesc() . '</p>';
                        }
                    }

                    if ($descVal !== '') {
                        $descCols = ['descvi', 'descen', 'desc', 'mota', 'motavi', 'motaen'];
                        foreach ($descCols as $dc) {
                            if (in_array($dc, $columns)) {
                                $cols[] = $dc;
                                $vals[] = $descVal;
                            }
                        }
                    }

                    if (!empty($raw['link'])) {
                        $linkVal = $randomLink();
                        if (in_array('link', $columns)) { $cols[] = 'link'; $vals[] = $linkVal; }
                    }

                    if (!empty($raw['phone'])) {
                        $phoneVal = $randomPhone();
                        if (in_array('phone', $columns)) { $cols[] = 'phone'; $vals[] = $phoneVal; }
                    }

                    // content & content_cke
                    $contentVal = '';
                    if (!empty($raw['content']) || !empty($raw['content_cke'])) {
                        $paragraphs = [];
                        for ($p = 0; $p < rand(3, 5); $p++) {
                            $paragraphs[] = $randomDesc() . ' ' . $randomDesc();
                        }
                        
                        if (!empty($raw['content_cke'])) {
                            $contentVal = '<p>' . implode('</p><p>', $paragraphs) . '</p>';
                        } else {
                            $contentVal = implode("\n\n", $paragraphs);
                        }
                        
                        if ($aiItem && !empty($aiItem['content'])) {
                            $contentVal = trim($aiItem['content']);
                            if (!empty($raw['content_cke']) && !str_starts_with($contentVal, '<p>') && !str_contains($contentVal, '<br>')) {
                                $contentVal = '<p>' . str_replace("\n\n", '</p><p>', $contentVal) . '</p>';
                            }
                        }
                        
                        $contentCols = ['contentvi', 'contenten', 'content', 'noidung', 'noidungvi', 'noidungen'];
                        foreach ($contentCols as $cc) {
                            if (in_array($cc, $columns)) {
                                $cols[] = $cc;
                                $vals[] = $contentVal;
                            }
                        }
                    }

                    // code
                    if (!empty($raw['code'])) {
                        $codeVal = 'SP' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        if (in_array('code', $columns)) { $cols[] = 'code'; $vals[] = $codeVal; }
                    }

                    // prices and discount
                    if (!empty($raw['regular_price']) || !empty($raw['sale_price']) || !empty($raw['discount'])) {
                        $regularPrice = rand(10, 500) * 10000; // 100k đến 5tr
                        $discount = 0;
                        if (!empty($raw['discount'])) {
                            $discounts = [0, 5, 10, 15, 20, 25, 30, 40, 50];
                            $discount = $discounts[array_rand($discounts)];
                        }
                        $salePrice = 0;
                        if (!empty($raw['sale_price']) && $discount > 0) {
                            $salePrice = $regularPrice - ($regularPrice * $discount / 100);
                        } elseif (!empty($raw['sale_price']) && $discount == 0) {
                            $salePrice = $regularPrice;
                        }
                        
                        if (in_array('regular_price', $columns)) { $cols[] = 'regular_price'; $vals[] = $regularPrice; }
                        if (in_array('sale_price', $columns)) { $cols[] = 'sale_price'; $vals[] = $salePrice; }
                        if (in_array('discount', $columns)) { $cols[] = 'discount'; $vals[] = $discount; }
                    }
                    
                    // options2
                    if (!empty($raw['options2']) && is_array($raw['options2'])) {
                        $optData = [];
                        foreach ($raw['options2'] as $optKey => $optCfg) {
                            $optType = $optCfg['type'] ?? 'text';
                            if ($optType === 'number') {
                                $optData[$optKey] = rand(10, 1000);
                            } elseif ($optType === 'text') {
                                $optTitle = $optCfg['title'] ?? $optKey;
                                $lowerTitle = mb_strtolower($optTitle, 'UTF-8');
                                if (strpos($lowerTitle, 'điện thoại') !== false || strpos($lowerTitle, 'hotline') !== false) {
                                    $optData[$optKey] = $randomPhone();
                                } elseif (strpos($lowerTitle, 'địa chỉ') !== false) {
                                    $optData[$optKey] = 'Số ' . rand(1, 999) . ' ' . $randomTitle(2) . ', Quận ' . rand(1, 12) . ', TP. HCM';
                                } else {
                                    $optData[$optKey] = $optTitle . ' ' . rand(1, 999);
                                }
                            } elseif ($optType === 'code') {
                                $optData[$optKey] = 'CODE' . rand(100, 999);
                            } elseif ($optType === 'link-youtube') {
                                $optData[$optKey] = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
                            } else {
                                $optData[$optKey] = 'Data ' . rand(1, 100);
                            }
                        }
                        $optionsVal = json_encode($optData, JSON_UNESCAPED_UNICODE);
                        if (in_array('options2', $columns)) { $cols[] = 'options2'; $vals[] = $optionsVal; }
                    }

                    if (in_array('status', $columns)) {
                        $cols[] = 'status'; $vals[] = 'hienthi';
                    }

                    if (in_array('stt', $columns)) {
                        $cols[] = 'stt'; $vals[] = $i + 1;
                    }
                    if (in_array('numb', $columns)) {
                        $cols[] = 'numb'; $vals[] = $i + 1;
                    }
                    if (in_array('date_created', $columns)) {
                        $cols[] = 'date_created'; $vals[] = time();
                    }
                    if (in_array('created_at', $columns)) {
                        $cols[] = 'created_at'; $vals[] = date('Y-m-d H:i:s');
                    }
                    if (in_array('updated_at', $columns)) {
                        $cols[] = 'updated_at'; $vals[] = date('Y-m-d H:i:s');
                    }

                    if ($mainKey === 'type-photo' && in_array('com', $columns)) {
                        $kind = $raw['kind'] ?? 'static';
                        $cols[] = 'com';
                        $vals[] = ($kind === 'album') ? 'photo-album' : 'photo-static';
                    }

                    // Insert/Update logic
                    $lastId = 0;
                    if ($kind === 'static') {
                        $stExist = $pdo->prepare("SELECT id FROM `$table` WHERE `$typeCol` = ? LIMIT 1");
                        $stExist->execute([$typeVal]);
                        $existId = $stExist->fetchColumn();

                        if ($existId) {
                            $sets = array_map(fn($c) => "`$c` = ?", $cols);
                            $st = $pdo->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id = ?");
                            $vals[] = $existId;
                            $st->execute($vals);
                            $lastId = $existId;
                        } else {
                            $ph = array_fill(0, count($cols), '?');
                            $st = $pdo->prepare("INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $ph) . ")");
                            $st->execute($vals);
                            $lastId = $pdo->lastInsertId();
                        }
                    } else {
                        $ph = array_fill(0, count($cols), '?');
                        $st = $pdo->prepare("INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $ph) . ")");
                        $st->execute($vals);
                        $lastId = $pdo->lastInsertId();
                    }

                    // Insert to table_slug if slugs are enabled and generated
                    if ($hasSlug && $lastId && !empty($slugViVal)) {
                        $stSlugCheck = $pdo->query("SHOW TABLES LIKE 'table_slug'");
                        if ($stSlugCheck->rowCount() > 0) {
                            // Determine controller/model by checking main_key or com or type
                            $controller = '\\NASANICORE\\Controllers\\Web\\NewsController';
                            $model = '\\NASANICORE\\Models\\NewsModel';
                            $com = 'news';
                            if ($mainKey === 'type-photo') {
                                $controller = '\\NASANICORE\\Controllers\\Web\\PhotoController';
                                $model = '\\NASANICORE\\Models\\PhotoModel';
                                $kind = $raw['kind'] ?? 'static';
                                $com = ($kind === 'album') ? 'photo-album' : 'photo-static';
                            } elseif ($mainKey === 'type-products') {
                                $controller = '\\NASANICORE\\Controllers\\Web\\ProductController';
                                $model = '\\NASANICORE\\Models\\ProductModel';
                                $com = 'product';
                            }
                            // Delete old slug first if exists
                            $stDelSlug = $pdo->prepare("DELETE FROM `table_slug` WHERE `id_parent` = ? AND `type` = ?");
                            $stDelSlug->execute([$lastId, $typeVal]);

                            $stSlug = $pdo->prepare("INSERT INTO `table_slug` (`slugvi`, `namevi`, `controller`, `model`, `id_parent`, `com`, `act`, `type`, `created_at`, `updated_at`) VALUES (:slugvi, :namevi, :controller, :model, :id_parent, :com, :act, :type, :created_at, :updated_at)");
                            $stSlug->execute([
                                ':slugvi' => $slugViVal,
                                ':namevi' => $nameViVal ?? $slugViVal,
                                ':controller' => $controller,
                                ':model' => $model,
                                ':id_parent' => $lastId,
                                ':com' => $com,
                                ':act' => 'save',
                                ':type' => $typeVal,
                                ':created_at' => date('Y-m-d H:i:s'),
                                ':updated_at' => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                    $inserted++;
                }

                $report['details'][$subKey] = [
                    'table'    => $table,
                    'kind'     => $kind,
                    'seeded'   => $inserted,
                    'images'   => $imgTotal,
                ];
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        echo json_encode($report);
        exit;
    }

    throw new Exception("Action không hợp lệ: $action");

} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
