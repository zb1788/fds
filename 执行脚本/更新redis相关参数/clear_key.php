<?php
//功能有，检查redis状态正常，检查
$file_name="/home/phpscript/data_config.inc";
include("/home/phpscript/redis_config.inc");
try{
    $redis = new Redis();  
    $redis->connect($redis_ip,$redis_port,$time_out_moren);
    $redis->select($redis_db_index);
    $reslut= $redis->ping();
    if($reslut=="PONG" || $reslut=="+PONG") 
    {
        $keys=$redis->keys("username_rcode_down_*");
        for($i=0;$i<count($keys);$i++)
        {
            $redis->del($keys[$i]);
        }
        $keys=$redis->keys("username_action_*");
        for($i=0;$i<count($keys);$i++)
        {
            $redis->del($keys[$i]);
        }
        $keys=$redis->keys("down_username_*");
        for($i=0;$i<count($keys);$i++)
        {
            $redis->del($keys[$i]);
        }
        $keys=$redis->keys("down_userip_*");
        for($i=0;$i<count($keys);$i++)
        {
            $redis->del($keys[$i]);
        }
        $keys=$redis->keys("max_num_down_username*");
        for($i=0;$i<count($keys);$i++)
        {
            $redis->del($keys[$i]);
        }
        $keys=$redis->keys("max_num_down_userip*");
        for($i=0;$i<count($keys);$i++)
        {
            $redis->del($keys[$i]);
        }
    }
}catch(Exception $e){
}
?>