<?php

namespace RamboWoon;

use PDO;
use Exception;

class AutoMediaPipeline
{
    public static function getMainConfigs(): array
    {
        return [
            'type-photo' => [
                'target_dir' => 'public/upload/photo',
                'default_table' => 'table_photo',
                'default_column' => 'photo',
                'type_column' => 'type',
            ],
            'type-static' => [
                'target_dir' => 'public/upload/news',
                'default_table' => 'table_static',
                'default_column' => 'photo',
                'type_column' => 'type',
            ],
            'type-news' => [
                'target_dir' => 'public/upload/news',
                'default_table' => 'table_news',
                'default_column' => 'photo',
                'type_column' => 'type',
            ],
            'type-products' => [
                'target_dir' => 'public/upload/product',
                'default_table' => 'table_product',
                'default_column' => 'photo',
                'type_column' => 'type',
            ],
        ];
    }

    public static function resolveMainConfig(string $projectPath, string $mainKey): ?array
    {
        $mainConfigs = self::getMainConfigs();
        if (!isset($mainConfigs[$mainKey])) {
            return null;
        }
        $base = $mainConfigs[$mainKey];
        $keys = self::loadTypeKeysFromConfig($projectPath, $mainKey);
        if (empty($keys)) {
            return null;
        }

        $configFilePath = self::normalizePath($projectPath . '/config/' . $mainKey . '.php');
        $configData = [];
        if (is_file($configFilePath)) {
            $configData = require $configFilePath;
        }

        $subTypes = [];
        foreach ($keys as $subKey) {
            $cfgNode = $configData[$subKey] ?? null;
            $candidates = self::getCandidateKeysForSubType($subKey, $cfgNode);
            $regex = self::buildPrefixRegexFromTypeKeys($candidates) ?? '/^$/';
            $table = $base['default_table'] ?? self::inferTableName($mainKey, $subKey, null);
            $subTypes[$subKey] = [
                'table' => $table,
                'regex' => $regex,
                'column' => $base['default_column'] ?? 'photo',
                'type_column' => $base['type_column'] ?? 'type',
                'type_value' => $subKey,
                'candidates' => $candidates,
            ];
        }
        $base['sub_types'] = $subTypes;
        return $base;
    }

    public static function normalizePath(string $path): string
    {
        $clean = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        return rtrim($clean, DIRECTORY_SEPARATOR);
    }

    public static function toForwardSlash(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    public static function parseLaravelEnv(string $laravelBasePath): array
    {
        $envPath = self::normalizePath($laravelBasePath) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath)) {
            throw new Exception("Không tìm thấy file .env: {$envPath}");
        }

        $vars = [];
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            }
            $vars[$k] = $v;
        }
        return $vars;
    }

    public static function createPdoFromLaravelEnv(string $laravelBasePath): PDO
    {
        $env = self::parseLaravelEnv($laravelBasePath);
        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? '3306';
        $db = $env['DB_DATABASE'] ?? '';
        $user = $env['DB_USERNAME'] ?? '';
        $pass = $env['DB_PASSWORD'] ?? '';

        if ($db === '' || $user === '') {
            throw new Exception('Thiếu thông tin DB_DATABASE hoặc DB_USERNAME trong .env');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function classifyBySubType(string $fileName, array $subTypes): ?string
    {
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $baseClean = strtolower(self::stripAccents($base));

        // Sort subTypes by key length descending to match longer prefixes first
        uksort($subTypes, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        // 1. Pass 1: Strict prefix match using regex
        foreach ($subTypes as $subKey => $cfg) {
            if (preg_match($cfg['regex'], $base)) {
                return $subKey;
            }
        }

        // 2. Pass 2: Smart substring/keyword match
        foreach ($subTypes as $subKey => $cfg) {
            $candidates = $cfg['candidates'] ?? [$subKey];
            foreach ($candidates as $c) {
                $c = strtolower(self::stripAccents($c));
                if ($c === '') continue;

                if (strlen($c) > 3) {
                    if (str_contains($baseClean, $c)) {
                        return $subKey;
                    }
                } else {
                    $words = preg_split('/[^a-z0-9]/', $baseClean);
                    if (in_array($c, $words, true)) {
                        return $subKey;
                    }
                }
            }
        }

        return null;
    }

    public static function classifyAllBySubType(string $fileName, array $subTypes): array
    {
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $baseClean = strtolower(self::stripAccents($base));
        $matches = [];

        // 1. Pass 1: Strict prefix match using regex
        foreach ($subTypes as $subKey => $cfg) {
            if (preg_match($cfg['regex'], $base)) {
                $matches[] = $subKey;
            }
        }

        // 2. Pass 2: Smart substring/keyword match
        foreach ($subTypes as $subKey => $cfg) {
            if (in_array($subKey, $matches, true)) continue;
            
            $candidates = $cfg['candidates'] ?? [$subKey];
            foreach ($candidates as $c) {
                $c = strtolower(self::stripAccents($c));
                if ($c === '') continue;

                $matched = false;
                if (strlen($c) > 3) {
                    if (str_contains($baseClean, $c)) {
                        $matched = true;
                    }
                } else {
                    $words = preg_split('/[^a-z0-9]/', $baseClean);
                    if (in_array($c, $words, true)) {
                        $matched = true;
                    }
                }
                if ($matched) {
                    $matches[] = $subKey;
                    break;
                }
            }
        }

        return array_unique($matches);
    }

    public static function loadTypeKeysFromConfig(string $projectPath, string $mainKey): array
    {
        $filePath = self::normalizePath($projectPath . '/config/' . $mainKey . '.php');
        if (!is_file($filePath)) {
            return [];
        }
        $data = require $filePath;
        if (!is_array($data)) {
            return [];
        }
        return array_values(array_filter(array_keys($data), static function ($k) {
            return is_string($k) && trim($k) !== '';
        }));
    }

    public static function buildPrefixRegexFromTypeKeys(array $keys): ?string
    {
        if (empty($keys)) {
            return null;
        }
        $parts = [];
        foreach ($keys as $k) {
            $k = strtolower(trim((string)$k));
            if ($k === '') continue;
            $parts[] = preg_quote($k, '/');
            $parts[] = preg_quote(str_replace('-', '', $k), '/');
            $parts[] = preg_quote(str_replace('-', '_', $k), '/');
            $parts[] = preg_quote(str_replace(' ', '_', $k), '/');
        }
        $parts = array_values(array_unique(array_filter($parts)));
        if (empty($parts)) {
            return null;
        }
        return '/^(' . implode('|', $parts) . ')/i';
    }

    public static function inferTableName(string $mainKey, string $subKey, ?string $fallback = null): string
    {
        if ($mainKey === 'type-photo') {
            $k = strtolower(trim($subKey));
            if (str_ends_with($k, 's')) {
                return $k;
            }
            if (str_ends_with($k, 'y')) {
                return substr($k, 0, -1) . 'ies';
            }
            return $k . 's';
        }
        return $fallback ?: str_replace('-', '_', strtolower($subKey));
    }

    public static function getSubtypeRatio(string $projectPath, string $mainKey, string $subKey): ?array
    {
        $filePath = self::normalizePath($projectPath . '/config/' . $mainKey . '.php');
        if (!is_file($filePath)) {
            return null;
        }
        $data = require $filePath;
        if (!is_array($data) || !isset($data[$subKey]) || !is_array($data[$subKey])) {
            return null;
        }

        return self::extractRatioFromNode($data[$subKey]);
    }

    public static function isRatioMatch(int $imgW, int $imgH, int $targetW, int $targetH, float $tolerance = 12): bool
    {
        if ($imgW <= 0 || $imgH <= 0 || $targetW <= 0 || $targetH <= 0) {
            return false;
        }
        $pct = $tolerance > 1 ? $tolerance / 100.0 : $tolerance;
        $ratioImg = $imgW / $imgH;
        $ratioTarget = $targetW / $targetH;
        $diff = abs($ratioImg - $ratioTarget);
        return $diff <= ($ratioTarget * $pct);
    }

    private static function extractRatioFromNode($node): ?array
    {
        if (!is_array($node)) {
            return null;
        }

        $candidates = [];
        if (isset($node['images']) && is_array($node['images'])) {
            $candidates[] = $node['images']['photo'] ?? null;
            $candidates[] = $node['images'];
        }
        $candidates[] = $node;

        foreach ($candidates as $c) {
            if (!is_array($c)) continue;
            $w = (int)($c['width'] ?? $c['photo_width'] ?? $c['width_photo'] ?? 0);
            $h = (int)($c['height'] ?? $c['photo_height'] ?? $c['height_photo'] ?? 0);
            if ($w > 0 && $h > 0) {
                return ['width' => $w, 'height' => $h];
            }
        }

        // One level deep fallback
        foreach ($node as $v) {
            if (!is_array($v)) continue;
            $r = self::extractRatioFromNode($v);
            if ($r) return $r;
        }
        return null;
    }

    public static function resolveImageColumn(PDO $pdo, string $table, string $preferred = 'images'): string
    {
        $candidates = array_values(array_unique(array_filter([
            $preferred,
            'images',
            'image',
            'photo',
            'avatar',
            'thumb',
            'thumbnail',
            'img',
        ])));

        $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $existing = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[] = strtolower((string)($row['Field'] ?? ''));
        }

        foreach ($candidates as $col) {
            if (in_array(strtolower($col), $existing, true)) {
                return $col;
            }
        }

        throw new Exception("Không tìm thấy cột ảnh hợp lệ trong bảng {$table}");
    }

    public static function ensureColumnExists(PDO $pdo, string $table, string $column): void
    {
        $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (strtolower((string)($row['Field'] ?? '')) === strtolower($column)) {
                return;
            }
        }
        throw new Exception("Không tìm thấy cột {$column} trong bảng {$table}");
    }

    public static function stripAccents(string $str): string
    {
        $utf8 = [
            'a' => 'á|à|ả|ã|ạ|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd' => 'đ',
            'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i' => 'í|ì|ỉ|ĩ|ị',
            'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
            'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ằ|Ẳ|Ẵ|Ặ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
            'D' => 'Đ',
            'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
            'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
            'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
            'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
            'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ'
        ];
        foreach ($utf8 as $ascii => $uni) {
            $str = preg_replace("/($uni)/", $ascii, $str);
        }
        return $str;
    }

    public static function getCandidateKeysForSubType(string $subKey, ?array $cfgNode): array
    {
        $candidates = [$subKey];
        if ($cfgNode && isset($cfgNode['title_main'])) {
            $titleMain = $cfgNode['title_main'];
            if (is_string($titleMain) && trim($titleMain) !== '') {
                $candidates[] = $titleMain;
            }
        }

        $extra = [];
        foreach ($candidates as $c) {
            $cClean = strtolower(trim((string)$c));
            $extra[] = $cClean;
            $extra[] = str_replace('-', '', $cClean);
            $extra[] = str_replace('-', '_', $cClean);

            $noAccent = self::stripAccents($cClean);
            $extra[] = $noAccent;
            $extra[] = str_replace('-', '', $noAccent);
            $extra[] = str_replace('-', '_', $noAccent);
        }

        $allText = implode(' ', $extra);

        $synonyms = [
            'social' => ['mxh', 'mangxahoi', 'mang-xa-hoi', 'social', 'facebook', 'fb', 'zalo', 'youtube', 'yt', 'instagram', 'tiktok', 'twitter', 'messenger', 'google', 'pinterest'],
            'mangxahoi' => ['mxh', 'mangxahoi', 'mang-xa-hoi', 'social', 'facebook', 'fb', 'zalo', 'youtube', 'yt', 'instagram', 'tiktok', 'twitter', 'messenger', 'google', 'pinterest'],
            'slideshow' => ['slide', 'slideshow', 'slider', 'silde', 'silder'],
            'slide' => ['slide', 'slideshow', 'slider', 'silde', 'silder'],
            'gioithieu' => ['gt', 'gioithieu', 'gioi-thieu', 'about'],
            'tintuc' => ['tt', 'tintuc', 'tin-tuc', 'news'],
            'sanpham' => ['sp', 'sanpham', 'san-pham', 'product'],
            'duan' => ['da', 'duan', 'du-an', 'project'],
            'dichvu' => ['dv', 'dichvu', 'dich-vu', 'service'],
            'doitac' => ['dt', 'doitac', 'doi-tac', 'partner'],
            'lienhe' => ['lh', 'lienhe', 'lien-he', 'contact'],
            'chinhsach' => ['cs', 'chinhsach', 'chinh-sach', 'policy'],
        ];

        foreach ($synonyms as $keyword => $syns) {
            if (str_contains($allText, $keyword)) {
                foreach ($syns as $s) {
                    $extra[] = $s;
                    $extra[] = str_replace('-', '', $s);
                    $extra[] = str_replace('-', '_', $s);
                }
            }
        }

        return array_values(array_unique(array_filter($extra)));
    }

    public static function isGenericFilename(string $filename): bool
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $baseClean = strtolower(trim($base));
        
        $patterns = [
            '/^group\s*\d+$/i',
            '/^frame\s*\d+$/i',
            '/^vector\s*\d+$/i',
            '/^rectangle\s*\d+$/i',
            '/^component\s*\d+$/i',
            '/^ellipse\s*\d+$/i',
            '/^line\s*\d+$/i',
            '/^star\s*\d+$/i',
            '/^polygon\s*\d+$/i',
            '/^text\s*\d+$/i',
            '/^image\s*\d+$/i',
            '/^img\s*\d+$/i',
            '/^pic\s*\d+$/i',
            '/^photo\s*\d+$/i',
            '/^untitled\s*\d*$/i',
            '/^screenshot\s*/i',
            '/^download\s*/i',
            '/^\d+$/',
            '/^[a-f0-9]{32}$/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $baseClean)) {
                return true;
            }
        }
        
        return false;
    }
}
