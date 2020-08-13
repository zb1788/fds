<?php

/**
 * @desc 检查下载的用户账号和ip是否允许下载
 * $username:用户账号
 * $username_num：用户账号最大下载次数
 * $userip：客户端ip
 * $userip_num：ip最大下载次数
 * $redis：redis连接
 * $rcode：资源唯一标志
 * $expire_time ：资源下载最大延迟时间
 * 
 * return：1为允许下载，2为不允许下载,3为下载超过限制
 * 1、先检查是否是下载资源的辅助文件  
 * 2、检查是否在黑名单和黑ip中，如果在则不允许下载
 * 3、检查是否在白名单和白ip中，如果在则允许下载
 * 4、检查用户账号是否超过最大下载次数：没有超过，则允许下载，并增加下载次数；超过了则不允许下载，并增加下载次数,并更新下载次数对应账号信息
 * 5、检查用户ip是否超过最大下载次数：没有超过，则允许下载，并增加下载次数；超过了则不允许下载，并增加下载次数,并更新下载次数对应ip信息
 * 
 */
function checkdown($username, $username_num, $userip, $userip_num, $redis, $rcode, $expire_time, $actionid)
{
    $whitelist_username        = "whitelist_username"; //用户白名单键
    $blacklist_username        = "blacklist_username"; //用户黑名单键
    $whitelist_userip          = "whitelist_userip"; //ip白名单键
    $blacklist_userip          = "blacklist_userip"; //ip黑名单键
    $templimitusernameKey      = "limitusername";
    $templimituseripKey        = "limituserip";
    $max_num_down_username     = "max_num_down_username"; //追踪用户最大下载次数 
    $max_num_down_userip       = "max_num_down_userip"; //追踪ip最大下载次数
    $max_num_down_username_key = "max_num_down_username_"; //下载次数对应用户账号记录键
    $max_num_down_userip_key   = "max_num_down_userip_"; //下载次数对应用户ip记录键

    // 记录下载次数
    $today = date('Ymd');
    $today_expire = 2592000; // 60*60*24*30
    if($username) {
        $k = $today . '_username';
        $redis->zIncrBy($k, 1, $username);
        $redis->expire($k, $today_expire);
    }
    if($userip) {
        $k = $today . '_userip';
        $redis->zIncrBy($k, 1, $userip);
        $redis->expire($k, $today_expire);
    }

    //检查用户下载的资源是否已经过期，不过期可以下载
    $username_rcode_down_key   = "username_rcode_down_" . $username . "_" . $rcode;
    $isdownflag                = $redis->get($username_rcode_down_key);
    if ($isdownflag == "1") {
        return "1";
    }
    
    //检查黑名单
    $blacklist_username_info = $redis->get($blacklist_username);
    $temparray               = explode("|" . $username . "|", $blacklist_username_info);
    if (count($temparray) > 1) {
        //loginfo($redis,"noviewlog","black_username blacklist_username is ".$blacklist_username_info." username is ".$username);
        return "2";
    }
    //检查黑ip
    $blacklist_userip_info = $redis->get($blacklist_userip);
    $temparray             = explode("|" . $userip . "|", $blacklist_userip_info);
    if (count($temparray) > 1) {
        //loginfo($redis,"noviewlog","black_userip blacklist_userip is ".$blacklist_userip_info." username is ".$userip);
        return "2";
    }
    //检查白名单
    $whitelist_username_info = $redis->get($whitelist_username);
    $temparray               = explode("|" . $username . "|", $whitelist_username_info);
    if (count($temparray) > 1) {
        $redis->set($username_rcode_down_key, "1");
        $redis->expire($username_rcode_down_key, $expire_time);
        return "1";
    }
    //检查白ip
    $whitelist_userip_info = $redis->get($whitelist_userip);
    $temparray             = explode("|" . $userip . "|", $whitelist_userip_info);
    if (count($temparray) > 1) {
        $redis->set($username_rcode_down_key, "1");
        $redis->expire($username_rcode_down_key, $expire_time);
        return "1";
    }
    
    $actionid_key   = "username_action_" . $actionid;
    $actionid_value = $redis->get($actionid_key);
    //$actionid_value的值只作用于计算超过下载限制，当$actionid_value有值时，表示此rcode资源已经被禁止下载了，不需要计算下载次数
    //当$actionid_value不为空时，返回上次为1时，
    if ($actionid_value == "1") {
        return "3";
    }
    
    // $username_ip_expire = strtotime("today") + 60*60*24; // 2018-07-02 add
    //检查用户账号是否超过最大下载次数：没有超过，则允许下载，并增加下载次数；超过了则不允许下载，并增加下载次数
    $usernameflag     = "1";
    $tempusernameKey  = "down_username_" . $username;
    $usernamedowninfo = $redis->get($tempusernameKey);
    if ($usernamedowninfo != "") {
        $usernamedowninfo++;
        if ($usernamedowninfo <= $username_num) {
            $redis->set($tempusernameKey, $usernamedowninfo);
        } else {
            $max_num_down_username_value = $redis->get($max_num_down_username);
            if ($max_num_down_username_value != "") {
                if ($usernamedowninfo >= ($max_num_down_username_value + 1)) {
                    $redis->set($max_num_down_username, $usernamedowninfo);
                }
                if (($usernamedowninfo - 1) > $username_num) {
                    $tempinfovalue = $redis->get($max_num_down_username_key . ($usernamedowninfo - 1));
                    $redis->set($max_num_down_username_key . ($usernamedowninfo - 1), str_replace(";" . $username . ";", ";", $tempinfovalue));
                }
                $tempinfovalue = $redis->get($max_num_down_username_key . $usernamedowninfo);
                if ($tempinfovalue == "") {
                    $tempinfovalue = ";";
                }
                $redis->set($max_num_down_username_key . $usernamedowninfo, $tempinfovalue . $username . ";");
            } else {
                $redis->set($max_num_down_username, $usernamedowninfo);
                $redis->set($max_num_down_username_key . $usernamedowninfo, ";" . $username . ";");
            }
            //loginfo($redis,"noviewlog","limit view  the username down num(+1)  is ".$usernamedowninfo." username level is ".$username_num." max_num_down_username_value is ".$max_num_down_username_value);
            $usernameflag = "2";
        }
    } else {
        $usernamedowninfo = "1";
        $redis->set($tempusernameKey, $usernamedowninfo);
    }
    $useripflag     = "1";
    $tempuseripKey  = "down_userip_" . $userip;
    $useripdowninfo = $redis->get($tempuseripKey);
    if ($useripdowninfo != "") {
        $useripdowninfo++;
        if ($useripdowninfo <= $userip_num) {
            $redis->set($tempuseripKey, $useripdowninfo);
        } else {
            $max_num_down_userip_value = $redis->get($max_num_down_userip);
            if ($max_num_down_userip_value != "") {
                if ($useripdowninfo >= ($max_num_down_userip_value + 1)) {
                    $redis->set($max_num_down_userip, $useripdowninfo);
                }
                if (($useripdowninfo - 1) > $userip_num) {
                    $tempinfovalue = $redis->get($max_num_down_userip_key . ($useripdowninfo - 1));
                    $redis->set($max_num_down_userip_key . ($useripdowninfo - 1), str_replace(";" . $userip . ";", ";", $tempinfovalue));
                }
                $tempinfovalue = $redis->get($max_num_down_userip_key . $useripdowninfo);
                if ($tempinfovalue == "") {
                    $tempinfovalue = ";";
                }
                $redis->set($max_num_down_userip_key . $useripdowninfo, $tempinfovalue . $userip . ";");
            } else {
                $redis->set($max_num_down_userip, $useripdowninfo);
                $redis->set($max_num_down_userip_key . $useripdowninfo, ";" . $userip . ";");
            }
            //loginfo($redis,"noviewlog","limit view  the userip down num(+1)  is ".$useripdowninfo." userip level is ".$userip_num." max_num_down_userip_value is ".$max_num_down_userip_value);
            $useripflag = "2";
        }
    } else {
        $useripdowninfo = "1";
        $redis->set($tempuseripKey, $useripdowninfo);
    }
    if ($usernameflag == "2" || $useripflag == "2") {
        $redis->set($actionid_key, "1");
        $redis->expire($actionid_key, $expire_time);
        return "3";
    }
    $redis->set($username_rcode_down_key, "1");
    $redis->expire($username_rcode_down_key, $expire_time);

    return "1";
}

function loginfo($redis, $key, $value)
{
    $strlod = $redis->get($key);
    $redis->set($key, $value . "_发生时间为：" . date("Y-m-d H:i:s") . "       " . $strlod);
}

function checkfile($username, $username_num, $userip, $userip_num, $redis, $rcode, $expire_time, $actionid)
{
    $whitelist_username        = "whitelist_username"; //用户白名单键
    $blacklist_username        = "blacklist_username"; //用户黑名单键
    $whitelist_userip          = "whitelist_userip"; //ip白名单键
    $blacklist_userip          = "blacklist_userip"; //ip黑名单键
    $templimitusernameKey      = "limitusername";
    $templimituseripKey        = "limituserip";
    $max_num_down_username     = "max_num_down_username"; //追踪用户最大下载次数 
    $max_num_down_userip       = "max_num_down_userip"; //追踪ip最大下载次数
    $max_num_down_username_key = "max_num_down_username_"; //下载次数对应用户账号记录键
    $max_num_down_userip_key   = "max_num_down_userip_"; //下载次数对应用户ip记录键
    //检查用户下载的资源是否已经过期，不过期可以下载
    $username_rcode_down_key   = "username_rcode_down_" . $username . "_" . $rcode;
    $isdownflag                = $redis->get($username_rcode_down_key);
    if ($isdownflag == "1") {
        return "1";
    }
    
    //检查黑名单
    $blacklist_username_info = $redis->get($blacklist_username);
    $temparray               = explode("|" . $username . "|", $blacklist_username_info);
    if (count($temparray) > 1) {
        //loginfo($redis,"noviewlog","black_username blacklist_username is ".$blacklist_username_info." username is ".$username);
        return "2";
    }
    //检查黑ip
    $blacklist_userip_info = $redis->get($blacklist_userip);
    $temparray             = explode("|" . $userip . "|", $blacklist_userip_info);
    if (count($temparray) > 1) {
        //loginfo($redis,"noviewlog","black_userip blacklist_userip is ".$blacklist_userip_info." username is ".$userip);
        return "2";
    }
    //检查白名单
    $whitelist_username_info = $redis->get($whitelist_username);
    $temparray               = explode("|" . $username . "|", $whitelist_username_info);
    if (count($temparray) > 1) {
        return "1";
    }
    //检查白ip
    $whitelist_userip_info = $redis->get($whitelist_userip);
    $temparray             = explode("|" . $userip . "|", $whitelist_userip_info);
    if (count($temparray) > 1) {
        return "1";
    }
    
    $actionid_key   = "username_action_" . $actionid;
    $actionid_value = $redis->get($actionid_key);
    //$actionid_value的值只作用于计算超过下载限制，当$actionid_value有值时，表示此rcode资源已经被禁止下载了，不需要计算下载次数
    //当$actionid_value不为空时，返回上次为1时，
    if ($actionid_value == "1") {
        return "3";
    }
    
    // $username_ip_expire = strtotime("today") + 60*60*24; // 2018-07-02 add
    //检查用户账号是否超过最大下载次数：没有超过，则允许下载，并增加下载次数；超过了则不允许下载，并增加下载次数
    $usernameflag     = "1";
    $tempusernameKey  = "down_username_" . $username;
    $usernamedowninfo = $redis->get($tempusernameKey);
    if ($usernamedowninfo != "") {
        $usernamedowninfo++;
        if ($usernamedowninfo <= $username_num) {
        } else {
            $usernameflag = "2";
        }
    }
    
    $useripflag     = "1";
    $tempuseripKey  = "down_userip_" . $userip;
    $useripdowninfo = $redis->get($tempuseripKey);
    if ($useripdowninfo != "") {
        $useripdowninfo++;
        if ($useripdowninfo <= $userip_num) {
        } else {
            $useripflag = "2";
        }
    }
    
    if ($usernameflag == "2" || $useripflag == "2") {
        $redis->set($actionid_key, "1");
        $redis->expire($actionid_key, $expire_time);
        return "3";
    }

    return "1";
}