<?php
if (!function_exists('mkFolder')) {
    function mkFolder($path) {
        if (!is_readable($path)) {
            is_file($path) or mkdir($path, 0700, TRUE);
        }
    }
}

if (!function_exists('log2file')) {
    function log2file($msg = '') {
        $dest = __DIR__ . '/tmplog.php';
        if (!file_exists($dest)) {
            file_put_contents($dest, "<?php die('Access Defined!');?>\r\n", FILE_APPEND);
        }
        if (file_exists($dest)) {
            $time = date('Y-m-d H:i:s');
            if (is_array($msg)) {
                $myfp = fopen($dest, "a");
                fwrite($myfp, chr(10) . $time . ' = ' . var_export($msg, true) . ';' . chr(10));
                fclose($myfp);
            } else {
                file_put_contents($dest, $time . ' = ' . $msg . "\r\n", FILE_APPEND);
            }
        }
    }
}

if (!function_exists('getClientIp')) {
    function getClientIp() {
        if ($_SERVER["HTTP_X_FORWARDED_FOR"]) {
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } elseif ($_SERVER["HTTP_CLIENT_IP"]) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif ($_SERVER["REMOTE_ADDR"]) {
            $ip = $_SERVER["REMOTE_ADDR"];
        }
        if ($HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"]) {
            $ip = $HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"];
        } elseif ($HTTP_SERVER_VARS["HTTP_CLIENT_IP"]) {
            $ip = $HTTP_SERVER_VARS["HTTP_CLIENT_IP"];
        } elseif ($HTTP_SERVER_VARS["REMOTE_ADDR"]) {
            $ip = $HTTP_SERVER_VARS["REMOTE_ADDR"];
        } elseif (getenv("HTTP_X_FORWARDED_FOR")) {
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $ip = getenv("HTTP_CLIENT_IP");
        } elseif (getenv("REMOTE_ADDR")) {
            $ip = getenv("REMOTE_ADDR");
        } else {
            $ip = "";
        }
        return $ip;
    }
}