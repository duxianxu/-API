<?php

//检测用户登录标识是否正确 
function check_iflogin($uid, $iflogin)
{
//    $uid = intval($uid);
//    if ($uid <= 0) {
//        return false;
//    }
//    if (empty($iflogin) || strlen($iflogin) <= 0) {
//        return false;
//    }
//    $UcenterMember = M('UcenterMember');
//    $ucenterMember = $UcenterMember->where("uid=" . intval($uid))->find();
//    if (!is_array($ucenterMember) || $ucenterMember['status'] != 1) {
//        return false;
//    }
//
//    if (base64_encode(md5(md5($uid) . $ucenterMember['salt'])) == $iflogin) {
//        $BlackList = M("member_blacklist");
//        $black = $BlackList->where(" status=1 and user_id={$uid}")->find();
//        return is_array($black) ? false : true;
//    } else {
//        return false;
//    }
    return true;
}
/**
 * PHP DES 加密程式
 *
 * @param $key 密鑰（八個字元內）
 * @param $encrypt 要加密的明文
 * @return string 密文
 */
function encrypt($key, $encrypt)
{
    // 根據 PKCS#7 RFC 5652 Cryptographic Message Syntax (CMS) 修正 Message 加入 Padding
    $block = mcrypt_get_block_size(MCRYPT_DES, MCRYPT_MODE_ECB);
    $pad = $block - (strlen($encrypt) % $block);
    $encrypt .= str_repeat(chr($pad), $pad);

    // 不需要設定 IV 進行加密
    $passcrypt = mcrypt_encrypt(MCRYPT_DES, $key, $encrypt, MCRYPT_MODE_ECB);
    return base64_encode($passcrypt);
}
function aes($ostr, $securekey, $type='encrypt'){
    if($ostr==''){
        return '';
    }

    $key = "2mYHpWiFwdBJBHdMjpfhNp2GdwSDshsA";
    $iv = "snNZQ4dK4Dwc32ky";
    $td = mcrypt_module_open('rijndael-256', '', 'ofb', '');
    mcrypt_generic_init($td, $key, $iv);

    $str = '';

    switch($type){
        case 'encrypt':
            $str = base64_encode(mcrypt_generic($td, $ostr));
            break;

        case 'decrypt':
            $str = mdecrypt_generic($td, base64_decode($ostr));
            break;
    }

    mcrypt_generic_deinit($td);

    return $str;
}
function encode($data) {
    $td = mcrypt_module_open(MCRYPT_RIJNDAEL_256,'',MCRYPT_MODE_CBC,'');
    $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td),MCRYPT_RAND);
    mcrypt_generic_init($td,'2mYHpWiFwdBJBHdMjpfhNp2GdwSDshsA',$iv);
    $encrypted = mcrypt_generic($td,$data);
    mcrypt_generic_deinit($td);

    return $iv . $encrypted;
}
/**
 * PHP DES 解密程式
 *
 * @param $key 密鑰（八個字元內）
 * @param $decrypt 要解密的密文
 * @return string 明文
 */
function decrypt($key, $decrypt, $replace_space = 1)
{
    if ($replace_space == 1) {
        $decrypt = str_replace(" ", "+", $decrypt);
    }
    // 不需要設定 IV
    $str = mcrypt_decrypt(MCRYPT_DES, $key, base64_decode($decrypt), MCRYPT_MODE_ECB);

    // 根據 PKCS#7 RFC 5652 Cryptographic Message Syntax (CMS) 修正 Message 移除 Padding
    $pad = ord($str[strlen($str) - 1]);
    return substr($str, 0, strlen($str) - $pad);

}
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
function curl_post($url,$data){ // 模拟提交数据函数
    $curl = curl_init(); // 启动一个CURL会话
    curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
    curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
    curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
    curl_setopt($curl, CURLOPT_COOKIEFILE, $GLOBALS['cookie_file']); // 读取上面所储存的Cookie信息
    curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
    curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
    $tmpInfo = curl_exec($curl); // 执行操作
    if (curl_errno($curl)) {
        echo 'Errno'.curl_error($curl);
    }
    curl_close($curl); // 关键CURL会话
    return $tmpInfo; // 返回数据
}

/**
 * 模拟提交参数，支持https提交 可用于各类api请求
 * @param string $url ： 提交的地址
 * @param array $data :POST数组
 * @param string $method : POST/GET，默认GET方式
 * @return mixed
 */
function curl_get($url, $data='', $method='GET')
{
    $curl = curl_init(); // 启动一个CURL会话
    curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
    curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
    if ($method == 'POST') {
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        if ($data != '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        }
    }
    curl_setopt($curl, CURLOPT_HTTPHEADER,
        [
            'Content-Type:application/json;charset=utf-8',
        ]
    );
    curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
    curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
    $tmpInfo = curl_exec($curl); // 执行操作
    curl_close($curl); // 关闭CURL会话
    return $tmpInfo; // 返回数据
}
function add_code( $uid, $invite, $beinvite, $desc, $price, $useful_time, $code = "", $number = 1){
    $uid = intval($uid);
    if( empty($uid) ){
        return false;
    }
    if( empty($invite) && empty($beinvite) ){
        return false;
    }
    $data = [
        "uid" => $uid,
        "price" => $price,
        "desc" => $desc,
        "receive_time" => time(),
        "useful_time" => $useful_time * 3600,
        "over_time" => time()+$useful_time * 3600,
        "status" => 1,
        "invite" => $invite,
        "beinvite" => $beinvite
    ];
    for ($i=0; $i < $number ; $i++) {
        $res = M("xiaomi_code")->add($data);
    }
    if($res){
        return true;
    }
    return false;

}
function getTree($data, $pId)
{
    $tree = '';
    foreach($data as $k => $v)
    {
        if($v['create_time'] == $pId)
        {        //父亲找到儿子
            $v['create_time'] = getTree($data, $v['create_time']);
            $tree[] = $v;
            //unset($data[$k]);
        }
    }
    return $tree;
}


