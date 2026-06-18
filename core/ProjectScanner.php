<?php
namespace RamboWoon;

class ProjectScanner {
    private $baseDir;

    public function __construct($baseDir) {
        $this->baseDir = $baseDir;
    }

    public function getCategories($strictMonth = false) {
        $categories = [];
        if (!is_dir($this->baseDir)) return [];
        $items = scandir($this->baseDir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $path = $this->baseDir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                // 1. Match YYYY_MM folders (e.g. 2026_06), thangX, or XXtX directly in root
                if (preg_match('/^\d{4}_\d{2}$/', $item) || preg_match('/^(thang\d{1,2}|\d{2}t\d{1,2})$/i', $item)) {
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
                            // Match thang6, thang12, 26t6, 26t12, etc.
                            if (preg_match('/^(thang\d{1,2}|\d{2}t\d{1,2})$/i', $subItem)) {
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
                if (preg_match('/^thang(\d{1,2})$/i', $cat, $m)) {
                    return date('Y') . '_' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
                }
                if (preg_match('/^(\d{2})t(\d{1,2})$/i', $cat, $m)) {
                    return '20' . $m[1] . '_' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
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

    public function getProjects($category = null) {
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
                    if (preg_match('/^\d{4}_\d{2}$/', $item) || preg_match('/^\d{4}$/', $item) || preg_match('/^(thang\d{1,2}|\d{2}t\d{1,2})$/i', $item)) {
                        continue;
                    }
                    
                    $projects[] = [
                        'name' => $item,
                        'path' => $path,
                        'category' => '',
                        'relPath' => $item,
                        'type' => 'project'
                    ];
                }
            }
            
            // 2. Scan month subdirectories
            $categories = $this->getCategories();
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
                            
                            $projects[] = [
                                'name' => $item,
                                'path' => $path,
                                'category' => $cat,
                                'relPath' => $cat . '/' . $item,
                                'type' => 'project'
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
                        
                        $projects[] = [
                            'name' => $item,
                            'path' => $path,
                            'category' => $category,
                            'relPath' => $category . '/' . $item,
                            'type' => 'project'
                        ];
                    }
                }
            }
        }
        
        return $projects;
    }
}
