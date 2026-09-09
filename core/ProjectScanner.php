<?php
namespace RamboWoon;

class ProjectScanner {
    private $baseDir;

    public function __construct($baseDir) {
        if (basename($baseDir) === 'RBWManager') {
            $baseDir = dirname($baseDir);
        }
        $this->baseDir = $baseDir;
    }

    private function getCacheFile() {
        $dataDir = __DIR__ . '/../data';
        if (!is_dir($dataDir)) @mkdir($dataDir, 0777, true);
        return $dataDir . '/projects_cache.json';
    }

    public function buildCache() {
        $strictCategories = $this->scanCategoriesRaw(true);
        $allCategories = $this->scanCategoriesRaw(false);

        $projectsCache = [];
        $projectsCache[''] = $this->scanProjectsRaw('');
        $projectsCache['all'] = $this->scanProjectsRaw('all');

        foreach ($allCategories as $cat) {
            $projectsCache[$cat] = $this->scanProjectsRaw($cat);
        }

        $data = [
            'updated_at' => time(),
            'categories_strict' => $strictCategories,
            'categories_all' => $allCategories,
            'projects' => $projectsCache
        ];

        file_put_contents($this->getCacheFile(), json_encode($data));
        return $data;
    }

    public function getCacheData($forceRefresh = false) {
        $cacheFile = $this->getCacheFile();
        $needBuild = $forceRefresh || !file_exists($cacheFile);

        if (!$needBuild && is_dir($this->baseDir)) {
            $cacheMTime = file_exists($cacheFile) ? filemtime($cacheFile) : 0;
            if (filemtime($this->baseDir) > $cacheMTime) {
                $needBuild = true;
            } else {
                $subDirs = @scandir($this->baseDir) ?: [];
                foreach ($subDirs as $sd) {
                    if ($sd === '.' || $sd === '..') continue;
                    $fullSd = $this->baseDir . DIRECTORY_SEPARATOR . $sd;
                    if (is_dir($fullSd) && filemtime($fullSd) > $cacheMTime) {
                        $needBuild = true;
                        break;
                    }
                }
            }
        }

        if ($needBuild) {
            return $this->buildCache();
        }

        $raw = @file_get_contents($cacheFile);
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data)) {
            return $this->buildCache();
        }
        return $data;
    }

    public function getCategories($strictMonth = false, $forceRefresh = false) {
        $cache = $this->getCacheData($forceRefresh);
        return $strictMonth ? ($cache['categories_strict'] ?? []) : ($cache['categories_all'] ?? []);
    }

    public function getProjectByName($projectName, $category = null) {
        if ($category) {
            $categories = [$category];
        } else {
            $categories = $this->getCategories();
            $categories[] = ''; // Also search the root directory
        }
        foreach ($categories as $cat) {
            $projects = $this->getProjects($cat);
            foreach ($projects as $p) {
                if ($p['name'] === $projectName) {
                    return $p;
                }
            }
        }
        return null;
    }

    public function getProjects($category = null, $forceRefresh = false) {
        $catKey = ($category === null) ? '' : $category;
        $cache = $this->getCacheData($forceRefresh);
        if (isset($cache['projects'][$catKey])) {
            return $cache['projects'][$catKey];
        }
        return $this->scanProjectsRaw($category);
    }

    private function scanCategoriesRaw($strictMonth = false) {
        $categories = [];
        if (!is_dir($this->baseDir)) return [];
        $items = scandir($this->baseDir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $path = $this->baseDir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                // 1. Match YYYY_MM folders (e.g. 2026_06), thangX, XXtX, or digit month directly in root
                if (preg_match('/^\d{4}_\d{2}$/', $item) || preg_match('/^(thang\d{1,2}|\d{2}t\d{1,2}|\d{1,2})$/i', $item)) {
                    $categories[] = $item;
                }
                // 2. Check if it is a year folder (e.g. 2026)
                elseif (preg_match('/^\d{4}$/', $item)) {
                    $subItems = @scandir($path) ?: [];
                    $hasMonthSubdirs = false;
                    foreach ($subItems as $subItem) {
                        if ($subItem == '.' || $subItem == '..') continue;
                        $subPath = $path . DIRECTORY_SEPARATOR . $subItem;
                        if (is_dir($subPath)) {
                            // Match thang6, thang12, 26t6, 26t12, 6, 12, etc.
                            if (preg_match('/^(thang\d{1,2}|\d{2}t\d{1,2}|\d{1,2})$/i', $subItem)) {
                                $categories[] = $item . '/' . $subItem;
                                $hasMonthSubdirs = true;
                            }
                        }
                    }
                    if (!$strictMonth && !$hasMonthSubdirs) {
                        $categories[] = $item;
                    }
                }
                // 3. Match folders containing any digit (for non-strict)
                else {
                    if (!$strictMonth) {
                        if (preg_match('/\d/', $item)) {
                            $categories[] = $item;
                        }
                    }
                }
            }
        }
        
        // Custom sort: group and sort categories by normalized date descending
        usort($categories, function($a, $b) {
            $normalize = function($cat) {
                if (preg_match('/^(\d{4})_(\d{2})$/', $cat, $m)) {
                    return $m[1] . '_' . $m[2];
                }
                if (preg_match('/^(\d{4})\/thang(\d{1,2})$/i', $cat, $m)) {
                    return $m[1] . '_' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
                }
                if (preg_match('/^(\d{4})\/(\d{2})t(\d{1,2})$/i', $cat, $m)) {
                    return $m[1] . '_' . str_pad($m[3], 2, '0', STR_PAD_LEFT);
                }
                if (preg_match('/^(\d{4})\/(\d{1,2})$/', $cat, $m)) {
                    return $m[1] . '_' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
                }
                if (preg_match('/^thang(\d{1,2})$/i', $cat, $m)) {
                    return date('Y') . '_' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
                }
                if (preg_match('/^(\d{2})t(\d{1,2})$/i', $cat, $m)) {
                    return '20' . $m[1] . '_' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
                }
                if (preg_match('/^(\d{1,2})$/', $cat, $m)) {
                    return date('Y') . '_' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
                }
                return preg_replace('/[^a-zA-Z0-9]/', '_', $cat);
            };
            
            $normA = $normalize($a);
            $normB = $normalize($b);
            
            if ($normA === $normB) {
                return strcmp($b, $a);
            }
            return strcmp($normB, $normA);
        });
        
        return $categories;
    }

    private function scanProjectsRaw($category = null) {
        if ($category === null) {
            $category = '';
        }
        
        $projects = [];
        $managerName = basename(dirname(__DIR__));
        
        if ($category === '' || $category === 'all') {
            // 1. Scan root directory
            $items = is_dir($this->baseDir) ? scandir($this->baseDir) : [];
            foreach ($items as $item) {
                if ($item == '.' || $item == '..' || $item == 'download') continue;
                $path = $this->baseDir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    $nameLower = strtolower($item);
                    if (in_array($nameLower, ['.git', '.github', '.idea', '.vscode', 'logs', 'temp_conv', 'backups', 'images', 'source_laravel', 'download', 'vendor', 'node_modules'])) {
                        continue;
                    }
                    if ($nameLower === strtolower($managerName)) {
                        continue;
                    }
                    if (preg_match('/^\d{4}_\d{2}$/', $item) || preg_match('/^\d{4}$/', $item) || preg_match('/^(thang\d{1,2}|\d{2}t\d{1,2}|\d{1,2})$/i', $item)) {
                        continue;
                    }
                    
                    $mtime = is_dir($path) ? (@filemtime($path) ?: 0) : 0;
                    $projects[] = [
                        'name' => $item,
                        'path' => $path,
                        'category' => '',
                        'relPath' => $item,
                        'type' => 'project',
                        'mtime' => $mtime,
                        'modified_at' => $mtime > 0 ? date('d/m/Y H:i', $mtime) : ''
                    ];
                }
            }
            
            // 2. Scan month subdirectories
            $categories = $this->scanCategoriesRaw(false);
            foreach ($categories as $cat) {
                $catDir = $this->baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cat);
                if (is_dir($catDir)) {
                    $catItems = scandir($catDir);
                    foreach ($catItems as $item) {
                        if ($item == '.' || $item == '..' || $item == 'download') continue;
                        $path = $catDir . DIRECTORY_SEPARATOR . $item;
                        if (is_dir($path)) {
                            $nameLower = strtolower($item);
                            if (in_array($nameLower, ['.git', '.github', '.idea', '.vscode', 'logs', 'temp_conv', 'backups', 'images', 'source_laravel', 'download', 'vendor', 'node_modules'])) {
                                continue;
                            }
                            
                            $mtime = is_dir($path) ? (@filemtime($path) ?: 0) : 0;
                            $projects[] = [
                                'name' => $item,
                                'path' => $path,
                                'category' => $cat,
                                'relPath' => $cat . '/' . $item,
                                'type' => 'project',
                                'mtime' => $mtime,
                                'modified_at' => $mtime > 0 ? date('d/m/Y H:i', $mtime) : ''
                            ];
                        }
                    }
                }
            }
        } else {
            // Scan specific category (month subdirectory)
            $dir = $this->baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $category);
            if (is_dir($dir)) {
                $items = scandir($dir);
                foreach ($items as $item) {
                    if ($item == '.' || $item == '..' || $item == 'download') continue;
                    $path = $dir . DIRECTORY_SEPARATOR . $item;
                    if (is_dir($path)) {
                        $nameLower = strtolower($item);
                        if (in_array($nameLower, ['.git', '.github', '.idea', '.vscode', 'logs', 'temp_conv', 'backups', 'images', 'source_laravel', 'download', 'vendor', 'node_modules'])) {
                            continue;
                        }
                        if ($nameLower === strtolower($managerName)) {
                            continue;
                        }
                        
                        $mtime = is_dir($path) ? (@filemtime($path) ?: 0) : 0;
                        $projects[] = [
                            'name' => $item,
                            'path' => $path,
                            'category' => $category,
                            'relPath' => $category . '/' . $item,
                            'type' => 'project',
                            'mtime' => $mtime,
                            'modified_at' => $mtime > 0 ? date('d/m/Y H:i', $mtime) : ''
                        ];
                    }
                }
            }
        }
        
        return $projects;
    }

    /**
     * Đọc cấu hình phiên bản PHP của các site từ RBWStack (data/sites.json)
     * Tự động nhận diện đường dẫn linh hoạt trên mọi máy tính (C:, D:, E:, Linux, v.v.)
     *
     * @return array Mảng map 'relPath' => 'php-x.x.x'
     */
    public function getPhpSitesConfig() {
        $possiblePaths = [];

        // 1. Thư mục gốc RBWStack từ baseDir (baseDir thường là /www => dirname là /RBWStack)
        if (!empty($this->baseDir)) {
            $possiblePaths[] = dirname($this->baseDir) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sites.json';
        }

        // 2. Từ vị trí file ProjectScanner.php: ../../../data/sites.json (core -> RBWManager -> www -> RBWStack)
        $possiblePaths[] = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sites.json';
        $possiblePaths[] = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sites.json';

        // 3. Quét theo ký tự ổ đĩa hiện tại trên Windows (C:\RBWStack, D:\RBWStack,...)
        if (defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows') {
            $drive = substr(__DIR__, 0, 2);
            if ($drive) {
                $possiblePaths[] = $drive . '\\RBWStack\\data\\sites.json';
            }
            $possiblePaths[] = 'C:\\RBWStack\\data\\sites.json';
            $possiblePaths[] = 'D:\\RBWStack\\data\\sites.json';
        }

        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                $content = @file_get_contents($path);
                if ($content) {
                    $json = json_decode($content, true);
                    if (is_array($json)) {
                        return $json;
                    }
                }
            }
        }

        return [];
    }

    /**
     * Lấy phiên bản PHP mặc định mà hệ thống máy chủ RBWStack đang chạy
     *
     * @return string Ví dụ: 'php-8.4.23'
     */
    public function getSystemPhpVersion() {
        return 'php-' . PHP_VERSION;
    }

    /**
     * Xác định phiên bản PHP của một dự án:
     * - Nếu có trong sites.json => lấy phiên bản riêng được cấu hình (is_custom_php = true)
     * - Nếu không có trong sites.json => lấy theo PHP mặc định hệ thống (is_custom_php = false)
     *
     * @param array $project Thông tin dự án
     * @param array|null $sitesConfig Dữ liệu từ sites.json (nếu đã nạp trước)
     * @return array [php_version, php_display, is_custom_php, system_php]
     */
    public function resolveProjectPhp($project, $sitesConfig = null) {
        if ($sitesConfig === null) {
            $sitesConfig = $this->getPhpSitesConfig();
        }

        $systemPhp = $this->getSystemPhpVersion();
        
        $pName = $project['name'] ?? '';
        $pCat = $project['category'] ?? '';
        $relPath = str_replace('\\', '/', $project['relPath'] ?? ($pCat ? "$pCat/$pName" : $pName));
        $trimRelPath = trim($relPath, '/');

        $matchedPhp = null;
        $isCustom = false;

        // Khớp theo relPath chính xác (ví dụ: '2026_05/hoanggia_0865426w')
        if (isset($sitesConfig[$relPath])) {
            $matchedPhp = $sitesConfig[$relPath];
            $isCustom = true;
        } elseif (isset($sitesConfig[$trimRelPath])) {
            $matchedPhp = $sitesConfig[$trimRelPath];
            $isCustom = true;
        } elseif (isset($sitesConfig[$pName])) {
            $matchedPhp = $sitesConfig[$pName];
            $isCustom = true;
        } else {
            // Kiểm tra case-insensitive nếu hệ thống có khác biệt chữ hoa chữ thường
            foreach ($sitesConfig as $key => $val) {
                if (strcasecmp($key, $relPath) === 0 || strcasecmp($key, $trimRelPath) === 0 || strcasecmp($key, $pName) === 0) {
                    $matchedPhp = $val;
                    $isCustom = true;
                    break;
                }
            }
        }

        $effectivePhp = $matchedPhp ?: $systemPhp;

        // Rút gọn phiên bản PHP để hiển thị đẹp trên badge (ví dụ: 'PHP 7.4', 'PHP 8.3', 'PHP 8.4')
        $shortDisplay = $effectivePhp;
        if (preg_match('/(?:php-?)?(\d+\.\d+)(?:\.\d+)?/i', $effectivePhp, $m)) {
            $shortDisplay = 'PHP ' . $m[1];
        }

        return [
            'php_version' => $effectivePhp,
            'php_display' => $shortDisplay,
            'is_custom_php' => $isCustom,
            'system_php' => $systemPhp
        ];
    }
}
