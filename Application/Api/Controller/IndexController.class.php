<?php
namespace Api\Controller;
use Think\Controller\RestController;
class IndexController extends RestController {
	private $ceshi_config = 'mysql://apitest-baojia:TKQqB5Gwachds8dv@10.1.11.110:3306/baojia_mebike#utf8';  //测试数据库
    public function index(){
        echo 111;
    }
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
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); // 执行操作
        curl_close($curl); // 关闭CURL会话
        return $tmpInfo; // 返回数据
    }
	
	public  function  download(){
		$version_update = M("zg_app_version_update","",$this->ceshi_config)->field("version,zdr_version,apk_file_name")->where(["type"=>0])->find();
		$apk_file_name = $version_update["apk_file_name"]?$version_update["apk_file_name"]:"app-2.0.6-release.apk";
		$this->assign("apk_file_name",$apk_file_name);
		$this->display();
	}
	public function Invitation($uid="",$mobile=""){
		$uKey = C('KEY');
        $uid = decrypt($uKey, $_POST['uid']);
        $uid = trim($uid);
        // if( empty($uid) ){
        //    $this->response(["status" => -1, "info" => "参数有误"], 'json');
        // }
		$type = $this->get_device_type();
		$uid = $_POST['uid'] ? $_POST['uid'] : '2650355';
		$this->assign("uid",$uid);
		$this->assign("type",$type);
		$this->display();
	}
	public function coupon(){
		$type = $this->get_device_type();
		$uid = str_replace('XXXX', '/', $_GET['uid']);
		$uKey = C('KEY');
		$uid = decrypt($uKey, $uid);
		$uid = trim($uid);
		$result = M("xiaomi_code","",'mysql://bjtest_dev1:yU23VFFFwfZ9y5YG@10.1.11.110:3306/testbaojia#utf8')->where(["uid"=>$uid,"beinvite"=>$uid])->select();
		$this->assign("code",$result);
        $this->assign("type",$type);
		$this->display();
	}
	public function code(){
		$mobile = $_GET['mobile'];
		$vid = str_replace('XXXX', '/', $_GET['vid']);
		$uid = str_replace('XXXX', '/', $_GET['uid']);
		$invite = $_GET['invite'];
		$this->assign("mobile",$mobile);
		$this->assign("invite",$invite);
		$this->assign("vid",$vid);
		$this->assign("uid",$uid);
		$this->display();
	}
	public function rule(){
		$this->display();
	}
	public function quickLoginCode(){
		$uKey = C('KEY');
        $mobile = encrypt($uKey, $_POST['mobile']);
        $mobile = trim($mobile);
        $data['invite'] = $_POST['invite'];
		$data['mobile'] = $mobile;
		$data['app_id'] = '218';
		$data['client_id'] = '1218';
		$data['qudao_id'] = 'guanfang';
		$data['sign'] ='2f90dd7cc07b3dc5d3f867d1d58b5937';
		$data['timestamp'] = time();
		$data['version'] = '3.0.1';
		$data['device_model'] = '';
		$data['device_os'] = '';
		$data['from'] = 'h5reg';
		$url = 'http://api.baojia.com/test4/Api/User/quickLoginCode';
		$result = $this->curl_get($url,$data,'POST');
		echo $result;
	}
	public function Code_check(){
		$uKey = C('KEY');
        $mobile = encrypt($uKey, $_POST['mobile']);
        $mobile = trim($mobile);
        $code = encrypt($uKey, $_POST['code']);
        $code = trim($code);
        $data['mobile'] = $mobile;
        $data['invite'] = $_POST['invite'];
        $data['code'] = $code;
		$data['vid'] = $_POST['vid'];
		$data['uid'] = $_POST['uid'];
		$data['app_id'] = '218';
		$data['client_id'] = '1218';
		$data['qudao_id'] = 'guanfang';
		$data['sign'] ='2f90dd7cc07b3dc5d3f867d1d58b5937';
		$data['timestamp'] = time();
		$data['version'] = '3.0.1';
		$data['device_model'] = '';
		$data['device_os'] = '';
		$data['encrypt'] = 1;
		$data['from'] = 'h5reg';
		$url = 'http://api.baojia.com/test4/Api/User/quickLogin';
		$result = $this->curl_get($url,$data,'POST');
		echo $result;
	}
	//判断设备型号
	public function get_device_type(){
	 $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
	 $type = 'other';
	 if(strpos($agent, 'iphone') || strpos($agent, 'ipad') ){
	 $type = '1'; //IOS
	 }
	 if(strpos($agent, 'android')){
	 $type = '2'; //android
	 }
	 return $type;
	}
	public function php1(){
		echo phpinfo();
	}

}