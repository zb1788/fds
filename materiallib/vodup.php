<?php
header('Access-Control-Allow-Origin:*');
require('./vodup.inc');
// require('./debug.php');
include("./mimetypes.inc");

$path = '';
if (isset($_POST[$realPath])) {
    $path = $_POST[$realPath];
    $path = iconv('UTF-8', 'GBK//TRANSLIT//IGNORE', $path);
}

$tmpPath = $savePath . '/' . $path;
if (!is_dir($tmpPath)) {
    $old = umask(0);
    if (!mkdir($tmpPath, 0777, true)) {
        umask($old);
        echo 6;
        exit;
    } else {
        chmod($tmpPath, 0777);
    }
    umask($old);
}

if (isset($_POST[$base64_str]) && $_POST[$base64_str] && isset($_POST[$base64_file]) && $_POST[$base64_file]) {
    $encodedData = $_POST[$base64_str];
    // $encodedData = str_replace(' ', '+', $encodedData);
    $decodedData = base64_decode($encodedData);
    if ($decodedData) {
        $destFileName = $_POST[$base64_file];
        $destFileName = iconv('UTF-8', 'GBK//TRANSLIT//IGNORE', $destFileName);
        $targetFile   = $tmpPath . '/' . $destFileName;
        $ifp = fopen($targetFile, "wb");
        fwrite($ifp, $decodedData);
        fclose($ifp);
        $fileBytes = filesize($targetFile);
        if ($fileBytes > 0) {
            chmod($targetFile, 0777);
            echo 0;
        } else {
            echo 5;
        }
    } else {
        echo 5;
    }
    exit;
}

if(!isset($_FILES[$fileName])) {
    exit;
}

$upload_err       = $_FILES[$fileName]['error'];
$file_source_name = $_FILES[$fileName]['name'];
$file_temp_name   = $_FILES[$fileName]['tmp_name'];
$file_type        = $_FILES[$fileName]['type'];
$file_size        = $_FILES[$fileName]['size'];

if ($file_size === 0) {
    echo 5;
    exit;
}

$file_src_name = $file_source_name;
$file_ext_name = explode(".", $file_src_name);
$file_ext_name = strtolower(end($file_ext_name));

if (isset($_POST['name']) && $_POST['name']) {
    $file_source_name = $_POST['name'];
}

$redis_merge_key_prefix = 'merge_file:';

function mergeChunking($filename, $flg) {
    global $redis_merge_key_prefix;
    try {
        if (class_exists('Redis', false)) {
            include("./redis_config.inc");
            $redis = new Redis();
            $redis->connect($redis_ip, $redis_port, $time_out_moren);
            if($redis_auth) $redis->auth($redis_auth);
            $redis->select($redis_db_index);
            $key = $redis_merge_key_prefix . $filename;
            if($flg == 0) {
                return $redis->get($key);
            }
            if($flg == 1) {
                $redis->set($key, 1);
                $redis->expire($key, 10);
            }
            if($flg == -1) {
                $redis->del($key);
            }
        }
    } catch(Exception $e) {}
}

function allChunks($filename, $chunks) {
    for($i = 0; $i < $chunks; $i++) {
        $chunk_file = $filename . '.' . $i;
        if(! file_exists($chunk_file)) {
            return false;
        }
    }
    return true;
}

function removeChunks($filename, $chunks) {
    for($i = 0; $i < $chunks; $i++) {
        $chunk_file = $filename . '.' . $i;
        // error_log('delete chunk == ' . $chunk_file);
        if(file_exists($chunk_file)) {
            unlink($chunk_file);
        }
    }
}

if ($upload_err === 0) {
    if ($fileSize > 0 && $file_size > $fileSize * 1024 * 1024) {
        echo 8;
        exit;
    }

    // $targetfilename = $tmpPath . '/' . $file_source_name;
    $targetfilename = $tmpPath . '/' . iconv('UTF-8', 'GBK//TRANSLIT//IGNORE', $file_source_name);
    
    // check file mime-type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileMimeType = finfo_file($finfo, $file_temp_name);
    finfo_close($finfo);
//error_log(iconv('UTF-8', 'GBK', $file_src_name) . ' == ' . $fileMimeType);
    $fileMimeType = strtolower($fileMimeType);
    if(! in_array($fileMimeType, $mimetypes_pass)) { // mimetype white-list
        $extAllow = false;
        $exts = array_keys($mimetypes, $fileMimeType);
        foreach($mimetypes_new as $key => $val) {
            if(in_array($fileMimeType, $val)) {
                $exts[] = $key;
            }
        }
        foreach($exts as $val) {
            if(in_array($val, $uploadAllowTypes)) {
                $extAllow = true;
                break;
            }
        }
        if(!$extAllow) {
            error_log($file_src_name . ' => ' . $fileMimeType);
            if($check_mimetype) {
                $cks = $_POST['chunks'] ?? 0;
                if($cks) {
                    removeChunks($targetfilename, $cks);
                }
                echo 11; // extend not allowed
                exit;
            }
        }

        if(!in_array($file_ext_name, $exts)) {
            error_log($file_src_name . ' => ' . $fileMimeType . ' => ' . $file_ext_name);
            if($check_mimetype) {
                $cks = $_POST['chunks'] ?? 0;
                if($cks) {
                    removeChunks($targetfilename, $cks);
                }
                echo 12; // extend not match mime-type
                exit;
            }
        }
    }

    if(isset($_POST['chunks']) && isset($_POST['chunk'])) {
        $chunks = intval($_POST['chunks']);
        $chunk = intval($_POST['chunk']);
        $totalSize = isset($_POST['size']) ? $_POST['size'] : 0;
        // error_log('chunks == ' . $chunks . ', chunk == ' . $chunk);
        $chunk_file = $targetfilename . '.' . $chunk;
        if(file_exists($chunk_file)) {
            unlink($chunk_file);
        }
        $saved = file_put_contents($chunk_file, file_get_contents($file_temp_name), FILE_APPEND);
        if($saved) {
            // error_log("saved chunk {$chunk}/{$chunks} {$file_size}/{$totalSize} {$targetfilename}");
            if(allChunks($targetfilename, $chunks)) {
                if(mergeChunking($targetfilename, 0)) {
                    echo 0;
                    exit;
                }
                mergeChunking($targetfilename, 1);
                // error_log("merge chunks......{$targetfilename}");
                if(file_exists($targetfilename)) {
                    unlink($targetfilename);
                }
                $merge = true;
                for($i = 0; $i < $chunks; $i++) {
                    $chunk_file = $targetfilename . '.' . $i;
                    if(file_exists($chunk_file)) {
                        $saved = file_put_contents($targetfilename, file_get_contents($chunk_file), FILE_APPEND);
                        if($saved) {
                            unlink($chunk_file);
                            // error_log("merged {$i}/{$chunks} {$targetfilename}");
                        } else {
                            // error_log("merge failed {$i}/{$chunks} {$targetfilename}");
                            $merge = false;
                            break;
                        }
                    } else {
                        // error_log("merge failed not found {$i}/{$chunks} {$targetfilename}");
                        $merge = false;
                        break;
                    }
                }
                $error_code = false;
                if($merge) {
                    chmod($targetfilename, 0777);
                    if($totalSize) {
                        // error_log('size = ' . filesize($targetfilename) . '/' . $totalSize);
                        if(filesize($targetfilename) != $totalSize) {
                            unlink($targetfilename);
                            $error_code = 10;
                            // echo 10;
                            // exit;
                        } else {
                            // error_log("merge success {$chunks} {$targetfilename}");
                        }
                    }
                } else {
                    removeChunks($targetfilename, $chunks);
                    $error_code = 9;
                    // echo 9;
                    // exit;
                }
                mergeChunking($targetfilename, -1);
                if($error_code) {
                    echo $error_code;
                    exit;
                }
            }
        } else {
            removeChunks($targetfilename, $chunks);
            echo 7;
            exit;
        }

        echo 0;
        exit;
    }

    if (move_uploaded_file($file_temp_name, $targetfilename)) {
        chmod($targetfilename, 0777);
        echo 0;
        exit;
    } else {
        echo 7;
        exit;
    }
} else {
    echo $upload_err;
    exit;
}