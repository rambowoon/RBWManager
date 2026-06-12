<?php

require_once __DIR__ . '/core/ProjectScanner.php';
require_once __DIR__ . '/core/AutoMediaPipeline.php';

use RamboWoon\AutoMediaPipeline;
use RamboWoon\ProjectScanner;

header('Content-Type: application/json; charset=utf-8');

try {
    $mainKey = trim($_GET['main_key'] ?? '');
    $projectName = trim($_GET['project_name'] ?? '');
    if ($mainKey === '') {
        throw new Exception('Thiếu main_key');
    }
    if ($projectName === '') {
        throw new Exception('Thiếu project_name');
    }

    $baseDir = dirname(__DIR__);
    $scanner = new ProjectScanner($baseDir);
    $project = $scanner->getProjectByName($projectName);
    if (!$project || empty($project['path'])) {
        throw new Exception("Không tìm thấy dự án: {$projectName}");
    }

    $mainCfg = AutoMediaPipeline::resolveMainConfig($project['path'], $mainKey);
    if (!$mainCfg) {
        throw new Exception("Không đọc được cấu hình type từ file config/{$mainKey}.php");
    }

    $projectImagesDir = AutoMediaPipeline::normalizePath(
        $project['path'] . '/assets/images/images'
    );
    if (!is_dir($projectImagesDir)) {
        throw new Exception("Không tìm thấy thư mục ảnh nguồn của dự án: {$projectImagesDir}");
    }

    $subTypes = $mainCfg['sub_types'];
    $subTypeRatios = [];
    foreach (array_keys($subTypes) as $subKey) {
        $subTypeRatios[$subKey] = AutoMediaPipeline::getSubtypeRatio($project['path'], $mainKey, $subKey);
    }
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    $grouped = [];
    foreach (array_keys($subTypes) as $k) {
        $grouped[$k] = [];
    }

    $files = scandir($projectImagesDir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }

        $abs = $projectImagesDir . DIRECTORY_SEPARATOR . $f;
        if (!is_file($abs)) {
            continue;
        }

        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }

        $size = @filesize($abs) ?: 0;
        $w = 0;
        $h = 0;
        $dim = @getimagesize($abs);
        if ($dim) {
            $w = (int)$dim[0];
            $h = (int)$dim[1];
        }

        $matchedSubTypes = []; // Array of ['sub_type' => ..., 'match_source' => ...]

        // Check name match first
        $detectedSubTypes = AutoMediaPipeline::classifyAllBySubType($f, $subTypes);
        $nameMatches = [];
        foreach ($detectedSubTypes as $detectedSubType) {
            // Check if this subtype has defined ratio/dimensions
            $isAlbumOrGallery = (str_contains($detectedSubType, 'album') || str_contains($detectedSubType, 'gallery'));
            $rr = $isAlbumOrGallery ? null : ($subTypeRatios[$detectedSubType] ?? null);
            $ratioOk = true;
            if (is_array($rr) && (int)$rr['width'] > 0 && (int)$rr['height'] > 0) {
                // Check if the size is within reasonable bounds (40% to 500% of target)
                $minW = (int)$rr['width'] * 0.4;
                $maxW = (int)$rr['width'] * 5.0;
                // For very small targets (<= 50), cap max size to 150px to prevent large banners matching small types
                if ((int)$rr['width'] <= 50) {
                    $maxW = 150;
                }
                
                if ($w < $minW || $w > $maxW) {
                    $ratioOk = false;
                } else {
                    // For small icons (<= 50x50), we don't enforce strict ratio checking, but still check if it is roughly square if target is square
                    $isTargetSquare = (abs((int)$rr['width'] - (int)$rr['height']) <= 5);
                    $isImageSquare = ($w > 0 && $h > 0 && abs($w - $h) <= max(10, $w * 0.2));
                    
                    if ((int)$rr['width'] > 50 || (int)$rr['height'] > 50) {
                        $exactMatch = ($w === (int)$rr['width'] && $h === (int)$rr['height']);
                        $ratioMatch = AutoMediaPipeline::isRatioMatch($w, $h, (int)$rr['width'], (int)$rr['height'], 12);
                        if (!$exactMatch && !$ratioMatch) {
                            $ratioOk = false;
                        }
                    } elseif ($isTargetSquare && !$isImageSquare) {
                        $ratioOk = false;
                    }
                }
            }

            if ($ratioOk) {
                $nameMatches[] = $detectedSubType;
            }
        }

        if (!empty($nameMatches)) {
            if (count($nameMatches) > 1) {
                usort($nameMatches, function($a, $b) use ($subTypeRatios, $w) {
                    $rrA = $subTypeRatios[$a] ?? null;
                    $rrB = $subTypeRatios[$b] ?? null;
                    $diffA = $rrA ? abs($w - (int)$rrA['width']) : 9999;
                    $diffB = $rrB ? abs($w - (int)$rrB['width']) : 9999;
                    return $diffA <=> $diffB;
                });
            }
            
            $matchedSubTypes[] = [
                'sub_type' => $nameMatches[0],
                'match_source' => 'name',
            ];
        }

        // If not matched by name, check by ratio match with size-based bounds (50% - 500%)
        if (empty($matchedSubTypes)) {
            foreach ($subTypes as $subKey => $cfg) {
                $rr = $subTypeRatios[$subKey] ?? null;
                if (is_array($rr) && (int)$rr['width'] > 0 && (int)$rr['height'] > 0) {
                    $exactMatch = ($w === (int)$rr['width'] && $h === (int)$rr['height']);
                    $ratioMatch = AutoMediaPipeline::isRatioMatch($w, $h, (int)$rr['width'], (int)$rr['height'], 12);
                    if ($exactMatch || $ratioMatch) {
                        $minW = (int)$rr['width'] * 0.5;
                        $maxW = (int)$rr['width'] * 5.0;
                        if ($w >= $minW && $w <= $maxW) {
                            $matchedSubTypes[] = [
                                'sub_type' => $subKey,
                                'match_source' => 'ratio',
                            ];
                        }
                    }
                }
            }
        }

        // If no match found at all, skip the image
        if (empty($matchedSubTypes)) {
            continue;
        }

        $previewUrl = '/' . str_replace('\\', '/', trim($project['relPath'], '/'))
            . '/assets/images/images/' . rawurlencode($f);

        // Add to each matched subtype
        foreach ($matchedSubTypes as $match) {
            $subType = $match['sub_type'];
            $matchSource = $match['match_source'];

            $item = [
                'file' => $f,
                'sub_type' => $subType,
                'ext' => $ext,
                'size' => $size,
                'width' => $w,
                'height' => $h,
                'preview_url' => $previewUrl,
                'match_source' => $matchSource,
            ];

            $grouped[$subType][] = $item;
        }

        // Special rule: in type-photo, favicon group also scans any png/ico image containing 'logo' by default.
        if (
            $mainKey === 'type-photo'
            && isset($grouped['favicon'])
            && in_array($ext, ['png', 'ico'], true)
            && str_contains(strtolower($f), 'logo')
            && !in_array('favicon', array_column($matchedSubTypes, 'sub_type'), true)
        ) {
            $grouped['favicon'][] = [
                'file' => $f,
                'sub_type' => 'favicon',
                'ext' => $ext,
                'size' => $size,
                'width' => $w,
                'height' => $h,
                'preview_url' => $previewUrl,
                'match_source' => 'name',
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'main_key' => $mainKey,
        'project_name' => $projectName,
        'groups' => $grouped,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
