<?php

namespace Api\Controller;
use Think\Controller\RestController;
define("APP_GETUI",'/opt/web/news/ThinkPHP/Library/Org/Getui');  //线上
//define("APP_GETUI",dirname(__FILE__).'/../../../ThinkPHP/Library/Org/Getui'); //本地
require_once(realpath(APP_GETUI).'/'.'IGt.Push.php');
class GetuiController extends BController{

private $host = "http://sdk.open.api.igexin.com/apiex.htm";
/**测试***/
// private $APPKEY = "IFEOjqdgmf5S54eNjfb2Y1";
// private $APPID = "YzSIAdOXmZAAZZnkTng3f6";
// private $CID = "";
// private $MASTERSECRET = "XH8vXTL0ZGAAW5Ev2B8NT2";
/***线上的***/
private $APPKEY = "BvFpt85L1g8SHpOefEwGU5";
private $APPID = "3febR1rTLp8QSkar48WC42";
private $CID = "";
private $MASTERSECRET = "C8reEqsNDFA69NIm2Z73e3";

/**小蜜app***/
// AppID： 
// qikmMk44Wk9oElpIcjbre3
// AppSecret： 
// EOk4BNcTrx6D8tiFMSUGW3
// AppKey： 
// bwpbeOF1ksAJVIl6qFID59
// MasterSecret： 
// eUvKxxEpqV9JnQ2741yV94重置

//宝驾掌柜 推送小蜜
public function  xiaomi_push($uid="",$message="测试"){
	$content = $message;
    if(empty($uid)){
        return "UID不能为空";
    }
    if(empty($content)){
        return "推送内容不能为空";
    }
	$client_id = M("xiaomi_getui")->where(["user_id"=>$uid])->getField("client_id");
	if(empty($client_id)){
		return "别名不能为空";
	}
    $a = $this->pushto_Android($client_id,$content,$is_client=1);
	
	
    $b = $this->pushto_Ios($client_id,$content,$is_client=1);
}


public function push($uid="",$message="测试"){
    $content = $message;
    if(empty($uid)){
        return "UID不能为空";
    }
    if(empty($content)){
        return "推送内容不能为空";
    }
    // $a = $this->pushto_Android($uid,$content);
	// var_dump($a);
    $b = $this->pushto_Ios($uid,$content);
	var_dump($b);
}

/**
*is_client  客户端  0=宝驾掌柜  1=小蜜app
**/
public function pushto_Android($uid='',$content='',$is_client=0){
    \Think\Log::write($uid.$content."客户端".$is_client,'推送用户信息');
	if($is_client == 1){
		$this->APPKEY = "bwpbeOF1ksAJVIl6qFID59";
		$this->MASTERSECRET = "eUvKxxEpqV9JnQ2741yV94";
		$this->APPID = "qikmMk44Wk9oElpIcjbre3";
	}
	
    $igt = new \IGeTui(NULL,$this->APPKEY,$this->MASTERSECRET,false);
    // $rep = $igt->bindAlias($this->APPID,$uid,"6e38e7693deb85018f2489825b686430");
    // var_dump($rep);die;
    //消息模版：
    // 1.TransmissionTemplate:透传功能模板
    // 2.LinkTemplate:通知打开链接功能模板
    // 3.NotificationTemplate：通知透传功能模板
    // 4.NotyPopLoadTemplate：通知弹框下载功能模板

    $template = $this->IGtNotificationTemplateDemo($content,$is_client);
   // echo "<pre/>";
   // print_r($template);die;

    //个推信息体
    $message = new \IGtSingleMessage();

    $message->set_isOffline(true);//是否离线
    $message->set_offlineExpireTime(3600*12*1000);//离线时间
    $message->set_data($template);//设置推送消息类型
//	$message->set_PushNetWorkType(0);//设置是否根据WIFI推送消息，1为wifi推送，0为不限制推送
    //接收方
    $target = new \IGtTarget();
    $target->set_appId($this->APPID);
	if($is_client == 1){
		$target->set_clientId($uid);
	}else{
		$target->set_alias($uid);
	}
    
    try {
        $rep = $igt->pushMessageToSingle($message, $target);
        
        \Think\Log::write(var_export($rep, true),'推送');

//        var_dump($rep["result"]);
//        return $rep["result"];
        var_dump($rep);
        echo ("<br><br>");

    }catch(RequestException $e){
        $requstId =e.getRequestId();
        $rep = $igt->pushMessageToSingle($message, $target,$requstId);
        \Think\Log::write(var_export($rep, true),'推送');
//        return $rep["result"];

        var_dump($rep);
        echo ("<br><br>");
    }

}
    public function pushto_Ios($uid='',$content='',$is_client=0){
	
        \Think\Log::write($uid.$content,'推送用户信息');
		if($is_client == 1){
			$this->APPKEY = "bwpbeOF1ksAJVIl6qFID59";
			$this->MASTERSECRET = "eUvKxxEpqV9JnQ2741yV94";
			$this->APPID = "qikmMk44Wk9oElpIcjbre3";
		}
        $igt = new \IGeTui(NULL,$this->APPKEY,$this->MASTERSECRET,false);

        //消息模版：
        // 1.TransmissionTemplate:透传功能模板
        // 2.LinkTemplate:通知打开链接功能模板
        // 3.NotificationTemplate：通知透传功能模板
        // 4.NotyPopLoadTemplate：通知弹框下载功能模板

    $template = $this->IGtTransmissionTemplateDemo($content,$is_client);
//    echo "<pre/>";
//    print_r($template);die;

        //个推信息体
        $message = new \IGtSingleMessage();

        $message->set_isOffline(true);//是否离线
        $message->set_offlineExpireTime(3600*12*1000);//离线时间
        $message->set_data($template);//设置推送消息类型
//	$message->set_PushNetWorkType(0);//设置是否根据WIFI推送消息，1为wifi推送，0为不限制推送
        //接收方
        $target = new \IGtTarget();
        $target->set_appId($this->APPID);
		if($is_client == 1){
            $target->set_clientId($uid);
		}else{
		    $target->set_alias($uid);
		}
        try {
            $rep = $igt->pushMessageToSingle($message, $target);

            \Think\Log::write(var_export($rep, true),'推送');


            var_dump($rep);
            echo ("<br><br>");

        }catch(RequestException $e){
            $requstId =e.getRequestId();
            $rep = $igt->pushMessageToSingle($message, $target,$requstId);
            \Think\Log::write(var_export($rep, true),'推送');

            var_dump($rep);
            echo ("<br><br>");
        }

    }
    function IGtTransmissionTemplateDemo($content='',$is_client=0){
		if($is_client == 1){
			$this->APPKEY = "bwpbeOF1ksAJVIl6qFID59";
		    $this->APPID = "qikmMk44Wk9oElpIcjbre3";
		}
        $template =  new \IGtTransmissionTemplate();
        $template->set_appId($this->APPID);//应用appid
        $template->set_appkey($this->APPKEY);//应用appkey
        $template->set_transmissionType(1);//透传消息类型
		if($is_client == 1){
            $template->set_transmissionContent($content);//透传内容
		}else{
			$template->set_transmissionContent(json_encode(array("actionType"=>1,"content"=>"测试")));//透传内容
		}
        //$template->set_duration(BEGINTIME,ENDTIME); //设置ANDROID客户端在此时间区间内展示消息
        //APN简单推送
//        $template = new IGtAPNTemplate();
//        $apn = new IGtAPNPayload();
//        $alertmsg=new SimpleAlertMsg();
//        $alertmsg->alertMsg="";
//        $apn->alertMsg=$alertmsg;
////        $apn->badge=2;
////        $apn->sound="";
//        $apn->add_customMsg("payload","payload");
//        $apn->contentAvailable=1;
//        $apn->category="ACTIONABLE";
//        $template->set_apnInfo($apn);
//        $message = new IGtSingleMessage();

        //APN高级推送
        $apn = new \IGtAPNPayload();
        $alertmsg=new \DictionaryAlertMsg();
        $alertmsg->body=$content;
        $alertmsg->actionLocKey="ActionLockey";
        $alertmsg->locKey="LocKey";
        $alertmsg->locArgs=array("locargs");
        $alertmsg->launchImage="launchimage";
//        IOS8.2 支持
        if($is_client == 1){
			$alertmsg->title="小蜜app";
		}else{
            $alertmsg->title="宝驾掌柜";
		}
        $alertmsg->titleLocKey="TitleLocKey";
        $alertmsg->titleLocArgs=array("TitleLocArg");

        $apn->alertMsg=$alertmsg;
        $apn->badge=1;
        $apn->sound="";
        $apn->add_customMsg("payload","payload");
        $apn->contentAvailable=1;
        $apn->category="ACTIONABLE";
        $template->set_apnInfo($apn);

        //PushApn老方式传参
//    $template = new IGtAPNTemplate();
//          $template->set_pushInfo("", 10, "", "com.gexin.ios.silence", "", "", "", "");

        return $template;
    }
    function IGtNotificationTemplateDemo($content="",$is_client=0){
		if($is_client == 1){
			$this->APPKEY = "bwpbeOF1ksAJVIl6qFID59";
		    $this->APPID = "qikmMk44Wk9oElpIcjbre3";
		}
		
        $template =  new \IGtNotificationTemplate();
        $template->set_appId($this->APPID);//应用appid
        $template->set_appkey($this->APPKEY);//应用appkey
        $template->set_transmissionType(1);//透传消息类型
        
		if($is_client == 1){
			$template->set_transmissionContent(json_encode(array("actionType"=>1)));//透传内容
			$template->set_title("小蜜app");//通知栏标题
			$template->set_text($content);//通知栏内容
			$template->set_logo("http://wwww.igetui.com/logo.png");//通知栏logo
		}else{
			$template->set_transmissionContent($content);//透传内容
			$template->set_title("宝驾掌柜");//通知栏标题
			$template->set_text($content);//通知栏内容
			$template->set_logo("http://wwww.igetui.com/logo.png");//通知栏logo
		}
        $template->set_isRing(true);//是否响铃
        $template->set_isVibrate(true);//是否震动
        $template->set_isClearable(true);//通知栏是否可清除
        //$template->set_duration(BEGINTIME,ENDTIME); //设置ANDROID客户端在此时间区间内展示消息
        return $template;
    }
















}
?>