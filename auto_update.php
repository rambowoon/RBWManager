<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/core/ProjectScanner.php';
require_once __DIR__ . '/core/AutoMediaPipeline.php';

use RamboWoon\AutoMediaPipeline;
use RamboWoon\ProjectScanner;

function changeTitleSlug($text) {
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
}

header('Content-Type: application/json; charset=utf-8');

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new Exception('Payload không hợp lệ');
    }

    $mainKey = trim($payload['main_key'] ?? '');
    $projectName = trim($payload['project_name'] ?? '');
    $images = $payload['images'] ?? [];

    if ($mainKey === '' || $projectName === '') {
        throw new Exception('Thiếu main_key hoặc project_name');
    }
    if (!is_array($images) || count($images) === 0) {
        throw new Exception('Danh sách ảnh trống');
    }

    $baseDir = dirname(__DIR__);
    $scanner = new ProjectScanner($baseDir);
    $project = $scanner->getProjectByName($projectName);
    if (!$project || empty($project['path'])) {
        throw new Exception("Không tìm thấy dự án: {$projectName}");
    }

    $laravelBasePath = AutoMediaPipeline::normalizePath($project['path']);
    $mainCfg = AutoMediaPipeline::resolveMainConfig($project['path'], $mainKey);
    if (!$mainCfg) {
        throw new Exception("Không đọc được cấu hình type từ file config/{$mainKey}.php");
    }
    $subCfgs = $mainCfg['sub_types'];

    $pdo = AutoMediaPipeline::createPdoFromLaravelEnv($laravelBasePath);

    $projectImagesDir = AutoMediaPipeline::normalizePath($laravelBasePath . '/assets/images/images');
    if (!is_dir($projectImagesDir)) {
        throw new Exception("Không tìm thấy thư mục ảnh nguồn của dự án: {$projectImagesDir}");
    }

    $imagesBySub = [];
    $imageMeta = [];
    $selectedSourceFiles = [];
    foreach ($images as $item) {
        $file = is_array($item) ? trim((string)($item['file'] ?? '')) : trim((string)$item);
        $subType = is_array($item) ? trim((string)($item['sub_type'] ?? '')) : '';
        if ($file === '') {
            continue;
        }
        if ($subType === '') {
            $subType = AutoMediaPipeline::classifyBySubType($file, $subCfgs) ?? '';
        }
        if ($subType === '' || !isset($subCfgs[$subType])) {
            continue;
        }
        $src = $projectImagesDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($src)) {
            continue;
        }
        $imagesBySub[$subType][] = $file;
        $selectedSourceFiles[$file] = $src;
        if (!isset($imageMeta[$file])) {
            $dim = @getimagesize($src);
            $imageMeta[$file] = [
                'width' => $dim ? (int)$dim[0] : 0,
                'height' => $dim ? (int)$dim[1] : 0,
            ];
        }
    }

    if (empty($imagesBySub)) {
        throw new Exception('Không có ảnh hợp lệ để xử lý');
    }

    $targetDirNormalized = ltrim(str_replace('\\', '/', $mainCfg['target_dir']), '/');
    if (str_starts_with($targetDirNormalized, 'public/')) {
        $targetDirNormalized = substr($targetDirNormalized, 7);
    }

    $report = [
        'status' => 'success',
        'message' => 'Đã cập nhật xoay vòng và dọn rác thành công',
        'details' => [],
    ];

    $totalUpdated = 0;
    $totalInserted = 0;

    $pdo->beginTransaction();
    try {
        foreach ($imagesBySub as $subType => $files) {
            $cfg = $subCfgs[$subType];
            $table = $cfg['table'];
            $column = $cfg['column'] ?? 'photo';
            $typeColumn = $cfg['type_column'] ?? 'type';
            $typeValue = $cfg['type_value'] ?? $subType;
            AutoMediaPipeline::ensureColumnExists($pdo, $table, $column);
            AutoMediaPipeline::ensureColumnExists($pdo, $table, $typeColumn);
            if (count($files) === 0) {
                continue;
            }

            $isAlbumOrGallery = (str_contains($subType, 'album') || str_contains($subType, 'gallery'));
            $ratioCfg = $isAlbumOrGallery ? null : AutoMediaPipeline::getSubtypeRatio($project['path'], $mainKey, $subType);
            $usableFiles = $files;
            $ratioMatchedCount = 0;
            $ratioDebug = [];
            if ($ratioCfg) {
                $rw = (int)$ratioCfg['width'];
                $rh = (int)$ratioCfg['height'];
                $matched = [];
                foreach ($files as $f) {
                    $mw = (int)($imageMeta[$f]['width'] ?? 0);
                    $mh = (int)($imageMeta[$f]['height'] ?? 0);
                    $isLogoForFavicon = ($subType === 'favicon' && preg_match('/^logo\./i', $f));
                    $isMatch = $isLogoForFavicon || AutoMediaPipeline::isRatioMatch($mw, $mh, $rw, $rh, 20);
                    $ratioDebug[] = [
                        'file' => $f,
                        'image_size' => $mw . 'x' . $mh,
                        'target_ratio' => $rw . ':' . $rh,
                        'match' => $isMatch,
                    ];
                    if ($isMatch) {
                        $matched[] = $f;
                    }
                }
                $usableFiles = $matched;
                $ratioMatchedCount = count($matched);
            }

            $countImg = count($usableFiles);
            if ($countImg === 0) {
                $report['details'][$subType] = [
                    'table' => $table,
                    'type_filter' => $typeColumn . '=' . $typeValue,
                    'selected_images' => count($files),
                    'usable_images' => 0,
                    'ratio_matched_images' => $ratioMatchedCount,
                    'ratio_target' => $ratioCfg ? ($ratioCfg['width'] . ':' . $ratioCfg['height']) : null,
                    'updated_rows' => 0,
                    'deleted_old_files' => 0,
                    'copied_new_files' => 0,
                    'note' => $ratioCfg
                        ? 'Không có ảnh nào khớp tỷ lệ hình chính của type'
                        : 'Không đọc được tỷ lệ hình chính trong config type',
                    'ratio_debug' => $ratioDebug,
                ];
                continue;
            }

            $sql = "SELECT id, `{$column}` AS img_path FROM `{$table}` WHERE `{$typeColumn}` = :type_value ORDER BY id ASC";
            $stRows = $pdo->prepare($sql);
            $stRows->execute([':type_value' => $typeValue]);
            $rows = $stRows->fetchAll();

            $numRows = count($rows);
            $allowCreateNew = ($mainKey === 'type-photo' || $mainKey === 'type-news');
            $loopCount = ($allowCreateNew && $countImg > $numRows) ? $countImg : $numRows;

            $updated = 0;
            $inserted = 0;
            $deletedOld = 0;
            $copiedNew = 0;
            $deletedCandidates = []; // collect attempted deletion paths for debug
            $copyLog = []; // collect copy attempts for debug

            $configFilePath = AutoMediaPipeline::normalizePath($project['path'] . '/config/' . $mainKey . '.php');
            $configData = [];
            if (is_file($configFilePath)) {
                $configData = require $configFilePath;
            }
            $titleMain = $configData[$subType]['title_main'] ?? $subType;

            for ($i = 0; $i < $loopCount; $i++) {
                $isUpdate = ($i < $numRows);
                if ($isUpdate) {
                    $chosenFile = $usableFiles[$i % $countImg];
                    $row = $rows[$i];
                } else {
                    $chosenFile = $usableFiles[$i];
                    $row = null;
                }

                $sourcePath = $selectedSourceFiles[$chosenFile] ?? '';
                if ($sourcePath === '' || !is_file($sourcePath)) {
                    $copyLog[] = [
                        'record_id' => $isUpdate ? (int)$row['id'] : 'new',
                        'status' => 'source_not_found',
                        'source' => $sourcePath
                    ];
                    continue;
                }

                // Step 1: Copy new file first
                $today = date('Y/m/d');
                $publicPath = $laravelBasePath . DIRECTORY_SEPARATOR . 'public';
                if (is_dir($publicPath)) {
                    $destFolder = AutoMediaPipeline::normalizePath(
                        $publicPath . '/' . str_replace('/', DIRECTORY_SEPARATOR, $targetDirNormalized) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $today)
                    );
                } else {
                    $destFolder = AutoMediaPipeline::normalizePath(
                        $laravelBasePath . '/' . str_replace('/', DIRECTORY_SEPARATOR, $targetDirNormalized) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $today)
                    );
                }
                if (!is_dir($destFolder)) {
                    $mkdirOk = @mkdir($destFolder, 0777, true);
                    if (!$mkdirOk) {
                        $copyLog[] = [
                            'record_id' => $isUpdate ? (int)$row['id'] : 'new',
                            'status' => 'mkdir_failed',
                            'dest_folder' => $destFolder
                        ];
                        continue;
                    }
                }

                $ext = strtolower(pathinfo($chosenFile, PATHINFO_EXTENSION));
                $base = pathinfo($chosenFile, PATHINFO_FILENAME);
                $sanitizedBase = preg_replace('/[^a-zA-Z0-9_-]/', '', $base);
                $newName = $sanitizedBase . '_' . time() . '_' . substr(uniqid(), -6) . ($ext ? ".{$ext}" : '');
                $newAbsPath = $destFolder . DIRECTORY_SEPARATOR . $newName;
                
                $copyOk = @copy($sourcePath, $newAbsPath);
                if (!$copyOk) {
                    $copyLog[] = [
                        'record_id' => $isUpdate ? (int)$row['id'] : 'new',
                        'status' => 'copy_failed',
                        'source' => $sourcePath,
                        'dest' => $newAbsPath,
                        'dest_exists' => is_file($newAbsPath)
                    ];
                    continue;
                }
                $copiedNew++;
                $copyLog[] = [
                    'record_id' => $isUpdate ? (int)$row['id'] : 'new',
                    'status' => 'copy_ok',
                    'dest' => AutoMediaPipeline::toForwardSlash(str_replace('\\', '/', $newAbsPath))
                ];

                // Step 2: If copy successful, delete old file (update mode only)
                if ($isUpdate && $row) {
                    $oldRel = trim((string)$row['img_path']);
                    if ($oldRel !== '') {
                        if (!preg_match('#^https?://#i', $oldRel) && !str_starts_with($oldRel, '//')) {
                            $normalizedRel = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $oldRel), DIRECTORY_SEPARATOR);
                            $candidates = [];
                            $candidates[] = $laravelBasePath . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . $normalizedRel;
                            
                            foreach ($candidates as $candPath) {
                                $candNorm = AutoMediaPipeline::normalizePath((string)$candPath);
                                $exists = is_file($candNorm);
                                
                                if ($exists) {
                                    $deleted = false;
                                    try {
                                        $deleted = @unlink($candNorm);
                                    } catch (Throwable $e) {
                                        $deleted = false;
                                    }
                                    $deletedCandidates[] = ['record_id' => (int)$row['id'], 'path' => $candNorm, 'exists' => true, 'deleted' => (bool)$deleted];
                                    if ($deleted) {
                                        $deletedOld++;
                                        break;
                                    }
                                } else {
                                    $deletedCandidates[] = ['record_id' => (int)$row['id'], 'path' => $candNorm, 'exists' => false];
                                }
                            }
                        }
                    }
                }

                // Step 3: Update or Insert into DB
                $dbPath = $targetDirNormalized;
                if (str_starts_with($dbPath, 'upload/')) {
                    $dbPath = substr($dbPath, 7);
                }
                $newRel = AutoMediaPipeline::toForwardSlash($dbPath . '/' . $today . '/' . $newName);

                if ($isUpdate && $row) {
                    $st = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = :img WHERE id = :id");
                    $st->execute([
                        ':img' => $newRel,
                        ':id' => (int)$row['id'],
                    ]);
                    $updated++;
                } else {
                    if ($mainKey === 'type-photo') {
                        $kind = $configData[$subType]['kind'] ?? 'static';
                        $comVal = ($kind === 'album') ? 'photo-album' : 'photo-static';
                        $nameVi = $titleMain . ' ' . ($i + 1);

                        $st = $pdo->prepare("INSERT INTO `{$table}` (`{$column}`, `{$typeColumn}`, `com`, `namevi`, `status`, `numb`, `date_created`, `created_at`, `updated_at`) VALUES (:img, :type_val, :com, :namevi, 'hienthi', :numb, :date_created, :created_at, :updated_at)");
                        $st->execute([
                            ':img' => $newRel,
                            ':type_val' => $typeValue,
                            ':com' => $comVal,
                            ':namevi' => $nameVi,
                            ':numb' => $i + 1,
                            ':date_created' => time(),
                            ':created_at' => date('Y-m-d H:i:s'),
                            ':updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        $inserted++;
                    } else if ($mainKey === 'type-news') {
                        $nameVi = $titleMain . ' ' . ($i + 1);
                        $descVi = 'Mô tả tóm tắt cho bài viết: ' . $nameVi;
                        $hasSlug = !empty($configData[$subType]['slug']);
                        $slugVi = $hasSlug ? changeTitleSlug($nameVi) : null;

                        $st = $pdo->prepare("INSERT INTO `{$table}` (`{$column}`, `{$typeColumn}`, `namevi`, `descvi`, `slugvi`, `id_parent`, `status`, `numb`, `date_created`, `created_at`, `updated_at`) VALUES (:img, :type_val, :namevi, :descvi, :slugvi, 0, 'hienthi', :numb, :date_created, :created_at, :updated_at)");
                        $st->execute([
                            ':img' => $newRel,
                            ':type_val' => $typeValue,
                            ':namevi' => $nameVi,
                            ':descvi' => $descVi,
                            ':slugvi' => $slugVi,
                            ':numb' => $i + 1,
                            ':date_created' => time(),
                            ':created_at' => date('Y-m-d H:i:s'),
                            ':updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        $newId = $pdo->lastInsertId();

                        if ($hasSlug) {
                            // Insert into table_slug
                            $stSlug = $pdo->prepare("INSERT INTO `table_slug` (`slugvi`, `namevi`, `controller`, `model`, `id_parent`, `com`, `act`, `type`, `created_at`, `updated_at`) VALUES (:slugvi, :namevi, :controller, :model, :id_parent, :com, :act, :type, :created_at, :updated_at)");
                            $stSlug->execute([
                                ':slugvi' => $slugVi,
                                ':namevi' => $nameVi,
                                ':controller' => '\\NASANICORE\\Controllers\\Web\\NewsController',
                                ':model' => '\\NASANICORE\\Models\\NewsModel',
                                ':id_parent' => $newId,
                                ':com' => 'news',
                                ':act' => 'save',
                                ':type' => $typeValue,
                                ':created_at' => date('Y-m-d H:i:s'),
                                ':updated_at' => date('Y-m-d H:i:s'),
                            ]);
                        }
                        $inserted++;
                    }
                }
            }

            $report['details'][$subType] = [
                'table' => $table,
                'type_filter' => $typeColumn . '=' . $typeValue,
                'selected_images' => count($files),
                'usable_images' => $countImg,
                'ratio_matched_images' => $ratioMatchedCount,
                'ratio_target' => $ratioCfg ? ($ratioCfg['width'] . ':' . $ratioCfg['height']) : null,
                'updated_rows' => $updated,
                'inserted_rows' => $inserted,
                'deleted_old_files' => $deletedOld,
                'copied_new_files' => $copiedNew,
                'ratio_debug' => $ratioDebug,
                'copy_log' => $copyLog,  // DEBUG: show all copy attempts
                'deletion_debug' => $deletedCandidates,  // DEBUG: show all deletion attempts
            ];
            $totalUpdated += $updated;
            $totalInserted += $inserted;
        }

        if ($totalInserted > 0 && $totalUpdated > 0) {
            $report['message'] = "Đã cập nhật xoay vòng {$totalUpdated} hình và tạo mới {$totalInserted} hình thành công";
        } elseif ($totalInserted > 0) {
            $report['message'] = "Đã tạo mới {$totalInserted} hình thành công";
        } elseif ($totalUpdated > 0) {
            $report['message'] = "Đã cập nhật xoay vòng {$totalUpdated} hình thành công";
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Keep source images in assets/images/images.
    // Garbage cleanup here means replacing old DB-linked media by record update flow,
    // not deleting the source pool files.

    echo json_encode($report);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
