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
class BoxOperationController extends BController {

    private $config_1 = array(
        'clientsecret'=>'54211dce821d43ff410c83b9e1fd571c',
        'clientid'=>'c93dc8bb3acea98d1b79c038133cbf43',
        'url'=>'http://221.123.179.93:18081/bagechuxing/'
    );
    private  $ceshi_url = 'mysql://apitest-baojia:TKQqB5Gwachds8dv@10.1.11.110:3306/baojia#utf8';

    //绑定盒子
    public   function   xmBindBox($uid=0,$plate_no='',$car_model='',$imei='',$corporation_id=0,$gis_lng=0,$gis_lat=0,$type=0,$uuid='yy'){

         \Think\Log::write("绑定盒子：".$uid."请求参数：" . json_encode($_POST), "INFO");
         $imei = '0'.$imei;
         $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
         if(!$user_arr){
             $arr = ['code'=>0,'message'=>'该用户不存在'];
             $this->ajaxReturn($arr,'json');
         }
         $this->check_terminal($uid,$uuid);
         $model = M('rent_content');
         $model2 = M('car_item_device');
         $corporation = M("baojia_oauth.oauth2_client_corporation")->field("corporation_id,client_id")
            ->where(["corporation_id"=>$corporation_id])
            ->find();
         if(empty($corporation['client_id'])){
             $arr = ['code'=>0,'message'=>'企业用户异常！'];
             $this->ajaxReturn($arr,'json');
         }
        $brand = M('corporation')->field("corporation_brand_id")
            ->where(["id"=>$corporation['corporation_id']])
            ->find();

        $client_info = M("baojia_oauth.oauth2_client")->field("user_id,client_id")
            ->where(["client_id" => $corporation['client_id']])
            ->find();

        //车牌号校验
        if ($plate_no && $imei) {
            $detection = $this->plate_no_detection($plate_no, $brand['corporation_brand_id']);
            if ($detection == 1) {
                $arr = ["code" => 0, "message" => "[车牌号]" . $plate_no . "格式错误"];
                $this->ajaxReturn($arr, 'json');
            }

            //扫描车辆
            $carData = M('baojia_cloud.car')->field("car_id,client_id")->where("plate_no='{$plate_no}' and car_item_id = 0")->find();
            if ($carData) {
                $device1 = M('baojia_cloud.car_device_bind')->field('id,device_id,device_no')->where("car_id={$carData['car_id']} and client_id = '{$carData['client_id']}'")->find();
                if ($device1['device_no']) {
                    $arr = ["code" => 401, "message" => "该车已绑定编号" . $device1['device_no'] . "的盒子，请先解绑后在绑定"];
                    $this->ajaxReturn($arr, 'json');
                }
            } else {
                $rent_content = $model->alias('rc')->field('rc.id,rc.car_item_id,cid.imei,civ.plate_no')
                    ->join('car_item_device cid on rc.car_item_id = cid.car_item_id', 'left')
                    ->join('car_item_verify civ on cid.car_item_id = civ.car_item_id', 'left')
                    ->where(['civ.plate_no' => $plate_no])->find();

                if ($rent_content['imei']) {
                    $arr = ['code' => 401, 'message' => '该车已绑定编号' . $rent_content['imei'] . '的盒子，请先解绑后在绑定'];
                    $this->ajaxReturn($arr, 'json');
                }

            }

            //设备号格式
            $imei_detection = $this->imei_detection($imei);
            if ($imei_detection == 1) {
                $arr = ["code" => 0, "message" => "[设备号]" . $imei . "格式错误"];
                $this->ajaxReturn($arr, 'json');
            }
            //扫描盒子号
            $imei_arr = $model2->alias('cid')->field('cid.car_item_id,cid.imei,civ.plate_no')
                ->join('car_item_verify civ on cid.car_item_id = civ.car_item_id', 'left')
                ->where(['cid.imei' => $imei])->find();
            if ($imei_arr['plate_no']) {
                $arr = ['code' => 401, 'message' => '该盒子已绑定编号' . $imei_arr['plate_no'] . '的车辆，请先解绑后在绑定'];
                $this->ajaxReturn($arr, 'json');
            } else {
                $clientid = $corporation['client_id']?$corporation['client_id']:$this->config_1['clientid'];
                $device1 = M('baojia_cloud.car_device_bind')->field('id,car_id,device_no')->where("device_no='{$imei}' and client_id = '{$clientid}'")->find();
                if ($device1) {
                    $carData = M('baojia_cloud.car')->where("car_id={$device1['car_id']} and car_item_id=0")->getField('plate_no');
                    if ($carData) {
                        $arr = ['code' => 401, 'message' => '该盒子已绑定编号' . $carData . '的车辆，请先解绑后在绑定'];
                        $this->ajaxReturn($arr, 'json');
                    }
                }
            }
        }else{
            if ($imei) {
                //设备号格式
                $imei_detection = $this->imei_detection($imei);
                if ($imei_detection == 1) {
                    $arr = ["code" => 0, "message" => "[设备号]" . $imei . "格式错误"];
                    $this->ajaxReturn($arr, 'json');
                }
                //扫描盒子号
                $imei_arr = $model2->alias('cid')->field('cid.car_item_id,cid.imei,civ.plate_no')
                    ->join('car_item_verify civ on cid.car_item_id = civ.car_item_id', 'left')
                    ->where(['cid.imei' => $imei])->find();
                if ($imei_arr['plate_no']) {
                    $arr = ['code' => 401, 'message' => '该盒子已绑定编号' . $imei_arr['plate_no'] . '的车辆，请先解绑后在绑定'];
                    $this->ajaxReturn($arr, 'json');
                } else {
                    $clientid = $corporation['client_id']?$corporation['client_id']:$this->config_1['clientid'];
                    $device1 = M('baojia_cloud.car_device_bind')->field('id,car_id,device_no')->where("device_no='{$imei}' and client_id = '{$clientid}'")->find();
                    if ($device1) {
                        $carData = M('baojia_cloud.car')->where("car_id={$device1['car_id']} and car_item_id=0")->getField('plate_no');
                        if ($carData) {
                            $arr = ['code' => 401, 'message' => '该盒子已绑定编号' . $carData . '的车辆，请先解绑后在绑定'];
                            $this->ajaxReturn($arr, 'json');
                        }
                    }
                }

                $arr = ['code'=>0,'message'=>'盒子号检测成功'];
                $this->ajaxReturn($arr,'json');

            }else{
                $detection = $this->plate_no_detection($plate_no, $brand['corporation_brand_id']);
                if ($detection == 1) {
                    $arr = ["code" => 0, "message" => "[车牌号]" . $plate_no . "格式错误"];
                    $this->ajaxReturn($arr, 'json');
                }

                //扫描车辆
                $carData = M('baojia_cloud.car')->field("car_id,client_id")->where("plate_no='{$plate_no}' and car_item_id = 0")->find();
                if ($carData) {
                    $device1 = M('baojia_cloud.car_device_bind')->field('id,device_id,device_no')->where("car_id={$carData['car_id']} and client_id = '{$carData['client_id']}'")->find();
                    if ($device1['device_no']) {
                        $arr = ["code" => 401, "message" => "该车已绑定编号" . $device1['device_no'] . "的盒子，请先解绑后在绑定"];
                        $this->ajaxReturn($arr, 'json');
                    }
                } else {
                    $rent_content = $model->alias('rc')->field('rc.id,rc.car_item_id,cid.imei,civ.plate_no')
                        ->join('car_item_device cid on rc.car_item_id = cid.car_item_id', 'left')
                        ->join('car_item_verify civ on cid.car_item_id = civ.car_item_id', 'left')
                        ->where(['civ.plate_no' => $plate_no])->find();

                    if ($rent_content['imei']) {
                        $arr = ['code' => 401, 'message' => '该车已绑定编号' . $rent_content['imei'] . '的盒子，请先解绑后在绑定'];
                        $this->ajaxReturn($arr, 'json');
                    }

               }

                $arr = ['code'=>0,'message'=>'车牌号检测成功'];
                $this->ajaxReturn($arr,'json');

            }
        }
        if($type == 0 && $plate_no && $imei){
            $arr = ['code'=>1,'message'=>'检测成功','data'=>['aa'=>'']];
            $this->ajaxReturn($arr,'json');
        }

        if(empty($car_model) || empty($gis_lng)){
            $arr = ['code'=>0,'message'=>'参数错误'];
            $this->ajaxReturn($arr,'json');
        }

        $cloud_car = M('baojia_cloud.car')->where("plate_no='{$plate_no}'")->getField('car_item_id');
        if($cloud_car){
            M("baojia_cloud.car")->where(["car_item_id"=>$cloud_car])->save(["rent_content_id"=>0,"car_item_id"=>0]);
        }

        $car_model = ltrim($car_model,'轻骑版');
        $data = [];
        $time = time();
        $temp_i = 0;
        $data['car_id']        = $client_info['user_id']."00".$time."00".$temp_i;// int(10) DEFAULT NULL,
        $data['client_id']     = $client_info["client_id"];// varchar(100) DEFAULT NULL,
        $data['plate_no']      = (string)$plate_no;// varchar(50) DEFAULT NULL,
        $data['car_brand']     = (string)"小蜜单车";// varchar(100) DEFAULT NULL,
        if($car_model == '小马'){
            $data['car_series']    = (string)$car_model."电单车";// varchar(100) DEFAULT NULL,  车系名
        }else{
            $data['car_series']    = (string)$car_model;// varchar(100) DEFAULT NULL,  车系名
        }
        $data['car_model']     = (string)"轻骑版";// varchar(100) DEFAULT NULL,  型号名
        $data['price_km']      = (string)"0.4";// float(10,2) DEFAULT NULL,  0.4元/公里
        $data['price_min']     = (string)"0.15";// float(10,2) DEFAULT NULL,     0.15元/分
        $data['device_sn']     = (string)$imei;// varchar(100) DEFAULT NULL,
        $data['all_day_price'] = (string)"24";// varchar(100) DEFAULT NULL,  日封顶24
        $data['night_price']   = (string)"12";// varchar(100) DEFAULT NULL,    夜封顶12
        $data['device_type']   = 18;// varchar(100) DEFAULT NULL,
        $url = 'http://10.1.11.51:81/api/badiandao/getonecar?source=1';
        $res_arr = $this->control_post($data,$url);

        $res_arr = json_decode($res_arr,true);
        if($res_arr['code'] == 1){
            $operation = D('FedGpsAdditional')->operation_log($uid,-10,$plate_no,$gis_lng,$gis_lat,20,$corporation_id);
            $res_arr['data'] = array('operationId'=>$operation);
        }
//        echo "<pre>";
//        print_r($res_arr);
        $this->ajaxReturn($res_arr,'json');
    }

    //盒子解绑
    public  function  unbundledBox($uid=0,$plate_no='',$imei='',$corporation_id=2118,$gis_lng=116.3903914388021,$gis_lat=39.97961154513889,$car_status=13,$desc='',$type=1,$uuid='yy'){

        \Think\Log::write($plate_no."盒子解绑：".$uid."请求参数：" . json_encode($_POST), "INFO");
        $imei = '0'.$imei;
        $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
        if(!$user_arr){
            $arr = ['code'=>0,'message'=>'该用户不存在'];
            $this->ajaxReturn($arr,'json');
        }
        $this->check_terminal($uid,$uuid);
        $corporation = M("baojia_oauth.oauth2_client_corporation")->field("corporation_id,client_id")
            ->where(["corporation_id"=>$corporation_id])
            ->find();
        if(empty($corporation['client_id'])){
            $arr = ['code'=>0,'message'=>'企业用户异常！'];
            $this->ajaxReturn($arr,'json');
        }
        $client_info = M("baojia_oauth.oauth2_client")->field("user_id,client_id")
            ->where(["client_id"=>$corporation['client_id']])
            ->find();
        $brand = M('corporation')->field("corporation_brand_id")
            ->where(["id"=>$corporation['corporation_id']])
            ->find();
        $newCar = 0; //是否新车解绑  0  新车  1 老车
        $find_imei = "";
        if($plate_no){
            //车牌号校验
            $detection = $this->plate_no_detection($plate_no,$brand['corporation_brand_id']);
            if($detection == 1){
                $arr = ["code"=>0,"message"=>"[车牌号]".$plate_no."格式错误"];
                $this->ajaxReturn($arr,'json');
            }

            $carData = M('baojia_cloud.car')->field("car_id,client_id")->where("plate_no='{$plate_no}' and car_item_id = 0")->find();
            if($carData){
                $device1 = M('baojia_cloud.car_device_bind')->field('id,device_id,device_no')->where("car_id={$carData['car_id']} and client_id = '{$carData['client_id']}'")->find();
                if(empty($device1['device_no'])){
                    $arr = ["code"=>0,"message"=>"该车未绑定盒子无需解绑"];
                    $this->ajaxReturn($arr,'json');
                }
                $find_imei = $device1['device_no'];
            }else{
                $newCar = 1;
                $car_item_id = M("car_item_verify")->where(["plate_no"=>$plate_no])->getField("car_item_id");
                if($car_item_id){
                    $imei_arr = M("car_item_device")->where(["car_item_id"=>$car_item_id])->getField("imei");
                    if(!$imei_arr){
                        $arr = ["code"=>0,"message"=>"该车未绑定盒子无需解绑"];
                        $this->ajaxReturn($arr,'json');
                    }
                    $find_imei = $imei_arr;
                }else{
                    $arr = ["code"=>0,"message"=>"该车未绑定盒子无需解绑"];
                    $this->ajaxReturn($arr,'json');
                }

            }
        }

        if(!empty($imei)){
            //设备号格式
            $imei_detection = $this->imei_detection($imei);
            if($imei_detection == 1){
                $arr = ["code"=>0,"message"=>"[设备号]".$imei."格式错误"];
                $this->ajaxReturn($arr,'json');
            }

            $clientid = $corporation['client_id']?$corporation['client_id']:$this->config_1['clientid'];
            $device1 = M('baojia_cloud.car_device_bind')->field('id,car_id,device_id,device_no')->where("device_no='{$imei}' and client_id = '{$clientid}'")->find();
            $carData = [];
            if($device1){
                $carData = M('baojia_cloud.car')->field("plate_no")->where("car_id={$device1['car_id']} and car_item_id=0")->find();
                $plate_no = $carData['plate_no'];
            }
            if(empty($carData['plate_no'])){

                $newCar = 1;
                $car_item_id = M("car_item_device")->where(["imei"=>$imei])->getField("car_item_id");

                if($car_item_id){
                    $plate_no = M("car_item_verify")->where(["car_item_id"=>$car_item_id])->getField("plate_no");
                    if(!$plate_no){
                        $arr = ["code"=>0,"message"=>"该车未绑定盒子无需解绑"];
                        $this->ajaxReturn($arr,'json');
                    }
                }else{
                    $arr = ["code"=>0,"message"=>"该车未绑定盒子无需解绑"];
                    $this->ajaxReturn($arr,'json');
                }
            }
        }

		\Think\Log::write($plate_no."两次盒子号" .$find_imei."二：". $imei, "INFO");
        if($find_imei && $imei && ($find_imei != $imei)){
            $arr = ["code"=>401,"message"=>"盒子号与车辆不匹配","data"=>['title'=>'盒子号与车辆不匹配','content'=>'该车盒子编号为'.$find_imei.'请查证后在解绑！']];
            $this->ajaxReturn($arr,'json');
        }
        if($type==0){
            $arr = ["code"=>1,"message"=>"检测成功"];
            $this->ajaxReturn($arr,'json');
        }

        if(empty($car_status) || empty($gis_lng) || ($car_status == 13 && empty($desc))){
            $arr = ["code"=>0,"message"=>"参数错误"];
            $this->ajaxReturn($arr,'json');
        }
        if($newCar == 0){
            $reuslt = $this->del_car($uid,$plate_no,$client_info['user_id']);
            if($reuslt['code'] == 1){
                 D('FedGpsAdditional')->operation_log($uid,-10,$plate_no,$gis_lng,$gis_lat,21,$corporation_id,$car_status,$desc);
            }
            $this->ajaxReturn($reuslt,'json');
        }else{
            $car_item_device = M("car_item_device")->alias('cid')
                ->field('cid.id,cid.usercar_id,cid.car_item_id,rc.id rent_content_id')
                ->join('rent_content rc on cid.car_item_id = rc.car_item_id','left')
                ->where(["cid.imei"=>$imei])->find();
            M()->startTrans();
            if($car_item_device['rent_content_id']){
                \Think\Log::write($imei."线上车辆盒子数据".$newCar."：".$uid."返回值：" . json_encode($car_item_device), "INFO");
                $hasOrder = M("trade_order")->field("id,rent_content_id,rent_type,status")->where(" rent_content_id={$car_item_device['rent_content_id']} and rent_type=3 and status>=10100 and status<80200 and status<>10301 ")->find();
                if($hasOrder){
                    $arr = ["code"=>0,"message"=>"出租中的车辆不能解绑"];
                    $this->ajaxReturn($arr,'json');
                }
                $carStart = new \Api\Logic\CarStart();
                $result = $carStart->hzdelete($car_item_device['id'],$user_arr['user_id']);
                \Think\Log::write($imei."线上盒子解绑：".$uid."返回值：" . json_encode($result), "INFO");
                if($result['status'] == 20){
                    D('FedGpsAdditional')->operation_log($uid,-10,$plate_no,$gis_lng,$gis_lat,21,$corporation_id,$car_status,$desc);
                    if($car_item_device['usercar_id'] > 0){
                        $boxDisable = $this->boxDisable($car_item_device['usercar_id'],$car_item_device['imei']);
                        \Think\Log::write($imei."线上盒子解绑同步：".$uid."返回值：" . json_encode($boxDisable), "INFO");
                    }
                    //车辆下线
                    $verify_status = $carStart->verify_status($car_item_device['rent_content_id'],$uid);
                    \Think\Log::write($imei."车辆下线：".$uid."返回值：" . json_encode($verify_status), "INFO");
                    if($verify_status['code'] == 1){
                        $setRecycle = $carStart->setRecycle($car_item_device['rent_content_id'],$uid);
                        \Think\Log::write($imei."车辆移至回收站：".$uid."返回值：" . json_encode($setRecycle), "INFO");
                        if($setRecycle['code'] == 1){
                            M()->commit();
                            $arr = ["code"=>1,"message"=>"解绑成功"];
                            $this->ajaxReturn($arr,'json');
                        }else{
                            M()->rollback();
                            $this->ajaxReturn($setRecycle,'json');
                        }
                    }else{
                        M()->rollback();
                        $this->ajaxReturn($verify_status,'json');
                    }
                }else{
                    $arr = ["code"=>0,"message"=>"解绑失败"];
                    $this->ajaxReturn($arr,'json');
                }
            }else{
                $arr = ["code"=>0,"message"=>"该车未绑定盒子无需解绑"];
                $this->ajaxReturn($arr,'json');
            }
        }
    }

    //禁用盒子
    private function boxDisable($ucid, $imei) {

        // $url = C('BOX_API_URL') . "index.php?m=bjapisearch&ucid=" . $ucid . "&uid=" . $uid . "&cid=" . $cid . "&yz=2&cmd=sameyz";
        $url = C('BOX_API_URL') . "/index.php?m=bjapisearch&usercar_id=" . $ucid . "&imei=" . $imei . "&cmd=serviceObjsDelete";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取数据返回
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); // 在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回
        $output = curl_exec($ch);
        $output = substr($output, 1, -1);
        $arr = json_decode($output, true);
        if ($arr["status"] > 0) {
            return true;
        } else {
            return false;
        }
    }

    //新车盒子解绑
    public function  newUnbundledBox($user_id=0,$plate_no='',$corporation_id=0,$operationId=0,$uuid='yy'){

        \Think\Log::write("绑定过程中盒子解绑：".$user_id."请求参数：" . json_encode($_POST), "INFO");
        $oModel = M('operation_logging');
        $user_arr = A('Battery')->beforeUserInfo($user_id,$corporation_id);
        if(!$user_arr){
            $arr = ['code'=>0,'message'=>'该用户不存在'];
            $this->ajaxReturn($arr,'json');
        }
        $this->check_terminal($user_id,$uuid);
        if(empty($operationId)){
            $arr = ['code'=>0,'message'=>'参数错误'];
            $this->ajaxReturn($arr,'json');
        }
        $corporation = M("baojia_oauth.oauth2_client_corporation")->field("corporation_id,client_id")
            ->where(["corporation_id"=>$corporation_id])
            ->find();
        if(empty($corporation['client_id'])){
            $arr = ['code'=>0,'message'=>'企业用户异常！'];
            $this->ajaxReturn($arr,'json');
        }
        $client_info = M("baojia_oauth.oauth2_client")->field("user_id")
            ->where(["client_id"=>$corporation['client_id']])
            ->find();
        $brand = M('corporation')->field("corporation_brand_id")
            ->where(["id"=>$corporation['corporation_id']])
            ->find();
        //车牌号校验
        $detection = $this->plate_no_detection($plate_no,$brand['corporation_brand_id']);
        if($detection == 1){
            $arr = ["code"=>0,"message"=>"[车牌号]".$plate_no."格式错误"];
            $this->ajaxReturn($arr,'json');
        }

        $carData = $this->del_car($user_id,$plate_no,$client_info['user_id']);
        if($carData['code'] == 1){
            //更新操作状态
            $oModel->where("id={$operationId}")->setField('status',-1);
        }
        $this->ajaxReturn($carData,'json');
    }

    private  function  del_car($user_id,$plate_no,$qy_uid){
        $carData = M('baojia_cloud.car')->field("car_id,client_id")->where("plate_no='{$plate_no}' and car_item_id=0")->find();

        $res['code'] = 0;
        $res['message'] = '该车已被解绑';
        if($carData){
            $device1 = M('baojia_cloud.car_device_bind')->field('id,device_id,device_no')->where("car_id={$carData['car_id']} and client_id = '{$carData['client_id']}'")->find();
            $del_log['update_by']  = $qy_uid;
            $del_log['app_userId'] = $user_id;
            $del_log['update_time'] = time();
            $del_log['opt_type'] = 1;
            $del_log['obj_name'] = 'Cooperation/lists';
            $del_log['pre_info'] = serialize(json_encode($carData));
            $del_log['after_info'] = 0;

            $del1 = M('baojia_cloud.car')->where("car_id={$carData['car_id']}")->delete();
            if($del1){
                M('baojia_cloud.baojia_cloud_opt_log')->add($del_log);
                $res['code'] = 1;
                $res['message'] = '解绑成功';
            }
            if(is_array($device1)){
                M('baojia_cloud.car_device_bind')->where("id={$device1['id']}")->delete();
                M('baojia_cloud.device')->where("device_id = {$device1['device_id']} and client_id = '{$carData['client_id']}'")->delete();
            }
        }else{
            $res['code'] = 1;
            $res['message'] = '解绑成功';
        }
        return  $res;
    }

    //查看是否有未绑定完成的盒子
    public   function   undoneBox($user_id=2658265,$corporation_id=2118,$uuid='yy'){

        if(empty($user_id) || empty($corporation_id)){
            $arr = ['code'=>0,'msg'=>'参数错误'];
            $this->ajaxReturn($arr,'json');
        }
        $user_arr = A('Battery')->beforeUserInfo($user_id,$corporation_id);
        if(!$user_arr){
            $arr = ['code'=>0,'msg'=>'该用户不存在'];
            $this->ajaxReturn($arr,'json');
        }
        $this->check_terminal($user_id,$uuid);
        $oModel = M('operation_logging');
        $operation = $oModel->field('plate_no,id')->where("uid={$user_id} and operate=20 and status=0")->find();
		
        $res['code'] = 0;
        $res['message'] = '没有未完成盒子绑定';
        if($operation){
            $carData = M('baojia_cloud.car')->field("car_id,client_id")->field('car_id,client_id')->where("plate_no='{$operation['plate_no']}'")->find();
            if(empty($carData['car_id'])){
				$carData['car_id'] = 0;
			}
            $device1 = M('baojia_cloud.car_device_bind')->field('id,device_id,device_no')->where("car_id={$carData['car_id']} and client_id = '{$carData['client_id']}'")->find();
            if($device1){
                $res['code'] = 1;
                $res['message'] = '你有未完成的盒子绑定';
                $res['data']['operationId'] = $operation['id'];
                $res['data']['corporation_id'] = $corporation_id;
                $res['data']['user_id']  = $user_id;
                $res['data']['plate_no'] = $operation['plate_no'];
                $res['data']['device_no'] = ltrim($device1['device_no'], "0");
            }else{
                $oModel->where("uid={$user_id} and id={$operation['id']}")->setField('status',3);
                $res['code'] = 0;
                $res['message'] = '盒子已经解绑';
            }
        }
        $this->ajaxReturn($res,'json');
    }

    //完成盒子绑定
    public  function  completeBoxBound($user_id=0,$corporation_id=0,$operationId=0,$uuid='yy'){

        $user_arr = A('Battery')->beforeUserInfo($user_id,$corporation_id);
        if(!$user_arr){
            $arr = ['code'=>0,'msg'=>'该用户不存在'];
            $this->ajaxReturn($arr,'json');
        }
        $this->check_terminal($user_id,$uuid);
        $oModel = M('operation_logging');
        $operation = $oModel->field('plate_no,id')->where("uid={$user_id} and id={$operationId} and operate=20 and status=0")->find();
        if($operation){
            //更新操作状态
            $oModel->where("id={$operationId}")->setField('status',1);
            $arr = ['code'=>1,'msg'=>'绑定完成'];
            $this->ajaxReturn($arr,'json');
        }else{
            $arr = ['code'=>0,'msg'=>'绑定失败'];
            $this->ajaxReturn($arr,'json');
        }
    }

    //品牌和型号接口
    public   function  brandModel(){
        $brandModel['brand_model'][] = '九九一轻骑版';
        $brandModel['brand_model'][] = '小马轻骑版';
        $brandModel['brand_model'][] = '新日轻骑版';
        $brandModel['brand_model'][] = '台铃轻骑版';
        $arr = ['code'=>1,'msg'=>'数据接收成功','data'=>$brandModel];
        $this->ajaxReturn($arr,'json');
    }

    //电压检测
    public    function   voltageDetection($user_id=0,$imei='')
    {
        $imei = '0'.$imei;
        $user_arr = A('Battery')->beforeUserInfo($user_id,0);
        if(!$user_arr){
            $arr = ['code'=>0,'msg'=>'该用户不存在'];
            $this->ajaxReturn($arr,'json');
        }

        //设备号格式
        $imei_detection = $this->imei_detection($imei);
        if($imei_detection == 1){
            $arr = ["code"=>0,"message"=>"[设备号]".$imei."格式错误"];
            $this->ajaxReturn($arr,'json');
        }
		
        // $service_url = 'http://wg.baojia.com/simulate/service';
        $service_url = C("GATEWAY_LINK");
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
                $res2 = $this->VoltagePost($cData, $service_url);
                if ($res2['rtCode'] == '0') {
                    break;
                } else {
                    sleep(1);
                }
            }
//            echo "dddddddd";
           // echo "<pre>";
           // print_r($res2);
            if ($res2['rtCode'] == '0' && strlen($res2['rtCode']) > 0) {
                // $electricity = A('Yunwei')->getDumpEle($res2['result']['voltage']);
				$electricity = $res2['result']['dumpEle'];
                // $electricity = $electricity ? intval($electricity * 100) : 0;
                if($res2['result']['voltage'] > 47){
                    $message = '电量'.$electricity.'%(电压'.$res2['result']['voltage'].'V)';
                    $this->response(["code" => 1, "message" => $message], 'json');
                }else{
                    $message = "电量".$electricity."%(电压".$res2['result']['voltage']."V)\r\n检测电池电压值较低，请更换电池后再试试";
                    $this->response(["code" => 2, "message" => $message], 'json');
                }
            } else {
                $this->response(["code" => 0, "message" => "再试一次"], 'json');
            }
        }else{
            $data['carId'] = ltrim($imei, "0");
            $data['type'] = 34;
            $data['cmd'] = 'statusQuery';
            $res = $this->VoltagePost($data, $service_url);
			
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
                    $res2 = $this->VoltagePost($cData, $service_url);
                    if ($res2['rtCode'] == '0') {
                        break;
                    } else {
                        sleep(1);
                    }
                }
//                echo "ggggg";
               // echo "<pre>";
               // print_r($res2);
                if ($res2['rtCode'] == '0' && strlen($res2['rtCode']) > 0) {
                    // $electricity = A('Yunwei')->getDumpEle($res2['result']['voltage']);
					$electricity = $res2['result']['dumpEle'];
                    $electricity = $electricity ? intval($electricity * 100) : 0;
                    if($electricity > 55){
                        $message = '电量'.$electricity.'%(电压'.$res2['result']['voltage'].'V)';
                        $this->response(["code" => 1, "message" => $message], 'json');
                    }else{
                        $message = "电量".$electricity."%(电压".$res2['result']['voltage']."V)\r\n检测电池电压值较低，请更换电池后再试试";
                        $this->response(["code" => 2, "message" => $message], 'json');
                    }
                } else {
                    $this->response(["code" => 0, "message" => "再试一次"], 'json');
                }
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
    /**添加盒子
     * Array $data 添加的数据
     **/
    public function add_imei($data){
        $res = M('car_item_device')->add($data);
        return $res;
    }
    /**盒子更换
     * $old_imei 旧的盒子号
     * $imei 新的盒子号
    **/
    public function update_car_item_device($old_imei="865067025712095",$imei="865067025367080",$uid = '2650355', $iflogin = '',$rent_content_id = '5884159',$plate_no = '',$gis_lng = '',$gis_lat = '',$corporation_id="10258",$uuid='ff'){
        C('BOX_API_URL', "http://iobox.baojia.com/");
        $uid = I("post.uid");
        $old_imei = I("post.old_imei");
        $imei = I("post.imei");
        $rent_content_id = I("post.rent_content_id");
//        M("car_item_device")->where(["id"=>111921])->save(["mobile"=>'43668214556']);die;
            if(strlen($old_imei)==15){
                $old_imei = "0".$old_imei;
            }
            if(strlen($imei)==15){
                $imei = "0".$imei;
            }

//            $old_imei = "0865067025712095";
//            $imei = "0865067025367080";
//            $rent_content_id = '5884159';
        $this->check_terminal($uid,$uuid);
        $search = new \Api\Logic\SearchInfo();
        $user_type = $search->getUserType($uid, $corporation_id);
        if($user_type["role_type"]==3 || ( $user_type["role_type"]==1&&$user_type["job_type"]==1 ) ){
            //运维全职 整备有此功能
        }else{
            $this->response(["code" => -1, "message" => "你没有权限操作"], 'json');
        }
        //判断车辆的出租中和待租中状态
        $hasOrder = M("trade_order")->where(" rent_content_id={$rent_content_id} and rent_type=3 and status>=10100 and status<80200 and status<>10301 ")->find();
        if($hasOrder){
            $this->response(["code" => -1, "message" => "出租中的车辆不允许更换盒子"], 'json');
        }
        $sell_status = M("mebike_status")->where("rent_content_id={$rent_content_id}")->getField("sell_status");
        if( $sell_status==1 ){
            $this->response(["code" => -1, "message" => "待租中的车辆不允许更换盒子"], 'json');
        }
        $imei_detection = $this->imei_detection($old_imei);
        if ($imei_detection == 1) {
            $this->response(["code" => -1, "message" => "旧盒子号格式错误，请重试"], 'json');
        }
        $imei_detection1 = $this->imei_detection($imei);
        if ($imei_detection1 == 1) {
            $arr = ["code" => -1, "message" => "新盒子号格式错误，请重试"];
            $this->ajaxReturn($arr, 'json');
        }

        //判断当前车辆绑定旧盒子号($oldboxdata["imei"])是否和接收的旧盒子($old_imei)号一致
        $rent_content = M("rent_content")->where(["id"=>$rent_content_id])->field("city_id,car_item_id")->find();
//
        $oldboxdata = M("car_item_device")->where(["car_item_id" => $rent_content["car_item_id"]])->field("*")->find();


        //入库新盒子
        $user_name = M("member")->field("last_name,first_name")->where("uid=".$user_type["user_id"])->find();
        $data = M("car_item_device")->where("imei={$imei}")->field("id,car_item_id,track_sid")->find();  //查询新的盒子是否存在

        //查询新盒子是否绑定车辆
        $is_car = M("car_item_verify")->where(["car_item_id" => $data["car_item_id"]])->field("id")->find();  //查询新的盒子是否存在
        if(!empty($is_car)){
            $this->response(["code" => -1, "message" => "新盒子号已经绑定车辆，请先解绑"], 'json');
        }
//        echo "<pre>";
//        print_r($data);die;
        if(empty($data)){
            $insert_data = [
                "city_id" => $rent_content["city_id"],
                "imei" => $imei,
                "mobile" =>$oldboxdata["mobile"],
                "device_type" =>18,
                "receive_uid" => $uid,
                "create_time" => time(),
                "service_name" =>$user_name['last_name'] . $user_name['first_name'],
                "receive_time" => time(),
                "status" => 2
            ];
            $this->add_imei($insert_data);
        }else{
            M("car_item_device")->where(["id"=>$data["id"]])->save(["mobile"=>$oldboxdata['mobile']]);
        }
//        echo "<pre>";
//        print_r($imei);die;
        if($oldboxdata["imei"] != $old_imei){
            $this->response(["code" => -1, "message" => "旧盒子号未绑定该车，请重试"], 'json');
        }
        if($oldboxdata["imei"] == $imei){
            $this->response(["code" => -1, "message" => "该车已绑定新盒子号，请重试"], 'json');
        }
        $data = M("car_item_device")->where("imei={$imei}")->field("id,track_sid")->find();  //查询新的盒子是否存在

        if(empty($data)){
            $this->response(["code" => -1, "message" => "新盒子号不存在，请重试"], 'json');
        }
        if(!empty($data["track_sid"])){
            $this->response(["code" => -1, "message" => "新盒子号已绑定其他车辆，请解绑后重试"], 'json');
        }
        $check_imei = $this->locationVerify($imei);
        if(!$check_imei){
            $this->response(["code" => -1,"message" => "新盒子号检测不到信号"],"json");
        }
        //同步盒子
        //禁用旧盒子
        $this->boxDisable($oldboxdata["usercar_id"], $oldboxdata["imei"]);

        $data_tb = array();
        $data_tb['imei'] = $imei;
        $data_tb['mobile'] = empty($oldboxdata["mobile"]) ? "43668214556" : $oldboxdata["mobile"];
        $data_tb['start_time'] = date("y-m-d", $oldboxdata["start_time"]);
        $data_tb['end_time'] = date("Y-m-d", $oldboxdata["end_time"]);
        $data_tb['device_type'] = $oldboxdata['device_type'];
        $data_tb['car_item_id'] = $oldboxdata['car_item_id'];
        $data_tb['owner_id'] = $oldboxdata['owner_id'];
        $data_tb['city_id'] = $oldboxdata['city_id'];
        $data_tb['carname'] = M("car_item")->where(array("id" => $oldboxdata["car_item_id"]))->getField("car_model_name");
        $data_tb['carmono'] = M("car_item_verify")->where(array("car_item_id" => $oldboxdata["car_item_id"]))->getField("plate_no");
        $data_tb['utftogbk'] = 1;


        $new_user_car_id = $this->boxSameinfo($data_tb);

//        echo "<pre>";
//        print_r($data);die;
        if (!$new_user_car_id) {
            $this->response(["code" => -1,"message" => "同步失败"],"json");
        }
        $is_mebike = $this->isMebike($oldboxdata["car_item_id"]);
        if(empty($oldboxdata["track_sid"]) && !$is_mebike){
            $this->response(["code" => -1,"message" => "原盒子未绑定工单，不能更换"],"json");
        }
        $newbox = array(//新盒子数据更改
            'owner_id' => $oldboxdata['owner_id'],
            'mobile' => empty($oldboxdata["mobile"]) ? "43668214556" : $oldboxdata["mobile"],
            'car_item_id' => $oldboxdata['car_item_id'],
            'usercar_id' => $new_user_car_id,
            'start_time' => $oldboxdata['start_time'],
            'end_time' => $oldboxdata['end_time'],
            'status' => $oldboxdata['status'],
            'pay_status' => $oldboxdata['pay_status'],
            'install_status' => $oldboxdata['install_status'],
            'track_sid' => $oldboxdata['track_sid'],
            'trade_order' => $oldboxdata['trade_order'],
            'donate' => $oldboxdata['donate'],
        );

        M("car_item_device")->where("id={$data['id']}")->save($newbox); //更改新盒子数据

        $newboxsave = array(//修改car_item_device_track表字段的数组

            'item_device_id' => $data['id'],
            'device_imei' => $imei,
            'device_mobile' => "",
        );
        $car_item_device_track_save = M("car_item_device_track")->where("sid={$oldboxdata['track_sid']}")->save($newboxsave); //更改car_item_device_track数据
        $car_item_device_track_select = M("car_item_device")->where("id={$data['id']}")->field("track_sid")->find(); //获得修改信息的SID(准备添加到service_track_record表中)

        $service_track_record_save = array(
            "sid" => $car_item_device_track_select['track_sid'],
            "create_time" => time(),
            "type" => 2,
            "uid" => $uid,
            "remarks" => "盒子号:" . $oldboxdata['imei'] . "SIM卡号" . $oldboxdata['mobile'] . "变更为新盒子号:" . $imei . "新SIM卡号",
        );
        //同步记录到operation_logging

        $op_data = [
            "uid" => $uid,
            "operate" => 26, //26 = 更换盒子,
            "rent_content_id" => $rent_content_id,
            "plate_no" => $data_tb['carmono'],
            "time" => time(),
            "gis_lng" => $gis_lng,
            "gis_lat" => $gis_lat
        ];
        $res = D("OperationLogging")->add_record($op_data);

        $aaa = M("service_track_record")->add($service_track_record_save);
        $oldbox = array(//声明数组老盒子数据更改
            'owner_id' => 0,
            'car_item_id' => 0,
            'mobile' => '',
            'usercar_id' => 0,
            'start_time' => 0,
            'end_time' => 0,
            'status' => 2,
            'pay_status' => 0,
            'install_status' => 0,
            'track_sid' => 0,
            'trade_order' => 0,
            'donate' => 0,
        );
        M("car_item_device")->where("id=".$oldboxdata["id"])->save($oldbox); //更改老盒子数据
        if ($car_item_device_track_save > 0) {
            $this->response(["code" => 1,"message" => "更换盒子成功"],"json");
        }elseif($is_mebike){

            $old_imei = $oldboxdata["imei"];
            $no_zero_old_imei = ltrim($old_imei,0);
            $no_zero_imei = ltrim($imei,0);
            $car_group_r = M("car_group_r","","BAOJIA_LINK_DC")->where(["carId"=>$no_zero_old_imei])->find();
            $result6 = -1;
            if($car_group_r){
                $result6 = M("car_group_r","","BAOJIA_LINK_DC")->where(["id"=>$car_group_r["id"]])->save(["carId"=>$no_zero_imei]);
            }

            $corporation_car_group = M("corporation_car_group","","BAOJIA_LINK_DC")->where(["carId"=>$old_imei])->find();

            $result7 = -1;
            if($corporation_car_group){
                $result7 = M("corporation_car_group","","BAOJIA_LINK_DC")->where(["id"=>$corporation_car_group["id"]])->save(["carId"=>$imei]);
            }

            \Think\Log::write("unbindThirdDevice更换盒子::result".$result6."|".$result7, 'INFO');

            $this->response(["code" => 1,"message" => "更换盒子成功"],"json");

        }

//        echo "<pre>";
//        print_r($check_imei);
    }

    //校验定位
    private function locationVerify($imei = 0) {
        //$imei = "354188047309385";
        //354188047309385
        $url = C('BOX_API_URL') . "/index.php?m=bjapisearch&is_admin_type=1&imei=" . $imei . "&cmd=serviceObjsRealTrack";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取数据返回
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); // 在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回
        $output = curl_exec($ch);
        $output = substr($output, 1, -1);
        $arr = json_decode($output, true);
        if (strlen($arr["detail"]["objList"]['lng']) > 0) {
            return true;
        } else {
            return false;
        }
    }
    //同步盒子
    private function boxSameinfo($data) {
        $data_url = http_build_query($data);
        $url = C('BOX_API_URL') . "/index.php?m=sameinfo&" . $data_url;
        // error_log("test123".$url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取数据返回
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); // 在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回
        $output = curl_exec($ch);
        // error_log($output);



        $output = substr($output, 1, -1);

        $arr = json_decode($output, true);
        if ($arr["id"]) {
            return $arr["id"];
        } else {
            return false;
        }
    }
    public function isMebike($car_item_id){

        \Think\Log::write("isMebike:".$car_item_id, 'INFO');
        $rent_info = M("rent_content")->where(["car_item_id"=>$car_item_id])->field("corporation_id,car_item_id")->find();
        $corporation_id = $rent_info["corporation_id"];
        if($corporation_id > 0){
            $corporation_info = M("corporation")->where(["id"=>$corporation_id])->field("parent_id")->find();
            if($corporation_info["parent_id"] > 0){
                $corporation_info_parent = M("corporation")->where(["id"=>$corporation_info["parent_id"]])->field("corporation_brand_id")->find();
                if(in_array($corporation_info_parent["corporation_brand_id"],["100000","100036","100010"])){
                    \Think\Log::write("isMebike:true", 'INFO');
                    return true;

                }
            }
        }
        \Think\Log::write("isMebike:false", 'INFO');
        return false;


    }
    private function control_post($post_data,$url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private  function VoltagePost($data, $url)
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

    //车牌号检测
    public   function   plate_no_detection($plate_no,$corporation_brand_id){
        //车牌号校验
        $status = 0;
        if(!
        (preg_match('/^[A-Z]{2}((\d{6})|(\d{9,10}))$/', $plate_no)
            || (
                preg_match("/^[\x{4e00}-\x{9fa5}]{2}\d{6,8}$/u",$plate_no)
                && $corporation_brand_id == 100000
            )
            || preg_match("/^[\x{4e00}-\x{9fa5}]{1}[A-Z0-9]{5,6}$/u",$plate_no)
        )
        ){
            $status = 1;
        }
        return  $status;
    }

    public   function   imei_detection($imei)
    {
        //设备号格式
        $status = 0;
        if (!in_array(strlen($imei), [16])) {
            $status = 1;
        }
        return  $status;
    }
    public   function   zoneBinding($plate_no='',$groupId='',$corporation_id=0){
        $zsboxdb='mysqli://api-baojia:CSDV4smCSztRcvVb@10.1.11.14:3306/baojia_box';
        $zsdb='mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc';
        //企业负责人
        $model = M('corporation');
        $corporation = $model->where(['id'=>$corporation_id])->getField('finance_user_id');
        if($corporation){
            $where1="carmono ={$plate_no} and devicetype in(12,14,16,9,18) and car_owner_id={$corporation}";
            $car_de_info=M('gps_usercar','',$zsboxdb)->field('imei,carmono,devicetype')->where($where1)->find();
            if($car_de_info){
                $car_group=M('car_group_r','',$zsdb)->field("id,plate_no")->where("plate_no='{$car_de_info['carmono']}'")->find();
                if($car_group){
                    ['code'=>0,'msg'=>'该车已绑定行驶区域'];
                }
                if(!in_array($car_de_info['devicetype'],[14])){
                    $car_group_arr['carId']=ltrim($car_de_info['imei'],0);
                }else{
                    $car_group_arr['carId']=$car_de_info['imei'];
                }
                $car_group_arr['groupId']=$groupId;
                $car_group_arr['plate_no']=$car_de_info['carmono'];
                $car_group_arr['addtime']=time();
                $car_group_arr['updatetime']=time();
                $car_group_arr['status']=1;
                $car_group_arr['user_id']=$corporation;
                $cid=M('car_group_r','',$zsdb)->add($car_group_arr);
                if($cid){
                    ['code'=>1,'msg'=>'绑定成功'];
                }else{
                    ['code'=>0,'msg'=>'绑定失败'];
                }
            }else{
                ['code'=>0,'msg'=>'该车辆不属于该账户,车辆无法绑定'];
            }
        }else{
            ['code'=>0,'msg'=>'该企业不存在'];
        }
    }


}