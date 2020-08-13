<?php
header("Content-type: text/html; charset=utf-8");

function log2file($str = NULL) {
	if($str) {
		$f = "/tmp/upfile.log";
        if(is_array($str)) {
			$myfp = fopen($f, "a");
			fwrite($myfp, chr(10).'$array='.var_export($str,true).';'.chr(10));
			fclose($myfp);
		} else {
			file_put_contents($f, date("Y-m-d H:i:s") . " " . $str ."\n\n", FILE_APPEND);
		}
	}
}

function getClientIP()
{
    $clientIp = "";

    if ($HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"]) {
        $clientIp = $HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"];
    } else if ($HTTP_SERVER_VARS["HTTP_CLIENT_IP"]) {
        $clientIp = $HTTP_SERVER_VARS["HTTP_CLIENT_IP"];
    } else if ($HTTP_SERVER_VARS["REMOTE_ADDR"]) {
        $clientIp = $HTTP_SERVER_VARS["REMOTE_ADDR"];
    } else if (getenv("HTTP_X_FORWARDED_FOR")) {
        $clientIp = getenv("HTTP_X_FORWARDED_FOR");
    } else if (getenv("HTTP_CLIENT_IP")) {
        $clientIp = getenv("HTTP_CLIENT_IP");
    } else if (getenv("REMOTE_ADDR")) {
        $clientIp = getenv("REMOTE_ADDR");
    }

    return $clientIp;
}

$authFlg = false;

// log2file($_POST);

if( isset($_POST["ut"]) && $_POST["ut"] ) {
    $ut = $_POST["ut"];

    $clientIP = getClientIP();
    if($clientIP) {
        $vodupKey = 'vodup.' . $clientIP;
        include("./redis_config.inc");
        include("./data_config.inc");
        try {
            $redis = new Redis();
            $redis->connect($redis_ip,$redis_port,$time_out);
            if($redis_auth) $redis->auth($redis_auth);
            $redis->select($redis_db_index);
            if( $redis->get($vodupKey) ) {
                $redis->setTimeout($vodupKey, 3600); // s
                $authFlg = true;
            } else {
                $ini_array = @parse_ini_file("/etc/vcom/yjtconfig.properties");
                $ssoip = $ini_array['SSO_IP'];
                $ssourl = 'http://'.$ssoip.'/sso/ssoGrant?isPortal=0&appFlg=BG&ut='.$ut;
// log2file($ssourl);
                include("./classes/Curl.class.php");
                $curl = new Curl();
                $result = @$curl->get($ssourl, 10);
// log2file($result);
                if($result) {
                    $result = iconv("GBK", "UTF-8//IGNORE", $result);
                    $resultArray = json_decode($result,true);
                    if($resultArray && $resultArray["authFlg"]) {
                        $redis->setex($vodupKey, 3600, $ut); // 1h
                        $authFlg = true;
                    }
                }
            }
        } catch(Exception $e) {
            $authFlg = true;
            // error_log($e->getMessage());
            error_log($e);
        }
    } else {
        $authFlg = true;
    }
}

if ( $authFlg === false ) {
    header("HTTP/1.1 403 Forbidden");
    header("Status: 403 Forbidden");
	exit;
}
