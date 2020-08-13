<?php
/**
 * Description of Mcrypt3Des
 * 利用Mcrypt对字符串进行加密，并对密文进行解密。
 * @author wangchuan
 */
$rootPath=$_SERVER["DOCUMENT_ROOT"];
//echo $rootPath;
$keyPath=$rootPath."/des3/3deskey";
//echo $keyPath;

class Mcrypt3Des {
    var  $key="";
    var  $keyfile="/var/www/html/des3/3deskey";
	//var  $keyfile=$keyPath;
    var  $iv="12345678";
    function __construct($keyPath){
		$this->keyfile=$keyPath;
        $file_handle=fopen($this->keyfile,"r");
        if(!feof($file_handle)) {
            $line = fgets($file_handle);
            $this->key = $line;
            fclose($file_handle);
        }
    }
    /*对明文进行加密*/
    function encrypt($input){
        if($this->key=="") return null;
        $input = $this->pkcs5_pad($input);
        $key = str_pad($this->key,24,'0',STR_PAD_RIGHT);
        $td = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_CBC, '');
        //$iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td),MCRYPT_RAND);
        // 根据密钥和iv初始化$td,完成内存分配等初始化工作
        //@mcrypt_generic_init($td, $key, $this->iv);
        $data = null;
        if (mcrypt_generic_init($td, $key, $this->iv)!= -1)
        {
            // 进行加密
            $data = mcrypt_generic($td, $input);
            //$data = base64_encode($data);
            $data = bin2hex($data);
        }
        // 反初始化$td,释放资源
        mcrypt_generic_deinit($td);
        // 关闭资源对象，退出
        mcrypt_module_close($td);
        return $data;
    }
   /*对密文进行解密*/
    function decrypt($encrypted){
        if($this->key=="") return null;
        // $encrypted = base64_decode($encrypted);
        $encrypted = $this->hex2bin($encrypted);
        $key = str_pad($this->key,24,'0',STR_PAD_RIGHT);
        $td = mcrypt_module_open(MCRYPT_3DES,'',MCRYPT_MODE_CBC, '');
        //$iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td),MCRYPT_RAND);
        $ks = mcrypt_enc_get_key_size($td);
        // 根据密钥和iv初始化$td,完成内存分配等初始化工作
        //@mcrypt_generic_init($td, $key, $this->iv);
        $data=null;
        if (mcrypt_generic_init($td, $key, $this->iv)!= -1)
        {
            $decrypted = mdecrypt_generic($td, $encrypted);
            $data=$this->pkcs5_unpad($decrypted);
        }
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return $data;
    }

   /* pkcs5填充*/
    function pkcs5_pad($text) {
        $blocksize = mcrypt_get_block_size(MCRYPT_3DES,MCRYPT_MODE_CBC);
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }
   /* pkcs5去填充*/
    function pkcs5_unpad($text){
        $pad = ord($text{strlen($text)-1});
        if ($pad > strlen($text)) {
            return false;
        }
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad){
            return false;
        }
        return substr($text, 0, -1 * $pad);
    }
   /*十六进制转二进制*/
    function hex2bin($hex_string) {
        return pack('H*', $hex_string);
    }


	//3des加密 OPENSSL_RAW_DATA 为Pkcs7填充模式
	function des_encrypt($data)
	{
		
		//$key 	= md5($key);				//32位长度
		//$iv 	= substr(md5($iv),0,8);		//取前8位
		$str	= openssl_encrypt($data, 'des-ede3-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv);
		//return base64_encode($str);
		return bin2hex($str);
	 
	}
	//3des解密  OPENSSL_RAW_DATA 为Pkcs7填充模式
	function des_decrypt($data)
	{
		//$data	= base64_decode($data);
		//$key 	= md5($key);				//32位长度
		//$iv 	= substr(md5($iv),0,8);		//取前8位
		$data = pack('H*', $data);
		$str	= openssl_decrypt($data, 'des-ede3-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv);
		return ($str);
	 
	}






}

$mcrypt3Des= new Mcrypt3Des($keyPath);

?>
