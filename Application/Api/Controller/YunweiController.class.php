<?php
/**
 * Created by PhpStorm.
 * User: CHI
 * Date: 2017/7/21
 * Time: 16:04
 */
namespace Api\Controller;
use Think\Controller\RestController;
use Think\Upload;
class YunweiController extends BController {

    private $PI = 3.14159265358979324;
    private $url = 'http://zykuaiche.com.cn:81/g/service';
    private $url2 = 'http://zykuaiche.com.cn:81/s/service';
    private $key = '987aa22ae48d48908edafda758ae82a8';

    private $box_config = 'mysqli://api-baojia:CSDV4smCSztRcvVb@10.1.11.14:3306/baojia_box';
//    private $box_config = 'mysql://apitest-baojia:TKQqB5Gwachds8dv@10.1.11.110:3306/baojia_box';
    private $baojia_config = 'mysqli://api-baojia:CSDV4smCSztRcvVb@10.1.11.2:3306/baojia';
    private $ceshi_config = 'mysql://apitest-baojia:TKQqB5Gwachds8dv@10.1.11.110:3306/baojia_mebike#utf8';  //测试数据库

    private $domainName   = 'http://xiaomi.baojia.com';  //正式线上域名
    private $test_domainName  = 'http://xmtest.baojia.com';  //预上线上域名
    private $shared_domainName  = 'http://pic.baojia.com';  //共享域名

    private $ol_table = 'operation_logging';
    private $mebike_s_table = 'mebike_status';
    public function index()
    {
        $this->display('yunwei');
    }

    //客户端异常日志上传
    public  function  abnormalUpload($userName='')
    {
        //app参数
        $newLog = 'log_time:' . date('Y-m-d H:i:s');
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . "Public/Logs/" . date('Y-m-d', time()) . "客户端异常数据.txt", json_encode($_FILES["abnormal"]) . $newLog . PHP_EOL, FILE_APPEND);
        if(!$userName){
            $this->response(["code" => 0, "message" => "参数错误"], 'json');
        }
        if(isset($_FILES["abnormal"])){
            $_FILES["uploadfile"]=$_FILES["abnormal"];
            $root   =  "/hd2/web/upfiles/pic/Public/Logs";
            $filename = date('Y-m-d H:i:s').$userName.".txt";
            $filepath = $root.$filename;
            $pic=move_uploaded_file($_FILES["uploadfile"]["tmp_name"], $filepath);
            if($pic){
                $this->response(["code" => 1, "message" => "上传成功"], 'json');
            }else{
                $this->response(["code" => 0, "message" => "上传失败"], 'json');
            }
        }else{
            $this->response(["code" => 0, "message" => "上传失败"], 'json');
        }
    }

    //强制更新app
    public   function  forcedUpdate($version='2.0.3',$device_os = 'Android',$uid="aa"){
        $download_time = date("Y-m-d H:i:s",time());
        $download_ip   = get_client_ip();
        file_put_contents("/hd2/web/upfiles/pic/Public/Logs/宝驾掌柜下载".date("Y-m-d",time()).".txt",$download_time."ip地址".$download_ip.json_encode($_POST). PHP_EOL, FILE_APPEND);
		
        if(empty($version)){
            $this->response(["code" => 0, "message" => "参数错误"], 'json');
        }
        $version = explode('.',$version);
        if($device_os == 'Android'){
            $version_update = M("zg_app_version_update","",$this->ceshi_config)->field("version,zdr_version,specified_person,apk_file_name,content")->where(["type"=>0])->find();
            if(!empty($version_update["specified_person"]) && strpos($version_update["specified_person"],$uid) !==false){
                file_put_contents("/hd2/web/upfiles/pic/Public/Logs/指定宝驾掌柜下载".date("Y-m-d",time()).".txt",json_encode($_POST). PHP_EOL, FILE_APPEND);
                $newVersion = $version_update["zdr_version"];
            }else{
                $newVersion = $version_update["version"];
            }
        }else{
            $version_update = M("zg_app_version_update","",$this->ceshi_config)->field("version,zdr_version,specified_person,apk_file_name,content")->where(["type"=>1])->find();
            if(!empty($version_update["specified_person"]) && strpos($version_update["specified_person"],$uid) !==false){
                $newVersion = $version_update["zdr_version"];
                file_put_contents("/hd2/web/upfiles/pic/Public/Logs/指定宝驾掌柜下载".date("Y-m-d",time()).".txt",json_encode($_POST). PHP_EOL, FILE_APPEND);
            }else{
                $newVersion = $version_update["version"];
            }
        }
        $version2 = explode('.',$newVersion);
        if(intval($version[0]) < intval($version2[0])){
            $data["isForced"] = 1;
        }else if(intval($version[0]) > intval($version2[0])){
            $data["isForced"] = 0;
        }else{
            if(intval($version[1]) < intval($version2[1])){
                $data["isForced"] = 1;
            }else if(intval($version[1]) > intval($version2[1])){
                $data["isForced"] = 0;
            }else{
                if(intval($version[2]) < intval($version2[2])){
                    $data["isForced"] = 1;
                }else if(intval($version[2]) > intval($version2[2])){
                    $data["isForced"] = 0;
                }else{
                    $data["isForced"] = 0;
                }
            }
        }
        $data['title'] = "更新提示";
        $data['version'] = $newVersion;
        $data['content'] = "1、【新增】详情页新增“更换盒子”功能；\r\n2、【调整】换电与调度组合任务时，去掉任务优先级；";
        if($device_os == 'Android'){
            $data['saveUrl'] = $this->shared_domainName."/Public/app/".$version_update["apk_file_name"];
            $files_arr = get_headers($this->shared_domainName."/Public/app/".$version_update["apk_file_name"],true);
            $data['contentLength'] = $files_arr['Content-Length']?$files_arr['Content-Length']:0;
        }else if($device_os == 'iOS'){
            // $data['saveUrl'] = "https://www.pgyer.com/71gZ";
            $data['saveUrl'] = "https://fir.im/BaojiaMaintain";
        }else{
            $data['saveUrl'] = "";
        }
        $this->response(["code" => 1, "message" => "数据接收成功","data"=>$data], 'json');
    }
	

    //检测是否换电接口 -- 老板本
    public  function   detectionElectricity($rent_content_id=0){
        $map['rc.id'] = $rent_content_id;
        $map['rcs.address_type'] = 99;
        $map['rcs.sort_id'] = 112;
        $map['rcs.plate_no'] = array('neq','');
        $map['rc.car_info_id'] = array('neq',30150);
        $reslut = M('rent_content')->alias('rc')
            ->field('rc.id,cid.imei')
            ->join('rent_content_search rcs ON rc.id = rcs.rent_content_id','left')
            ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
            ->where($map)->find();
        if($reslut){
            //查询电量
            $electricity = D('FedGpsAdditional')->electricity_info($reslut['imei']);

            if($electricity['residual_battery1'] <= 35 && $electricity['residual_battery2'] <= 35){
                $this->response(["code" => 1, "message" => "该车缺电请换电","data"=>["rent_content_id"=>$reslut['id'],"residual_battery"=>$electricity['residual_battery1']]], 'json');
            }else{
                $this->response(["code" => 2, "message" => "无需换电","data"=>["rent_content_id"=>$reslut['id'],"residual_battery"=>$electricity['residual_battery1']]], 'json');
            }
        }else{
            $this->response(["code" => 0, "message" => "该车辆不存在"], 'json');
        }
    }

    //完成换电接口 -- 老板本
    public  function  completeExchange($rent_content_id = 0, $residual_battery = 0,$uid=0,$operationId=0,$plate_no="",$gis_lng=0,$gis_lat=0,$power_per=100){
        $map['rc.id'] = $rent_content_id;
        $reslut = M('rent_content')->alias('rc')->field('rc.id,cid.imei,cid.device_type,rc.car_item_id')
            ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
            ->where($map)->find();
        if($reslut){
            //判断操作记录是否存在
            $model = M($this->ol_table);
            $ol_arr = $model->where(['id'=>$operationId])->getField('id');
            if(!$ol_arr){
                $this->response(["code" => 0, "message" => "参数错误"], 'json');
            }
            if(empty($uid) || empty($plate_no) || empty($gis_lng)){
                $this->response(["code" => 0, "message" => "参数错误"], 'json');
            }

            $hd_qian = D('FedGpsAdditional')->electricity_info($reslut['imei']);
            $residual_battery = $hd_qian['residual_battery2'];
            $url = C("GATEWAY_LINK");
            //读取网关的key存到redis 1分钟失效
            $redis = new \Redis();
//            $redis->connect('127.0.0.1', 6379);
            $redis->pconnect('10.1.11.82',6379,0.5);
            $dyKey = ltrim($reslut['imei'],"0").":Voltage";
            $kValue = $redis->get($dyKey);
            if($kValue){
                $cData['carId'] = ltrim($reslut['imei'],"0");
                $cData['key']   = $kValue;
                $cData['cmd']   = 'resultQuery';
                for($i=0;$i<10;$i++){
                    $res2 = $this->VoltagePost($cData,$url);
                    if($res2['rtCode'] == '0'){
                        break;
                    }else{
                        sleep(1);
                    }
                }
                if($res2['rtCode'] == '0' && strlen($res2['rtCode']) > 0){
                    $electricity = $this->getDumpEle($res2['result']['voltage']);
                    $electricity = $electricity ? intval($electricity * 100) : 0;
                    if ($electricity > 35) {
                        //换电完成上架待租
                        M('rent_content')->where(["id"=>$reslut['id']])->setField('sell_status',1);
                        //车牌号为空
                        if(empty($plate_no) || $plate_no == "null"){
                            $plate_no = M("car_item_verify")->where(["car_item_id"=>$reslut['car_item_id']])->getField("plate_no");
                        }
                        //查询我预约的车辆订单完成换电取消
                        $hMap['plate_no'] = $plate_no;
                        $hMap['uid'] = $uid;
                        $hMap['status'] = 0;
                        $have_order = M("baojia_mebike.have_order")->where($hMap)->getField('id');
                        if($have_order){
                            M("baojia_mebike.have_order")->where(['id'=>$have_order])->setField(['status'=>1,'update_time'=>time()]);
                        }
                        //更新操作记录
                        $olDdata['gis_lng'] = $gis_lng;
                        $olDdata['gis_lat'] = $gis_lat;
                        $olDdata['before_battery'] = $residual_battery;
                        if($electricity){
                            $date['desc'] = '换电时获取时时电压的电量:'.$electricity;
                        }
                        $olDdata['step'] = 3;
                        $olDdata['time'] = time();
                        $model->where(['id'=>$ol_arr])->save($olDdata);
                        //清除redis历史电压
                        if($reslut['imei']){
                            $redis = new \Redis();
                            $redis->pconnect('10.1.11.82',6379,0.5);
                            $key='prod:boxxan_analyzer'.$reslut['imei'];
                            $length = $redis->LLEN($key);
                            $redis->LTRIM($key,$length,-1);
                        }
                        $this->response(["code" => 1, "message" => "已完成换电，请上传照片", "data" => ["operationId" => $operationId, "residual_battery" => $electricity]], 'json');
                    } else {
                        $this->response(["code" => -4, "message" => "新电池检测不合格，请重新更换电池"], 'json');
                    }
                }else{
                    $this->response(["code" => 0, "message" => "再试一次"], 'json');
                }
            }else {
                $data['carId'] = ltrim($reslut['imei'], "0");
                $data['type'] = 34;
                $data['cmd'] = 'statusQuery';
//              $data['directRt'] = 'false';
                $res = $this->VoltagePost($data, $url);
                if ($res['rtCode'] == '0') {
                    if (!$kValue) {
                        $redis->set($dyKey, $res['msgkey']);
                        $redis->expire($dyKey, 20);
                    }
                    $cData['carId'] = ltrim($reslut['imei'], "0");
                    $cData['key'] = $res['msgkey'];
                    $cData['cmd'] = 'resultQuery';
                    for($i=0;$i<10;$i++){
                        $res2 = $this->VoltagePost($cData,$url);
                        if($res2['rtCode'] == '0'){
                            break;
                        }else{
                            sleep(1);
                        }
                    }
                    if($res2['rtCode'] == '0' && strlen($res2['rtCode']) > 0){
                        $electricity = $this->getDumpEle($res2['result']['voltage']);
                        $electricity = $electricity ? intval($electricity * 100) : 0;
                        if ($electricity > 35) {
                            //换电完成上架待租
                            M('rent_content')->where(["id"=>$reslut['id']])->setField('sell_status',1);
                            //车牌号为空
                            if(empty($plate_no) || $plate_no == "null"){
                                $plate_no = M("car_item_verify")->where(["car_item_id"=>$reslut['car_item_id']])->getField("plate_no");
                            }
                            //查询我预约的车辆订单完成换电取消
                            $hMap['plate_no'] = $plate_no;
                            $hMap['uid'] = $uid;
                            $hMap['status'] = 0;
                            $have_order = M("baojia_mebike.have_order")->where($hMap)->getField('id');
                            if($have_order){
                                M("baojia_mebike.have_order")->where(['id'=>$have_order])->setField(['status'=>1,'update_time'=>time()]);
                            }
                            //更新操作记录
                            $olDdata['gis_lng'] = $gis_lng;
                            $olDdata['gis_lat'] = $gis_lat;
                            $olDdata['before_battery'] = $residual_battery;
                            if($electricity){
                                $date['desc'] = '换电时获取时时电压的电量:'.$electricity;
                            }
                            $olDdata['step'] = 3;
                            $olDdata['time'] = time();
                            $model->where(['id'=>$ol_arr])->save($olDdata);
                            //清除redis历史电压
                            if($reslut['imei']){
                                $redis = new \Redis();
                                $redis->pconnect('10.1.11.82',6379,0.5);
                                $key='prod:boxxan_analyzer'.$reslut['imei'];
                                $length = $redis->LLEN($key);
                                $redis->LTRIM($key,$length,-1);
                            }
                            $this->response(["code" => 1, "message" => "已完成换电，请上传照片", "data" => ["operationId" => $operationId, "residual_battery" => $electricity]], 'json');
                        } else {
                            $this->response(["code" => -4, "message" => "新电池检测不合格，请重新更换电池"], 'json');
                        }
                    }else{
                        $this->response(["code" => 0, "message" => "再试一次"], 'json');
                    }
                }else if($res['rtCode'] == 1){
                    $this->response(["code" => 0, "message" => "设备接收命令并返回失败"], 'json');
                }else if($res['rtCode'] == 3){
                    $this->response(["code" => 0, "message" => "设备断开连接"], 'json');
                }else if($res['rtCode'] == 6){
                    $this->response(["code" => 0, "message" => "命令重复"], 'json');
                }else{
                    $this->response(["code" => 0, "message" => "查询失败 可能盒子已离线"], 'json');
                }
            }
        }else{
            $this->response(["code" => 0, "message" => "该车辆不存在"], 'json');
        }
    }

    //换电接口
    public  function  electricity($rent_content_id = 0, $residual_battery = 100,$uid=0,$plate_no="",$gis_lng=0,$gis_lat=0,$power_per=100,$carRule=0,$corporation_id=0,$pid=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $areaLogic= new \Api\Logic\Area();
        $battery_standard = $areaLogic->need_change($corporation_id);

        $map['rc.id'] = $rent_content_id;
        $reslut = M('rent_content')->alias('rc')->field('rc.sell_status,rc.id,cid.imei,cid.device_type,rc.car_item_id')
            ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
            ->where($map)->find();
        if($reslut) {
            //开始换电记录
            $start_hd = D("FedGpsAdditional")->undone_operation($uid,$rent_content_id,33);
            if(!$start_hd){
                D("FedGpsAdditional")->operation_log($uid,$rent_content_id,$plate_no,$gis_lng,$gis_lat,33,$corporation_id,0,0,$pid);
            }
            $workOrder = D('FedGpsAdditional')->ownWorkOrder($uid,$rent_content_id,$corporation_id);
            if($workOrder){
                $pid = $workOrder['id']?$workOrder['id']:0;
            }else{
                $this->response(["code" => 0, "message" => "你没有权限处理该辆车"], 'json');
            }
            $operate_status = M("rent_sku_hour")->where("rent_content_id=".$rent_content_id)->getField("operate_status");
            //比德文盒子换电流程
            if ($reslut['device_type'] == 16) {
                if($residual_battery <= $battery_standard){
                    if($reslut['device_type'] == 16){
                        $this->url2 = $this->url = "http://yd.zykuaiche.com:81/s/service";
                        $imei = ltrim($reslut['imei'],"0");
                        $api_result_json = $this->setpower($imei,$power_per/100);

                        if($api_result_json["rtCode"] == 0){
                            $this->setDbPower($reslut['imei'],$power_per);
                        }
                    }else{
                        $this->setDbPower($reslut['imei'],$power_per);
                    }
                }
                $res = D('FedGpsAdditional')->electricity_info($reslut['imei']);
                if($res['residual_battery1'] > $residual_battery && $res['residual_battery2'] > $residual_battery){
                    //换电完成上架待租
                    $car_prompt = $this->setCarStatus($uid,$plate_no='',$reslut['id'],$operate_status);

                    //更新里程和电量
                    $edata = array('battery_capacity'=>$power_per,'running_distance'=>60);
                    M('rent_content_ext')->where(["rent_content_id"=>$reslut['id']])->setField($edata);
                    //车牌号为空
                    if(empty($plate_no) || $plate_no == "null"){
                        $plate_no = M("car_item_verify")->where(["car_item_id"=>$reslut['car_item_id']])->getField("plate_no");
                    }
                    //查询我预约的车辆订单完成换电取消
                    $this->cancelHaveOrder($plate_no,$uid);

                    $operationId = $this->operationLog($uid,$rent_content_id,$plate_no,$gis_lng,$gis_lat,$residual_battery,0,$carRule,0,$corporation_id,$pid,$reslut['sell_status']);
                    \Think\Log::write($plate_no."操作记录结果：".$operationId."请求参数：" . json_encode($_POST), "INFO");
                    //清除redis历史电压
                    $this->clearRedisVoltage($reslut['imei']);
                    $this->response(["code" => 1, "message" => "已完成换电，请上传照片","data"=>["operationId"=>$operationId],"residual_battery"=>$res['residual_battery1'],"car_prompt"=>$car_prompt], 'json');
                }else{
                    $this->response(["code" => -2, "message" => "无需换电或换电没有完成"], 'json');
                }
            } else {
                $hd_qian = D('FedGpsAdditional')->electricity_info($reslut['imei']);
                if ( $residual_battery > $battery_standard) {
                    $this->response(["code" => 0, "message" => "该电车无需换电~"], 'json');
                }
                $url = C("GATEWAY_LINK");

                //读取网关的key存到redis 1分钟失效
                $redis = new \Redis();
//            $redis->connect('127.0.0.1', 6379);
                $redis->pconnect('10.1.11.82', 6379, 0.5);
                $dyKey = ltrim($reslut['imei'], "0") . ":Voltage";
                $kValue = $redis->get($dyKey);
                $exchangeRecordKey = $rent_content_id . ":ExchangeRecord";
                $exchangeRecordValue = $redis->get($exchangeRecordKey);
                if ($kValue) {
                    $cData['carId'] = ltrim($reslut['imei'], "0");
                    $cData['key'] = $kValue;
                    $cData['cmd'] = 'resultQuery';
                    for ($i = 0; $i < 10; $i++) {
                        $res2 = $this->VoltagePost($cData, $url);
                        if ($res2['rtCode'] == '0') {
                            break;
                        } else {
                            sleep(1);
                        }
                    }
                    if ($res2['rtCode'] == '0' && strlen($res2['rtCode']) > 0) {
                        \Think\Log::write($plate_no."请求网关电压：".$res2['result']['voltage']."--".$battery_standard, "INFO");
                        $electricity = $this->getDumpEle($res2['result']['voltage']);
                        $electricity = $electricity ? intval($electricity * 100) : 0;
                        if ($electricity > $battery_standard) {
                            //换电完成上架待租
                            $car_prompt = $this->setCarStatus($uid,$plate_no='',$reslut['id'],$operate_status);

                            //车牌号为空
                            if (empty($plate_no) || $plate_no == "null") {
                                $plate_no = M("car_item_verify")->where(["car_item_id" => $reslut['car_item_id']])->getField("plate_no");
                            }
                            //查询我预约的车辆订单完成换电取消
                            $this->cancelHaveOrder($plate_no,$uid);

                            if ($exchangeRecordValue) {
                                $operationId = $exchangeRecordValue;
                            } else {
                                $operationId = $this->operationLog($uid, $rent_content_id, $plate_no, $gis_lng, $gis_lat, $residual_battery, $electricity, $carRule, $hd_qian['residual_battery2'],$corporation_id,$pid,$reslut['sell_status']);
                                $redis->set($exchangeRecordKey, $operationId);
                                $redis->expire($exchangeRecordKey, 3);
                            }
                            \Think\Log::write($plate_no."操作记录结果：".$operationId."请求参数：" . json_encode($_POST), "INFO");
                            //换电日志
                            D('FedGpsAdditional')->exchangeLog($uid, $rent_content_id, $plate_no, $gis_lng, $gis_lat, 2);
                            //清除redis历史电压
                            $this->clearRedisVoltage($reslut['imei']);

                            $this->setDbPower($reslut['imei'], $electricity);
                            $this->response(["code" => 1, "message" => "已完成换电，请上传照片", "data" => ["operationId" => $operationId, "residual_battery" => $electricity,"car_prompt"=>$car_prompt]], 'json');
                        } else {
                            $this->response(["code" => 2, "message" => "请更换满电量电池"], 'json');
                        }
                    } else {
                        $this->response(["code" => 0, "message" => "再试一次"], 'json');
                    }
                } else {
                    $data['carId'] = ltrim($reslut['imei'], "0");
                    $data['type'] = 34;
                    $data['cmd'] = 'statusQuery';
//              $data['directRt'] = 'false';
                    $res = $this->VoltagePost($data, $url);
                    if ($res['rtCode'] == '0') {
                        if (!$kValue) {
                            $redis->set($dyKey, $res['msgkey']);
                            $redis->expire($dyKey, 60);
                        }
                        $cData['carId'] = ltrim($reslut['imei'], "0");
                        $cData['key'] = $res['msgkey'];
                        $cData['cmd'] = 'resultQuery';
                        for ($i = 0; $i < 10; $i++) {
                            $res2 = $this->VoltagePost($cData, $url);
                            if ($res2['rtCode'] == '0') {
                                break;
                            } else {
                                sleep(1);
                            }
                        }
                        if ($res2['rtCode'] == '0' && strlen($res2['rtCode']) > 0) {
                            \Think\Log::write($plate_no."请求网关电压：".$res2['result']['voltage']."--".$battery_standard, "INFO");
                            $electricity = $this->getDumpEle($res2['result']['voltage']);
                            $electricity = $electricity ? intval($electricity * 100) : 0;
                            if ($electricity > $battery_standard) {
                                //换电完成上架待租
                                $car_prompt = $this->setCarStatus($uid,$plate_no='',$reslut['id'],$operate_status);

                                //车牌号为空
                                if (empty($plate_no) || $plate_no == "null") {
                                    $plate_no = M("car_item_verify")->where(["car_item_id" => $reslut['car_item_id']])->getField("plate_no");
                                }
                                //查询我预约的车辆订单完成换电取消
                                $this->cancelHaveOrder($plate_no,$uid);

                                if ($exchangeRecordValue) {
                                    $operationId = $exchangeRecordValue;
                                } else {
                                    $operationId = $this->operationLog($uid, $rent_content_id, $plate_no, $gis_lng, $gis_lat, $residual_battery, $electricity, $carRule, $hd_qian['residual_battery2'],$corporation_id,$pid,$reslut['sell_status']);
                                    $redis->set($exchangeRecordKey, $operationId);
                                    $redis->expire($exchangeRecordKey, 3);
                                }
                                \Think\Log::write($plate_no."操作记录结果：".$operationId."请求参数：" . json_encode($_POST), "INFO");
                                //换电日志
                                D('FedGpsAdditional')->exchangeLog($uid, $rent_content_id, $plate_no, $gis_lng, $gis_lat, 2);
                                //清除redis历史电压
                                $this->clearRedisVoltage($reslut['imei']);

                                $this->setDbPower($reslut['imei'], $electricity);
                                $this->response(["code" => 1, "message" => "已完成换电，请上传照片", "data" => ["operationId" => $operationId, "residual_battery" => $electricity],"car_prompt"=>$car_prompt], 'json');
                            } else {
                                $this->response(["code" => 2, "message" => "请更换满电量电池"], 'json');
                            }
                        } else {
                            $this->response(["code" => 0, "message" => "再试一次"], 'json');
                        }
                    } else if ($res['rtCode'] == 1) {
                        $this->response(["code" => 0, "message" => "设备接收命令并返回失败"], 'json');
                    } else if ($res['rtCode'] == 3) {
                        $this->response(["code" => 0, "message" => "设备断开连接"], 'json');
                    } else if ($res['rtCode'] == 6) {
                        $this->response(["code" => 0, "message" => "命令重复"], 'json');
                    } else {
                        $this->response(["code" => 0, "message" => "查询失败 可能盒子已离线"], 'json');
                    }
                }
            }
        }else{
            $this->response(["code" => 0, "message" => "该车辆不存在"], 'json');
        }
    }

    //设置车辆车辆状态
    public  function  setCarStatus($uid,$plate_no='',$rent_content_id,$operate_status){
        //更新未完成的操作记录
        $wMap["uid"] = $uid;
        $wMap["rent_content_id"] = $rent_content_id;
        $wMap["operate"]         = array('in',array(33,34));
        $wMap["status"]          = 0;
        M($this->ol_table)->where($wMap)->setField(["status"=>1,"update_time"=>date("Y-m-d H:i:s")]);
        //老板上架
//        if($operate_status != 6){
//            M('rent_content')->where(["id"=>$rent_content_id])->setField('sell_status',1);
//        }
        $model = M($this->mebike_s_table);
        $reserve_status = $model->where(["rent_content_id"=>$rent_content_id])->getField("reserve_status");
        $data["lack_power_status"] = 0;
        if(!in_array($reserve_status,array(100,101))){
            $data["reserve_status"]    = 0;
        }
        $data["update_time"]       = date("Y-m-d H:i:s",time());
        $map["rent_content_id"]    = $rent_content_id;
        $res3 = $model->where($map)->setField($data);
        $car_status = D('FedGpsAdditional')->is_car_status($rent_content_id);
        if($operate_status != 6 && empty($car_status)){
            $model->startTrans();
            $res  = $model->where($map)->setField(["sell_status"=>1,"update_time" => date("Y-m-d H:i:s",time()),"sell_time"=>date("Y-m-d H:i:s",time())]);
            $res2 = M("rent_content")->where(["id"=>$rent_content_id])->setField(["sell_status"=>1,"update_time" => time()]);
            if($res && $res2){
                $model->commit();
            }else{
                $model->rollback();
            }
        }
        $c_arr["uid"] = $uid;
        $c_arr["plate_no"] = $plate_no;
        $c_arr["rent_content_id"] = $rent_content_id;
        $c_arr["operate_status"] = $operate_status;
        $c_arr["reserve_status"] = $reserve_status;
        $c_arr["res"]  = $res?$res:0;
        $c_arr["res2"] = $res2?$res2:0;
        $c_arr["res3"] = $res3?$res3:0;
        \Think\Log::write("车的状态：".$plate_no."返回值：" . json_encode($car_status)."参数：".json_encode($c_arr), "INFO");
        return  $car_status?array_column($car_status,"name"):"";
    }

    //设置数据库的电量
    public function setDbPower($imei = '',$power_per = 100){
        M('fed_gps_additional')->where(["imei"=>$imei])->setField('residual_battery',$power_per);
        // $data = array('residual_battery'=>$power_per,'update_battery_time'=>time());
        $data = array('residual_battery'=>$power_per);
        M('gps_additional','',$this->box_config)->where(["imei"=>$imei])->setField($data);
    }

    //换电完成取消预约订单
    public   function  cancelHaveOrder($plate_no,$uid){
        $hMap['plate_no'] = $plate_no;
        $hMap['uid'] = $uid;
        $hMap['status'] = 0;
        $have_order = M("baojia_mebike.have_order")->where($hMap)->getField('id');
        if ($have_order) {
            M("baojia_mebike.have_order")->where(['id' => $have_order])->setField(['status' => 1, 'update_time' => time()]);
        }
    }

    //清除redis历史电压
    public  function  clearRedisVoltage($imei=''){
        if (!empty($imei)) {
//            $redis = new \Redis();
//            $redis->pconnect('10.1.11.82', 6379, 0.5);
//            $key = 'prod:boxxan_analyzer' . $imei;
            $Redis = new \Redis();
            $Redis->pconnect('10.1.11.83', 36379, 0.5);
            $Redis->AUTH('oXjS2RCA1odGxsv4');
//            $Redis->pconnect('10.1.11.95', 6379, 0.5);
            $Redis->SELECT(2);
            $key = "analyzer:xab:keys:calculate:".$imei;
            $length = $Redis->LLEN($key);

            $Redis->LTRIM($key, $length, -1);
        }
    }
   

    //电压转换电量
    public function getDumpEle($voltage){
        $v = ($voltage - 43) / (0.12);
        $battery=0;
        if ($voltage > 53) { // 53 ~
            $battery=1;
        } else if ($voltage >= 43 && $voltage <= 55) { //43~55
            $battery= $v / 100;
        } else if ($voltage < 43 && $voltage > 10) { //43~10
            $battery= -1;
        }
        return sprintf('%.2f',$battery);
    }


    public  function  operationLog($uid,$rent_content_id,$plate_no,$gis_lng,$gis_lat,$residual_battery,$electricity=0,$carRule=0,$huandq=0,$corporation_id=0,$pid=0,$sell_status=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        // $model = M('operation_logging','',C('BAOJIA_CS_LINK'));
        $model = M($this->ol_table);
        $date['uid'] = $uid;
        $date['rent_content_id'] = $rent_content_id;
        $date['plate_no'] = $plate_no;
        $date['operate'] = -1;
        $date['status']  = 2;
        $date['gis_lng'] = $gis_lng;
        $date['gis_lat'] = $gis_lat;
        $date['before_battery'] = $residual_battery;
        if($electricity){
            $date['desc'] = '换电时获取时时电压的电量:'.$electricity."--点完成换电前查询库电量:".$huandq;
            $date['battery'] = $electricity;
        }else{
            $date['battery'] = 100;
        }
        if($carRule == -1){
            $date['car_rule'] = $carRule;
        }
        if($corporation_id){
            $date['corporation_id'] = $corporation_id;
        }
        if($pid){
            $date['pid'] = $pid;
        }
        if($sell_status){
            $date['pre_status'] = $sell_status;
        }
        $date['time'] = time();
        $res = $model->add($date);
        return $res;
    }

    //完成换电上传图片记录type 为 1 最新版本 否则以前版本
    public  function electricityLog($operationId=0,$uid=0,$type=0,$source=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        // $model = M('operation_logging','',C('BAOJIA_CS_LINK'));
        $model = M($this->ol_table);
        $pic = $this->upload();
        if($pic){
            $map['id'] = $operationId;
            $date['pic1'] = $pic;
            if($type == 1){
                $date['step'] = 5;
                $date['operate'] = 1;
            }else{
                $date['status'] = 1;
            }
            if($source == 2){
                $date['operate_source'] = 1;
                $date['update_time']    = date("Y-m-d H:i:s",time());
            }else{
                $date['time'] = time();
            }
            $res = $model->where($map)->save($date);
            if($res){
                $reason = D('FedGpsAdditional')->theDayCarNum($uid,0);
                $this->response(["code" => 1, "message" => "上传完成",'data'=>$reason['prompt']], 'json');
            }else{
                $this->response(["code" => 0, "message" => "上传失败"], 'json');
            }
        }else{
            $this->response(["code" => 0, "message" => "上传失败"], 'json');
        }
    }


    public function upload(){
        if(isset($_FILES["picture"])){
            $_FILES["uploadfile"]=$_FILES["picture"];
            // $root   =  "E:\php\wamp\www\qiye-admin\Application\public\img";
            $root   =  "/hd2/web/upfiles/pic/Public/img/pic";
            $root1="/" . date("Y") . "/" . date("md") . "/";

            $filename = md5(time().rand(100,100)) . ".png";
            $filepath = $root.$root1.$filename;
            $rr = $this->createdir($root.$root1, 0777);
            $pic=move_uploaded_file($_FILES["uploadfile"]["tmp_name"], $filepath);
            if($pic){
                return $pic_url='Public/img/pic'.$root1.$filename;
            }else{
                return $pic_url= '';
            }
        }
        return $pic_url= '';
    }

    protected function createdir($path, $mode) {
        if (is_dir($path)) {  //判断目录存在否，存在不创建
            //echo "目录'" . $path . "'已经存在";
            return true;
        } else { //不存在创建
            $re = mkdir($path, $mode, true); //第三个参数为true即可以创建多极目录
            if ($re) {
                //echo "目录创建成功";
                return true;
            } else {
                //echo "目录创建失败";
                return false;
            }
        }
    }



    //车辆历史操作记录
    public   function  carHistoryRecord($uid=3031365,$rent_content_id=5862402,$search="",$time=0,$operate=0,$page=1,$corporation_id=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        // $model = M('operation_logging','',C('BAOJIA_CS_LINK'));
        $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
        $model = M($this->ol_table);

        $lmap['rent_content_id'] = $rent_content_id;
        //搜索内容
        if($search){
            $umap["user_name"] = array('like', '%' . $search . '%');
//			 $umap["status"]    = 1;
            $repair = M('baojia_mebike.repair_member')->field('user_id')->where($umap)->group("user_id")->select();
            if($repair){
                $repair = array_column($repair, 'user_id');
                $lmap['uid'] = array('in', $repair);
            }else{
                $lmap['uid'] = "";
            }
        }

        if($time){
            $start_time = strtotime(date("Y-m-d",$time)." 0:0:0");
            $end_time   = strtotime(date("Y-m-d",$time)." 23:59:59");
            $lmap['time'] = array('between',array($start_time,$end_time));
        }
        if($operate){
            $operate = intval($operate);
            if($operate == 1){
                $lmap['operate'] = array('in',array(-1,1,2,44));
            }else{
                $lmap['operate'] = $operate;
            }
        }else{
            if($user_arr['role_type'] == '运维'){
//                $lmap['operate'] = array('not in',array(7,9,10));
//                $lmap['uid'] = array('neq',$user_arr['user_id']);
                $lmap['_string'] = " (uid = {$user_arr['user_id']} and operate IN (7, 9, 10)) or operate not IN (7, 9, 10)";
            }else{
                $lmap['operate'] = array('neq',0);
            }
        }

        $page = $page?$page:1;
        $page_size = 15;
        $offset = $page_size * ($page - 1);
        $result = $model->field('id,uid,plate_no,operate,car_status,car_rule,time,source,desc')->where($lmap)->order('time desc')->limit($offset,$page_size)->select();

        if($result){
            foreach ($result as &$val){
				$val["desc"] = $val["desc"]?$val["desc"]:"";
                if($val['operate'] == 5){
                    if($val['car_status'] == 1){
                        $val['operate_id'] = 51;
                    }else{
                        $val['operate_id'] = 52;
                    }
                }else if(($val['operate'] == 100 && I('post.device_os') != 'Android') || $val['operate'] == 25){
                    $val['operate_id'] = 51;
                }else if($val['operate'] == 18 || $val['operate'] == 35){
                    $val['operate_id'] = 11;
                }else{
                    $val['operate_id'] = $val['operate'];
                }
                //查询运维姓名
                $rmap['user_id'] = $val['uid'];
                $repair = M('baojia_mebike.repair_member')->where($rmap)->getField('user_name');
                $val['user_name'] = $repair?$repair:"";
                //是否弹出浮层
                if(in_array($val['operate'],array(-1,1,2,4,5,6,11,16,17,18,25,35,100))){
                    $val['is_spring'] = 1;
                }else if(in_array($val['operate'],array(7,9,10,19))){
                    $val['is_spring'] = 2;
                }else{
                    $val['is_spring'] = 0;
                }

                if($val['operate'] == 1){
                    if($val['car_rule'] == -1){
                        $val['operate'] = '无电离线换电';
                    }else{
                        $val['operate'] = '换电设防';
                    }
                }else if($val['operate'] == -1){
                    if($val['car_rule'] == -1){
                        $val['operate'] = '无电离线换电';
                    }else {
                        $val['operate'] = '换电未设防';
                    }
                }else if($val['operate'] == 2){
                    if($val['car_rule'] == -1){
                        $val['operate'] = '无电离线换电';
                    }else {
                        $val['operate'] = '换电设防失败';
                    }
                }else if($val['operate'] == 3){
                    $val['operate'] = ($val['source'] == 2)?'QY确认回收':'确认回收';
                }else if($val['operate'] == 4){
                    $val['operate'] = '完成小修';
                }else if($val['operate'] == 5 || $val['operate'] == 100){
                    if($val['source'] == 1 && $val['operate'] == 100){
                        $val['operate'] = '备注信息';   //微信h5运维端老数据
                    }else if($val['source'] == 1){
                        $val['operate'] = '下架回收,来自H5';
                    }else{
                        if($val['car_status'] == 2){
                            $val['operate'] = '下架回收,待调度';
                        }else{
                            $val['operate'] = '下架回收,待维修';
                        }
                    }
                }else if($val['operate'] == 6){
                    $val['operate'] = '待小修';
                }else if($val['operate'] == 7){
                    $val['operate'] = '车辆丢失';
                }else if($val['operate'] == 8){
                    if($val['source'] == 1){
                        $val['operate'] = 'H5上架待租';
                    }else if($val['source'] == 2){
                        $val['operate'] = 'QY上架待租';
                    }else if($val['source'] == 3){
                        $val['operate'] = 'ICSS上架待租';
                    }else{
                        $val['operate'] = '上架待租';
                    }
                }else if($val['operate'] == 9){
                    $val['operate'] = '疑失';
                }else if($val['operate'] == 10){
                    $val['operate'] = '疑难';
                }else if($val['operate'] == 11){
                    $val['operate'] = '电池丢失';
                }else if($val['operate'] == 12){
                    $val['operate'] = '设防';
                }else if($val['operate'] == 13){
                    $val['operate'] = '撤防';
                }else if($val['operate'] == 14){
                    $val['operate'] = '启动';
                }else if($val['operate'] == 15){
                    if($val['source'] == 1){
                        $val['operate'] = 'H5人工停租';
                    }else if($val['source'] == 2){
                        $val['operate'] = 'QY人工停租';
                    }else{
                        $val['operate'] = 'ICSS人工停租';
                    }
                }else if($val['operate'] == 16){
                    $val['operate'] = '手动矫正';
                }else if($val['operate'] == 17){
                    $val['operate'] = '自动矫正';
                }else if($val['operate'] == 18){
                    $val['operate'] = '车辆找回上报';
                }else if($val['operate'] == 19){
                    $val['operate'] = $val['desc'];
                }else if($val['operate'] == 23){
                    $val['operate'] = "无此故障";
                }else if($val['operate'] == 24){
                    $val['operate'] = "有此故障";
                }else if($val['operate'] == 30){
                    $val['operate'] = "检修任务";
                }else if($val['operate'] == 31){
                    $val['operate'] = "寻车任务";
                }else if($val['operate'] == 32){
                    $val['operate'] = "验收入库";
                }else if($val['operate'] == 33){
                    $val['operate'] = "开始换电";
                }else if($val['operate'] == 34){
                    $val['operate'] = "开仓锁";
                }else if($val['operate'] == 35){
                    $val['operate'] = "故障上报";
                }else if($val['operate'] == 36){
                    $val['operate'] = "备注车辆";
                }else if($val['operate'] == 37){
                    $val['operate'] = "被扣押上报";
                }else if($val['operate'] == 42){
                    $val['operate'] = "回库任务";
                }else if($val['operate'] == 43){
                    $val['operate'] = "调度任务";
                }else if($val['operate'] == 44){
                    $val['operate'] = "换电任务";
                }else if($val['operate'] == 45){
                    $val['operate'] = "关仓";
                }else if($val['operate'] == 46){
                    $val['operate'] = "重启盒子";
                }else if($val['operate'] == 25){
                    $val['operate'] = "特殊下架";
                }else if($val['operate'] == 26){
                    $val['operate'] = "更换盒子";
                }else{
                    $val['operate'] = '';
                }

                $val['time1'] = date("H:i",$val['time']);
                $val['date'] = date("Y-m-d",$val['time']);
                unset($val['uid'],$val['time'],$val['car_status']);
            }
        }else{
            $result = [];
        }
        //操作类型
        $type_arr[0]['operate_id'] = 1;
        $type_arr[0]['operate']    = "换电";
        $type_arr[1]['operate_id'] = 8;
        $type_arr[1]['operate']    = "上架待租";
        $type_arr[2]['operate_id'] = 5;
        $type_arr[2]['operate']    = "下架回收";
        $type_arr[3]['operate_id'] = 6;
        $type_arr[3]['operate']    = "待小修";
        $type_arr[4]['operate_id'] = 9;
        $type_arr[4]['operate']    = "疑失";
        $type_arr[5]['operate_id'] = 10;
        $type_arr[5]['operate']    = "疑难";
        $type_arr[6]['operate_id'] = 7;
        $type_arr[6]['operate']    = "车辆丢失";
        $type_arr[7]['operate_id'] = 4;
        $type_arr[7]['operate']    = "完成小修";
        $type_arr[8]['operate_id'] = 3;
        $type_arr[8]['operate']    = "确认回收";
        $type_arr[9]['operate_id'] = 11;
        $type_arr[9]['operate']    = "电池丢失";
        $type_arr[10]['operate_id'] = 12;
        $type_arr[10]['operate']    = "设防";
        $type_arr[11]['operate_id'] = 13;
        $type_arr[11]['operate']    = "撤防";
        $type_arr[12]['operate_id'] = 14;
        $type_arr[12]['operate']    = "启动";
        $type_arr[13]['operate_id'] = 15;
        $type_arr[13]['operate']    = "人工停租";
        $type_arr[14]['operate_id'] = 16;
        $type_arr[14]['operate']    = "手动矫正";
        $type_arr[15]['operate_id'] = 17;
        $type_arr[15]['operate']    = "自动矫正";
//        echo "<pre>";
//        print_r($type_arr);

        $this->response(["code" => 1, "message" => "数据接收完成","data"=>['type_arr'=>$type_arr,"historyRecord"=>$result,"page"=>$page]], 'json');

    }

    //个人历史操作记录
    public   function  personalHistoryRecord($uid=2790831,$search="",$time=0,$operate=0,$page=1,$device_os='',$version='1.1.3',$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        // $model = M('operation_logging','',C('BAOJIA_CS_LINK'));
        $model = M($this->ol_table);
        if($device_os == 'Android'){
            $pastVersion = '1.1.1';
        }else{
            $pastVersion = '1.1.2';
        }
        $compatible = $this->versionCompare($version,$pastVersion); //当前版本只要大于$pastVersion就显示换电未上传图片
        //搜索内容
        if($search){
            $map['plate_no'] = $search;
            $map['address_type'] = 99;
            $map['sort_id'] = 112;
            $res = M('rent_content_search')->where($map)->getField('rent_content_id');
            $lmap['rent_content_id'] = $res;
        }

        if($time){
            $start_time = strtotime(date("Y-m-d",$time)." 0:0:0");
            $end_time   = strtotime(date("Y-m-d",$time)." 23:59:59");
            $lmap['time'] = array('between',array($start_time,$end_time));
        }
        if($operate){
            $operate = intval($operate);
            if($operate == 1){
                $lmap['operate'] = array('in',array(1,2));
            }else{
                $lmap['operate'] = $operate;
            }
        }
        $lmap['uid'] = $uid;
        if($compatible == 0){
            $lmap['_string'] = " operate in(-1,1,2,3,4,5,6,11,18) or (operate in(20,21) and status = 1)";
        }else{
            $lmap['status'] = array('neq',2);
            $lmap['_string'] = " operate in(-1,1,2,3,4,5,6) ";
        }
        $page = $page?$page:1;
        $page_size = 15;
        $offset = $page_size * ($page - 1);
        $result = $model->field('id,plate_no,pic1,pic2,gis_lng,gis_lat,operate,car_status,status,time,source,car_rule')->where($lmap)->order('time desc')->limit($offset,$page_size)->select();

        if($result){
            foreach ($result as &$val){
                if($val['operate'] == 5){
                    if($val['car_status'] == 1){
                        $val['operate_id'] = 51;
                    }else{
                        $val['operate_id'] = 52;
                    }
                }else{
                    if(($val['operate'] == -1 || $val['operate'] == 1 || $val['operate'] == 2) && empty($val['pic1']) && $compatible == 0){
                        $val['operate_id'] = -2;  //换电未上传图片状态
                    }else if($val['operate'] == 18){
                        $val['operate_id'] = 11;
                    }else{
                        $val['operate_id'] = $val['operate'];
                    }
                }
                if($val['operate'] == 1){
                    if($val['car_rule'] == -1){
                        $val['operate'] = '无电离线换电';
                    }else{
                        $val['operate'] = '换电设防';
                    }
                }else if($val['operate'] == -1){
                    if($val['car_rule'] == -1){
                        $val['operate'] = '无电离线换电';
                    }else {
                        $val['operate'] = '换电未设防';
                    }
                }else if($val['operate'] == 2){
                    if($val['car_rule'] == -1){
                        $val['operate'] = '无电离线换电';
                    }else {
                        $val['operate'] = '换电设防失败';
                    }
                }else if($val['operate'] == 3){
                    $val['operate'] = ($val['source'] == 2)?'QY确认回收':'确认回收';
                }else if($val['operate'] == 4){
                    $val['operate'] = '完成小修';
                }else if($val['operate'] == 5){
                    if($val['source'] == 1){
                        $val['operate'] = '下架回收,来自H5';
                    }else{
                        if($val['car_status'] == 2){
                            $val['operate'] = '下架回收,待调度';
                        }else{
                            $val['operate'] = '下架回收,待维修';
                        }
                    }
                }else if($val['operate'] == 11){
                    $val['operate'] = '电池丢失';
                }else if($val['operate'] == 18){
                    $val['operate'] = '车辆找回上报';
                }else if($val['operate'] == 20){
                    $val['operate'] = '绑定盒子';
                }else if($val['operate'] == 21){
                    $val['operate'] = '解绑盒子';
                }else{
                    $val['operate'] = '待小修';
                }

                $val['is_spring'] = 1;
                $val['time1'] = date("H:i",$val['time']);
                $val['date'] = date("Y-m-d",$val['time']);
                unset($val['uid'],$val['car_status'],$val['time'],$val['pic1'],$val['pic2'],$val['gis_lng'],$val['gis_lat']);
            }
        }else{
            $result = [];
        }
        //操作类型
        $type_arr[0]['operate_id'] = 1;
        $type_arr[0]['operate']    = "换电设防";
        $type_arr[1]['operate_id'] = 2;
        $type_arr[1]['operate']    = "换电设防失败";
        $type_arr[2]['operate_id'] = 3;
        $type_arr[2]['operate']    = "确认回收";
        $type_arr[3]['operate_id'] = 4;
        $type_arr[3]['operate']    = "完成小修";
        $type_arr[4]['operate_id'] = 5;
        $type_arr[4]['operate']    = "下架回收";
        $type_arr[5]['operate_id'] = 6;
        $type_arr[5]['operate']    = "待小修";
        $type_arr[6]['operate_id'] = 11;
        $type_arr[6]['operate']    = "电池丢失";
        $type_arr[7]['operate_id'] = 18;
        $type_arr[7]['operate']    = "车辆找回上报";
//        echo "<pre>";
//        print_r($type_arr);

        $this->response(["code" => 1, "message" => "数据接收完成","data"=>['type_arr'=>$type_arr,"historyRecord"=>$result,'page'=>$page]], 'json');

    }

    //个人操作记录统计
    public   function  historyRecordCount($uid=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        // $model = M('operation_logging','',C('BAOJIA_CS_LINK'));
        $model = M($this->ol_table);
        $start_time = strtotime(date("Y-m-d",time())." 0:0:0");
        $end_time   = strtotime(date("Y-m-d",time())." 23:59:59");
        $dq_month_one = date('Y-m-01', strtotime(date("Y-m-d")));
        $start_dq_month=strtotime(date('Y-m-01', strtotime(date("Y-m-d"))));
        $end_dq_month  = strtotime(date('Y-m-d', strtotime("$dq_month_one +1 month -1 day"))." 23:59:59");

        //查询当天无电离线换电
        $wMap['time'] = array('between',array($start_time,$end_time));
        $wMap['uid']  = $uid;
        $wMap['car_rule'] = -1;
        $wMap['operate'] = array('in',array(-1,1,2));
        $no_off_line = $model->where($wMap)->count('id');
        $lmap['time'] = array('between',array($start_time,$end_time));
        $lmap['uid']  = $uid;
        $lmap['status'] = array('neq',2);
        $lmap['operate'] = array('in',array(-1,1,2,3,4,5,6,18));
        $resday = $model->field('count(1) num,operate')->where($lmap)->group('operate')->select();
        $resday = array_column($resday, 'num','operate');

        $day_arr['hd_car_num'] = $resday[1] + $resday[2];
        $day_arr['repair_car_num'] = intval($resday[6]);
        $day_arr['recover_car_num'] = intval($resday[5]);
        $day_arr['complete_recover_car_num'] = intval($resday[4]);
        $day_arr['confirm_recovery_car_num'] = intval($resday[3]);
        $day_arr['no_off_line_num'] = intval($no_off_line);
        $bMap['time']    = array('between',array($start_time,$end_time));
        $bMap['uid']     = $uid;
        $bMap['status']  = 1;
        $bMap['operate'] = 20;
        $day_arr['box_num'] = $model->where($bMap)->count('id');
        $day_arr['total_car'] = array_sum($resday) + $day_arr['box_num'];
        //当前月的操作记录统计
        $mmap['time'] = array('between',array($start_dq_month,$end_dq_month));
        $mmap['uid']  = $uid;
        $mmap['status'] = array('neq',2);
        $mmap['operate'] = array('in',array(-1,1,2,3,4,5,6,18));
        $resmonth = $model->field('count(1) num,operate')->where($mmap)->group('operate')->select();
        $resmonth = array_column($resmonth, 'num','operate');
        $month_arr['total_car'] = array_sum($resmonth);
        $month_arr['hd_car_num'] = $resmonth[1] + $resmonth[2];
        $month_arr['repair_car_num'] = intval($resmonth[6]);
        $month_arr['recover_car_num'] = intval($resmonth[5]);
        $month_arr['complete_recover_car_num'] = intval($resmonth[4]);
        $month_arr['confirm_recovery_car_num'] = intval($resmonth[3]);
//        echo "<pre>";
//        print_r($month_arr);
        $this->response(["code" => 1, "message" => "数据接收完成","data"=>['day_count'=>$day_arr,"month_count"=>$month_arr]], 'json');
    }

    //查询个人工作记录详情
    public  function   operationInfo($operationId="813081"){

        // $model = M('operation_logging','',C('BAOJIA_CS_LINK'));
        $model = M($this->ol_table);
        $map['id'] = $operationId;
        $result = $model->field('rent_content_id,gis_lng,gis_lat,pic1,pic2,operate,car_status,desc')->where($map)->find();

        if($result){
            $areaLogic= new \Api\Logic\Area();
            $address = $areaLogic->GetAmapAddress($result['gis_lng'],$result['gis_lat']);
            $result['address'] = $address?$address:"";
            if($result['operate'] == 1 || $result['operate'] == 2 || $result['operate'] == -1){
                $result['title'] = "换电附件";
                $result['car_status'] = "";
                $result['pic1'] = $result['pic1']?"http://pic.baojia.com/".$result['pic1']:"";
            }else if($result['operate'] == 11 || $result['operate'] == 18 || $result['operate'] == 35){
                if($result['operate'] == 18){
                    $result['title'] = "车辆找回上报附件";
                }else if($result['operate'] == 35){
					$result['title'] = "故障上报";
				}else{
                    $result['title'] = "电池丢失附件";
                }
                $result['car_status'] = $result['desc']?$result['desc']:"";
                $pic_url = [];
                if($result['pic1']){
                    $pic_url[]= "http://pic.baojia.com/".$result['pic1'];
                }
                if($result['pic2']){
                    $pic_url[]= "http://pic.baojia.com/".$result['pic2'];
                }
                $pic_url = implode(',',$pic_url);
                $result['pic1'] = $pic_url?$pic_url:"";
            }else if($result['operate'] == 4){
                $result['title'] = "完成小修";
                if($result['car_status'] == 3){
                    $result['car_status'] = "脚蹬子缺失";
                }else if($result['car_status'] == 4){
                    $result['car_status'] = "车支子松动";
                }else if($result['car_status'] == 5){
                    $result['car_status'] = "车灯损坏";
                }else if($result['car_status'] == 6){
                    $result['car_status'] = "车灯松动";
                }else if($result['car_status'] == 7){
                    $result['car_status'] = "车把松动";
                }else if($result['car_status'] == 8){
                    $result['car_status'] = "鞍座丢失";
                }else if($result['car_status'] == 9){
                    $result['car_status'] = "二维码丢失";
                }else{
                    $result['car_status'] = "";
                }
                $result['pic1'] = $result['pic2']?"http://pic.baojia.com/".$result['pic2']:"";
            }else if($result['operate'] == 6){
				$result['title'] = "待小修附件";
				if($result['car_status'] == 3){
					$result['car_status'] = "脚蹬子缺失";
				}else if($result['car_status'] == 4){
					$result['car_status'] = "车支子松动";
				}else if($result['car_status'] == 5){
					$result['car_status'] = "车灯损坏";
				}else if($result['car_status'] == 6){
					$result['car_status'] = "车灯松动";
				}else if($result['car_status'] == 7){
					$result['car_status'] = "车把松动";
				}else if($result['car_status'] == 8){
					$result['car_status'] = "鞍座丢失";
				}else{
					$result['car_status'] = "二维码丢失";
				}
                $result['pic1'] = $result['pic1']?"http://pic.baojia.com/".$result['pic1']:"";
            }else if($result['operate'] == 5 || $result['operate'] == 25){
                if($result['operate'] == 25){
					$result['title'] = "特殊下架";
					$result['car_status'] = $result['desc']?$result['desc']:"";
				}else{
					if($result['car_status'] == 2){
						$result['title'] = "待调度";
						$result['car_status'] = "";
					}else{
						$car_str = "";
						if(strpos($result['car_status'],'10') !== false){
							$car_str .= "车辆线路损坏\r\n";
						}
						if(strpos($result['car_status'],'11') !== false){
							$car_str .= "车辆丢失部件\r\n";
						}
						if(strpos($result['car_status'],'12') !== false){
							$car_str .= "无法打开电子锁\r\n";
						}
						if(strpos($result['car_status'],'13') !== false){
							$car_str .= "换电无法正常上线\r\n";
						}
						if(strpos($result['car_status'],'14') !== false){
							$car_str .= "无法设防\r\n";
						}
						if(strpos($result['car_status'],'15') !== false){
							$car_str .= "拧车把不走\r\n";
						}
						if(strpos($result['car_status'],'16') !== false){
							$car_str .= "二维码损坏\r\n";
						}
						if(strpos($result['car_status'],'17') !== false){
							$car_str .= "电池丢失并车辆被破坏\r\n";
						}
						if(strpos($result['car_status'],'18') !== false){
							$car_str .= $result['desc'];
						}
						$result['title'] = "待维修";
						if($car_str){
							$result['car_status'] = $car_str;
						}else{
							$result['car_status'] = $result['desc'];
						}
					}
				}
                $result['pic1'] = "";
            }else if($result['operate'] == 100){
                $result['title'] = "待维修";
                $result['car_status'] = $result['desc'];
                $result['pic1'] = "";
            }else{
                if($result['operate'] == 16 || $result['operate'] ==17){
                    $result['title'] = "位置矫正";
                }else{
                    $result['title'] = "车辆回收位置";
                }
                $result['car_status'] = "";
                $result['pic1'] = "";
            }
            $gps = D('Gps');
            $gps_arr = $gps->gcj_decrypt($result['gis_lat'],$result['gis_lng']);
            $pt = [$gps_arr['lon'],$gps_arr['lat']];
			$car_arr = M("rent_content")->alias("rc")->field("cid.imei")
			      ->join("car_item_device cid on rc.car_item_id = cid.car_item_id","left")
				  ->where(["rc.id"=>$result['rent_content_id']])->find();
            $area = $areaLogic->isXiaomaInArea($result['rent_content_id'],$pt,$car_arr["imei"]);
            if($result['operate'] == 100){
                $result['area'] = $result['desc'];
            }else{
                $result['area'] = $area?"界内":"界外";
            }

            unset($result['gis_lng'],$result['gis_lat'],$result['operate'],$result['pic2'],$result['rent_content_id'],$result['desc']);
            // file_put_contents($_SERVER['DOCUMENT_ROOT']."canshu66666.txt",json_encode($result));
            $this->response(["code" => 1, "message" => "数据接收完成","data"=>['operationInfo'=>$result]], 'json');
        }else{
            $this->response(["code" => 0, "message" => "该操作记录不存在"], 'json');
        }
    }

   
    //版本比较
    public  function  versionCompare($version='1.1',$pastVersion='1.1.1'){
        $version = explode('.',$version);
        $pastVersion = explode('.',$pastVersion);
        if(intval($version[0]) < intval($pastVersion[0])){
            $isForced = 1;
        }else if(intval($version[0]) > intval($pastVersion[0])){
            $isForced = 0;
        }else{
            if(intval($version[1]) < intval($pastVersion[1])){
                $isForced = 1;
            }else if(intval($version[1]) > intval($pastVersion[1])){
                $isForced = 0;
            }else{
                if(intval($version[2]) < intval($pastVersion[2])){
                    $isForced = 1;
                }else if(intval($version[2]) > intval($pastVersion[2])){
                    $isForced = 0;
                }else{
                    $isForced = 0;
                }
            }
        }
        return $isForced;
    }

 

    private function VoltagePost($data, $url)
    {
        $json = json_encode($data);
//        echo  $json;die;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        curl_setopt($ch, CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json)
            ]
        );

        $output = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($output, true);
        return $data;
    }


    //小蜜重置通知
    public function setpower($imei,$power = 1)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'powerSet';
        $data['power'] = $power;
        $r = $this->post($data);
//         echo(json_encode($r));
        return $r;
    }

    private function post($data, $nosign)
    {
        $postUrl = $this->url;
        if($nosign){
            $postUrl = $this->url2;
        }
        $sign = $this->getSign3($data, $this->key);
        $data['sign'] = $sign['sign'];
        $json = json_encode($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        curl_setopt($ch, CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json)
            ]
        );

        /*if (is_array($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_HEADER, 0);
        }*/
        $output = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($output, true);
        return $data;
    }

    /**
     * 对数据签名
     * @param array $params 需要参加签名的参数kv数组
     * @param string $secret 密钥
     * @param string $joiner
     * @param string $separator
     * @return array
     */
    private function getSign3($params, $secret, $joiner = '&', $separator = '=')
    {
        $preStr = '';
        ksort($params);
        //$first = true;
        foreach ($params as $k => $v) {
            $kName = strtolower($k);
            if (!in_array($kName, ['sign', 'msg', ''])) {
                $preStr = $preStr . $k . $separator . $v . $joiner;
            }
        }

        if (!empty($preStr)) {
            $preStr = substr($preStr, 0, -strlen($separator)) . $secret;
        }
        $sign = md5($preStr);
        return ['sign' => $sign, 'value' => $preStr];
    }


    public function gpsStatusInfo($imei){

        $info = M("baojia_box.gps_status",null,$this->box_config)->field('latitude,longitude')->where(["imei"=>$imei])->find();

        $gps=D('Gps');
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_latitude"] = $gd["lat"];
        $info["gd_longitude"] = $gd["lon"];
        return $info;
    }

  
    /***
     * 换电过程中网关操作
     * @param $user_id  员工id
     * @param $operationId  操作记录id
     * @param $corporation_id  公司id
     * @param $rent_content_id 车辆id
     * @param $operation_type 操作类型  34=开舱锁  35换电设防  5=设防
     * @param $gis_lng 手机定位，高德坐标经度
     * @param $gis_lat 手机定位，高德坐标纬度
     */
    public   function   boxGatewayOperating($corporation_id=0,$user_id=0,$rent_content_id=0,$operation_type=0,$gis_lng='',$gis_lat='',$operationId=0,$pid=0){

        $map['rc.id'] = $rent_content_id;
        $reslut = M('rent_content')->alias('rc')->field('rc.id,cid.imei,cid.device_type,rc.car_item_id')
            ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
            ->where($map)->find();
        if(!$reslut){
            $this->ajaxReturn(["code" => 0, "message" => "该车不存在"], 'json');
        }
        if($operation_type==35){
            $result = D('Control')->control($corporation_id,$user_id,$rent_content_id,5,$gis_lng,$gis_lat);
        }else{
            $result = D('Control')->control($corporation_id,$user_id,$rent_content_id,$operation_type,$gis_lng,$gis_lat);
        }

        if($operation_type==35 && empty($operationId)){
            $this->ajaxReturn(["code" => 0, "message" => "参数不完整"], 'json');
        }
        if($result['code'] == 1){
            if($operation_type==35){
                M($this->ol_table)->where(['id'=>$operationId])->setField('operate',1);
                $reason = D('FedGpsAdditional')->theDayCarNum($user_id,0);
            }else{
                $start_hd = D("FedGpsAdditional")->undone_operation($user_id,$rent_content_id,34);
                if(!$start_hd){
                    D("FedGpsAdditional")->operation_log($user_id,$rent_content_id,$result["plate_no"],$gis_lng,$gis_lat,34,$corporation_id,0,0,$pid);
                }
            }
            $prompt = $reason['prompt']?$reason['prompt']:"";
            $r=A('Operation')->repairAdd($user_id,$rent_content_id,$result["plate_no"],$operation_type);
            $this->ajaxReturn(["code" => 1, "message" => $result['message'],"result"=>$r,"data"=>$prompt], 'json');
        }elseif($result['code'] == 0){
            if ($operation_type == 35){
                M($this->ol_table)-> where(['id'=>$operationId])->setField('operate',2);
                $reason = D('FedGpsAdditional')->theDayCarNum($user_id,0);
            }
            $prompt = $reason['prompt']?$reason['prompt']:"";
            $this->ajaxReturn(["code" => 0, "message" => $result['message'],"data" => $prompt], 'json');
        }else{
            $this->ajaxReturn($result);
        }
    }

    /* 特殊工单是否弹出工单框
     *$uid  int      用户id
    * $wOrderId  int  工单id
   **/
    public   function   SpecialWorkOrder($uid=918200,$wOrderId=318,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        if($uid && $wOrderId){
            $user_arr = A('Battery')->beforeUserInfo($uid);
            if(!$user_arr){
                $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
            }
            $model = M("dispatch_order","",'BAOJIA_CS_LINK');
            $map["id"]  = $wOrderId;
            $map["uid"] = $uid;
            $map["is_special"]    = 1;
            $map["verify_status"] = 1;
            $res = $model->where($map)->field("id,plate_no,special_address,special_remark")->find();
            if($res){
                if($res["plate_no"]){
                    $res["work_type"]    = 1;
                }else{
                    $res["work_type"]    = 0;
                }
                $res["workOrder_title"]  = "特殊工单".$res["id"];
                $res["work_status"]      = "待处理";
                $res["work_order_task"]  = "拉回";
                unset($res["plate_no"]);

                $this->ajaxReturn(["code" => 1, "message" => "数据接收成功","data"=>$res], 'json');
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "该特殊工单不存在"], 'json');
            }

        }else{
            $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
        }
    }


    /***
     *特殊工单车辆上报
     **/
    public   function   SpecialCarReported($uid=2658265,$wOrderId=0,$plate_no='',$gis_lng='',$gis_lat='',$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $upload_image = new \Api\Logic\UploadImage();
        $model = M("baojia_mebike.dispatch_order");
        if($uid && $wOrderId && $plate_no && $gis_lng) {
            if( substr($plate_no,0,2) == 'dd' ){
                $plate_no = str_replace('dd', 'DD', $plate_no);
            }elseif( substr($plate_no,0,2) == 'XM'){
                $plate_no = $plate_no;
            }elseif( substr($plate_no,0,2) !== 'DD'){
                if( substr($plate_no,0,1)==8 ){
                    $plate_no = 'XM'.$plate_no;
                }else{
                    $plate_no = 'DD'.$plate_no;
                }
            }
            $user_arr = A('Battery')->beforeUserInfo($uid);
            if (!$user_arr) {
                $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
            }
            $car_arr = M("car_item_verify")->alias('civ')->field("rc.id,civ.plate_no,civ.car_item_id")
                ->join("rent_content rc on civ.car_item_id = rc.car_item_id","left")
                ->where(["civ.plate_no" => $plate_no])->find();
            if(!$car_arr){
                $this->ajaxReturn(["code" => 0, "message" => "该车辆不存在"], 'json');
            }

            $vMap["plate_no"] = $car_arr["plate_no"];
            $vMap["verify_status"] = array('in',array(1,2,3));
            $res_arr = $model->field("id,verify_status")->where($vMap)->find();
            \Think\Log::write("特殊车辆上报".$plate_no."请求参数：" . json_encode($_POST)."返回参数：".json_encode($res_arr), "INFO");
            if($res_arr["verify_status"] == 3 && !empty($res_arr["id"])){
                $this->response(["code" => 0, "message" => "待审核的车辆不能操作"], 'json');
            }else if(($res_arr["verify_status"] == 1 || $res_arr["verify_status"] == 2) && !empty($res_arr["id"])){
                $model->where(["id"=>$res_arr["id"]])->setField(["verify_status"=>-1,"update_time"=>time()]);
            }

            if( isset($_FILES["pic1"]) && isset($_FILES["pic2"]) ){
                $pic = $upload_image->upload($_FILES["pic1"],$_FILES["pic2"]);
                $pic1 = $pic["pic1"];
                $pic2 = $pic["pic2"];
            }
            if(empty($pic1['short_path']) || empty($pic2['short_path'])){
                $this->response(["code" => 0, "message" => "图片缺失，请重新上传"], 'json');
            };
            $map["id"] = $wOrderId;
            $map["uid"] = $uid;
            $map["is_special"] = 1;
            $map["verify_status"] = 1;
            $res = $model->where($map)->field("id,plate_no,special_address,special_remark")->find();

            if ($res) {
                $wData["plate_no"] = $car_arr["plate_no"];
                $wData["car_item_id"] = $car_arr["car_item_id"];
                $wData["rent_content_id"] = $car_arr["id"];
                $wData["pic_car"] = $pic1['short_path'];
                $wData["pic_env"] = $pic2['short_path'];
                $wData["reported_lng"] = $gis_lng;
                $wData["reported_lat"] = $gis_lat;
                $areaLogic= new \Api\Logic\Area();
                $address = $areaLogic->GetAmapAddress($gis_lng,$gis_lat);
                $wData["reported_address"] = $address?$address:'';
                $wData["reported_time"] = time();
                $result = $model->where(["id"=>$res["id"]])->save($wData);
                if($result){
                    $car_info["wOrderId"] = $wOrderId;
                    $car_info["uid"]      =  $uid;
                    $car_info["plate_no"] = $plate_no;
                    $this->ajaxReturn(["code" => 1, "message" => "车辆上报成功","data"=>$car_info], 'json');
                }else{
                    $this->ajaxReturn(["code" => 0, "message" => "车辆上报失败"], 'json');
                }
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "该特殊工单不存在"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
        }
    }

    /***
     *故障排查详情
     *pid      int     任务id
     ***/
    public  function  faultScreening($uid=2623840,$rent_content_id=2359990,$pid=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $user_arr = A('Battery')->beforeUserInfo($uid);
        if (!$user_arr) {
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
        $oMap["pid"]  = $pid;
        $oMap["rent_content_id"] = $rent_content_id;
        $oMap["operate"] = array("in",array(23,24));
        $operation = M($this->ol_table)->field("car_status")->where($oMap)->order("time desc")->find();
        if($operation){
            $repairs_id = explode(",",$operation["car_status"]);
            $map["id"] = array('in',$repairs_id);
        }else{
            $isFault = D("FedGpsAdditional")->isFaultScreening($rent_content_id, $uid);

            if (!$isFault) {
                $this->ajaxReturn(["code" => 0, "message" => "无需排查"], 'json');
            }

            //查询故障信息
            $map["rent_content_id"] = $rent_content_id;
            $map["operate"] = $isFault["operate"];
            $map["status"] = array("in",array(0,1));
            if(empty($isFault["user_id"]) || $isFault["user_id"] == null){
                $map["uid"] = "";
            }else{
                $map["uid"] = array("in", $isFault["user_id"]);
            }
        }

        $fault = M("xiaomi_repairs")->field("id,uid,operate,code,proposal,pic1,pic2,pic3,pic4,create_time")
            ->where($map)->order("create_time desc")->group("uid")->limit(3)->select();

        if(!$fault){
            $this->ajaxReturn(["code" => 0, "message" => "故障排查信息不存在"], 'json');
        }

        foreach ($fault as &$val){
            //查询上报用户
            $user = M("member")->field("CONCAT(last_name,first_name) user_name")->where(["uid"=>$val["uid"]])->find();
            $val["user_name"]  = $user["user_name"]?$user["user_name"]:"";
            if($val["operate"] == 7){
                $val["fault_type"] = $val["proposal"];
            }else{
                $val["fault_type"] = $this->fault_type($val["code"]);
            }
            $val["pic1"] = $val["pic1"]?"http://pic.baojia.com/".$val["pic1"]:"";
            $val["pic2"] = $val["pic2"]?"http://pic.baojia.com/".$val["pic2"]:"";
            $val["pic3"] = $val["pic3"]?"http://pic.baojia.com/".$val["pic3"]:"";
            $val["pic4"] = $val["pic4"]?"http://pic.baojia.com/".$val["pic4"]:"";
            unset($val["code"],$val["uid"],$val["proposal"]);
        }

        $isFault["operate"] = $isFault["operate"]?$isFault["operate"]:$fault[0]["operate"];
        $result["fault_operate"] = $this->fault_operate($isFault["operate"]);
        $result["fault_list"] = $fault;
        $result["fault_dsc"]  = "请排查是否存在以下故障";
        $this->ajaxReturn(["code" => 1, "message" => "数据接收成功","data"=>$result], 'json');
    }

    /***
     *检修故障排查接口
     * *uid             int   员工id
     * corporation_id   int   企业id
     * rent_content_id  int  车辆id
     * is_fault   int     是否有此故障  1 无此故障  2 有此故障
     * gis_lng   string   高德经度
     * gis_lat   string   高德纬度
     * pid       int      工单id
     **/
    public   function   troubleshoot($uid=0,$corporation_id=0,$rent_content_id=0,$is_fault=0,$gis_lng='',$gis_lat='',$pid=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $areaLogic= new \Api\Logic\Area();
        $model = M($this->ol_table);
        $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
        if (!$user_arr) {
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
        if(empty($rent_content_id) || empty($is_fault)  || empty($gis_lng) || empty($pid)){
            $this->ajaxReturn(["code" => 0, "message" => "参数不完整"], 'json');
        }
        $car_arr = M("rent_content")->alias("rc")
            ->join("car_item_verify civ on rc.car_item_id=civ.car_item_id","left")
            ->field("rc.id,rc.car_item_id,civ.plate_no")->where(["rc.id"=>$rent_content_id])->find();
        if(!$car_arr){
            $this->ajaxReturn(["code" => 0, "message" => "该车辆不存在"], 'json');
        }
        $isFault = D("FedGpsAdditional")->isFaultScreening($rent_content_id,$uid);
        \Think\Log::write($car_arr['plate_no']."需排查参数：" .json_encode($_POST)."，结果：".json_encode($isFault), "INFO");
        $operation = $model->field('id,plate_no,rent_content_id,operate,pid,desc')->where("uid={$uid} and rent_content_id={$rent_content_id} and  pid={$pid} and operate in(23,24)")->find();

        if($operation){
            if($operation["operate"] == 23 && $is_fault == 2){
                \Think\Log::write($car_arr['plate_no']."删除数据：" .json_encode($operation), "INFO");
                $model->where(["id"=>$operation["id"],"operate"=>23])->delete();
            }else{
                if($operation["operate"] == 24){
                    //有上报记录
                    $op_arr = $model->where(["pid" => $operation['pid'], "operate" => 35])->find();
                    if($op_arr){
                        $this->ajaxReturn(["code" => 0, "message" => "该车辆已排查"], 'json');
                    }else{
                        $res["operationId"] = $operation['id'];
                        $this->ajaxReturn(["code" => 1, "message" => "确认故障成功","data"=>$res], 'json');
                    }
                }else{
                    $this->ajaxReturn(["code" => 0, "message" => "该车辆已排查"], 'json');
                }
            }
        }

        if($isFault){
            if(!empty($isFault['repairs_id'])) {
                $repairs_id = implode(",", $isFault['repairs_id']);
            }else{
                $repairs_id = "";
            }
            $fault_operate = $this->fault_operate($isFault["operate"]);
            if($is_fault == 2){
                $result = D("FedGpsAdditional")->operation_log($uid,$rent_content_id,$car_arr['plate_no'],$gis_lng,$gis_lat,24,$corporation_id,$repairs_id,$fault_operate,$pid);
            }else{
                $result = D("FedGpsAdditional")->operation_log($uid,$rent_content_id,$car_arr['plate_no'],$gis_lng,$gis_lat,23,$corporation_id,$repairs_id,$fault_operate,$pid);
            }
            if($result){
                $oData = ["status" => 1, "update_time" => date("Y-m-d H:i:s")];
                $model->where(["id" => $result])->setField($oData);
                $data["status"] = 2;
                $data["update_time"] = date("Y-m-d H:i:s",time());
                $fMap["rent_content_id"] = $rent_content_id;
                $fMap["status"] = array("in",array(0,1));
                $repairs_xm = M("xiaomi_repairs")->where($fMap)->setField($data);
                if($repairs_xm && $is_fault == 2){
                    //下架车辆
                    //$areaLogic->step_status(["repaire_status" => 100], $rent_content_id, 100);
                    if(!empty($isFault['repairs_id']) && !empty($repairs_id)){
                        $this->fault_coupon($uid,$repairs_id,$car_arr['plate_no']);
                    }
                }
                //把车更改为正常状态
                M("rent_sku_hour")->where(["rent_content_id"=>$rent_content_id,"operate_status"=>12])->setField(["operate_status"=>8,"update_time"=>time()]);
                $res["operationId"] = $result;
                \Think\Log::write($car_arr['plate_no']."c端需排查故障处理："."，结果：".$repairs_xm."操作记录记录id".$result, "INFO");
                $this->ajaxReturn(["code" => 1, "message" => "确认故障成功","data"=>$res], 'json');
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "确认故障失败"], 'json');
            }
        }else{
            if($is_fault == 2){
                $result = D("FedGpsAdditional")->operation_log($uid,$rent_content_id,$car_arr['plate_no'],$gis_lng,$gis_lat,24,$corporation_id,'','正常检修',$pid);
            }else{
                $result = D("FedGpsAdditional")->operation_log($uid,$rent_content_id,$car_arr['plate_no'],$gis_lng,$gis_lat,23,$corporation_id,'','正常检修',$pid);
            }
            if($result){
                $oData = ["status" => 1, "update_time" => date("Y-m-d H:i:s")];
                $model->where(["id" => $result])->setField($oData);
                $res["operationId"] = $result;
                $this->ajaxReturn(["code" => 1, "message" => "确认故障成功","data"=>$res], 'json');
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "确认故障失败"], 'json');
            }
        }

    }


    /***
     *排查是否有此故障
     * uid   int  员工id
     * rent_content_id  int  车辆id
     * is_fault   int     是否有此故障  1 无此故障  2 有此故障
     * gis_lng   string   高德经度
     * gis_lat   string   高德纬度
     * repairs_id  string  c端故障上报id  多个逗号隔开
     * pid       int      工单id   派单传  抢单不传
     **/
    public  function  isCarFault($uid=0,$corporation_id=0,$rent_content_id=0,$is_fault=0,$gis_lng='',$gis_lat='',$pid=0,$repairs_id='',$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $areaLogic= new \Api\Logic\Area();
        $redis = new \Redis();
        $redis->pconnect('10.1.11.82', 6379, 0.5);
        $user_arr = A('Battery')->beforeUserInfo($uid);
        if (!$user_arr) {
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
        if(empty($rent_content_id) || empty($is_fault)  || empty($gis_lng) || empty($repairs_id)){
            $this->ajaxReturn(["code" => 0, "message" => "参数不完整"], 'json');
        }
        $repairs_id = explode(",",$repairs_id);
        foreach ($repairs_id as $key=>$val){
            if(empty($val)){
                unset($repairs_id[$key]);
            }
        }
        $repairs_id = implode(",",$repairs_id);
        $car_arr = M("rent_content")->field("id,car_item_id")->where(["id"=>$rent_content_id])->find();
        if(!$car_arr){
            $this->ajaxReturn(["code" => 0, "message" => "该车辆不存在"], 'json');
        }
        $isFault = D("FedGpsAdditional")->isFaultScreening($rent_content_id,$uid);
        if(!$isFault){
            $this->ajaxReturn(["code" => 0, "message" => "无需排查"], 'json');
        }
        $fault_operate = $this->fault_operate($isFault["operate"]);
        $plate_no = M("car_item_verify")->where(["car_item_id"=>$car_arr['car_item_id']])->getField("plate_no");
        \Think\Log::write($plate_no."需排查参数：" .json_encode($_POST)."，结果：".json_encode($isFault), "INFO");
        $operation = M($this->ol_table)->field('id,plate_no,rent_content_id,operate,desc')->where("uid={$uid} and rent_content_id={$rent_content_id} and operate in(23,24) and status=0")->find();
        if($operation){
            $this->ajaxReturn(["code" => 0, "message" => "该车辆已排查"], 'json');
        }
        if($is_fault == 2){
            $redis->set($uid."-".$rent_content_id."is_fault",2);
            $redis->expire($uid."-".$rent_content_id."is_fault", 7200);
            $result = D("FedGpsAdditional")->operation_log($uid,$rent_content_id,$plate_no,$gis_lng,$gis_lat,24,$corporation_id,$repairs_id,$fault_operate,$pid);
            $res["operate"] = "排查用户上报故障为：".$fault_operate;
        }else{
            $redis->set($uid."-".$rent_content_id."is_fault",1);
            $redis->expire($uid."-".$rent_content_id."is_fault", 7200);
            $result = D("FedGpsAdditional")->operation_log($uid,$rent_content_id,$plate_no,$gis_lng,$gis_lat,23,$corporation_id,$repairs_id,$fault_operate,$pid);
            $res["operate"] = "排查用户上报故障为：无故障";
        }
        $res["fault_dsc"] = "请仔细排查该车是否还存在其他问题";
        if($result){
            //处理历史无故障记录  status = 无故障历史数据
            M($this->ol_table)->where("rent_content_id = {$rent_content_id} and operate=23 and id <>{$result}")->setField(["status"=>3,"update_time"=>date("Y-m-d H:i:s")]);
            $data["status"] = 2;
            $data["update_time"] = date("Y-m-d H:i:s",time());
            $fMap["rent_content_id"] = $rent_content_id;
            $fMap["status"] = array("in",array(0,1));
            $repairs_xm = M("xiaomi_repairs")->where($fMap)->setField($data);
            if($repairs_xm && $is_fault == 2){
                //下架车辆
                $areaLogic->step_status(["repaire_status" => 100], $rent_content_id, 100);
                $this->fault_coupon($uid,$repairs_id,$plate_no);
            }
            //把车更改为正常状态
            M("rent_sku_hour")->where(["rent_content_id"=>$rent_content_id,"operate_status"=>12])->setField(["operate_status"=>8,"update_time"=>time()]);
            $res["operationId"] = $result;
            \Think\Log::write($plate_no."c端需排查故障处理："."，结果：".$repairs_xm."操作记录记录id".$result, "INFO");
            $this->ajaxReturn(["code" => 1, "message" => "确认故障成功","data"=>$res], 'json');
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "确认故障失败"], 'json');
        }
    }

    public   function  fault_coupon($uid,$repairs_id,$plate_no){
        $repairs_id = explode(",",$repairs_id);
        $map["id"]  = array("in",$repairs_id);
        $repairs = M("xiaomi_repairs")->field("uid")->where($map)->limit(3)->select();
        $user_id = array_unique(array_column($repairs,'uid'));
        foreach ($user_id as $val){
            if(!empty($val)){
                $data["uid"]   =  $val;
                $data["price"] =  1;
                $data["desc"]  =  "故障上报奖励";
                $data["receive_time"]  =  time();
                $data["useful_time"]   =  3600*24*30;
                $data["over_time"]     =  time() + (3600*24*30);
                $data["status"]        =  1;
                $res = M("xiaomi_code")->add($data);
                $getui = "";
                if($res){
                    $getui = A("Getui")->xiaomi_push($val,"故障上报奖励");
                }
                \Think\Log::write($plate_no."--".$val."故障优惠券：" .$uid."-".json_encode($repairs_id)."--".$getui."，结果：".$res, "INFO");
            }
        }
    }

    //故障类型
    public  function fault_operate($operate){
        $fault_operate["1"] = "车把显示器";
        $fault_operate["2"] = "刹车";
        $fault_operate["3"] = "线路";
        $fault_operate["4"] = "二维码";
        $fault_operate["5"] = "车座";
        $fault_operate["6"] = "车辆后支架";
        $fault_operate["7"] = "其它";
        $fault_operate["8"] = "上私锁";
        return $fault_operate[$operate]?$fault_operate[$operate]:"";
    }

    //具体损坏内容
    public  function  fault_type($type){
        $fault_type["1"] = "左车把显示器损坏";
        $fault_type["2"] = "右车把显示器损坏";
        $fault_type["3"] = "刹车把手损坏";
        $fault_type["4"] = "刹车线路破坏";
        $fault_type["5"] = "车辆线路破坏";
        $fault_type["6"] = "二维码喷漆破损";
        $fault_type["7"] = "二维码掉落";
        $fault_type["8"] = "后车座损坏";
        $fault_type["9"] = "缺少螺丝无法支撑";
        $fault_type["10"] = "支架变形无法支撑";
        $fault_type["11"] = "支架掉落无法支撑";
        $fault_type["12"] = "无法弹起";
        $fault_type["13"] = "拧把不走";
        $fault_type["14"] = "车灯不亮";
        $fault_type["15"] = "刹车失灵";
        $fault_type["17"] = "鸣笛不响";
        $fault_type["18"] = "其他问题";
        return $fault_type[$type]?$fault_type[$type]:"";
    }

    //地图首页未完成的动作
    public   function  unfinishedAction($uid=2623840,$corporation_id=2118,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
        if(!$user_arr){
            $arr = ['code'=>0,'msg'=>'该用户不存在'];
            $this->ajaxReturn($arr,'json');
        }
        $oModel = M($this->ol_table);
        $operation = $oModel->field('id,plate_no,rent_content_id,operate,desc')->where("uid={$uid} and operate in(23,24) and status=0")->find();
        $res = [];
        if($operation){
            $operation["action_type"]  = 1;
            $operation["action_level"]  = 1;
            $operation["action_title"] = "请仔细排查该车是否还存在其他问题";
            $operation["action_info"] = "车辆".$operation["plate_no"]."的排查工作未完成，请\r\n前往并完成本次操作！";
            if($operation["operate"] == 24){
                $operation["isFault"] = 2;
                $operation["action_operate"] = "存在故障“".$operation['desc']."”";
            }else{
                $operation["isFault"] = 1;
                $operation["action_operate"] = "无“".$operation['desc']."”故障";
            }
            unset($operation["operate"],$operation['desc']);
            $res[] = $operation;
            $this->ajaxReturn(['code'=>1,'message'=>'数据接收成功',"data"=>$res],'json');
        }else{
            $arr = ['code'=>0,'message'=>'没有未完成动作'];
            $this->ajaxReturn($arr,'json');
        }
    }


    //位置校正  source 操作接口来源 2=故障排查矫正  4=换电矫正 5=寻车矫正 6=检修矫正
    public function faultCorrectivePosition($user_id,$corporation_id=0,$rent_content_id,$latY='',$lngX = '',$operationId=0,$pid=0,$source=0,$uuid='yy'){
        $this->check_terminal($user_id,$uuid);
		$SearchInfo = new \Api\Logic\SearchInfo();
        if($source == 2){
            if(!$latY || !$lngX||!$user_id||!$rent_content_id || !$operationId){
                $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
            }
        }else{
            if(!$latY || !$lngX||!$user_id||!$rent_content_id || !$pid){
                $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
            }
        }
        $areaLogic= new \Api\Logic\Area();
        $is_rating = $areaLogic->is_rating($rent_content_id);
        if($is_rating){
            $this->ajaxReturn(["code" => -1, "message" => "车辆正在出租中"], 'json');
        }
        if($latY == 0 ||$lngX==0) {
            $this->ajaxReturn(["code" => -100, "message" => "未获取到定位"], 'json');
        }
        $imei=$SearchInfo->getGpsImei($rent_content_id);
        if(empty($imei)){
            $this->ajaxReturn(["code" => -1, "message" => "查询数据有误"], 'json');
        }
        $rent_info = M("rent_content")->field("car_item_id")->where(["id"=>$rent_content_id])->find();
        $plate_no  = $SearchInfo->getPlateNo($rent_info["car_item_id"]);
        //$operation_type = 25;//位置校正
        $gps = new \Api\Model\GpsModel();
        $origin = $gps->gcj_decrypt($latY,$lngX);
        $new_latitude = $origin["lat"];
        $new_longitude = $origin["lon"];
//        $result = A("Operation")->gpsStatusData($imei,$new_latitude,$new_longitude);
        $result = $gps->gpsStatusData($imei,$new_latitude,$new_longitude);
        \Think\Log::write($plate_no."位置校正，参数：" . json_encode($_POST)."，结果：".$result, "INFO");
        if($result > 0){
            D('FedGpsAdditional')->operation_log($user_id,$rent_content_id,$plate_no,$lngX,$latY,16,$corporation_id,0,0,$pid,$source);
            M("rent_content")->where(["id"=>$rent_content_id])->save(["update_time" => time()]);
            if($source == 2) {
                $oData = ["status" => 1, "update_time" => date("Y-m-d H:i:s")];
                M($this->ol_table)->where(["id" => $operationId])->setField($oData);
            }
            $this->ajaxReturn(["code" => 1, "message" => "更新成功"],'json');
        }elseif ($result == -100){
            $this->ajaxReturn(["code" =>0, "message" => "校正的位置与盒子位置一致"],'json');
        }else{
            $this->ajaxReturn(["code" =>0, "message" => "更新失败"],'json');
        }
    }


    /****
     *验收入库扫码检测接口
     * uid          用户id
     * plate_no     车牌号
     **/
    public   function   storageTest($uid=0,$corporation_id=2118,$plate_no='',$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $user_arr = A('Battery')->beforeUserInfo($uid);
        $model2 = M($this->mebike_s_table);
        $model3 = M("baojia_mebike.dispatch_order");
        $model4 = M("baojia_mebike.dispatch_store");
        if($user_arr){
            if(!$plate_no){
                $this->response(["code" => 0, "message" => "参数不完整"], 'json');
            }
            $is_dd=substr($plate_no,0,2);
            if($is_dd && (strtoupper($is_dd)=='DD'||strtoupper($is_dd)=='XM')){
                $plate_no = strtoupper($plate_no);
            }elseif( substr($is_dd,0,1)==8 ){
                $plate_no='XM'.$plate_no;
            }else{
                $plate_no='DD'.$plate_no;
            }
            $car_arr = M("car_item_verify")->alias("civ")
                ->join("rent_content rc on civ.car_item_id= rc.car_item_id","left")
                ->join("corporation  c  on rc.corporation_id = c.id","left")
                ->field("civ.car_item_id,civ.plate_no,rc.id,c.parent_id")->where(["civ.plate_no"=>$plate_no])->find();
            if($car_arr["car_item_id"]){
                if($corporation_id != $car_arr["parent_id"]){
                    $this->response(["code" => 0, "message" => "没有权限操作此车"], 'json');
                }
				$areaLogic = new \Api\Logic\Area();
                $is_rating = $areaLogic->is_rating($car_arr["id"]);
				if($is_rating){
					 $this->response(["code" => 0, "message" => "出租中的车辆不入库"], 'json');
				}
//                $mebike = $model2->field("storage_status")->where(["car_item_id"=>$car_arr["car_item_id"]])->find();
//                if($mebike["storage_status"] == 100){
//
//                }else{
//                    $this->response(["code" => 0, "message" => "该车辆不属于入库车"], 'json');
//                }
                $res = array();
                $res["plate_no"] = $car_arr["plate_no"];
                $res["rent_content_id"] = $car_arr["id"];
                //收回入库工单
                $dMap["rent_content_id"] = $car_arr["id"];
                $dMap["verify_status"]   = array("in",array(2,3,4));
//                $dMap["create_status_code"]  = 20;
                $dispatch_order = $model3->field("id,verify_status")->where($dMap)->find();
                if($dispatch_order){
                    if($dispatch_order["verify_status"] == 2){
                        $res["is_window"] = 1;
                        $res["head_info"] = "该车".$plate_no."未完成回库任务，请确认车辆是否已送达库房";
                    }else{
                        $res["is_window"] = 0;
                        $res["head_info"] = "该车".$plate_no."已完成回库任务，请确认车辆是否已送达库房";
                    }
                }else{
                    $res["is_window"] = 1;
                    $res["head_info"] = "该车".$plate_no."为正常车辆，请确认是否验收入库";
                }
                //查询库房
                $treasury = $model4->field("id,name")->where(["corporation_id"=>$corporation_id,"status"=>1])->select();
                $res["treasury"] = $treasury;
                $this->response(["code" => 1, "message" => "数据接收成功","data"=>$res], 'json');
            }else{
                $this->response(["code" => 0, "message" => "该车辆不存在"], 'json');
            }
        }else{
            $this->response(["code" => 0, "message" => "该运维人员不存在"], 'json');
        }
    }

    /***
     *库管验收入库接口
     *uid                 用户id
     *corporation_id      企业id
     *plate_no            车牌号
     *rent_content_id     车辆id
     *gis_lng             高德坐标经度
     *gis_lat             高德坐标纬度
     *treasuryId          库房  库房id
     *remark              备注
     ***/
    public   function    acceptanceStorage($uid=0,$corporation_id=0,$plate_no='',$rent_content_id=0,$gis_lng='',$gis_lat='',$treasuryId=0,$remark='',$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
        $model2 = M($this->mebike_s_table);
        $model3 = M("baojia_mebike.dispatch_order");
        $model4 = M("baojia_mebike.dispatch_store");
        if($user_arr){
            if(!$plate_no || !$gis_lng || !$treasuryId){
                $this->response(["code" => 0, "message" => "参数不完整"], 'json');
            }
            $map['rc.id'] = $rent_content_id;
            $car_arr = M('rent_content')->alias('rc')->field('rc.sell_status,rc.id,cid.imei,cid.device_type,rc.car_item_id')
                ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
                ->where($map)->find();
            if($car_arr){
				$areaLogic = new \Api\Logic\Area();
                $is_rating = $areaLogic->is_rating($car_arr["id"]);
				if($is_rating){
					 $this->response(["code" => 0, "message" => "出租中的车辆不入库"], 'json');
				}
                $mebike_status = $model2->field("storage_status,seized_status")->where(["rent_content_id"=>$car_arr["id"]])->find();
                if($mebike_status["storage_status"] == 200){
                    $this->response(["code" => 0, "message" => "该车辆已经入库车"], 'json');
                }
                //查询车的坐标
                $status_info = $this->gpsStatusInfo($car_arr["imei"]);
                //查询库房
                $treasury = $model4->field("id,name,user_name,gis_lng,gis_lat,radius")->where(["id"=>$treasuryId,"corporation_id"=>$corporation_id,"status"=>1])->find();

                if(empty($treasury["radius"])){
                    $this->response(["code" => 0, "message" => "未查询到库房"], 'json');
                }
                $distance = round(D("Control")->distance($status_info["gd_latitude"],$status_info["gd_longitude"],$treasury['gis_lat'],$treasury['gis_lng']));

                if($distance > $treasury["radius"]){
                    $this->response(["code" => 0, "message" => "车辆距离库房太远不能入库"], 'json');
                }
                $distance2 = round(D("Control")->distance($treasury['gis_lat'],$treasury['gis_lng'],$gis_lat,$gis_lng));
                if($distance2 > $treasury["radius"]){
                    $this->response(["code" => 0, "message" => "手机定位距离库房太远不能入库"], 'json');
                }
                //收回入库工单
                $dMap["rent_content_id"] = $car_arr["id"];
                $dMap["verify_status"]   = array("in",array(2,3,4));
//                $dMap["create_status_code"]  = 20;
//                $dMap["id"]  = 2;
                $dispatch_order = $model3->field("id,verify_status")->where($dMap)->order("id desc")->find();
                if($dispatch_order["id"]){
                    $pid = $dispatch_order['id'];
                    $oData["store_id"]    = $treasury["id"];
                    $oData["store_uid"]   = $uid;
                    $oData["store_uname"] = $user_arr["user_name"];
                    $oData["store_verify_time"] = date("Y-m-d H:i:s");
                    $model3->where(["id"=>$pid])->save($oData);
                }else{
                    $pid = 0;
                }
                $operationId = D("FedGpsAdditional")->operation_log($uid,$rent_content_id,$plate_no,$gis_lng,$gis_lat,32,$corporation_id=0,$treasury["id"],$remark,$pid);
                if($operationId){
                    if($mebike_status["seized_status"] == 100){
                        $mData["seized_status"] = 0;
                    }
                    $mData["storage_status"] = 200;
                    $mData["update_time"]    = date("Y-m-d H:i:s",time());
                    $model2->where(["rent_content_id"=>$car_arr["id"]])->setField($mData);
                    $this->response(["code" => 1, "message" => "确认成功","data"=>["operationId"=>$operationId]], 'json');
                }else{
                    $this->response(["code" => 0, "message" => "确认失败"], 'json');
                }
            }else{
                $this->response(["code" => 0, "message" => "该车辆不存在"], 'json');
            }
        }else{
            $this->response(["code" => 0, "message" => "该运维人员不存在"], 'json');
        }
    }

    /****
     *未找到车辆接口
     *pid                 任务id
     *uid                 用户id
     *corporation_id      企业id
     *plate_no            车牌号
     *rent_content_id     车辆id
     *gis_lng             高德坐标经度
     *gis_lat             高德坐标纬度
     * confirm            是否确认未找到车辆  0=不确认 1=确认
     */
    public   function   carLost($uid=0,$corporation_id=0,$plate_no='',$rent_content_id=0,$gis_lng='',$gis_lat='',$pid=0,$confirm=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
        $model = M($this->ol_table);
        if($user_arr){
            if(!$plate_no || !$gis_lng || !$pid){
                $this->response(["code" => 0, "message" => "参数不完整"], 'json');
            }
            $map['rc.id'] = $rent_content_id;
            $car_arr = M('rent_content')->alias('rc')->field('rc.sell_status,rc.id,cid.imei,cid.device_type,rc.car_item_id')
                ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
                ->where($map)->find();
            if($car_arr){
                $status_info = $this->gpsStatusInfo($car_arr["imei"]);
                $distance = round(D("Control")->distance($status_info["gd_latitude"],$status_info["gd_longitude"],$gis_lat,$gis_lng));
                if($distance>1000){
                    \Think\Log::write($plate_no."距离".$distance."，允许距离：" . 1000, "INFO");
                    $this->response(["code" => -1, "message" => "与车定位相差过远\n请在车定位一公里内\n上报未找到车辆"], 'json');
                }
                if($confirm != 1){
                    $this->response(["code" => -2, "message" => "未找到车辆".$plate_no."\n结束任务将无法获取任务佣金"], 'json');
                }
                $oMap["pid"]     = $pid;
                $oMap["operate"] = 7;
                $operation = $model->field("id,pid,operate")->where($oMap)->find();
                if($operation){
                    $this->response(["code" => 0, "message" => "该车辆你已经标记未找到"], 'json');
                }else {
                    $test = 0;
                    if($test == 1){
                        //查询标记未找到的次数
                        $oMap1["rent_content_id"] = $car_arr["id"];
                        $oMap1["operate"] = 7;
                        $oMap1["status"] = 0;
                        $lost_num = $model->where($oMap1)->group("uid")->count();
                        $dsc = "";
                        if ($lost_num >= 3 && $lost_num <= 5) {
                            $dsc = "一级寻车";
                            $mData["search_status"] = 100;
                            $mData["update_time"] = date("Y-m-d H:i:s");
                            M($this->mebike_s_table)->where(["car_item_id" => $car_arr["car_item_id"]])->setField($mData);
                        } else if ($lost_num >= 6 && $lost_num <= 10) {
                            $dsc = "二级寻车";
                            $mData["search_status"] = 200;
                            $mData["update_time"] = date("Y-m-d H:i:s");
                            M($this->mebike_s_table)->where(["car_item_id" => $car_arr["car_item_id"]])->setField($mData);
                        } else if ($lost_num >= 11 && $lost_num <= 15) {
                            $dsc = "三级寻车";
                            $mData["search_status"] = 300;
                            $mData["update_time"] = date("Y-m-d H:i:s");
                            M($this->mebike_s_table)->where(["car_item_id" => $car_arr["car_item_id"]])->setField($mData);
                        } else if ($lost_num >= 16) {
                            $dsc = "四级寻车";
                            $mData["search_status"] = 400;
                            $mData["update_time"] = date("Y-m-d H:i:s");
                            M($this->mebike_s_table)->where(["car_item_id" => $car_arr["car_item_id"]])->setField($mData);
                        }
                    }

                    $mebike = M($this->mebike_s_table)->field("search_status,search_count,found_time,dispatch_status,storage_status")->where(["car_item_id" => $car_arr["car_item_id"]])->find();
                    $lost_num  = intval($mebike["search_count"]) + 1;
                    $oMap1["rent_content_id"] = $car_arr["id"];
                    $oMap1["uid"] = $uid;
                    $oMap1["operate"] = 7;
                    if(!empty($mebike["found_time"])){
                        $oMap1["time"] = array('gt',strtotime($mebike["found_time"]));
                    }
                    $lost_operation = $model->field("id,rent_content_id,uid,operate,time")->where($oMap1)->find();
                    \Think\Log::write($plate_no."寻车丢失sql" . $model->getlastsql() , "INFO");
                    if(!$lost_operation){
                        $mData["search_count"] = intval($mebike["search_count"]) + 1;
                    }
                    $dsc = "";
                    if ($lost_num >= 3 && $lost_num <= 5) {
                        $dsc = "一级寻车";
                        $mData["search_status"] = 100;
                    } else if ($lost_num >= 6 && $lost_num <= 10) {
                        $dsc = "二级寻车";
                        $mData["search_status"] = 200;
                    } else if ($lost_num >= 11 && $lost_num <= 15) {
                        $dsc = "三级寻车";
                        $mData["search_status"] = 300;
                    } else if ($lost_num >= 16) {
                        $dsc = "四级寻车";
                        $mData["search_status"] = 400;
                    }
                    if($mebike["storage_status"] == 100){
                        $mData["storage_status"] = 0;
                    }
                    $mData["dispatch_status"] = 0;
                    $mData["update_time"] = date("Y-m-d H:i:s");
                    M()->startTrans();
                    $res = M($this->mebike_s_table)->where(["car_item_id" => $car_arr["car_item_id"]])->setField($mData);
                    \Think\Log::write($plate_no."更新车的状态sql" . M($this->mebike_s_table)->getlastsql() , "INFO");
                    //查询车辆当前状态
                    $operationId = D("FedGpsAdditional")->operation_log($uid, $rent_content_id, $plate_no, $gis_lng, $gis_lat, 7, $corporation_id, 0, $dsc, $pid);
                    if ($operationId && $res) {
                        //任务日志
                        D("FedGpsAdditional")->taskLog_add($uid,$pid,3,$gis_lng,$gis_lat,'未找到车辆结束任务');
                        M("baojia_mebike.dispatch_order")->where(["id"=>$pid])->setField(["verify_status"=>3,"verify_time"=>time(),"update_time"=>time()]);
                        M()->commit();
                        $this->response(["code" => 1, "message" => "标记成功","data"=>["operationId"=>$operationId]], 'json');
                    } else {
                        M()->rollback();
                        $this->response(["code" => 0, "message" => "标记失败"], 'json');
                    }
                }
            }else{
                $this->response(["code" => 0, "message" => "该车辆不存在"], 'json');
            }
        }else{
            $this->response(["code" => 0, "message" => "该运维人员不存在"], 'json');
        }
    }

    //查询我有没有该车的任务
    public   function   ownCarTask($uid=0,$corporation_id,$rent_content_id=0,$pid=0){
        $model = M('baojia_mebike.dispatch_order');
        $map['id']  = $pid;
        $map['uid'] = $uid;
        $map['rent_content_id'] = $rent_content_id;
        $map['corporation_id']  = $corporation_id;
        $map['verify_status']   = array('in',array(1,2,3));
        $result = $model->where($map)->find();
        return $result;
    }

    /***
     *任务检测接口
     * uid             用户id
     * corporation_id  企业id
     * rent_content_id 车辆id
     * operationId     操作记录id
     * step   检测步骤
     * $task_type   任务类型  1=寻车  （对应 step 0=位置矫正  1图片）
     * 5=检修  (对应 step 0=故障检测  1=图片检测  2=后舱锁  3=位置矫正 4=车辆上架待租)
     * 4=换电（对应 step  0=电量检测  1=后舱锁检测 2=上传图片检测 3=自动上架检测）
     **/
    public   function   MissionDetection($uid=0,$corporation_id=0,$rent_content_id=0,$operationId=0,$step=0,$task_type=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $areaLogic= new \Api\Logic\Area();
        $battery_standard = $areaLogic->need_change($corporation_id);
        $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
        $model = M($this->ol_table);
        if($user_arr){
            if(!$task_type){
                $this->response(["code" => 0, "message" => "参数错误"], 'json');
            }
            $map['rc.id'] = $rent_content_id;
            $car_arr = M('rent_content')->alias('rc')->field('rc.sell_status,rc.id,cid.imei,cid.device_type,rc.car_item_id')
                ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
                ->where($map)->find();
            if($car_arr){
                $plate_no = M("car_item_verify")->where(["car_item_id" => $car_arr['car_item_id']])->getField("plate_no");
                //查询操作记录
                $oMap["id"]      = $operationId;
                $oMap["uid"]     = $uid;
//                $oMap["status"]  = 0;
                $operation = $model->field("id,pic1,pic2,step,car_status,pid,rent_content_id")->where($oMap)->find();
                if($operation){
                    if($task_type == 5){
                        $result = $this->OverhaulInspection($uid,$plate_no,$car_arr['id'],$car_arr['car_item_id'],$car_arr['imei'],$oMap,$operation,$step);
                    }elseif ($task_type == 4){
                        $result = $this->BatteryTest($uid,$plate_no,$car_arr['id'],$car_arr['imei'],$battery_standard,$oMap,$operation,$step);
                    }else{
                        $result = $this->patrolDetection($uid,$plate_no,$car_arr['id'],$car_arr['car_item_id'],$oMap,$operation,$step);
                    }
                    $this->response(["code" => 1, "message" => "数据接收","data"=>$result], 'json');
                }else{
                    $this->response(["code" => 0, "message" => "该操作记录不存在"], 'json');
                }
            }else{
                $this->response(["code" => 0, "message" => "该车辆不存在"], 'json');
            }
        }else{
            $this->response(["code" => 0, "message" => "该运维人员不存在"], 'json');
        }
    }
    //检修任务检测
    public  function  OverhaulInspection($uid,$plate_no,$rent_content_id,$car_item_id,$imei,$oMap,$operation,$step){
        $model = M($this->ol_table);
        $model2 = M($this->mebike_s_table);
        //有无故障记录
        $op_arr = $model->field("id,pid,operate")->where(["pid"=>$operation['pid'],"operate"=>23])->find();

        if($op_arr) {
            if(intval($step) == 0){
                //有无故障记录
                $result["status"] = 0;
                $op_arr = $model->field("id,pid,operate")->where(["pid" => $operation['pid'], "operate" => 23])->find();
                if ($op_arr) {
                    $result["status"] = 1;
                    $model->where($oMap)->setField("car_status", 1);
                }
            }else if ($step == 1) {
                //上传图片检测
                if (empty($operation["pic1"])) {
                    $result["status"] = 0;
                } else {
                    $result["status"] = 1;
                    if ($operation["car_status"] == "1") {
                        $model->where($oMap)->setField("car_status", $operation["car_status"] . ",2");
                    }
                }
            } else if ($step == 2) {
                //后舱锁检测
                $seat_lock = M('box_data', '', C("DB_CONFIG_BOX"))->where(["imei" => $imei])->getField("seat_lock_status");
                if ($seat_lock == 1) {
                    $result["status"] = 0;
                } else {
                    $result["status"] = 1;
                    if ($operation["car_status"] == "1,2") {
                        $model->where($oMap)->setField("car_status", $operation["car_status"] . ",3");
                    }
                }
            } else if ($step == 3) {
                //矫正位置检测
                $correction = $model->field("id,pid,operate")->where(["pid" => $operation['pid'], "operate" => 16])->find();
                $result["status"] = 0;
                if ($correction) {
                    $result["status"] = 1;
                    if ($operation["car_status"] == "1,2,3") {
                        $model->where($oMap)->setField("car_status", $operation["car_status"] . ",4");
                    }
                }
            } else if ($step == 4) {
                $result["status"] = 0;
                $offline_status = $model2->where(["car_item_id" => $car_item_id])->getField("offline_status");
                if (intval($offline_status) === 0) {
                    $result["status"] = 1;
                    $model->where($oMap)->setField("car_status", $operation["car_status"] . ",5");
                }
                $operation["car_status"] = $operation["car_status"] . ",5";
            }
            $overhaul_status = "1,2,3,4,5";
        }else{
            if(intval($step) === 0){
                $result["status"] = 0;
                //有上报记录
                $op_arr = $model->field("id,pid,operate")->where(["pid" => $operation['pid'], "operate" => 35])->find();
                if ($op_arr) {
                    $result["status"] = 1;
                    $model->where($oMap)->setField("car_status", 1);
                }
            }else if ($step == 1) {
                //上传图片检测
                if (empty($operation["pic1"])) {
                    $result["status"] = 0;
                } else {
                    $result["status"] = 1;
                    if ($operation["car_status"] == "1") {
                        $model->where($oMap)->setField("car_status", $operation["car_status"] . ",2");
                    }
                }
            }elseif ($step == 3){
                //矫正位置检测
                $correction = $model->field("id,pid,operate")->where(["pid" => $operation['pid'], "operate" => 16])->find();
                $result["status"] = 0;
                if ($correction) {
                    $result["status"] = 1;
                    if ($operation["car_status"] == "1,2") {
                        $model->where($oMap)->setField("car_status", $operation["car_status"] . ",3");
                    }
                }
                $operation["car_status"] = $operation["car_status"] . ",3";
            }
            $overhaul_status = "1,2,3";
        }
        $qqiu["step"] = $step;
        $qqiu["operation"] = $operation;
        \Think\Log::write($plate_no."检修检测结果：".$operation["car_status"]."请求参数".json_encode($qqiu), "INFO");
        if((string)$operation["car_status"] == $overhaul_status && $result["status"]==1){
            $result["is_detection"]   = 0;
            $result["detection_info"] = "任务未能检测通过，请检查";
            //失效未找到车辆历史记录
            $oData["status"] = 1;
            $oData["update_time"] = date("Y-m-d H:i:s");
            $model->where(["pid"=>$operation['pid'],"rent_content_id"=>$operation["rent_content_id"],"operate"=>30,"status"=>0])->save($oData);
            M()->startTrans();
            //任务日志
            $res1 = D("FedGpsAdditional")->taskLog_add($uid,$operation['pid'],3,'','','检修结束任务');
            //结束任务
            $TaskSearch = new \Api\Logic\TaskSearch();
            $all_rent_status = $TaskSearch->getTask($rent_content_id);
            if(!empty($all_rent_status["all_rent_status"])){
                $all_rent_status = implode(",",$all_rent_status["all_rent_status"]);
            }else{
                $all_rent_status = 12;
            }
            $res2 = M("baojia_mebike.dispatch_order")->where(["id"=>$operation['pid']])->setField(["verify_status"=>3,"finish_status_code"=>$all_rent_status,"verify_time"=>time(),"update_time"=>time()]);
            //修改车辆状态
            $repaire_status = $model2->where(["car_item_id" => $car_item_id])->getField("repaire_status");
            $res3 = 1;
            if($repaire_status == 100 || $overhaul_status == "1,2,3,4,5"){
                $mData["repaire_status"] = 101;
                $mData["update_time"]    = date("Y-m-d H:i:s");
                $mData["sell_time"]    = date("Y-m-d H:i:s");
                $res3 = $model2->where(["car_item_id" => $car_item_id])->setField($mData);
            }
            if($res1 && $res2 && $res3){
                $result["is_detection"]   = 1;
                $result["detection_info"] = "检测通过，任务完成";
                M()->commit();

                $redis = new \Redis();
                $redis->pconnect('10.1.11.82', 6379, 0.5);
                $dyKey1  = C("KEY_PREFIX"). "inspectioned_rent_content_id:".$rent_content_id;
                $kValue1 = $redis->get($dyKey1);
                if(empty($kValue1)){
                    $redis->set($dyKey1, $rent_content_id);
                    $redis->expire($dyKey1, 3600);
                }
            }else{
                M()->rollback();
            }
        }else{
            $result["is_detection"]   = 0;
            $result["detection_info"] = "任务未能检测通过，请检查";
        }
        return  $result;
    }

    //寻车任务检测
    public  function  patrolDetection($uid,$plate_no,$rent_content_id,$car_item_id,$oMap,$operation,$step){

        $model = M($this->ol_table);
        $model2 = M($this->mebike_s_table);
        if(intval($step) == 0){
            //上传图片检测
            if(empty($operation["pic1"]) || empty($operation["pic2"])){
                $result["status"]=0;
            }else{
                $result["status"]=1;
                $model->where($oMap)->setField("car_status",1);
            }
        }else if($step == 1){
            //矫正位置检测
            $correction = $model->field("id,pid,operate")->where(["pid"=>$operation['pid'],"operate"=>16])->find();
            if($correction){
                $result["status"]=1;
                if($operation["car_status"] == "1"){
                    $model->where($oMap)->setField("car_status",$operation["car_status"].",2");
                }
            }else{
                $result["status"]=0;
            }
            $operation["car_status"] = "1,2";
        }
        $qqiu["step"] = $step;
        $qqiu["operation"] = $operation;
        \Think\Log::write($plate_no."寻车检测结果：".$operation["car_status"]."请求参数".json_encode($qqiu), "INFO");
        if((string)$operation["car_status"] == "1,2" && $result["status"]==1){
            $result["is_detection"]   = 1;
            $result["detection_info"] = "检测通过，任务完成";
            //失效未找到车辆历史记录
            $oData["status"] = 1;
            $oData["update_time"] = date("Y-m-d H:i:s");
            $model->where(["pid"=>$operation['pid'],"rent_content_id"=>$operation["rent_content_id"],"operate"=>7,"status"=>0])->save($oData);
            //修改车的状态
            $mMap["car_item_id"] = $car_item_id;
            $car_status = $model2->field("search_status")->where($mMap)->find();
            if($car_status["search_status"] > 0){
                $res1 = $model2->where($mMap)->setField(["repaire_status"=>300,"search_status"=>0,"search_count"=>0,"found_time"=>date("Y-m-d H:i:s"),"update_time"=>date("Y-m-d H:i:s")]);
            }
            M("rent_sku_hour")->where($mMap)->setField(["operate_status"=>8,"update_time"=>time()]);
            //任务日志
            $res2 =  D("FedGpsAdditional")->taskLog_add($uid,$operation['pid'],3,'','','寻车结束任务');
            //结束任务
            $TaskSearch = new \Api\Logic\TaskSearch();
            $all_rent_status = $TaskSearch->getTask($rent_content_id);
            if(!empty($all_rent_status["all_rent_status"])){
                $all_rent_status = implode(",",$all_rent_status["all_rent_status"]);
            }else{
                $all_rent_status = 12;
            }
            $res3  =  M("baojia_mebike.dispatch_order")->where(["id"=>$operation['pid']])->setField(["verify_status"=>3,"finish_status_code"=>$all_rent_status,"verify_time"=>time(),"update_time"=>time()]);
            \Think\Log::write($plate_no."寻车状态：".$car_status."执行结果：".json_encode(array("res1"=>$res1,"res2"=>$res2,"res3"=>$res3)), "INFO");
        }else{
            $result["is_detection"]   = 0;
            $result["detection_info"] = "任务未能检测通过，请检查";
        }
        return  $result;
    }

    //换电任务检测
    public  function  BatteryTest($uid,$plate_no,$rent_content_id,$imei,$battery_standard,$oMap,$operation,$step){
        $TaskSearch = new \Api\Logic\TaskSearch();
        $model  = M($this->ol_table);
        $result = array();
        if(intval($step) == 0){
            //电量检测
            /* $battery_level = M('box_data','',C("DB_CONFIG_BOX"))->where(["imei"=>$imei])->getField("battery_level");
             \Think\Log::write($plate_no."查询数据电量：".$battery_level."--".$battery_standard."盒子号：".$imei, "INFO");
             if($battery_level > $battery_standard){
                 $data['desc'] = '换电时获取时时电量:'.$battery_level."--对比电量值：".$battery_standard;
                 $data['battery'] = $battery_level;
                 $data['car_status']   = 1;
                 $operation["car_status"] = "1";
                 $model->where($oMap)->save($data);
                 //清除历史电压
                 $this->clearRedisVoltage($imei);
                 //换电车辆上架待租
                 $operate_status = M("rent_sku_hour")->where("rent_content_id=".$rent_content_id)->getField("operate_status");
                 $operate_status = $operate_status?$operate_status:0;
                 $this->setCarStatus($uid,$plate_no,$rent_content_id,$operate_status);
                 $this->setDbPower($imei, $battery_level);
                 $result["status"]=1;
             }else{
                 $result["status"]=0;
             }*/
            $voltage = $this->imeiVoltage($imei);
            if($voltage['rtCode'] == '0'){
                \Think\Log::write($plate_no."请求网关电压：".$voltage['result']['voltage']."--".$battery_standard."盒子号：".$imei, "INFO");
                // $electricity = $this->getDumpEle($voltage['result']['voltage']);
                $electricity = $voltage['result']['dumpEle'];
                $electricity = $electricity ? intval($electricity * 100) : 0;
                if ($electricity > $battery_standard) {
                    $data['desc'] = '换电时获取时时电量:'.$electricity."-电压:".$voltage['result']['voltage'];
                    $data['battery'] = $electricity;
                    $data['car_status']   = 1;
                    $operation["car_status"] = "1";
                    $model->where($oMap)->save($data);
                    //清除历史电压
                    $this->clearRedisVoltage($imei);
                    //换电车辆上架待租
                    $operate_status = M("rent_sku_hour")->where("rent_content_id=".$rent_content_id)->getField("operate_status");
                    $operate_status = $operate_status?$operate_status:0;
                    $this->setCarStatus($uid,$plate_no,$rent_content_id,$operate_status);
                    $this->setDbPower($imei, $electricity);
                    $result["status"]=1;
                }else{
                    $result["status"]=0;
                }
            }else{
                $result["status"]=0;
            }
        }else if($step == 1){
            //后舱锁检测
            $seat_lock = M('box_data','',C("DB_CONFIG_BOX"))->where(["imei"=>$imei])->getField("seat_lock_status");
            if($seat_lock == 1){
                $result["status"]=0;
            }else{
                $result["status"]=1;
                if($operation["car_status"] == "1") {
                    $model->where($oMap)->setField("car_status", $operation["car_status"].",2");
                }
            }
        } else if($step == 2){
            //上传图片检测
            if(empty($operation["pic1"])){
                $result["status"]=0;
            }else{
                $result["status"]=1;
                if($operation["car_status"] == "1,2") {
                    $model->where($oMap)->setField("car_status", $operation["car_status"].",3");
                }
            }
        }else if($step == 3){
            //上架检测
//            $sell_status = M($this->mebike_s_table)->where(["rent_content_id"=>$rent_content_id])->getField("sell_status");
            $sell_status =  $this->shelvesDetection($plate_no,$rent_content_id);
			
            if($sell_status == 1){
                $result["status"]=1;
                if($operation["car_status"] == "1,2,3"){
                    $model->where($oMap)->setField(["car_status"=>$operation["car_status"].",4","status"=>1]);
                }
            }else{
                $result["status"]=0;
            }
        }else if($step == 4){
            //矫正位置检测
            $correction = $model->field("id,pid,operate")->where(["pid"=>$operation['pid'],"operate"=>16])->find();
            if($correction){
                $result["status"]=1;
                if($operation["car_status"] == "1,2,3,4"){
                    $model->where($oMap)->setField("car_status",$operation["car_status"].",5");
                }
            }else{
                $result["status"]=0;
            }
        }
        $qqiu["step"] = $step;
        $qqiu["battery_standard"] = $battery_standard;
        $qqiu["operation"] = $operation;
        \Think\Log::write($plate_no."换电检测结果：".$operation["car_status"]."请求参数".json_encode($qqiu), "INFO");
        if((string)$operation["car_status"] == "1,2,3,4" && $result["status"]==1){
            $redis = new \Redis();
            $redis->pconnect('10.1.11.82', 6379, 0.5);
            $dyKey1  = C("KEY_PREFIX"). "changed_rent_content_id:".$rent_content_id;
            $kValue1 = $redis->get($dyKey1);
            if(empty($kValue1)){
                $redis->set($dyKey1, $rent_content_id);
                $redis->expire($dyKey1, 3600);
            }

            \Think\Log::write($plate_no."检测成功", "INFO");

            $result["is_detection"]   = 1;
            $result["detection_info"] = "检测通过，任务完成";
            //失效未找到车辆历史记录
            $oData["status"] = 1;
            $oData["update_time"] = date("Y-m-d H:i:s");
            $model->where(["pid"=>$operation['pid'],"rent_content_id"=>$operation["rent_content_id"],"operate"=>44,"status"=>0])->save($oData);
            //任务日志
            D("FedGpsAdditional")->taskLog_add($uid,$operation['pid'],3,'','','换电结束任务');
            //结束任务
            $all_rent_status = $TaskSearch->getTask($rent_content_id);
            if(!empty($all_rent_status["all_rent_status"])){
                $all_rent_status = implode(",",$all_rent_status["all_rent_status"]);
            }else{
                $all_rent_status = 12;
            }
            M("baojia_mebike.dispatch_order")->where(["id"=>$operation['pid']])->setField(["verify_status"=>3,"finish_status_code"=>$all_rent_status,"verify_time"=>time(),"update_time"=>time()]);
        }else{
            $result["is_detection"]   = 0;
            $result["detection_info"] = "任务未能检测通过，请检查";
        }
        return $result;
    }

    //上架检测
    public  function  shelvesDetection($plate_no='DD703059',$rent_content_id='5882701'){
		
        $param = "sell_status,offline_status,out_status,lack_power_status,transport_status,repaire_status,seized_status,reserve_status,storage_status,rent_status,damage_status,scrap_status,other_status,dispatch_status";
        $mebike_status = M($this->mebike_s_table)->field($param)->where(["rent_content_id"=>$rent_content_id])->find();
       
        \Think\Log::write($plate_no."换电获取车的状态：".json_encode($mebike_status), "INFO");
        if($mebike_status["sell_status"] == 1){
            return  true;
        }else{
            $out_status = $mebike_status["out_status"];
            unset($mebike_status["sell_status"],$mebike_status["out_status"]);
            foreach ($mebike_status as $key=>$val){
                if(!empty($val)){
                    return   false;
                }
            }
            if($out_status == 200){
                return  true;
            }else{
                return  false;
            }
        }
    }

    /***
     *查询网关时时电压
     **/
    public   function   imeiVoltage($imei='0865067026198096'){
//       $url = 'http://wg.baojia.com/simulate/service';
        $url = C("GATEWAY_LINK");
        //读取网关的key存到redis 1分钟失效
        $redis = new \Redis();
        $redis->pconnect('10.1.11.82', 6379, 0.5);
        $dyKey = ltrim($imei, "0") . ":Voltage";
        $kValue = $redis->get($dyKey);
        if ($kValue) {
            $cData['carId'] = ltrim($imei, "0");
            $cData['key'] = $kValue;
            $cData['cmd'] = 'resultQuery';
            for ($i = 0; $i < 10; $i++) {
                $res2 = $this->VoltagePost($cData, $url);
                if ($res2['rtCode'] == '0') {
                    break;
                } else {
                    sleep(1);
                }
            }
           // echo "<pre>";
           // print_r($res2);
            return $res2;
        }else{
            $data['carId'] = ltrim($imei, "0");
            $data['type'] = 34;
            $data['cmd'] = 'statusQuery';
            $res = $this->VoltagePost($data, $url);
           // echo "<pre>";
           // print_r($res);
            if ($res['rtCode'] == '0') {
                if (!$kValue) {
                    $redis->set($dyKey, $res['msgkey']);
                    $redis->expire($dyKey, 60);
                }
                $cData['carId'] = ltrim($imei, "0");
                $cData['key'] = $res['msgkey'];
                $cData['cmd'] = 'resultQuery';
                for ($i = 0; $i < 10; $i++) {
                    $res2 = $this->VoltagePost($cData, $url);
                    if ($res2['rtCode'] == '0') {
                        break;
                    } else {
                        sleep(1);
                    }
                }
               // echo "<pre>";
               // print_r($res2);
                return $res2;
            }else if ($res['rtCode'] == 1) {
                $this->response(["code" => 0, "message" => "设备接收命令并返回失败"], 'json');
            } else if ($res['rtCode'] == 3) {
                $this->response(["code" => 0, "message" => "设备断开连接"], 'json');
            } else if ($res['rtCode'] == 6) {
                $this->response(["code" => 0, "message" => "命令重复"], 'json');
            } else {
                $this->response(["code" => 0, "message" => "查询失败 可能盒子已离线"], 'json');
            }
        }
    }



    /***
     *换电、寻车、检修任务
     *pid                 任务id
     *uid                 用户id
     *corporation_id      企业id
     *plate_no            车牌号
     *rent_content_id     车辆id
     *gis_lng             高德坐标经度
     *gis_lat             高德坐标纬度
     * pic1               图片1
     * pic2               图片2
     * end_button         图片2  是否点结束任务按钮  0 = 没点 1=点击过
     *task_type           任务类型   1=寻车   5=检修  4=换电
     ***/
    public  function  operationTask($uid=0,$corporation_id=0,$plate_no='',$rent_content_id=0,$gis_lng='',$gis_lat='',$pid=0,$task_type=0,$end_button=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
        $model = M($this->ol_table);
        $upload_image = new \Api\Logic\UploadImage();
        if($user_arr){
            if(!$uid || !$plate_no || !$gis_lng || !$pid){
                $this->response(["code" => 0, "message" => "参数不完整"], 'json');
            }
            $map['rc.id'] = $rent_content_id;
            $car_arr = M('rent_content')->alias('rc')->field('rc.sell_status,rc.id,cid.imei,cid.device_type,rc.car_item_id')
                ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
                ->where($map)->find();
            if($car_arr){
                if($task_type == 5){
                    $wMap["operate"] = 30;
                }else if($task_type == 4){
                    $wMap["operate"] = 44;
                }else{
                    $wMap["operate"]= 31;
                }

                $data["gis_lng"] = $gis_lng;
                $data["gis_lat"] = $gis_lat;

                //查询未完成的任务
                $wMap["uid"] = $uid;
                $wMap["rent_content_id"] = $rent_content_id;
                $wMap["pid"] = $pid;
                $wMap["status"]  = 0;
                $task = $model->field("id,pid")->where($wMap)->find();
                if($task){
                    $data["update_time"]    = date("Y-m-d H:i:s",time());
                    $res = $model->where(["id"=>$task["id"]])->save($data);
                    if($res){
                        $this->response(["code" => 1, "message" => "结束任务成功","data"=>["operationId"=>$task['id']]], 'json');
                    }else{
                        $this->response(["code" => 0, "message" => "结束任务失败"], 'json');
                    }
                }else{
                    if($task_type == 5){
                        if( isset($_FILES["pic1"]) ){
                            $pic = $upload_image->upload($_FILES["pic1"],'');
                            $pic1 = $pic["pic1"];
                        }
                        if(empty($pic1['short_path'])){
                            $this->response(["code" => 0, "message" => "上传图片失败"], 'json');
                        }
                        $data["pic1"]    = $pic1['short_path'];
                        $data["operate"] = 30;
                    }else if($task_type == 4){
                        if( isset($_FILES["pic1"]) ){
                            $pic = $upload_image->upload($_FILES["pic1"],'');
                            $pic1 = $pic["pic1"];
                        }
                        if(empty($pic1['short_path'])){
                            $this->response(["code" => 0, "message" => "上传图片失败"], 'json');
                        }
                        $data["pic1"]    = $pic1['short_path'];
                        $data["operate"] = 44;
                    }else{
                        if( isset($_FILES["pic1"]) && isset($_FILES["pic2"]) ){
                            $pic = $upload_image->upload($_FILES["pic1"],$_FILES["pic2"]);
                            $pic1 = $pic["pic1"];
                            $pic2 = $pic["pic2"];
                        }
                        if(empty($pic1['short_path']) || empty($pic2['short_path'])){
                            $this->response(["code" => 0, "message" => "图片缺失，请重新上传"], 'json');
                        }
                        $data["pic1"]   = $pic1['short_path'];
                        $data["pic2"]   = $pic2['short_path'];
                        $data["operate"] = 31;
                    }

                    $residual_battery = D('FedGpsAdditional')->electricity_info($car_arr['imei']);
                    if($residual_battery['residual_battery1']){
                        $data["before_battery"] = $residual_battery['residual_battery1'];
                    }
                    $data["time"]    = time();
                    $data["uid"] = $uid;
                    $data["pid"] = $pid;
//                    if($task_type == 5) {
//                        $data["operate"] = 30;
//                    }else if($task_type == 4){
//                        $data["operate"] = 1;
//                    }else{
//                        $data["operate"] = 31;
//                    }
                    $data["corporation_id"] = $corporation_id;
                    $data["plate_no"] = $plate_no;
                    $data["rent_content_id"] = $rent_content_id;
                    $res = $model->add($data);
                    if($res){
                        $this->response(["code" => 1, "message" => "结束任务成功","data"=>["operationId"=>$res]], 'json');
                    }else{
                        $this->response(["code" => 0, "message" => "结束任务失败"], 'json');
                    }
                }
            }else{
                $this->response(["code" => 0, "message" => "该车辆不存在"], 'json');
            }
        }else{
            $this->response(["code" => 0, "message" => "该运维人员不存在"], 'json');
        }
    }

    /***
     * 进行中的任务
     * uid     用户id
     * taskId  任务id
     **/
    public  function   conductTask($uid=0,$taskId=0,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $where["uid"] = $uid;
        $where["id"]  = $taskId;
        $field = "id,uid,verify_status";
        $d_order = D("DispatchOrder")->get_one($where,$field);
        if($d_order["uid"]){
            //查询操作记录
            $oModel = M($this->ol_table);
            $operation = $oModel->field('id,pic1,pic2,operate')->where("pid={$d_order['id']} and operate in(1,30,31,42,43)")->find();
            $res = [];
            //检修查询点的是无故障还是有故障
            if($operation["operate"] == 30){
                $overhaul = $oModel->field('operate')->where("pid={$d_order['id']} and operate in(23,24)")->find();
                if($overhaul["operate"] == 24){
                    $res["is_fault"] = 1;
                }else{
                    $res["is_fault"] = 2;
                }
            }else{
                $res["is_fault"] = 0;
            }
            if(!empty($operation["pic1"])){
                $res["end_button"] = 1;
                $res["operationId"] = $operation["id"];
                $res["pic1"] = $operation["pic1"]?'http://pic.baojia.com/'.$operation["pic1"]:"";
                $res["pic2"] = $operation["pic2"]?'http://pic.baojia.com/'.$operation["pic2"]:"";
            }else{
                $res["end_button"] = 0;
                $res["pic1"] = "";
                $res["pic2"] = "";
            }
            $this->response(["code" => 1, "message" => "数据结收成功","data"=>$res], 'json');
        }else{
            $this->response(["code" => 0, "message" => "该任务不存在"], 'json');
        }
    }

    //我的任务列表
    /*
     * $verify_status =1 待处理， =2 禁行中 ,=3 待审核， =4 审核通过，=-2 审核不通过
     *
     * */
    public function task_order_list($uid = '3031365',$verify_status = '3',$p='1',$uuid='yy'){
        $this->check_terminal($uid,$uuid);
//        $uid = I("post.uid",0,'intval');
        $model = M('baojia_mebike.dispatch_order');
        if(empty($uid)){
            $this->response(["code" => -1, "message" => "uid不能为空"], 'json');
        }
        $where ="uid = {$uid}";
        $field = "id,plate_no,rent_content_id,task_id,price,create_status_code,verify_status,is_special,workload_type,remark,create_time,verify_time";
        $page = "{$p},15";
        if($verify_status == 1 || $verify_status == 2){
            $order = "create_time desc,id desc";
        }else{
            $order = "verify_time desc,id desc";
        }
        switch ($verify_status)
        {
            case -2:
                $where .= " and verify_status = -2";
                break;
            case 1:
                $where .= " and verify_status = 1";
                break;
            case 2:
                $where .= " and verify_status = 2";
                break;
            case 3:
                $where .= " and verify_status = 3";
                break;
            case 4:
                $where .= " and verify_status = 4";
                break;
        }

        $result = D("DispatchOrder")->get_all($where,$field,$page,$order);

        $new = [];
        foreach($result as $k => $v){
            if($verify_status == 1 || $verify_status == 2){
                $result[$k]['create_date'] = date("Y-m-d",$v['create_time']);
                $result[$k]['create_lasting'] = date("H:i",$v['create_time']);
            }else{
                $result[$k]['create_date'] = date("Y-m-d",$v['verify_time']);
                $result[$k]['create_lasting'] = date("H:i",$v['verify_time']);
            }
            $result[$k]['all_rent_status'] = $v["create_status_code"];
            $result[$k]['task_results'] = "";
            $result[$k]['font_color']   = "";
            if($v["is_special"] == 1){
                $result[$k]['title'] = "特殊";
                $result[$k]['task_type'] = 6;
                $result[$k]['task_info'] = "特殊任务";
            }else{
                $create_status_code = explode(",",$v['create_status_code']);
                $result[$k]['title']  = "";
                foreach ($create_status_code as $code){
                    if($this->dispatch_code($code)){
                        $result[$k]['title'] .= $this->dispatch_code($code);
                    }
                }
                if(empty($v['task_id'])){//历史数据处理
                    if(in_array($v['create_status_code'],[1,2,3,4,5])){
                        $result[$k]['task_type'] = 4;
                        $result[$k]['task_info'] = "换电任务";
                    }else if(in_array($v['create_status_code'],[7,10])){
                        $result[$k]['task_type'] = 3;
                        $result[$k]['task_info'] = "调度任务";
                    }else if(in_array($v['create_status_code'],[6,8,9,14])){
                        $result[$k]['task_type'] = 5;
                        $result[$k]['task_info'] = "检修任务";
                    }else if(in_array($v['create_status_code'],[11])){
                        $result[$k]['task_type'] = 2;
                        $result[$k]['task_info'] = "回库任务";
                    }else{
                        $result[$k]['task_type'] = 0;
                        $result[$k]['task_info'] = "";
                    }
                }else{
                    $task_info = M("baojia_mebike.dispatch_task")->field("id,title,level")->where(["id"=>$v['task_id']])->find();
                    $result[$k]['task_type'] = $task_info['id'];
                    $result[$k]['task_info'] = $task_info['title'];
                    if($v["verify_status"] == 3){
                        //查询异常结束任务
                        $abnormal = M($this->ol_table)->field("operate")->where("operate in(35,37,7) and pid={$v["id"]}")->find();
                        if($abnormal["operate"] == 35){
                            $result[$k]['task_results'] = "故障上报";
                            $result[$k]['font_color']   = "#FF5D4F";
                        }else if($abnormal["operate"] == 37){
                            $result[$k]['task_results'] = "扣押上报";
                            $result[$k]['font_color']   = "#FF5D4F";
                        }else if($abnormal["operate"] == 7){
                            $result[$k]['task_results'] = "未找到车辆";
                            $result[$k]['font_color']   = "#FF5D4F";
                        }else{
                            if($result[$k]['task_type'] == 1){
                                $result[$k]['task_results'] = "完成寻回";
                            }else if($result[$k]['task_type'] == 2){
                                $result[$k]['task_results'] = "完成回库";
                            }else if($result[$k]['task_type'] == 3){
                                $result[$k]['task_results'] = "完成调度";
                            }else if($result[$k]['task_type'] == 4){
                                $result[$k]['task_results'] = "完成换电";
                            }else{
                                $result[$k]['task_results'] = "完成检修";
                            }
                        }
                    }
                }
            }
            if($v['workload_type'] == 1){
                $result[$k]['workload'] = '换电';
            }else if($v['workload_type'] == 2){
                $result[$k]['workload'] = '越界';
            }else if($v['workload_type'] == 3){
                $result[$k]['workload'] = '拉回';
            }else if($v['workload_type'] == 4){
                $result[$k]['workload'] = '丢失车找回';
            }else if($v['workload_type'] == 5){
                $result[$k]['workload'] = '上报丢失';
            }else if($v['workload_type'] == 6){
                $result[$k]['workload'] = '特殊车辆';
            }else{
                $result[$k]['workload'] = '';
            }
            $result[$k]['price']  = $v["price"]?$v["price"]:5;
            $result[$k]['remark']   = $v["remark"]?$v["remark"]:"";
            unset($result[$k]['verify_time'],$result[$k]['workload_type'],$result[$k]['create_time'],$result[$k]['create_status_code'],$result[$k]['task_id']);
        }

        foreach($result as $k => $v){
            $new[$v["create_date"]][] = $v;
        }

        foreach($new as $k => $v){
            $new1['records'] = $v;
            $start_time = strtotime($k." 0:0:0");
            $end_time   = strtotime($k." 23:59:59");
            if($verify_status == 1 || $verify_status == 2){
                $total = $model->where($where." and (create_time  BETWEEN ".$start_time."  AND  $end_time)")->count(1);
            }else{
                $total = $model->where($where." and (verify_time  BETWEEN ".$start_time."  AND  $end_time)")->count(1);
            }
            $new1["total"]   = $total;
            if($k == date("Y-m-d")){
                $new1["format"]  = '今天';
            }else if($k == date("Y-m-d",strtotime("-1 day"))){
                $new1["format"]  = '昨天';
            }else{
                $new1["format"]  = $k;
            }
            $new2[] = $new1;
        }
        if(empty($new)){
            $new3["orders"] = [];
        }else{
            $new3["orders"] = $new2;
        }
        //统计
        $task = D("DispatchOrder")->get_group("uid = {$uid} and verify_status in(-2,1,2,3,4)","count(id) total,verify_status","verify_status");
        $task1 = array();
        foreach ($task as $key=>$val){
            $task1[$val["verify_status"]] = $val;
        }
        $task2[0]["verify_status"] = 1;
        $task2[0]["verify_dsc"] = "待处理";
        $task2[0]["total"]      = $task1[1]["total"]?$task1[1]["total"]:0;
        $task2[1]["verify_status"] = 2;
        $task2[1]["verify_dsc"] = "进行中";
        $task2[1]["total"]      = $task1[2]["total"]?$task1[2]["total"]:0;
        $task2[2]["verify_status"] = 3;
        $task2[2]["verify_dsc"] = "待审核";
        $task2[2]["total"]      = $task1[3]["total"]?$task1[3]["total"]:0;
        $task2[3]["verify_status"] = 4;
        $task2[3]["verify_dsc"] = "已通过";
        $task2[3]["total"]      = $task1[4]["total"]?$task1[4]["total"]:0;
        $task2[4]["verify_status"] = -2;
        $task2[4]["verify_dsc"] = "未通过";
        $task2[4]["total"]      = $task1[-2]["total"]?$task1[-2]["total"]:0;

        $new3["task_count"] = $task2;
        $this->response(["code" => 1, "message" => "请求成功","data" => $new3], 'json');
    }

    //任务详情操作日志
    public  function  task_info($uid=2885684,$taskId=9355,$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        $areaLogic= new \Api\Logic\Area();
        $where = "uid = {$uid} and id = {$taskId}";
        $field = "verify_status,create_time,remark";
        $task  = $task = D("DispatchOrder")->get_one($where,$field,'');
        $model = M("baojia_mebike.dispatch_order_log");
        $task_log = $model->field("user_id,create_time,verify_status,remark")->where(["dispatch_order_id"=>$taskId])->select();
        foreach ($task_log as &$val){
            //查询用户名
            if($uid == $val["user_id"]){
                $val["user_name"] = "我";
                $val["user_type"] = 0;
            }else{
                $user = M("member")->field("CONCAT(last_name,first_name) user_name,user_type")->where(["uid"=>$val["user_id"]])->find();
                if($user["user_type"] == 1){
                    $val["user_type"] = 1;
                }else{
                    $val["user_type"] = 0;
                }
                $val["user_name"] = $user["user_name"];
            }
            if($val["verify_status"] == 4){
                $val["color"] = "#35C63D";
                $val["remark"] = "审核通过";
                $val["reason"] = "";
            }else if($val["verify_status"] == -2){
                $val["color"] = "#FF4A3B";
                $val["remark"] = "审核未通过";
                $val["reason"] = $task["remark"]?$task["remark"]:"";
            }else{
                $val["color"] = "";
                $val["reason"] = "";
            }
            $val["create_time"] = date("m-d H:i",$val["create_time"]);
            unset($val["user_id"]);
        }
        $data = array();
        if($task["verify_status"] == 1 && $task_log){
            $data["user_name"] = "我";
            $data["user_type"] = 0;
            $data["verify_status"] = 1;
            $data["remark"]    = "待开始任务";
            $data["color"] = "#FF872A";
            $data["reason"] = "";
            $data["create_time"]   = "已创建".$areaLogic->timediff($task["create_time"], time());
            $task_log[] = $data;
        }else if($task["verify_status"] == 2 && $task_log){
            $create_time = $model->field("create_time")->where(["dispatch_order_id"=>$taskId,"verify_status"=>2])->order("create_time desc")->find();
            $data["user_name"] = "我";
            $data["user_type"] = 0;
            $data["verify_status"] = 2;
            $data["remark"]    = "任务进行中";
            $data["color"] = "#FF872A";
            $data["reason"] = "";
            $data["create_time"]   = "已开始".$areaLogic->timediff($create_time["create_time"], time());
            $task_log[] = $data;
        } else if($task["verify_status"] == 3 && $task_log){
            $create_time = $model->field("create_time")->where(["dispatch_order_id"=>$taskId,"verify_status"=>3])->order("create_time desc")->find();
//            echo  "<pre>";
//            print_r($create_time);
            $data["user_name"] = "";
            $data["user_type"] = 1;
            $data["verify_status"] = 3;
            $data["remark"]    = "等待管理员审核中";
            $data["color"] = "#FF872A";
            $data["reason"] = "";
            $data["create_time"] = "已等待".$areaLogic->timediff($create_time["create_time"], time());
            $task_log[] = $data;
        }
        $this->response(["code" => 1, "message" => "请求成功","data" => $task_log], 'json');
    }

    /***
     *任务统计
     **/
    public function task_statistics($uid = '2885684', $search = '',$p='5',$uuid='yy'){
        $this->check_terminal($uid,$uuid);
        if(empty($uid)){
            $this->response(["code" => -1, "message" => "uid不能为空"], 'json');
        }
        $model = M($this->ol_table);
        $page = $p?$p:1;
        $page_size = 15;
        $offset = $page_size * ($page - 1);
        $map["ol.uid"]     = $uid;
        $map["ol.operate"] = 32;
        if($search){
            $is_dd=substr($search,0,2);
            if($is_dd && (strtoupper($is_dd)=='DD'||strtoupper($is_dd)=='XM')){
                $map["ol.plate_no"]  = $search;
            }else{
                $map["ol.pid"]  = $search;
            }
        }
        $result = $model->alias("ol")
            ->join("baojia_mebike.dispatch_order do on ol.pid = do.id","left")
            ->field("ol.plate_no,ol.car_status,ol.desc,ol.time,ol.pid,do.create_status_code")
            ->where($map)->order("time desc")->limit($offset,$page_size)->select();

        $new = [];
        foreach($result as $k => $v){
            //查询库房
            $treasury = M("baojia_mebike.dispatch_store")->where(["id"=>$v["car_status"]])->getField("name");
            $result[$k]['create_date'] = date("Y-m-d",$v['time']);
            $result[$k]['create_lasting'] = date("H:i",$v['time']);
            $result[$k]['treasury']  = $treasury?$treasury:"";
            $create_status_code   = $v['create_status_code'];
            $result[$k]['title']  = "";
            foreach ($create_status_code as $code){
                if($this->dispatch_code($code)){
                    $result[$k]['title'] .= $this->dispatch_code($code);
                }
            }
            $result[$k]['desc']  = $result[$k]['desc']?$result[$k]['desc']:"";
            unset($result[$k]['time'],$result[$k]['create_status_code'],$result[$k]['car_status']);
        }
        foreach($result as $k => $v){
            $new[$v["create_date"]][] = $v;
        }
        foreach($new as $k => $v){
            $new1['records'] = $v;
            $start_time = strtotime($k." 0:0:0");
            $end_time   = strtotime($k." 23:59:59");
            $map["ol.time"]= array("between",array($start_time,$end_time));
            $total = $model->alias("ol")->where($map)->count(1);
            $new1["total"]   = "共入库".$total."辆车";
            if($k == date("Y-m-d")){
                $new1["format"]  = '今天';
            }else if($k == date("Y-m-d",strtotime("-1 day"))){
                $new1["format"]  = '昨天';
            }else{
                $new1["format"]  = $k;
            }
            $new2[] = $new1;
        }
        $new3["orders"] =  $new2?$new2:[];
        $this->response(["code" => 1, "message" => "请求成功","data" => $new3], 'json');
    }


    public function dispatch_code($code){
        $return = [];
        $return["1"] = "低电";
        $return["2"] = "缺电";
        $return["3"] = "馈电";
        $return["4"] = "无电在线";
        $return["5"] = "无电离线";
        $return["6"] = "离线下架";
        $return["7"] = "两日无单";
        $return["8"] = "有单无程";
        $return["9"] = "待大修";
        $return["10"] = "越界";
        $return["11"] = "已入库";
        $return["12"] = "上架待租";
        $return["14"] = "需排查";
        $return["15"] = "存在故障需回库";
        $return["16"] = "已寻回需回库";
        $return["17"] = "无效盒子";
        $return["18"] = "非稳盒子";
        $return["19"] = "还车区域外需调度";
        $return["20"] = "已检修需调度";
        $return["21"] = "五日无单";
        $return["22"] = "四级寻车";
        $return["23"] = "三级寻车";
        $return["24"] = "二级寻车";
        $return["25"] = "一级寻车";
        $return["26"] = "用户上报";
        $return["27"] = "有电离线";
        return $return[$code]?$return[$code]:"";
    }

    //定时任务  查询10分钟换电记录 判断换电完成后8~10分钟换电记录是否无效
    public   function   dianliang_log(){
        $model = M('operation_logging','',$this->baojia_config);
        $map['time'] = array('gt',time()-1260);
        $map['operate'] = array('in',array(-1,1,2));
        $res = $model->where($map)->field('id,rent_content_id,time')->select();
        foreach ($res as &$val){
            $rMap['rc.id'] = $val['rent_content_id'];
            $reslut = M('rent_content','',$this->baojia_config)->alias('rc')->field('rc.id,cid.imei')
                ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
                ->where($rMap)->find();
            $gMap['imei'] = $reslut['imei']?$reslut['imei']:"";
            $res2 = M('gps_additional','',C('DB_CONFIG_BOX'))->where($gMap)->getField('residual_battery');
            $c_time = intval((time()-$val['time'])/60);
            if($c_time > 19 && $c_time < 21 && $res2 < 60){
                $remark = $res2."---".$c_time;
                $model->where(['id'=>$val['id']])->setField(['is_type'=>1,'remark'=>$remark]);
            }
//            $val['time'] = date("Y-m-d H:i:s",$val['time']);
        }
    }


    //定时任务统计昨天的每个人换电数量
    public  function  rankingDayCount(){
        $model = M('operation_logging','',$this->baojia_config);
        $model2 = M('baojia_mebike.cmf_ranking_day_count','',$this->baojia_config);
        $yesterday = date("Y-m-d",strtotime("-1 day"));
        $startTime = strtotime($yesterday . " 0:0:0");
        $endTime   = strtotime($yesterday . " 23:59:59");
        $cMap['count_time']  = array('between', array($startTime, $endTime));
        $result = $model2->field("id,uid,total,count_time")->where($cMap)->find();
        if(!$result){
            // $map['status'] = array('neq', 2);
            // $map['operate'] = array('in', array(-1, 1, 2));

            $map['time'] = array('between', array($startTime, $endTime));
            $map['_string'] = " ((operate IN (- 1, 1, 2) and STATUS <> 2) or (operate = 44 and STATUS = 1))";
            $allRankings = $model->field('uid,count(1) total')->where($map)->order('total desc')->group('uid')->select();
            foreach ($allRankings as $val){
                $data['uid']   = $val['uid'];
                $data['total'] = $val['total'];
                $data['count_time'] = $startTime;
                $model2->add($data);
            }
        }
    }

    //人工停租记录
    public  function  StopRentLog($uid=0,$rent_content_id=0,$plate_no='',$gis_lng=0,$gis_lat=0,$source=0){
        if($uid && $rent_content_id && $plate_no && $source){
            $model = M($this->ol_table);
            $date['uid'] = $uid;
            $date['rent_content_id'] = $rent_content_id;
            $date['plate_no'] = $plate_no;
            $date['operate'] = 15;
            $date['gis_lng'] = $gis_lng;
            $date['gis_lat'] = $gis_lat;
            $date['source'] = $source;
            $date['time'] = time();
            $res = $model->add($date);
            $this->ajaxReturn(["code" => 1, "message" => "数据接收成功",'data'=>["operationId"=> $res]], 'json');
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
        }
    }

    public   function   asasa(){
		
		// M("car_item_device")->where(["id"=>111921,"imei"=>"0867717038919594"])->delete();
		/*$rent_content_id = 5891029;
		$taskSearch = new \Api\Logic\TaskSearch();
        $task_q = $taskSearch->getTask($rent_content_id);
		echo  "<pre>";
		print_r($task_q);die;*/
	    
	
	    /*$mm = M("baojia_mebike.dispatch_order");
		$amap["plate_no"]= array('in',array('DD928788','DD955191'));
        $amap["verify_status"] = 2;
		$rrr = $mm->field("id,plate_no,verify_status")->where($amap)->select();
		
		// foreach($rrr as $vv){
		   // if(!empty($vv["id"])){
		   // echo $vv["id"]."<br>";
		      // $mm->where(["id"=>$vv["id"]])->setField(["verify_status"=>-1,"update_time"=>time(),"operate_uid"=>2693568]);
		   // }
		// }
		echo "<pre>";
		print_r($rrr);die;*/
        $m_model = M("mebike_status","",$this->baojia_config);
        $map["car_item_id"] = array('in',array(368671));
        $res_a = $m_model->where($map)->field("id,repaire_status,dispatch_status,storage_status,sell_status,transport_status")->select();
       // foreach ($res_a as $val){
           // if(!empty($val["id"])){
               // $m_model->where(["id"=>$val["id"]])->setField(["dispatch_status"=>0,"update_time"=>date("Y-m-d H:i:s")]);
           // }
       // }
        echo "<pre>";
        print_r($res_a);die;
		$areaLogic = new \Api\Logic\Area();
		$res  = $areaLogic->GetAmapAddress(116.50163783557,39.937234666906);
		
		$res2 = $areaLogic->GetAmapAddress(116.419448513450,39.869768880208);
		$juli = $areaLogic->getDistance(116.50163783557,39.937234666906, 116.467600,39.998480);
        echo "<pre>";
        print_r($res);
		echo  "<br>";
        print_r($res2);
		echo  "<br>";
        print_r($juli);die;
       
        $dddd = $this->getXiaomiPosition("0865067025295067");
       
        $reslut = $areaLogic->gpsStatusInfo('0865067025295067');
        echo "<pre>";
		print_r($dddd);
        print_r($reslut);
//        $reslut['gd_longitude'] = '121.4366091598';
//        $reslut['gd_latitude'] = '31.023767873473';
        $res = $areaLogic->GetAmapAddress($reslut['gd_longitude'],$reslut['gd_latitude']);
        echo "<pre>";
        print_r($res);
    }

    //获取小安设备redis 定位数据
    public function getXiaomiPosition($imei){
        $Redis = new \Redis();
        $Redis->pconnect('10.1.11.83', 36379, 0.5);
        $Redis->AUTH('oXjS2RCA1odGxsv4');
        $Redis->SELECT(2);
        $key = "prod:boxxan_".ltrim($imei,0);
        $result = $Redis->get($key);

        if(!$result){
            return false;
        }

        $result = json_decode($result,true);
        return $result;
    }
	

}