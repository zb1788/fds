<?php

/**
 * @desc ������ص��û��˺ź�ip�Ƿ���������
 * $username:�û��˺�
 * $username_num���û��˺�������ش���
 * $userip���ͻ���ip
 * $userip_num��ip������ش���
 * $redis��redis����
 * $rcode����ԴΨһ��־
 * $expire_time ����Դ��������ӳ�ʱ��
 * 
 * return��1Ϊ�������أ�2Ϊ����������,3Ϊ���س�������
 * 1���ȼ���Ƿ���������Դ�ĸ����ļ�  
 * 2������Ƿ��ں������ͺ�ip�У����������������
 * 3������Ƿ��ڰ������Ͱ�ip�У����������������
 * 4������û��˺��Ƿ񳬹�������ش�����û�г��������������أ����������ش��������������������أ����������ش���,���������ش�����Ӧ�˺���Ϣ
 * 5������û�ip�Ƿ񳬹�������ش�����û�г��������������أ����������ش��������������������أ����������ش���,���������ش�����Ӧip��Ϣ
 * 
 */
function checkdown($username, $username_num, $userip, $userip_num, $redis, $rcode, $expire_time, $actionid)
{
    $whitelist_username        = "whitelist_username"; //�û���������
    $blacklist_username        = "blacklist_username"; //�û���������
    $whitelist_userip          = "whitelist_userip"; //ip��������
    $blacklist_userip          = "blacklist_userip"; //ip��������
    $templimitusernameKey      = "limitusername";
    $templimituseripKey        = "limituserip";
    $max_num_down_username     = "max_num_down_username"; //׷���û�������ش��� 
    $max_num_down_userip       = "max_num_down_userip"; //׷��ip������ش���
    $max_num_down_username_key = "max_num_down_username_"; //���ش�����Ӧ�û��˺ż�¼��
    $max_num_down_userip_key   = "max_num_down_userip_"; //���ش�����Ӧ�û�ip��¼��

    // ��¼���ش���
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

    //����û����ص���Դ�Ƿ��Ѿ����ڣ������ڿ�������
    $username_rcode_down_key   = "username_rcode_down_" . $username . "_" . $rcode;
    $isdownflag                = $redis->get($username_rcode_down_key);
    if ($isdownflag == "1") {
        return "1";
    }
    
    //��������
    $blacklist_username_info = $redis->get($blacklist_username);
    $temparray               = explode("|" . $username . "|", $blacklist_username_info);
    if (count($temparray) > 1) {
        //loginfo($redis,"noviewlog","black_username blacklist_username is ".$blacklist_username_info." username is ".$username);
        return "2";
    }
    //����ip
    $blacklist_userip_info = $redis->get($blacklist_userip);
    $temparray             = explode("|" . $userip . "|", $blacklist_userip_info);
    if (count($temparray) > 1) {
        //loginfo($redis,"noviewlog","black_userip blacklist_userip is ".$blacklist_userip_info." username is ".$userip);
        return "2";
    }
    //��������
    $whitelist_username_info = $redis->get($whitelist_username);
    $temparray               = explode("|" . $username . "|", $whitelist_username_info);
    if (count($temparray) > 1) {
        $redis->set($username_rcode_down_key, "1");
        $redis->expire($username_rcode_down_key, $expire_time);
        return "1";
    }
    //����ip
    $whitelist_userip_info = $redis->get($whitelist_userip);
    $temparray             = explode("|" . $userip . "|", $whitelist_userip_info);
    if (count($temparray) > 1) {
        $redis->set($username_rcode_down_key, "1");
        $redis->expire($username_rcode_down_key, $expire_time);
        return "1";
    }
    
    $actionid_key   = "username_action_" . $actionid;
    $actionid_value = $redis->get($actionid_key);
    //$actionid_value��ֵֻ�����ڼ��㳬���������ƣ���$actionid_value��ֵʱ����ʾ��rcode��Դ�Ѿ�����ֹ�����ˣ�����Ҫ�������ش���
    //��$actionid_value��Ϊ��ʱ�������ϴ�Ϊ1ʱ��
    if ($actionid_value == "1") {
        return "3";
    }
    
    // $username_ip_expire = strtotime("today") + 60*60*24; // 2018-07-02 add
    //����û��˺��Ƿ񳬹�������ش�����û�г��������������أ����������ش��������������������أ����������ش���
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
    $redis->set($key, $value . "_����ʱ��Ϊ��" . date("Y-m-d H:i:s") . "       " . $strlod);
}

function checkfile($username, $username_num, $userip, $userip_num, $redis, $rcode, $expire_time, $actionid)
{
    $whitelist_username        = "whitelist_username"; //�û���������
    $blacklist_username        = "blacklist_username"; //�û���������
    $whitelist_userip          = "whitelist_userip"; //ip��������
    $blacklist_userip          = "blacklist_userip"; //ip��������
    $templimitusernameKey      = "limitusername";
    $templimituseripKey        = "limituserip";
    $max_num_down_username     = "max_num_down_username"; //׷���û�������ش��� 
    $max_num_down_userip       = "max_num_down_userip"; //׷��ip������ش���
    $max_num_down_username_key = "max_num_down_username_"; //���ش�����Ӧ�û��˺ż�¼��
    $max_num_down_userip_key   = "max_num_down_userip_"; //���ش�����Ӧ�û�ip��¼��
    //����û����ص���Դ�Ƿ��Ѿ����ڣ������ڿ�������
    $username_rcode_down_key   = "username_rcode_down_" . $username . "_" . $rcode;
    $isdownflag                = $redis->get($username_rcode_down_key);
    if ($isdownflag == "1") {
        return "1";
    }
    
    //��������
    $blacklist_username_info = $redis->get($blacklist_username);
    $temparray               = explode("|" . $username . "|", $blacklist_username_info);
    if (count($temparray) > 1) {
        //loginfo($redis,"noviewlog","black_username blacklist_username is ".$blacklist_username_info." username is ".$username);
        return "2";
    }
    //����ip
    $blacklist_userip_info = $redis->get($blacklist_userip);
    $temparray             = explode("|" . $userip . "|", $blacklist_userip_info);
    if (count($temparray) > 1) {
        //loginfo($redis,"noviewlog","black_userip blacklist_userip is ".$blacklist_userip_info." username is ".$userip);
        return "2";
    }
    //��������
    $whitelist_username_info = $redis->get($whitelist_username);
    $temparray               = explode("|" . $username . "|", $whitelist_username_info);
    if (count($temparray) > 1) {
        return "1";
    }
    //����ip
    $whitelist_userip_info = $redis->get($whitelist_userip);
    $temparray             = explode("|" . $userip . "|", $whitelist_userip_info);
    if (count($temparray) > 1) {
        return "1";
    }
    
    $actionid_key   = "username_action_" . $actionid;
    $actionid_value = $redis->get($actionid_key);
    //$actionid_value��ֵֻ�����ڼ��㳬���������ƣ���$actionid_value��ֵʱ����ʾ��rcode��Դ�Ѿ�����ֹ�����ˣ�����Ҫ�������ش���
    //��$actionid_value��Ϊ��ʱ�������ϴ�Ϊ1ʱ��
    if ($actionid_value == "1") {
        return "3";
    }
    
    // $username_ip_expire = strtotime("today") + 60*60*24; // 2018-07-02 add
    //����û��˺��Ƿ񳬹�������ش�����û�г��������������أ����������ش��������������������أ����������ش���
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