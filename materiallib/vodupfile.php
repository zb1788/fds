<?php
header('Access-Control-Allow-Origin:*');
include("./auth.php");
include("./vodup.inc");
include("./mimetypes.inc");

$path = "";
if(isset($_POST[$realPath])) {
    $path = $_POST[$realPath];
    $path = iconv("UTF-8","GBK//TRANSLIT//IGNORE",$path);
}

$upload_err        = $_FILES[$fileName]["error"];
$file_source_name  = $_FILES[$fileName]["name"];
$file_temp_name    = $_FILES[$fileName]["tmp_name"];
$file_type         = $_FILES[$fileName]["type"];
$file_size         = $_FILES[$fileName]["size"];

if($file_size == 0) $upload_err = 5;

$file_src_name = $file_source_name;
$file_ext_name = explode(".", $file_src_name);
$file_ext_name = strtolower(end($file_ext_name));

if($upload_err == 0) {
    if($fileSize > 0 && $file_size > $fileSize*1024*1024) {
        echo 8;
        exit;
    }

    // check file mime-type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileMimeType = finfo_file($finfo, $file_temp_name);
    finfo_close($finfo);
//error_log($file_source_name . ' == ' . $fileMimeType);
    $fileMimeType = strtolower($fileMimeType);
    if(! in_array($fileMimeType, $mimetypes_pass)) {
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
                echo 11; // extend not allowed
                exit;
            }
        }

        if(!in_array($file_ext_name, $exts)) {
            error_log($file_src_name . ' => ' . $fileMimeType . ' => ' . $file_ext_name);
            if($check_mimetype) {
                echo 12; // extend not match mime-type
                exit;
            }
        }
    }

    $tmpPath = $savePath . "/" . $path;
    if(!is_dir($tmpPath)) {
        $old = umask(0);
        if(!mkdir($tmpPath, 0777, true)) {
            umask($old);
            echo 6;
            exit;
        } else {
            chmod($tmpPath,0777);
        }
        umask($old);
	}

    // $targetfilename = $tmpPath . "/" . $file_source_name;
	$targetfilename = $tmpPath . "/" . iconv("UTF-8","GBK//TRANSLIT//IGNORE",$file_source_name);
    if(move_uploaded_file($file_temp_name, $targetfilename)) {
        chmod($targetfilename,0777);
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