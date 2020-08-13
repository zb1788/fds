<?php
/**
ischeck == true
0 - 正常（校验通过）
1 - 非法访问（验证码不正确）
2 - 文件不存在
3 - 非法访问（rcode不正确）
4 - 过期访问
5 - 非法访问（验证码不正确）
6 - 不允许下载（黑名单）
7 - 下载超过限制
**/
session_start();
include("mimetypes.inc");
include("downType.inc");
include("rewriteType.inc");
include("time_diff_valve_cfg.inc");
include("file_lib_root.inc");
include("des3/Mcrypt3Des.php");

function get_ip() {
    $arr = array('HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
    if(isset($_SERVER)) {
        foreach($arr as $key) {
            if(array_key_exists($key, $_SERVER)) {
                if($_SERVER[$key]) {
                    return $_SERVER[$key];
                }
            }
        }
    } else {
        foreach($arr as $key) {
            if(getenv($key)) {
                return getenv($key);
            }
        }
    }

    return null;
}

$ischeck = $_GET['ischeck'] ?? 0;

$need_redis_check = false;
if(isset($_GET["username"]) && $_GET["username"]) {
    $need_redis_check = true;
}

// 教师个人文件夹不限制访问次数
$filedir_pass = array('preparedir', 'teacherdir', 'jspkdir');
$filedir = $_GET['filedir'] ?? '';
if($filedir && in_array($filedir, $filedir_pass)) {
    $need_redis_check = false;
}

$startTime = date('YmdHis');
$clientIp  = "";

if(isset($_GET["CLIENTIP"]) && $_GET["CLIENTIP"]) {
    $clientIp = $_GET["CLIENTIP"];
} else {
    $clientIp = get_ip();
}

setlocale(LC_ALL, 'zh_CN.GBK');

$validateCode = $_GET["validate_code"] ?? '';
$userName     = $_GET["username"] ?? '';
//echo $validateCode;
//调用方法解密
//$validateCode = $mcrypt3Des->decrypt($validateCode);
$validateCode = $mcrypt3Des->des_decrypt($validateCode);

//echo $validateCode;
$temparray    = explode("|", $validateCode);
if (count($temparray) == 3) {
    $validateCode = $temparray[0];
    $tempusername = $temparray[1];
    $tempip       = $temparray[2];
    if ($userName != $tempusername || $clientIp != $tempip) {
        echo $ischeck ? 1 : "非法请求";
        exit;
    }
}
$file_path   = $_GET["filepath"] ?? ''; //相对路径
//echo $webroot;
//echo $file_path;
$rcode       = $_GET["rcode"] ?? '';
$rcodeLength = strlen($rcode);
//echo $rcode;
//$rcode       = $mcrypt3Des->decrypt($rcode);
$rcode       = $mcrypt3Des->des_decrypt($rcode);


// echo $rcode;
//echo $rcodeLength;

/*****验证文件是否存在******/
$file_name     = $fileLibRoot . $file_path; //物理路径
//echo $file_name;
$file_basename = basename($file_name); //文件名
//echo basename($file_name) ;//文件名
$fileinfo      = pathinfo($file_name); //文件信息数组
$fileExtname   = strtolower($fileinfo['extension']); //文件扩展名

//echo "<br>ext:";
//echo $fileExtname;
$fileMimeType = array_key_exists($fileExtname, $mimetypes) ? $mimetypes[$fileExtname] : 'application/octet-stream';
$fileDownType = array_key_exists($fileExtname, $downtypes) ? $downtypes[$fileExtname] : '';
//$fileDownType = $downtypes[$fileExtname];
//header($fileMimeType); 
//echo 'Content-type: '.$fileMimeType;
//echo $fileMimeType;
//echo "<br>";
$file_exist   = 0;
// for($i=0;$i<count($fileLib);$i++) {
// $file_path=str_replace('material',$fileLib[$i],$file_path);
$file_name    = $fileLibRoot . $file_path;
if (file_exists($file_name)) {
    $file_exist = 1;
    // break;
}
// }

if ($file_exist == 0) {
    //header('HTTP/1.1 404 Not Found');
    //header("status: 404 Not Found");
    echo $ischeck ? 2 : "该文件不存在";
    exit;
}

// 判断子文件是否存在
if($ischeck) {
    $subfiles = $_GET["subfile"] ?? '';
    if($subfiles) {
        $subfiles = explode(";", $subfiles);
        foreach($subfiles as $subfile) {
            $subfile = urldecode($subfile);
            $subfile = iconv("UTF-8", "GBK//IGNORE", $subfile);
            $subfile_path = $fileLibRoot . $subfile;
            if (! file_exists($subfile_path)) {
                echo 2;
                exit;
            }
        }
    }
}

/*****验证文件是否存在******/

/*****验证是否合法访问******/

$file_path_array = explode("/", $file_path);

if ($rcode == null || $rcodeLength == 0) {
    //echo $file_path;
    
    if (isset($_SESSION['rcodes'])) {
        //print_r($_SESSION['rcodes']);
        //print_r($file_path_array);
        $intersect = array_intersect($_SESSION['rcodes'], $file_path_array);
        //print_r($intersect);
        //echo count($intersect);
        if (count($intersect) == 0) {
            //header("http/1.1 403 Forbidden"); 
            //header("status: 403 Forbidden"); 
            echo $ischeck ? 3 : "无rcode的非法访问";
            exit;
        }
    } else {
        //header("http/1.1 403 Forbidden"); 
        //header("status: 403 Forbidden"); 
        echo $ischeck ? 3 : "无rcode的非法访问";
        exit;
    }
    
} else {
    // if(!strpos($file_path,'/'.$rcode.'/'))
    if (false) {
        //header("http/1.1 403 Forbidden"); 
        //header("status: 403 Forbidden"); 
        echo "rcode错误的非法访问";
        exit;
    } else {
        //echo "zhuce";
        if (! isset($_SESSION['rcodes'])) {
            //echo "未注册";
            $_SESSION['rcodes'] = array();
        }
        
        if ($validateCode != null && strlen($rcode) != 0 && is_numeric($validateCode)) {
            //echo "验证码合法"; 
            $nowCode  = time();
            //echo $nowCode;echo "-";echo $validateCode;echo "=";
            $codeDiff = $nowCode - $validateCode;
            //echo $codeDiff;
            if ($codeDiff > $time_diff_valve) {
                //print_r($_SESSION['rcodes']);
                //print_r($file_path_array);
                $intersect = array_intersect($_SESSION['rcodes'], $file_path_array);
                //print_r($intersect);
                //echo count($intersect);
                if (count($intersect) == 0) {
                    //header("http/1.1 403 Forbidden"); 
                    //header("status: 403 Forbidden"); 
                    echo $ischeck ? 4 : "已过期的验证码非法访问";
                    exit;
                }
            } else {
                //print_r($_SESSION['rcodes']);
                //echo $rcode;
                if (!in_array($rcode, $_SESSION['rcodes'])) {
                    //echo "注册资源编码";
                    $rescodesCount                      = count($_SESSION['rcodes']);
                    $_SESSION['rcodes'][$rescodesCount] = $rcode;
                    //echo $rescodesCount;
                }
                //print_r($_SESSION['rcodes']);
            }
        } else {
            //header("http/1.1 403 Forbidden"); 
            //header("status: 403 Forbidden"); 
            echo $ischeck ? 5 : "验证码非法";
            exit;
        }
    }
}


$actionid = $_GET["ad"] ?? '';
if ($actionid == null || $actionid == '') {
    $actionTime = date('YmdHisHis');
    $actionid   = $userName . $actionTime . '_0';
}

$is_img = strpos($fileMimeType, 'image/') === 0;

if($fileExtname != 'xml') {
    if($need_redis_check && $clientIp != '127.0.0.1' && !$is_img) {
        try {
            if (class_exists('Redis', false)) {
                include("redis.php");
                include("redis_config.inc");
                include("data_config.inc");
                if ($check_flag == "1") {
                    $redis = new Redis();
                    $redis->connect($redis_ip, $redis_port, $time_out);
                    if($redis_auth) $redis->auth($redis_auth);
                    $redis->select($redis_db_index);
                    if($ischeck) {
                        $checkresult = checkfile($userName, $username_num_down_max, $clientIp, $userip_num_down_max, $redis, $rcode, $expire_time, $actionid);
                    } else {
                        $checkresult = checkdown($userName, $username_num_down_max, $clientIp, $userip_num_down_max, $redis, $rcode, $expire_time, $actionid);
                    }
                    if ($checkresult == "2") {
                        echo $ischeck ? 6 : "不允许下载";
                        exit;
                    }
                    if ($checkresult == "3") {
                        echo $ischeck ? 7 : "下载超过限制";
                        exit;
                    }
                }
            }
        } catch(Exception $e) {}
    }

    if($ischeck) {
        echo 0;
        exit;
    }
    
    $filesize = filesize($file_name);
    $log_dir  = "/home/logs/";
    $log_date = date('Ymd');
    $log_file = $log_dir . $log_date . ".log";

    $time = date('YmdHis');

    // $file_path=substr($file_path,19);

    $channelId = 'pc';
    if(isset($_GET['cd']) && $_GET['cd']) {
        $channelId = $_GET['cd'];
    } else {
        include('./classes/Mobile_Detect.php');
        $detect = new Mobile_Detect();
        if($detect->isMobile()) {
            $channelId = 'mobile';
        } else if($detect->isTablet()) {
            $channelId = 'pad';
        }
    }
    /**
    $channelId = $_GET["cd"];
    if ($channelId == null || $channelId == '') {
        $channelId = $time;
    }
    **/
    $serviceType = 'file_down';
    $prgid       = $rcode;
    $contentType = 0;
    if (isset($_GET["ct"])) {
        $contentType = $_GET["ct"];
    }
    $endTime    = date('YmdHis');
    $action     = 'view';
    $bytesCount = $filesize;
    $log        = $userName . '|' . $actionid . '|' . $file_path . '|' . $channelId . '|' . $serviceType . '|' . $prgid . '|' . $contentType . '|' . $startTime . '|' . $endTime . '|' . $action . '|' . $bytesCount . '|' . $clientIp;
    $log .= "\r\n";
    //if (in_array("material", $file_path_array)) {
        $handle = fopen($log_file, "a");
        fwrite($handle, $log);
        fclose($handle);
    //}
}

if($ischeck) {
    echo 0;
    exit;
}

if (isset($_GET["title"]) && $_GET["title"]) {
    $file_basename = $_GET["title"];
    $file_basename = urldecode($file_basename);
    $file_basename = iconv("UTF-8", "GBK//IGNORE", $file_basename);
}

header('Pragma: public');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: pre-check=0, post-check=0, max-age=0');
header('Content-Transfer-Encoding: binary');
header('Content-Encoding: none');
header('Content-type: ' . $fileMimeType . ';charset=gbk');

if ($contentType)
    $fileDownType = 'attachment';
!empty($fileDownType) || $fileDownType = 'attachment';

$ua = $_SERVER["HTTP_USER_AGENT"];
if (preg_match("/Firefox|Edge/", $ua)) {
    header('Content-Disposition: ' . $fileDownType . '; filename*="gbk\'\'' . $file_basename . '"');
} else if(preg_match("/MicroMessenger/", $ua) || preg_match("/MQQBrowser/", $ua)) {
    header('Content-Disposition: ' . $fileDownType . '; filename*="gbk\'\'' . $file_basename . '"');
} else {
    header('Content-Disposition: ' . $fileDownType . '; filename="' . $file_basename . '"');
}

header('Content-length: ' . $filesize);

header('accept-ranges: bytes');

header("X-Sendfile: " . $file_name);