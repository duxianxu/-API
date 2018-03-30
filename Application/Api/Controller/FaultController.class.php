<?php
/**
 * Created by PhpStorm.
 * User: DuXu
 * Date: 2017/7/21
 * Time: 16:04
 */
namespace Api\Controller;
use Think\Controller\RestController;
class FaultController extends BController {

    private $zsdb='mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc'; //阿里云数据库
    private $box_config = 'mysqli://api-baojia-2:CSDV4smCSztRcvVb@10.1.11.14:3306/baojia_box';  //14盒子信息数据库
     //private $box_config = 'mysql://apitest-baojia:TKQqB5Gwachds8dv@10.1.11.110:3306/baojia_box';  //14盒子信息数据库  测试
     private $task_type = [
        '1' => '寻车',
        '2' => '回库',
        '3' => '调度',
        '4' => '换电',
        '5' => '检修',
         '6' => '特殊下架任务',
         '7' => '调度下架',
    ];
    public function index()
    {
        $this->display('index');
    }
    public function test(){
//       $res1 = M("aaa")->select();
//       var_dump($res1);
//                M("mebike_status")->where(["car_item_id"=>361159])->save(["sell_status"=>100]);
//		 echo "<pre/>";
//       print_r($id);die;
        echo phpinfo();
    }

    //分类规则
    public function sort_rules(){
        $return = [];
        $return[0]['name'] = "缺电";
        $return[0]['desc'] = "电量为30%以下";
        $return[0]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/quedian.png";
        $return[1]['name'] = "馈电下架";
        $return[1]['desc'] = "电量为20%以下不包含0%";
        $return[1]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/kuidian.png";
        $return[2]['name'] = "无电";
        $return[2]['desc'] = "电量为0%";
        $return[2]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/wudian.png";
        $return[3]['name'] = "无电离线";
        $return[3]['desc'] = "电量为0%的离线车辆，多为完全无电导致盒子离线";
        $return[3]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/wudianlixian.png";
        $return[4]['name'] = "离线下架";
        $return[4]['desc'] = "车辆盒子离线，并已下架";
        $return[4]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/lixianxiajia.png";
        $return[5]['name'] = "两日无单";
        $return[5]['desc'] = "当前时间48小时内无订单车辆";
        $return[5]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/liangriwudan.png";
        $return[6]['name'] = "故障车辆";
        $return[6]['desc'] = "连续三次无法使用并且移动小于300m";
        $return[6]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/youdanwucheng.png";
        $return[7]['name'] = "待小修";
        $return[7]['desc'] = "标记为需要小修的车辆，需要人员处理";
        $return[7]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/daixiaoxiu.png";
        $return[8]['name'] = "越界下架";
        $return[8]['desc'] = "超出服务区的下架车辆，需要调度拉回";
        $return[8]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/yuejiexiajia.png";
        $return[9]['name'] = "大修";
        $return[9]['desc'] = "标记为需要拉回维修的车辆";
        $return[9]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/daxiu.png";
        $return[10]['name'] = "待调动";
        $return[10]['desc'] = "标记为需要调度移动的车辆";
        $return[10]['image'] = "http://".$_SERVER['HTTP_HOST']."/Public/img/fenlei/daidiaodong.png";
        $this->response(["code" => 1, "data" => $return], 'json');
    }
    /*
            操作
            回收下架 sell_status = -10
            上架 sell_status = 1
            丢失 operate_status = 6  :
            电池丢失上报 operation_type = 3  :
            丢失车辆找回上报 operation_type = 4  : 35=故障上报 36=备注车辆37=被扣押上报
             派单主键id   pid
            operation_logging 1=换电设防, 2=换电设防失败, 3=确认回收, 4=完成小修, 5=下架回收(car_status=1待维修 =2 待调度), 6=小修上报, 7=车辆丢失, 8=上架待租
            desc               车况描述
            type               来源类型    1=车况上报里的下架回收  2=需排查的故障上报
    */
    public function repairOperation($corporation_id = '' ,$pid = '',$uid = '', $iflogin = '',$rent_content_id = '123',$plate_no = '',$gis_lng = '',$gis_lat = '',$operation_type = '', $car_status = '', $desc = "",$type=1,$uuid='ff')
    {

        // $uuid = $_POST["uuid"];
        if( empty($rent_content_id) || empty($plate_no) || empty($uid) || empty($operation_type) || empty($uuid) ){
            $this->response(["code" => -1001, "message" => "参数有误"], 'json');
        }
        $this->promossion($uid,$iflogin);
        $this->check_terminal($uid,$uuid);
        $rent_info = M("rent_content")->where("id = %d and sort_id = %d",$rent_content_id,112)->find();
        if(!$rent_info){
            $this->response(["code" => -1001, "message" => "车辆不存在"], 'json');
        }
        if(!$operation_type){
            $this->response(["code" => -1001, "message" => "无操作类型"], 'json');
        }
        $areaLogic= new \Api\Logic\Area();
        $is_rating = $areaLogic->is_rating($rent_content_id);
        if($is_rating){
            $this->response(["code" => -1001, "message" => "出租中车辆，请稍候再操作"], 'json');
        }
        $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
        $upload_image = new \Api\Logic\UploadImage();
        $battery = $this->plate($plate_no);
        $battery = (float)$battery['residual_battery'];
        $sell_status = M("rent_content")->where(["id"=>$rent_content_id])->getField('sell_status');
        $data_status = $areaLogic->get_status($rent_content_id);
        if($operation_type == 5){
            if( empty($car_status) ){
                $this->response(["code" => -1001, "message" => "请选择故障类型"], 'json');
            }
            if( empty($desc) ){
                $this->response(["code" => -1001, "message" => "请描述车辆问题"], 'json');
            }
            if($data_status["repaire_status"]==200){
                $this->response(["code" => -1001, "message" => "该车已为故障"], 'json');
            }
            $record_data = [
                "uid" => $uid,
                "operate" => 35, //35=故障上报
                "car_status" => $car_status,
                "desc" => $desc,
                "rent_content_id" => $rent_content_id,
                "plate_no" => $plate_no,
                "battery" => $battery,
                "time" => time(),
                "status" => 0,
                "pid" => $pid,
                "gis_lng" => $gis_lng,
                "gis_lat" => $gis_lat,
//                "pre_status" => $sell_status
            ];
            if( isset($_FILES["pic1"]) ){
                $pic1 = $upload_image->upload($_FILES["pic1"]);
                $record_data['pic1']=$pic1["pic1"]['short_path'];
            }
            if( isset($_FILES["pic2"]) ){
                $pic2 = $upload_image->upload($_FILES["pic2"]);
                $record_data['pic2']=$pic2["pic1"]['short_path'];
            }
            if( isset($_FILES["pic3"]) ){
                $pic3 = $upload_image->upload($_FILES["pic3"]);
                $record_data['pic3']=$pic3["pic1"]['short_path'];
            }
            if( isset($_FILES["pic4"]) ){
                $pic4 = $upload_image->upload($_FILES["pic4"]);
                $record_data['pic4']=$pic4["pic1"]['short_path'];
            }
            if( empty($record_data['pic1']) ){
                $this->response(["code" => -1001, "message" => "图片缺失，请重新上传"], 'json');
            }
			
			if($data_status["dispatch_status"]==100){
			   $car_status_data["dispatch_status"] = 0;
			}
			$car_status_data["repaire_status"] = 200;

            $res = $areaLogic->step_status($car_status_data, $rent_content_id, 100);

            if ($res) {
                $add = D("OperationLogging")->add_record($record_data);
                if($type==2){
                    $update1 = D("DispatchOrder")->update("id={$pid}",["verify_status"=>3,"verify_time"=>time()]);
                    $data = [
                        "user_id" => $uid,
                        "dispatch_order_id" => $pid,
                        "create_time" => time(),
                        "verify_status" => 3,
                        "lng" => $gis_lng,
                        "lat" => $gis_lat,
                        "remark" => "上报故障并结束了任务"
                    ];
                    $add_log = M("baojia_mebike.dispatch_order_log")->add($data);
                }
                $this->response(["code" => 1000, "message" => "故障上报成功"], 'json');
            } else {
                $this->response(["code" => -1000, "message" => "故障上报失败"], 'json');
            }

        }elseif($operation_type == 1){
            $record_data = [
                "uid" => $uid,
                "operate" => 8, //8=上架待租,
                "rent_content_id" => $rent_content_id,
                "plate_no" => $plate_no,
                "time" => time(),
                "battery" => $battery,
                "pid" => $pid,
                "gis_lng" => $gis_lng,
                "gis_lat" => $gis_lat

            ];
            M()->startTrans();
            $res = $areaLogic->step_status(["special_status"=>0,"seized_status"=>0,"transport_status"=>0,"repaire_status"=>0,"storage_status"=>0],$rent_content_id,0);
            $add = D("OperationLogging")->add_record($record_data);
            if($res&&$add){
                D("OperationLogging")->update("rent_content_id={$rent_content_id} and status=0 and operate=35",["status"=>1,"update_time"=>time()]);
				M()->commit();
                $this->response(["code" => 1000, "message" => "上架待租操作成功"], 'json');
            }else{
				M()->rollback();
                $this->response(["code" => -1000, "message" => "上架失败"], 'json');
            }
        }elseif($operation_type == 2){ //特殊下架
            if( $data_status["sell_status"]==100 ){
                $this->response(["code" => -1001, "message" => "车辆已下架,不可再次上报"], 'json');
            }
            //查询车辆是否有进行中任务
            $task_have = D("DispatchOrder")->get_one("plate_no = '{$plate_no}' and verify_status =2","verify_status");
            if( !empty($task_have) ){
                $this->response(["code" => -1001, "message" => "车辆任务正在进行中,暂不可操作!"], 'json');
            }
            //查询车辆是否派单
            $workorder = D("DispatchOrder")->get_one("plate_no = '{$plate_no}' and verify_status=1 and type=0","id,verify_status,create_status_code,is_special,special_remark,special_address","","create_time desc");
            if( !empty($workorder) ){
                $this->response(["code" => -1001, "message" => "车辆任务已被派出,暂不可操作!"], 'json');
            }
            if( empty($desc) ){
                $this->response(["code" => -1001, "message" => "请输入下架原因"], 'json');
            }
            if( isset($_FILES["pic1"]) ){
                $pic1 = $upload_image->upload($_FILES["pic1"]);
                $data['pic1']=$pic1["pic1"]['short_path'];
            }
            $data = [
                "uid" => $uid,
                "operate" => 25, //25=特殊下架,
                "rent_content_id" => $rent_content_id,
                "plate_no" => $plate_no,
                "time" => time(),
                "desc" => $desc,
                "battery" => $battery,
                "pid" => $pid,
                "gis_lng" => $gis_lng,
                "gis_lat" => $gis_lat

            ];
            M()->startTrans();
            $res = $areaLogic->step_status(["special_status"=>100],$rent_content_id,0);
            $add = D("OperationLogging")->add_record($data);
            if($res&&$add){
                M()->commit();
                $this->response(["code" => 1000, "message" => "特殊下架成功"], 'json');
            }else{
                M()->rollback();
                $this->response(["code" => -1000, "message" => "特殊下架失败"], 'json');
            }
        }elseif ($operation_type == 3) {//电池丢失上报
            if( isset($_FILES["pic1"]) && isset($_FILES["pic2"]) ){

                $pic = $upload_image->upload($_FILES["pic1"],$_FILES["pic2"]);
                $pic1 = $pic["pic1"];
                $pic2 = $pic["pic2"];
            }
            if(empty($pic1['short_path']) || empty($pic2['short_path'])){
                $this->response(["code" => -1001, "message" => "图片缺失，请重新上传"], 'json');
            };
            $data = [
                "uid" => $uid,
                "operate" => 11, //11 = 电池丢失,
                "rent_content_id" => $rent_content_id,
                "plate_no" => $plate_no,
                "time" => time(),
                "battery" => $battery,
                "pic1" => $pic1['short_path'],
                "pic2" => $pic2['short_path'],
                "pid" => $pid,
                "gis_lng" => $gis_lng,
                "gis_lat" => $gis_lat
            ];
            $res = D("OperationLogging")->add_record($data);
            if($res){
                $this->response(["code" => 1000, "message" => "电池丢失上报成功"], 'json');

            }else{
                $this->response(["code" => -1000, "message" => "电池丢失上报失败"], 'json');
            }
        }
        elseif ($operation_type == 7) {//被扣押上报
            $desc = $_POST['desc'] ? $_POST['desc'] : "";
            if($data_status["seized_status"]==100){
                $this->response(["code" => -1001, "message" => "该车已经被扣押"], 'json');
            }
            $data = [
                "uid" => $uid,
                "operate" => 37, //37=被扣押上报
                "rent_content_id" => $rent_content_id,
                "plate_no" => $plate_no,
                "time" => time(),
                "desc" => $desc,
                "battery" => $battery,
                "pid" => $pid,
                "gis_lng" => $gis_lng,
                "gis_lat" => $gis_lat
            ];
            if( isset($_FILES["pic1"]) ){
                $pic1 = $upload_image->upload($_FILES["pic1"]);
                $data['pic1']=$pic1["pic1"]['short_path'];
            }
            if( isset($_FILES["pic2"]) ){
                $pic2 = $upload_image->upload($_FILES["pic2"]);
                $data['pic2']=$pic2["pic1"]['short_path'];
            }
            if( isset($_FILES["pic3"]) ){
                $pic3 = $upload_image->upload($_FILES["pic3"]);
                $data['pic3']=$pic3["pic1"]['short_path'];
            }
            if( isset($_FILES["pic4"]) ){
                $pic4 = $upload_image->upload($_FILES["pic4"]);
                $data['pic4']=$pic4["pic1"]['short_path'];
            }
            if( empty($data['pic1']) ){
                $this->response(["code" => -1001, "message" => "图片缺失，请重新上传"], 'json');
            }
			M()->startTrans();
            $res = D("OperationLogging")->add_record($data);
            if($data_status["storage_status"]==100){
			   $car_status_data["storage_status"] = 0;
			}
			$car_status_data["seized_status"] = 100;
            $res1 = $areaLogic->step_status($car_status_data,$rent_content_id,100);
            if($res&&$res1){
                if($type==2){
                    $update1 = D("DispatchOrder")->update("id={$pid}",["verify_status"=>3,"verify_time"=>time()]);
                    $data1 = [
                        "user_id" => $uid,
                        "dispatch_order_id" => $pid,
                        "create_time" => time(),
                        "verify_status" => 3,
                        "lng" => $gis_lng,
                        "lat" => $gis_lat,
                        "remark" => "上报故障并结束了任务"
                    ];
                    $add_log = M("baojia_mebike.dispatch_order_log")->add($data1);
                }
				M()->commit();
                $this->response(["code" => 1000, "message" => "被扣押上报成功"], 'json');

            }else{
				M()->rollback();
                $this->response(["code" => -1000, "message" => "被扣押上报失败"], 'json');
            }
        }elseif ($operation_type == 8) {//备注车辆
            if( empty($desc) ){
                $this->response(["code" => -1001, "message" => "请备注车辆问题"], 'json');
            }
            $data = [
                "uid" => $uid,
                "operate" => 36, // 36=备注车辆
                "rent_content_id" => $rent_content_id,
                "plate_no" => $plate_no,
                "time" => time(),
                "desc" => $desc,
                "battery" => $battery,
                "pid" => $pid,
                "gis_lng" => $gis_lng,
                "gis_lat" => $gis_lat
            ];
            if( isset($_FILES["pic1"]) ){
                $pic1 = $upload_image->upload($_FILES["pic1"]);
                $data['pic1']=$pic1["pic1"]['short_path'];
            }
            if( isset($_FILES["pic2"]) ){
                $pic2 = $upload_image->upload($_FILES["pic2"]);
                $data['pic2']=$pic2["pic1"]['short_path'];
            }
            if( isset($_FILES["pic3"]) ){
                $pic3 = $upload_image->upload($_FILES["pic3"]);
                $data['pic3']=$pic3["pic1"]['short_path'];
            }
            if( isset($_FILES["pic4"]) ){
                $pic4 = $upload_image->upload($_FILES["pic4"]);
                $data['pic4']=$pic4["pic1"]['short_path'];
            }
            if( empty($data['pic1']) ){
                $this->response(["code" => -1001, "message" => "图片缺失，请重新上传"], 'json');
            }
            $res = D("OperationLogging")->add_record($data);
            if($res){
                $this->response(["code" => 1000, "message" => "备注车辆成功"], 'json');

            }else{
                $this->response(["code" => -1000, "message" => "备注车辆失败"], 'json');
            }
        }

    }
    //查询车辆状态
    public function car_status($uid = '', $iflogin = '',$rent_content_id = ''){
        $this->promossion($uid,$iflogin);
        $rent_info = M("rent_content")->where(["id"=>$rent_content_id,"sort_id"=>112])->getField('sell_status');
        if(!$rent_info){
            $this->response(["code" => -1001, "message" => "车辆不存在"], 'json');
        }
        $this->response(["code" => 1001, "data" => $rent_info], 'json');
    }

    //查询车辆回收时车辆位置
    public function find_location($uid = '', $iflogin = '',$id = '',$uuid='ff'){
        $this->promossion($uid,$iflogin);
        $this->check_terminal($uid,$uuid);
        $this->CheckInt($id);
        $result = D("OperationLogging")->get_all(["id"=>$id],"rent_content_id,gis_lat,gis_lng");
        $imei = M("rent_content")->alias('rc')
            ->join("car_item_device cid on rc.car_item_id = cid.car_item_id","left")
            ->field(" cid.imei,rc.id,rc.car_item_id")->where(["rc.id"=>$result[0]['rent_content_id']])->find();
        // $pt = [$result[0]['gis_lng'],$result[0]['gis_lat']];
        $pt = [$result[0]['gis_lng'],$result[0]['gis_lat']];
        $areaLogic= new \Api\Logic\Area();
        $location = $areaLogic->GetAmapAddress( $result[0]['gis_lng'], $result[0]['gis_lat']);
        $info = $areaLogic->isXiaomaInArea( $result[0]['rent_content_id'] , $pt, $imei['imei']);
        $result['is_Inarea'] = $info ? "界内" : "界外";
        $result['location'] = $location;
        if($result){
            $this->response(["code" => 1001, "message" => "成功", "data"=>$result], 'json');
        }else{
            $this->response(["code" => -1001, "message" => "失败"], 'json');
        }

    }
    //车辆详情接口
   /*
     * $is_scan = 1 扫码进入
     * $corporation_id 所属公司id
     * $page 页码值 数据分开返回
     * $type = 2 用于接口返回数据
     * */
    public function car_details($plate_no = 'DD932850', $uid = "2790831" ,$corporation_id = '2118' ,$iflogin = '',$gis_lng = '',$gis_lat = '',$is_scan = '0',$page = '1',$type = "1",$uuid='ff'){
        $uid = I("post.uid",0,'intval');
        $corporation_id = I("post.corporation_id",0,'intval');
        $uuid = $_POST["uuid"];
        $time1 = microtime_float();
        if($type != 2){
            $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
            if( empty($plate_no) || empty($uid) ){
                $this->response(["code" => 0, "message" => "参数有误"], 'json');
            }
            $this->promossion($uid,$iflogin);
            $this->check_terminal($uid,$uuid);
        }
        if(stripos($plate_no,"https://m")!== false){
            $plate_no = str_replace("https://m.baojia.com/car/","",$plate_no);
        }
        elseif(stripos($plate_no,"https://xm")!== false){
            $plate_no = str_replace("https://xm.baojia.com/","",$plate_no);
        }else{
            $is_dd=substr($plate_no,0,2);
            if($is_dd && (strtoupper($is_dd)=='DD'||strtoupper($is_dd)=='XM')){
                $plate_no = strtoupper($plate_no);
            }elseif( substr($is_dd,0,1)==8 ){
                $plate_no='XM'.$plate_no;
            }else{
                $plate_no='DD'.$plate_no;
            }
        }
        $gps = D('Gps');
        $areaLogic= new \Api\Logic\Area();
        $car_item_id = M("car_item_verify")->where("plate_no = '%s'",$plate_no)->getField("car_item_id");
		
        $rent_content_id = M("rent_content")->where(["car_item_id" => $car_item_id])->getField("id");
		
        if(empty($rent_content_id)){
            $this->response(["code" => -1, "message" => "查询无此车辆" ], 'json');
        }

        $data_status = $areaLogic->get_status($rent_content_id);
        //查询是否指派的车辆
       if( $type != 2 ){
           // $workorder = D("DispatchOrder")->get_one("uid ={$uid}  and plate_no = '{$plate_no}' and verify_status in(1,2,3)","id,verify_status,create_status_code,is_special,special_remark,special_address","","create_time desc");
		   if($user_arr["role_type"] != "整备"){
			   $workorder = D("DispatchOrder")->get_one("plate_no = '{$plate_no}' and verify_status in(1,2)","id,verify_status,create_status_code,is_special,special_remark,special_address,uid","","create_time desc");
			   if(!empty($workorder) &&  $workorder["uid"] != $uid){
				   $this->response(["code" => 4000, "message" => "任务正在进行中，你无权操作此车辆"], 'json');
			   }
		   }

            // 查询是否有权限操作
           $auth = D("Control")->userAuth($corporation_id,$rent_content_id);
           if( $auth==0 ){
               $this->response(["code" => 4000, "message" => "你没有管理这辆车的权限"], 'json');
           }
      }

        $imei = M("car_item_device")->where(["car_item_id" => $car_item_id])->getField("imei");

        if( empty($imei) ){
            $this->response(["code" => -1, "message" => "未获取到盒子号,请重新请求" ], 'json');
        }
        //丢失车辆恢复
        if( $is_scan==1 ) {
            if( empty($gis_lng)||empty($gis_lat) ){
                $this->response(["code"=>-1001,"message"=>"未获取到当前位置,请重新请求"]);
            }
            //计算扫码位置和盒子实际上报位置距离
            $sql = "select ROUND(st_distance(point(longitude, latitude),point($gis_lng, $gis_lat))*111195,0) AS distance from gps_status where imei = {$imei}";
            $distance = M("",'',$this->box_config)->query($sql);
            // echo M("",'',$this->box_config)->getLastsql();die;
            if( $distance[0]['distance']<=1000 ){

            }else{
                $this->response(["code" => -2000, "message"=>"当前距离车辆位置较远，你可以手动校正车辆位置后操作","rent_content_id"=>$rent_content_id,"data"=>["rent_content_id"=>$rent_content_id] ], 'json');

            }
        }

        //查询车辆是否被预约
        if($page==2){
            //用户最后用车
            $user_return = $this->trade_line($rent_content_id);
            $imei = ltrim($imei, "0");
            $data['imei']=$imei;
//            var_dump($imei);die;
//            $data['imei'] = "865067025712095";
            $data = json_encode($data);
            $imei_version = curl_get("http://47.95.64.117:82/deviceVersion",$data,"POST");
            $imei_version = json_decode($imei_version,true); //rtCode0 =  成功 ，5= 失败
            $version= array();
            if($imei_version['rtCode']==0){
                if($imei_version["curr"]==$imei_version["perfect"]){
                    $version["curr"] = "当前版本:".$imei_version['curr']."(已是最新版本)";
                    $version["perfect"] = "";
                    $version["update_url"] = "";
                }else{
                    $version["curr"] = "当前版本:".$imei_version['curr'];
                    $version["perfect"] = "最新版本:".$imei_version['perfect'];
                    $version["update_url"] = "http://".$_SERVER['HTTP_HOST']."/Api/Fault/update_box";
                }
            }else{
				$version["curr"] = "获取版本失败";
                $version["perfect"] = "";
                $version["update_url"] = "";
            }
            $result['version'] = $version;
			$user_return1 = json_decode(json_encode($user_return),TRUE);
            $seat_lock = M("baojia_box.box_data",null,$this->box_config)->where(["imei"=>$imei])->getField("seat_lock_status");
			$sql = "SELECT cn.name
FROM rent_content rn
LEFT JOIN car_info cn on cn.id=rn.car_info_id
WHERE cn.name LIKE '%助力%' AND rn.id={$rent_content_id} ";
            $zhuli = M("")->query($sql);
            if(!empty($zhuli)){
                $result['seat'] = ($seat_lock==1)?"后仓锁已开":"后仓锁已关";
            }else{
                $result['seat'] ="请不要忘记关闭电池仓";
            }
            $result['user_return'] = $user_return;
            $result['return_location'] = $areaLogic->GetAmapAddress( $user_return1['end']['lon'], $user_return1['end']['lat']);
            $result['remark'] = $this->car_operation_log($rent_content_id,2);
            $result['work_record'] = $this->car_operation_log($rent_content_id,1);
            $time2 = microtime_float();
            $result["time"] = $time2-$time1;
            $this->response(["code" => 1000, "message" => "请求成功" , "data" => $result, "is_stop" => false], 'json');
        }

        $res = M("rent_content")->alias("rc")
            ->join("car_item_verify civ ON civ.car_item_id = rc.car_item_id","left")
            ->join("rent_content_ext rce ON rce.rent_content_id = rc.id","left")
            ->join("corporation cc on cc.id = rc.corporation_id","left")
            ->join("car_item_device cid ON cid.car_item_id = rc.car_item_id","left")
            ->join("rent_content_return_config rcrc ON rcrc.rent_content_id = rc.id","left")
            ->join("car_info cn on cn.id=rc.car_info_id","left")
            ->join("car_item ci on ci.id = rc.car_item_id","left")
            ->join("car_model cm ON cm.id = cn.model_id","left")
            ->join("car_item_color cic on cic.id = ci.color","left")
            ->join("fed_gps_additional fga ON fga.imei = cid.imei","left")
            ->join("fed_gps_status fgs ON fgs.imei = cid.imei","left")
            ->join("car_info_picture cip on cip.car_info_id=rc.car_info_id and cip.car_color_id=ci.color","left")
            ->field("cc.name, rcrc.return_mode, rc.sell_status, civ.plate_no, civ.vin, cip.url picture_url, rc.shop_brand, rc.car_item_id, rc.id, rc.car_info_id, rc.status, cid.imei, fgs.latitude gis_lat, fgs.longitude gis_lng, cn.full_name, ci.`sort_name`, cic.color, cid.device_type, fga.residual_battery, fga.datetime")
            ->where("rc.id = {$rent_content_id}  AND rc.sort_id = 112 AND cip.status=2")
            ->select();
			
        //限行区域
        $groupid = M("car_group_r","",$this->zsdb)->field("groupId")->where("plate_no='{$plate_no}'")->find();
        $driving_area = M("group","",$this->zsdb)->field("name")->where(["id"=>$groupid['groupid']])->find(); //查询行驶区域名字
        $userinfo = M("baojia_cloud.group_manager")->field("user_id,user_name")->where(["groupId"=>$groupid['groupid']]) ->find(); //查询区长ID名字
        $mobile = M("ucenter_member")->field("mobile")->where(["uid"=>$userinfo['user_id']])->find();  //查询区长手机号

        $battery_last = D("OperationLogging")->get_one("operate in (-1,1,2,44) and rent_content_id=".$rent_content_id,'time',"id desc");
        //添加运维端数据
        if( !empty($gis_lng) && !empty($gis_lat) ){
            $data_visit_log = [
                "uid" => $uid,
                "rent_content_id" => $rent_content_id,
                "plate_no" => $plate_no,
                "imei" => $res[0]['imei'],
                "user_lng" => $gis_lng,
                "user_lat" => $gis_lat,
                "record_time" => time(),
                "gis_lng" => $res[0]['gis_lng'],
                "gis_lat" => $res[0]['gis_lat'],
                "distance"=>$gps->distance($res[0]['gis_lat'],$res[0]['gis_lng'],$gis_lat,$gis_lng)
            ];
            $add_visit = M("xiaomi_visit_log")->add($data_visit_log);
        }

        $info = M("baojia_box.gps_status",null,$this->box_config)->where(["imei"=>$res[0]['imei']])->field("id,imei,latitude,longitude,datetime,lastonline")->find();

        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_latitude"] = $gd["lat"];
        $info["gd_longitude"] = $gd["lon"];

        $bd = $gps->bd_encrypt($info["gd_latitude"],$info["gd_longitude"]);
        $info["bd_latitude"] = $bd["lat"];
        $info["bd_longitude"] = $bd["lon"];
        
        $areaLogic= new \Api\Logic\Area();
        $info["datetime_diff"] ="暂无定位";
        if($info["datetime"]) {
            $info["datetime_diff"] = $areaLogic->timediff($info["datetime"], time()) . "前";
        }
        $info["is_star"] = time() - $info['datetime'] > 1500 ? "无定位无星" : "有定位有星";
        $info["is_online"] = time() - strtotime($info['lastonline']) > 1200 ? "离线无心跳" : "在线有心跳";
        $info["location"] = $areaLogic->GetAmapAddress( $info["gd_longitude"], $info["gd_latitude"]);

        $result['failure_lat']="";
        $result['failure_lng']="";
        $result['failure_time']="";
        if($data_status['offline_status']==100||$data_status['repaire_status']==200||$data_status['out_status']==200){
            if($info['lastonline']) {
                //查看最后一次失败信息
                $failure = M("rent_failed_record_location")->field("rent_id,lng,lat,create_time,client_id")
                    ->where("rent_id=" . $rent_content_id . " and create_time>" . strtotime($info['lastonline']))
                    ->order('create_time DESC')->find();
            }else{
                $failure = M("rent_failed_record_location")->field("rent_id,lng,lat,create_time,client_id")
                    ->where(["rent_id"=>$rent_content_id])
                    ->order('create_time DESC')->find();
            }
            if(is_array($failure)){
                //'218,1218时存储的是百度地图坐标，其他存储的是高德坐标
                if(in_array($failure["client_id"], [218,1218])){
                    $failure_gd = $gps->bd_decrypt($failure['lat'],$failure['lng']);
                    $result['failure_lat']  = $failure_gd["lat"]?$failure_gd["lat"]:"";
                    $result['failure_lng']  = $failure_gd["lon"]?$failure_gd["lon"]:"";
                }else{
                    $result['failure_lat']  =$failure['lat'];
                    $result['failure_lng']  =$failure['lng'];
                }
                $result['failure_time'] = $failure["create_time"]?$areaLogic->timediff($failure["create_time"],time())."前":"";
            }
        }

        $pt=[$info['longitude'],$info['latitude']];
        $in_area_result = $areaLogic->isXiaomaInArea($rent_content_id,$pt,$info['imei']);
        $info['is_inarea'] = empty($in_area_result) ? "界外" : "界内";
        
        $jizhan = M("baojia_box.gps_status_bs",'',$this->box_config)->where(["imei"=>$res[0]['imei']])->find();
        $jizhan["gd_latitude"] = $gd["lat"];
        $jizhan["gd_longitude"] = $gd["lon"];
        $jizhan['location'] = $areaLogic->GetAmapAddress( $gd["lon"], $gd["lat"]);
        // \Think\Log::write(var_export($info, true),'盒子信息');
        $rent_sku_hour = M("rent_sku_hour")->where(["rent_content_id"=>$rent_content_id])->getField("operate_status");
        //车辆状态
        $is_rating = "";
        $data_status['repaire_status']==200 ?    $is_rating.= "待大修," : "";
        $data_status['repaire_status']==300 ?    $is_rating.= "寻回需维修," : "";
        $data_status['offline_status']==100 ?    $is_rating.= "离线," : "";
        $data_status['out_status']==200 ?        $is_rating.= "越界," : "";
        $data_status['lack_power_status']==300 ? $is_rating.= "馈电," : "";
        $data_status['lack_power_status']==400 ?        $is_rating.= "无电," : "";
        $data_status['transport_status']==100 ?  $is_rating.= "运输中," : "";
        $data_status['seized_status']==100 ?     $is_rating.= "扣押中," : "";
        $data_status['reserve_status']==100 ?    $is_rating.= "预约中,"   : "";
        $data_status['storage_status']==100 ?    $is_rating.= "收回途中," : "";
        $data_status['storage_status']==200 ?    $is_rating.= "入库," : "";
        $data_status['dispatch_status']==100 ?    $is_rating.= "调度中," : "";
        $data_status['search_status']==100 ?      $is_rating.= "一级寻车," : "";
        $data_status['search_status']==200 ?      $is_rating.= "二级寻车," : "";
        $data_status['search_status']==300 ?      $is_rating.= "三级寻车," : "";
        $data_status['search_status']==400 ?      $is_rating.= "四级寻车," : "";
//        $data_status['rent_status']==100 ?       $is_rating.= "出租中," : "";
        $data_status['damage_status']==100 ?     $is_rating.= "损坏不可租,"   : "";
        $data_status['scrap_status']==100 ?      $is_rating.= "报废," : "";
        $data_status['stop_status']==100 ?       $is_rating.= "人工停租," : "";
        $data_status['other_status']==100 ?      $is_rating.= "其它占用," : "";
        $data_status['special_status']==100 ?      $is_rating.= "特殊下架," : "";
        $is_rating = rtrim($is_rating,",");
        $is_rating1 = array();
        if(!empty($is_rating)){
            $is_rating = "已停租";
            $is_rating1 = explode(',',$is_rating);
        }
        $hasOrder = M("trade_order")->where(" rent_content_id={$rent_content_id} and rent_type=3 and status>=10100 and status<80200 and status<>10301 ")->find();
        if($hasOrder){
            $is_rating = "出租中";
        }
        if(empty($is_rating)){
            $is_rating = "待租中";
        }


        if( $res[0]['return_mode'] == 1){ //还车方式
            $result['return_way'] = "原点还";
        }
        if( $res[0]['return_mode'] == 2){ //还车方式
            $result['return_way'] = "网点还";
        }
        if( $res[0]['return_mode'] == 4){ //还车方式
            $result['return_way'] = "自由还";
        }
        if( $res[0]['return_mode'] == 32){ //还车方式
            $result['return_way'] = "区域还";
        }


        $time2 = microtime_float();
        $result["id"] = $rent_content_id;
//        $result["work_status"] = $verify_status;
//        $result["verify_status"] = $workorder?$workorder["verify_status"]:401;
//        $result["verify_click"] = $verify_click;
//        $result["pid"] = $pid;
        $result["picture_url"] = "http://pic.baojia.com/b/".$res[0]['picture_url'];
        $result["plate_no"] = $res[0]['plate_no'];
        if($data_status['repaire_status']==100 || $data_status['storage_status']==100){
            $result["sell_status"] = -8;
        }else{
            $result["sell_status"] = $res[0]['sell_status'];
        }
//当前车辆任务
        $task = [];
        $result["task"] = [];
        $taskSearch = new \Api\Logic\TaskSearch();
        $task_q = $taskSearch->getTask($rent_content_id);
        $workorder = D("DispatchOrder")->get_one("plate_no = '{$plate_no}' and verify_status in(1,2)","uid,id,workload_type,type,price,task_id,verify_status,create_status_code,is_special,special_remark,special_address","","create_time desc");
        $task = $task_q["task"];


        // echo "<pre/>";
        // print_r($task);die;

        $b = [];
        if($uid == $workorder["uid"]){
            if(!empty($workorder)){
                $mm[] = $workorder;
                $mm[0]["task_type_title"] = $this->task_type[$workorder["task_id"]];
                $mm[0]["all_rent_status"] = $workorder["create_status_code"];//工单前车辆状态
                $mm[0]["task_type"] = $workorder["task_id"];
                $status = "";
                $all_status = implode(",",$workorder["all_rent_status"]);
                foreach($all_status as $k =>$v){
                    $status.=$this->dispatch_code($v)." ";
                }
                $mm[0]["rent_status_title"] = $status;
                $mm[0]["task_money"] = $workorder["price"];
                $mm[0]["pid"] = $workorder["id"];
                if(!empty($task)){
                    foreach($task as $k1=>$v1){
                        if($v1["task_type"] == $workorder["task_id"]){
                            $task[$k1]["task_type_title"] = $this->task_type[$workorder["task_id"]];
                            $task[$k1]["all_rent_status"] = $workorder["create_status_code"];//工单前车辆状态
                            $task[$k1]["task_type"] = $workorder["task_id"];
                            $task[$k1]["verify_status"] = $workorder["verify_status"];
                            if(strrchr($workorder["create_status_code"],",")==false){
                                $status = $workorder["create_status_code"];
                            }else{
                                $status = trim(strrchr($workorder["create_status_code"],","),",");
                            }
                            $task[$k1]["rent_status_title"] = $this->dispatch_code($status);
                            $task[$k1]["task_money"] = $workorder["price"];
                            $task[$k1]["pid"] = $workorder["id"];
                        }else{

                            $task[$k1]["pid"]="";
                            $task[$k1]["verify_status"]=1;
                            $task[$k1]["all_rent_status"]=implode(",",$task_q["all_rent_status"]);
                        }
                    }
					
                    if($mm[0]['task_type']==$task[0]['task_type']||$mm[0]['task_type']==$task[1]['task_type'] ){
                        // $task = $mm;
                    }else{
                        $task = array_merge($mm,$task);
                    }

                   // echo "<pre/>";
                   // print_r($task);die;
                }else{
                    $task[0]["task_type_title"] = $this->task_type[$workorder["task_id"]];
                    $task[0]["all_rent_status"] = $workorder["create_status_code"];//工单前车辆状态
                    $task[0]["task_type"] = $workorder["task_id"];
                    $task[0]["verify_status"] = $workorder["verify_status"];
                    $status = "";
                    $all_status = implode(",",$workorder["all_rent_status"]);
                    foreach($all_status as $k =>$v){
                        $status.=$this->dispatch_code($v)." ";
                    }
                    $task[0]["rent_status_title"] = $status;
                    $task[0]["task_money"] = $workorder["price"];
                    $task[0]["pid"] = $workorder["id"];
                }

            }else{
                foreach($task as $k1=>$v1){
                    $task[$k1]["pid"]="";
                    $task[$k1]["verify_status"]=1;
                    $task[$k1]["all_rent_status"]=implode(",",$task_q["all_rent_status"]);
                }
            }
        }else{
            if(!empty($workorder)){
                if(!empty($task)){
                    foreach($task as $k1=>$v1){
                        if($v1["task_type"] == $workorder["task_id"]){

                        }else{
                            $task[$k1]["pid"]="";
                            $task[$k1]["verify_status"]=1;
                            $task[$k1]["all_rent_status"]=implode(",",$task_q["all_rent_status"]);
                            array_push($b,$task[$k1]);
                        }
                    }
                    $task = $b;
                }else{
                    $task = [];
                }
            }else{

                foreach($task as $k1=>$v1){
                    $task[$k1]["pid"]="";
                    $task[$k1]["verify_status"]=1;
                    $task[$k1]["all_rent_status"]=implode(",",$task_q["all_rent_status"]);
                }
            }
        }
        //有进行中的任务只显示进行中任务
//         $task1 = [];
//         foreach($task as $k2=>$v2){
//             if( $v2["verify_status"]==2 ){
//                 $task1[]=$v2;
//                 $result["task"] = $task1;
//                 break;
//             }else{
//                 $result["task"] = $task;
//             }
//         }
        
        $result["task"] = $task;
        if($data_status['special_status']==100){
            $result["task"] = [];
        }
       // echo "<pre/>";
       // print_r($result["task"]);die;
        $result["car_status"] = $is_rating;
        $result["is_rating"]["Off_hire_status"] = $task_q["Off_hire_status"]; //导致车辆停租状态
        $result["is_rating"]["for_rent_status"] = $task_q["for_rent_status"];//具体车辆状态

        $data_status['transport_status']==100 ?  array_push($result["is_rating"]["for_rent_status"],"运输中") : "";
        $data_status['seized_status']==100 ?     array_push($result["is_rating"]["for_rent_status"],"被扣押")  : "";
        $data_status['reserve_status']==100 ?    array_push($result["is_rating"]["for_rent_status"],"预约中")   : "";
        $data_status['storage_status']==100 ?    array_push($result["is_rating"]["for_rent_status"],"收回途中") : "";
        $data_status['storage_status']==200 ?    array_push($result["is_rating"]["for_rent_status"],"入库") : "";
        $data_status['damage_status']==100 ?     array_push($result["is_rating"]["for_rent_status"],"损坏不可租")   : "";
        $data_status['scrap_status']==100 ?      array_push($result["is_rating"]["for_rent_status"],"报废") : "";
        $data_status['stop_status']==100 ?       array_push($result["is_rating"]["for_rent_status"],"人工停租") : "";
        $data_status['other_status']==100 ?      array_push($result["is_rating"]["for_rent_status"],"其它占用")  : "";
        $data_status['special_status']==100 ?      array_push($result["is_rating"]["for_rent_status"],"特殊下架")  : "";


        //查询车的当前状态
        $result["check"] = $res[0]['status'] == 2 ? "已审" : "未审" ;
        $result["plate_no"] = $plate_no;
        $result["device_type"] = $res[0]['device_type'];
        $result["sort_name"] = $res[0]['sort_name'];
        $result["full_name"] = $res[0]['full_name'];
        $result["color"] = $res[0]['color'];
        $result["imei"] = $res[0]['imei'];
        $result["vin"] = $res[0]['vin']; //车架号
        $result["blong_station"] = $res[0]['name'];
        $result["battery_id"] = "";
        $result["battery_capacity"] = (float)$res[0]['residual_battery'] < 0 ? 0 : (float)$res[0]['residual_battery'];
        $result["battery_capacity_time"] = date("m月d日 H:i:s",$res[0]['datetime']);
        $result["battery_last"] = empty($battery_last['time']) ? "暂无换电记录" : "上次更换".$areaLogic->timediff($battery_last['time'],time())."前";
        $result["is_star"] = $info['is_star'];
        $result["datetime_diff"] = $info['datetime_diff'];  //定位
        $result["is_online"] = $info['is_online'];
        if($data_status['offline_status']==100){
            $result["is_online"]  = "离线无心跳";
        }
        $result["is_inarea"] = $info['is_inarea'];
        $result["car_location"] = $info['location'];
        $result['longitude'] = $info['longitude'];
        $result['latitude'] = $info['latitude'];
        $result["car_gd_latitude"] = $info["gd_latitude"];
        $result["car_gd_longitude"] = $info["gd_longitude"];
        $result["car_bd_latitude"] = $info["bd_latitude"];
        $result["car_bd_longitude"] = $info["bd_longitude"];
        $result["jizhan_location"] = $jizhan['location'];
        $result["driving_area"] = empty($driving_area['name']) ? "" : $driving_area['name'];
        $result["qu_manager"] = empty($userinfo['user_name']) ? "" : $userinfo['user_name'];
        $result["mobile"] = (float)$mobile['mobile'];
        $result["datetime"] = $info['datetime'] ? date("m月d日 H:i:s",$info['datetime']):"暂无定位";
        $result["lastonline"]="暂无心跳";
        if($info['lastonline']) {
            $result["lastonline"] = date("m月d日 H:i:s", strtotime($info['lastonline']));
        }
        $result["lastonline_time"]="暂无心跳";
        if($info['lastonline']) {
            $result["lastonline_time"] = $areaLogic->timediff(strtotime($info['lastonline']), time()) . "前";  //心跳
        }
        $result["yellow_map"] = time() - $info["datetime"]>86400 ? "该车辆位置长时间未上报，可能在室内\r\n请检查附近小区楼内或大型建筑下" : "";
//         if($type==2){
//            $user_return = $this->trade_line($rent_content_id,2);
//            $result['user_return'] = $user_return;
//            $result['info'] = $info;
//            $result['code'] = "10000";
//            return $result;
//        }
        if(is_array($result)){
            $this->response(["code" => 1000, "message" => "请求成功" , "data" => $result , "is_stop" => true , "page"=>2,"time"=>$time2-$time1], 'json');
        }else{
            $this->response(["code" => -1001, "message" => "失败" ], 'json');
        }
      

    }
    //更新盒子链接
    public function update_box($uid = '', $iflogin = '',$imei=""){
        $uid = $_POST['uid'];
        $imei = $_POST['imei'];
        $areaLogic= new \Api\Logic\Area();
        $this->promossion($uid,$iflogin);
        if(empty($imei)){
            return true;
        }
        $Redis = new \Redis();
        $Redis->pconnect('10.1.11.83', 36379, 0.5);
        $Redis->AUTH('oXjS2RCA1odGxsv4');
        $Redis->SELECT(2);
        if( !empty($Redis->get("update".$uid)) ){
            $this->response(['code' => -1, 'message' => '别急，正在处理，请勿重复操作'], 'json');
        }
        $Redis->set("update".$uid,1,100);
        $no_zero_imei = ltrim($imei, "0");
        $data['carId'] = $no_zero_imei;
        $data['cmd'] = 'upgrade';
        $r = $areaLogic->post1($data);
        if( !empty($Redis->get("update".$uid)) ){
            $this->response(['code' => -1, 'message' => '已发送升级请求'], 'json');
        }
    }
    //查询车辆故障
    public function car_fault($rent_content_id='5874604',$page=1,$limit=5,$uid=0){

        $rent_content_id = $_POST['rent_content_id'];
        $uuid = $_POST["uuid"];
        $this->check_terminal($uid,$uuid);
        if(empty($rent_content_id)){
            $this->response(["code" => -1, "message" => "参数错误" ], 'json');
        }
        $last_time = D("OperationLogging")->get_one("rent_content_id = {$rent_content_id} and operate = 8","time","id desc");
        $last_time = $last_time["time"];

//        var_dump($last_time);die;
        if($last_time){
            $result = D("OperationLogging")->get_all("rent_content_id = {$rent_content_id} and operate = 35 and time>{$last_time}","id,time,uid,car_status,pic1,pic2,pic3,pic4,desc","id desc");

        }else{
            $result = D("OperationLogging")->get_all("rent_content_id = {$rent_content_id} and operate = 35","id,time,uid,car_status,pic1,pic2,pic3,pic4,desc","id desc");

        }
        if(empty($result)){
            $this->response(["code" => -1, "message" => "暂无故障报修" ], 'json');
        }
        foreach ($result as $k =>$v){
            $fault = explode(",",$v['car_status']);
            foreach ($fault as $k1=>$v1){
                switch ($v1)
                {
                    case 1:
                        $fault[$k1]="车座";
                        break;
                    case 2:
                        $fault[$k1]="车把";
                        break;
                    case 3:
                        $fault[$k1]="支架";
                        break;
                    case 4:
                        $fault[$k1]="刹车";
                        break;
                    case 5:
                        $fault[$k1]="控制器";
                        break;
                    case 6:
                        $fault[$k1]="盒子";
                        break;
                    case 7:
                        $fault[$k1]="线路";
                        break;
                    case 8:
                        $fault[$k1]="脚踏板";
                        break;
                    case 9:
                        $fault[$k1]="车轮";
                        break;
                    case 10:
                        $fault[$k1]="其他";
                        break;
                    case 14:
                        $fault[$k1]="车辆各部件未发现损坏迹象，但车辆无法正常使用";
                        break;
                }
            }
            $result[$k]["fault"] = $fault;
            $result[$k]["time"] = date("Y-m-d H:i:s",$v["time"]);
            $result[$k]["username"] = M('baojia_mebike.repair_member')->where("user_id = ".$v['uid'])->getField('user_name');
            if(!empty($v["pic1"])){
                $result[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic1"];
            }
            if(!empty($v["pic2"])){
                $result[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic2"];
            }
            if(!empty($v["pic3"])){
                $result[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic3"];
            }
            if(!empty($v["pic4"])){
                $result[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic4"];
            }
        }
        $this->response(["code" => 1, "message" => "请求成功","data"=>$result,"page"=>$page ], 'json');
         // echo "<pre/>";
         // print_r($result);die;
    }
    //根据车辆ID查询最新的三条工作记录 type=1 查询工作记录 =2 查询备注车辆记录
    public function  car_operation_log($rent_content_id = '',$type=""){
        $res = [];
        if($type==1){
            $res = D("OperationLogging")->car_operation_log($rent_content_id);

        }else{
            $res = M("OperationLogging")->where(["rent_content_id"=>$rent_content_id,"operate"=>36])->field("id,uid,desc,time,pic1,pic2,pic3,pic4")->order("id desc")->limit(3)->select();
            if(!empty($res)){
                foreach ($res as $k=>$v){
                    $res[$k]["username"] = M('baojia_mebike.repair_member')->where("user_id = ".$v['uid'])->getField('user_name');
                    $res[$k]["time"] = date("Y-m-d H:i:s",$v["time"]);
                    if(!empty($v["pic1"])){
                        $res[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic1"];
                    }
                    if(!empty($v["pic2"])){
                        $res[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic2"];
                    }
                    if(!empty($v["pic3"])){
                        $res[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic3"];
                    }
                    if(!empty($v["pic4"])){
                        $res[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic4"];
                    }
                }
            }

        }
//        $this->response(["code" => 1, "message" => "请求成功","data"=>$res ], 'json');
        return $res;
    }
    //根据条件查询备注信息
    public function search_desc($rent_content_id="",$uid="",$key="",$page=1){
        $key = I("post.key");
        $rent_content_id = I("post.rent_content_id");
        $uuid = $_POST["uuid"];
        $this->check_terminal($uid,$uuid);
        if(empty($rent_content_id)){
            $this->response(["code" => -100, "message" => "参数错误" ], 'json');
        }
        $where = "";
        $where .= " rent_content_id = {$rent_content_id} and operate=36 ";
        $page = $page;
        $limit = 5;
        if(empty($key)){
            $result = D("OperationLogging")->get_all($where,"id,uid,desc,time,pic1,pic2,pic3,pic4","id desc",$page,$limit);
            $result1 = D("OperationLogging")->get_count($where);
        }else{
            $res =  M("baojia_mebike.repair_member")->where("user_name like '%{$key}%'")->getField("user_id");
            if(empty($res)){
                $where.=" and `desc` like '%{$key}%' ";
                $result = D("OperationLogging")->get_all($where,"id,uid,desc,time,pic1,pic2,pic3,pic4","id desc",$page,$limit);
                $result1 = D("OperationLogging")->get_count($where);
            }else{
                $where.=" and (`desc` like '%{$key}%' or uid = {$res})";
                $result = D("OperationLogging")->get_all($where,"id,uid,desc,time,pic1,pic2,pic3,pic4","id desc",$page,$limit);
                $result1 = D("OperationLogging")->get_count($where);
            }
        }
        if(!empty($result)){
            foreach ($result as $k=>$v){
                $result[$k]["username"] = M('baojia_mebike.repair_member')->where("user_id = ".$v['uid'])->getField('user_name');
                $result[$k]["time"] = date("Y-m-d H:i:s",$v["time"]);
                if(!empty($v["pic1"])){
                    $result[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic1"];
                }
                if(!empty($v["pic2"])){
                    $result[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic2"];
                }
                if(!empty($v["pic3"])){
                    $result[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic3"];
                }
                if(!empty($v["pic4"])){
                    $result[$k]["pic"][] =  "http://pic.baojia.com/".$v["pic4"];
                }
            }
        }else{
            $this->response(["code" => -1000, "message" => "暂无数据" ], 'json');
        }
//        echo "<pre>";
//        print_r($result1);
        if( $result1 <= ($page * $limit)){
            $page = $page-1;
        }
        $this->response(["code" => 1000, "message" => "请求成功","data"=>$result,"page"=>$page ], 'json');


        echo "<pre>";
        print_r($result);
    }

    //小修上报
    public function repair_report( $uid = '', $iflogin = '', $car_status = '', $rent_content_id = '', $plate_no = '',$gis_lng = '',$gis_lat = ''){
        $uid = $_POST['uid'];
        $car_status = $_POST['car_status'];
        $rent_content_id = $_POST['rent_content_id'];
        $plate_no = $_POST['plate_no'];
        $gis_lng = empty($_POST['gis_lng']) ? "" : $_POST['gis_lng'];
        $gis_lat =  empty($_POST['gis_lat']) ? "" : $_POST['gis_lat'];
        $upload_image = new \Api\Logic\UploadImage();
        $this->promossion($uid,$iflogin);

        if( empty($car_status) ){
            $this->response(["code" => -100, "message" => "请选择一个待小修问题" ], 'json');
        }
        if(isset($_FILES["picture"])){
            $pic = $upload_image->upload($_FILES["pic1"]);
            $pic1 = $pic["pic1"];
        }
        $data = [
            "uid" => $uid,
            "operate" => 6, //6 = 待小修,
            "car_status" => $car_status,
            "rent_content_id" => $rent_content_id,
            "plate_no" => $plate_no,
            "time" => time(),
            "pic1" => $pic1['short_path'],
            "gis_lng" => $gis_lng,
            "gis_lat" => $gis_lat
        ];

        // var_dump($data);die;
        $find = D("OperationLogging")->get_one(["rent_content_id" => $rent_content_id,"operate"=>6,"car_status"=>$car_status,"status"=>0],"id");
        if(!empty($find)){
            $this->response(["code" => -1, "message" => "该问题已经上报" ], 'json');
        }
        $sell_status = M("rent_content")->where(["id"=>$rent_content_id])->getField('sell_status');
        if($sell_status == 1){
            $res = D("OperationLogging")->add_record($data);
            if($res){
                M("rent_sku_hour")->where(["rent_content_id"=>$rent_content_id])->save(["operate_status"=>11,"update_time" => time()]);
                $this->response(["code" => 100, "message" => "小修上报成功" ], 'json');
            }else{
                $this->response(["code" => -100, "message" => "小修上报成功" ], 'json');
            }
        }else{
            $this->response(["code" => -1111, "message" => "非上架车辆不允许上报小修" ], 'json');
        }



    }
    //小修记录表
    public function repair_record($uid = '', $iflogin = '',$rent_content_id = ''){
        $this->promossion($uid,$iflogin);
        $rent_info = M("rent_content")->where(["id"=>$rent_content_id,"sort_id"=>112])->find();
        if(!$rent_info){
            $this->response(["code" => -1001, "message" => "车辆不存在"], 'json');
        }
        $location = D("OperationLogging")->get_all("rent_content_id = {$rent_content_id} and operate = 6 and status = 0","operate,rent_content_id,plate_no,car_status,id,pic1");
        foreach ($location as $k => $v) {
            if( $v['car_status'] == 3 ){
                $location[$k]['status'] = "脚蹬子缺失";
            }if( $v['car_status'] == 4 ){
                $location[$k]['status'] = "车灯损坏";
            }if( $v['car_status'] == 5 ){
                $location[$k]['status'] = "车支子松动";
            }if( $v['car_status'] == 6 ){
                $location[$k]['status'] = "车灯松动";
            }if( $v['car_status'] == 7 ){
                $location[$k]['status'] = "车把松动";
            }if( $v['car_status'] == 8 ){
                $location[$k]['status'] = "鞍座丢失";
            }if( $v['car_status'] == 9 ){
                $location[$k]['status'] = "二维码丢失";
            }
        }
        $this->response(["code" => 100, "data" => $location ], 'json');
        // echo "<pre>";
        // print_r($location);
    }
    //小修完成
    public function repair_done($pid="",$uid = '', $iflogin = '', $rent_content_id = '', $plate_no = '',$gis_lng = '',$gis_lat = '',$pic = '', $car_status = ''){
        $uid = $_POST['uid'];
        $rent_content_id = $_POST['rent_content_id'];
        $battery = $this->plate($plate_no);
        $battery = (float)$battery['residual_battery'];
        $id = $_POST['id'];  //1 返回上次图片   3 提交表单
        $plate_no = $_POST['plate_no'];
        $car_status = $_POST['car_status'];
        $gis_lng = empty($_POST['gis_lng']) ? "" : $_POST['gis_lng'];
        $gis_lat =  empty($_POST['gis_lat']) ? "" : $_POST['gis_lat'];
        $upload_image = new \Api\Logic\UploadImage();
        $this->promossion($uid,$iflogin);
        if( !empty($_FILES["picture"]["tmp_name"]) ){
            $pic = $upload_image->upload($_FILES["pic1"]);
            $pic1 = $pic["pic1"];
            $data =[
                "uid" => $uid,
                "operate" => 4, //4 = 完成小修,
                "rent_content_id" => $rent_content_id,
                "car_status" => $car_status,
                "plate_no" => $plate_no,
                "time" => time(),
                "pid" => $pid,
                "battery" => $battery,
                "pic2" => $pic1['short_path'],
                "gis_lng" => $gis_lng,
                "gis_lat" => $gis_lat
            ];
            $info = D("OperationLogging")->add_record($data);
            //修改待小修记录状态

            $info1 = D("OperationLogging")->update(["id" => $id],['status'=>1,"pic2"=>$pic['short_path'],"update_time" => time()]);
            if($info1 && $info){
                $unfinished =  D("OperationLogging")->get_one(["rent_content_id"=>$rent_content_id,"operate" => 6,"status"=>0],'id');
                if(empty($unfinished)){
                    M("rent_sku_hour")->where(["rent_content_id"=>$rent_content_id])->save(["operate_status"=>8,"update_time" => time()]);
                }
                $this->response(["code" => 1001, "message" => "完成小修" ], 'json');
            }else{
                $this->response(["code" => -1001, "message" => "小修失败" ], 'json');
            }
        }
    }


    public function trade_line($rent_content_id="",$type=1){
        $return = [];
        $gps = D('Gps');
        $r_mode = M('rent_content_return_config')->where('rent_content_id = %d' , $rent_content_id)->getField("return_mode");
        $orderid = M("baojia_mebike.trade_order_return_log")->where("rent_content_id = %d",$rent_content_id)->order("id desc")->limit(1)->field("order_id,user_id")->find();
        //echo M("baojia_mebike.trade_order_return_log")->getLastSql();die;
        //echo "<pre>";print_r($orderid);die;
        if(empty($orderid)){
            if($type!=2){
                $return=(object)['mobile_show'=>1,'mobile'=>0,'use_time'=>0];
            }else{
                $return=[['mobile_show'=>1,'mobile'=>0,'use_time'=>0]];
            }

            return $return;
        }
        if(empty($orderid['user_id'])){
            $orderid['user_id'] = M("trade_order")->where(["id"=>$orderid['order_id']])->getField("user_id");
        }
        $user  = M("ucenter_member")->alias("u")
            ->join("member m on m.uid=u.uid","left")
            ->where(["u.uid"=>$orderid['user_id']])
            ->field("u.mobile,m.last_name,m.first_name")
            ->find();
        $mobile =  $user["mobile"];
        $return['mobile_show'] = 0;
        $return['mobile'] = "********";
        if( time()>strtotime(date("Y-m-d 8:0:0")) && time()<strtotime(date("Y-m-d 20:0:0")) ){
            $return['mobile_show'] = 1;
            $return['mobile'] = empty($mobile) ? "" : $mobile;
        }

        $order=M('trade_order')->field("car_item_id,end_time,begin_time")->where("id=".$orderid['order_id'])->find();
        //echo M("trade_order")->getLastSql();die;
        //echo "<pre>";print_r($order);die;
        $beginTime=$order['begin_time'];
        $endtime=$order['end_time'];
        $device=M('car_item_device')->where('car_item_id='.$order['car_item_id'])->field('imei')->find();
        //echo "<pre>";print_r($device);
        $car_return_info=M('trade_order_car_return_info')->field("take_lng,take_lat,return_lng,return_lat")->where('order_id='.$orderid['order_id'])->find();
        //echo "<pre>";print_r($car_return_info);
        if($car_return_info && $car_return_info['take_lng']>0 && $car_return_info['take_lat']>0){
            $liststart['lat']=(float)$car_return_info['take_lat'];
            $liststart['lon']=(float)$car_return_info['take_lng'];
        }
        if($car_return_info && $car_return_info['return_lng']>0 && $car_return_info['return_lat']>0){
            $listend['lat']=(float)$car_return_info['return_lat'];
            $listend['lon']=(float)$car_return_info['return_lng'];
            $listend['datetime']=date('Y-m-d H:i:s',$order['end_time']);
        }
        $imei = $device['imei'];
        $condition="imei='$imei'";
        if($beginTime && $endtime)$condition.=" AND datetime between $beginTime AND $endtime";
        $PointArr1 = M('fed_gps_location')->field("latitude,longitude,datetime")->where("$condition")->order('datetime asc')->select();
        // $res = M('fed_gps_location')->getLastsql();
        //echo "<pre>";print_r($PointArr1);
        //切换备份表进行查询
        $tb=M("",null,$this->box_config)->query("show tables like 'gps_location_%'");
        //echo "<pre>";print_r($tb);
        $bt=date('ymd',$beginTime);
        $et=date('ymd',$endtime);
        foreach($tb as $key=>$v){
            foreach($v as $k1=>$v1){
                if(substr($v1,-3)!="zid"){
                    if(trim($v1,'gps_location_')>=$bt && trim($v1,'gps_location_')>=$et){
                        $tbs1[]=$v1;
                    }
                }
            }

        }
        sort($tbs1);
        if($tbs1[0]){
            $PointArr2=M("",null,$this->box_config)->query("select latitude,longitude from {$tbs1[0]} where  imei='{$imei}' and longitude>0 and latitude>0 and datetime>={$beginTime} and datetime<={$endtime} order by datetime asc");
        }
        if(count($PointArr1)>0 && count($PointArr2)>0){
            $PointArr2=array_merge($PointArr2,$PointArr1);
        }elseif(count($PointArr1)>1){
            $PointArr2=$PointArr1;
        }

        //$return['start'] = $liststart;
        $return['end'] = $listend;

        $return['user'] = $user;
        foreach ($PointArr2 as $k => $v) {
            $gd = $gps->gcj_encrypt($v["latitude"],$v["longitude"]);
            $PointArr2[$k]['latitude'] = $gd['lat'];
            $PointArr2[$k]['longitude'] = $gd['lon'];
        }
        $firstdata = reset($PointArr2);

        if ($firstdata) {
            $liststart['lat'] = $firstdata['latitude'];
            $liststart['lon'] = $firstdata['longitude'];
        }

        $return['start'] = $liststart;
//        echo "<pre>";print_r($PointArr2);die;
        if($r_mode==1){
            $end = end($PointArr2);
            $return['end']['lat'] = $end["latitude"];
            $return['end']['lon'] = $end["longitude"];
            $return['end']['datetime']=date('Y-m-d H:i:s',$end['datetime']);
        }

        $return['guiji'] = $PointArr2;
        $return['use_time'] = "还车于:".$listend['datetime'];
        $hasOrder = M("trade_order")->where(" rent_content_id={$rent_content_id}  and rent_type=3 and status>=10100 and status<80200 and status<>10301 and hand_over_state>=10200")->field("begin_time,end_time")->find();
        if($hasOrder){
            $stime = date("Y-m-d H:i:s",$hasOrder['begin_time']);
            $return['use_time'] = "用车时间:".$stime."~未结束";
        }

//        echo "<pre>";print_r($return);
        // $return['use_time_diff'] = "手机:".$areaLogic->timediff(strtotime($listend['datetime']),time())."前";
        return $return;
        // var_dump($PointArr2);

    }
    //根据车牌号查询信息
    public function plate($plate_no = ""){
        $areaLogic= new \Api\Logic\Area();
        $car_item_id = M("car_item_verify")->where("plate_no = '%s'",[$plate_no])->getField("car_item_id");
        $car = M("rent_content")->where(["car_item_id" => $car_item_id])->field("id,sell_status")->find();
        $data_status = $areaLogic->get_status($car['id']);
        $res = M("rent_content")->alias("rc")
            ->join("car_item_device cid ON cid.car_item_id = rc.car_item_id","left")
            ->join("car_item ci on ci.id = rc.car_item_id","left")
            ->join("car_item_color cic on cic.id = ci.color","left")
            ->join("fed_gps_additional fga ON fga.imei = cid.imei","left")
            ->join("fed_gps_status fgs ON fgs.imei = cid.imei","left")
            ->join("car_info_picture cip on cip.car_info_id=rc.car_info_id and cip.car_color_id=ci.color","left")
            ->field("rc.id,rc.car_item_id,rc.city_id,rc.sell_status,cip.url picture_url,cid.imei,fga.residual_battery")
            ->where("rc.id = ".$car['id']."  AND rc.sort_id = 112 AND cip.status=2")
            ->find();
        $gps = D('Gps');
        $info = M("baojia_box.gps_status",null,$this->box_config)->where(["imei"=>$res['imei']])->field("id,imei,latitude,longitude,datetime,lastonline")->find();
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_latitude"] = $gd["lat"];
        $info["gd_longitude"] = $gd["lon"];

        $bd = $gps->bd_encrypt($info["gd_latitude"],$info["gd_longitude"]);
        $info["bd_latitude"] = $bd["lat"];
        $info["bd_longitude"] = $bd["lon"];
        $info["location"] = $areaLogic->GetAmapAddress( $info["gd_longitude"], $info["gd_latitude"]);
        $res['info'] = $info;
        $res['data_status'] = $data_status;
        // echo "<pre/>";
        // print_r($res);
        return $res;
    }
    //预约接口
    public function have_order($plate_no = '', $uid = '' ,$iflogin = '',$gis_lng = '',$gis_lat = '' ,$task_type = "",$req_code= "",$is_personal_task="0",$total_money="",$uuid='ff'){
        $areaLogic= new \Api\Logic\Area();
        $uid=$_POST["uid"];
        $plate_no=$_POST["plate_no"];
        $this->promossion($uid,$iflogin);
        $this->check_terminal($uid,$uuid);
        if( !empty($plate_no) ){
            $res = $this->plate($plate_no);
            // var_dump($res);die;
        }
        if( $req_code == 1 ){ //点击图标查询信息
            $data['status'] = 1;  //预约按钮可点
            $data['type']=2;
            $data["picture_url"] = "http://pic.baojia.com/b/".$res['picture_url'];
            $data["car_location"] = $res['info']['location'];
            $data["return_title"] = "";
            $this->response(["code" => -1, "data"=>$data ], 'json');

        }

        if( $req_code == 0 ){  //首页加载调用查看有无预约
            $order = M("baojia_mebike.have_order")->where("uid = {$uid} and status=0")->field('id,create_time,rent_status,plate_no,is_owner,price')->find();
            if( !empty($order) ){
                $taskSearch = new \Api\Logic\TaskSearch();

                $res = $this->plate($order['plate_no']);
                $task_q = $taskSearch->getTask($res['id']);
                if(empty($task_q['task'])){
                    $paidan = D("DispatchOrder")->get_one("plate_no = '{$order['plate_no']}' and verify_status in(1,2) and uid={$uid}","id");
                    if(empty($paidan)){
                        M("baojia_mebike.have_order")->where("uid = {$uid} and status=0")->save(['status'=>1,'update_time'=>time()]);
                        $up = $areaLogic->step_status(["reserve_status"=>0],$res['id'],2);//取消预约修改预约状态上架车辆不修改sell_time
                        $this->response(["code" => -1, "data" => ['type'=>3,'message'=>'车辆当前不存在任务，预约已自动取消']], 'json');
                    }

                }
                $data['type'] = 1;
                $data['time'] = 1800;  //预约总时间
                $data['start_time'] = $order['create_time'];  //预约开始时间
                $data['rest_time'] = 1800-(time()-$order['create_time']);
                $data["picture_url"] = "http://pic.baojia.com/b/".$res['picture_url'];
                $data["task_type"] = $order['rent_status'];
                $data["task_type_title"] = str_replace("任务","",$this->task_type[$order['rent_status']]);
                $data["plate_no"] = $order['plate_no'];
                $data["rent_content_id"] = $res["id"]?$res["id"]:0;
                $data["gis_lat"] = $res['info']['gd_latitude'];
                $data["gis_lng"] = $res['info']['gd_longitude'];
                $data["bd_latitude"] = $res['info']['bd_latitude'];
                $data["bd_longitude"] = $res['info']['bd_longitude'];
                $data['is_personal_task'] = $order['is_owner'];
                $data['total_money'] = $order['price'];
                $data["city_id"] = $res['city_id'];
                $data["car_location"] = $res['info']['location'];
                $this->response(["code" => -1, "data"=>$data,"message" => "同一时间只能预约一辆车"], 'json');
            }else{
                $this->response(["code" => -1, "data" => ['type'=>0,'message'=>'没有预约订单']], 'json');
            }
        }
        if( $req_code == 2 ){ //点击立即预约按钮进行操作
            $car_item_id = M("car_item_verify")->where(["plate_no" => $plate_no])->getField("car_item_id");
            $rent_content = M("rent_content")->where(["car_item_id" => $car_item_id])->field("id,sell_status")->find();
            $trade_order = M("trade_order")->where("rent_type = 3 AND STATUS >= 10100 AND STATUS < 80200 AND STATUS <> 10301 and rent_content_id={$rent_content['id']}")->find();
            if( !empty($trade_order) ){
                $this->response(["code" => -10, "message" => "该车有订单暂不可预约"], 'json');
            }
            $order1 = M("baojia_mebike.have_order")->where("status=0 and plate_no='{$plate_no}'")->getField('id');
            if( !empty($order1) ){
                $this->response(["code" => -10, "message" => "该车已被预约"], 'json');
            }
            $task = D("DispatchOrder")->get_one("plate_no = '{$plate_no}' and verify_status=2","id");
            if( !empty($task) ){
                $this->response(["code" => -10, "message" => "该车任务进行中，不可再预约"], 'json');
            }
            $add = [
                'uid' => $uid,
                'plate_no' => $plate_no,
                'create_time' => time(),
                'status' => 0,
                'gis_lng' => $gis_lng,
                'gis_lat' => $gis_lat,
                'rent_status' => $task_type,
                "sell_status" => $rent_content['sell_status'],
                'is_owner' => $is_personal_task,
                'price' => $total_money

            ];
            $result = M("baojia_mebike.have_order")->add($add);
            if($result){
                $up = $areaLogic->step_status(["reserve_status"=>100],$res['id'],100);
            }
            $res = $this->plate($plate_no);
            $data['type'] = 1;
            $data["picture_url"] = "http://pic.baojia.com/b/".$res['picture_url'];
            $data["start_time"] = time();
            $data['rest_time'] = 1800;
            $data['time'] = 1800;  //预约总时间
            $data["gis_lat"] = $res['info']['gd_latitude'];
            $data["gis_lng"] = $res['info']['gd_longitude'];
            $data["bd_latitude"] = $res['info']['bd_latitude'];
            $data["bd_longitude"] = $res['info']['bd_longitude'];
            $data["task_type"] = $task_type;
            $data["task_type_title"] = str_replace("任务","",$this->task_type[$task_type]);
            $data["plate_no"] = $plate_no;
            $data["rent_content_id"] = $res["id"]?$res["id"]:0;
            $data["city_id"] = $res['city_id'];
            $data["car_location"] = $res['info']['location'];
            $data["is_personal_task"] = $is_personal_task;
            $data["total_money"] = $total_money;
            $data["return_title"] = "预约成功";
            $this->response(["code" => 1, "data" => $data], 'json');
        }

    }
    //定时任务  运维端预约换电车辆 30分钟自动取消  //取消预约修改预约状态上架车辆不修改sell_time
    public function order_close($plate_no = '', $uid = ''){
        $areaLogic= new \Api\Logic\Area();
        if( !empty($plate_no) && !empty($uid) && $plate_no!=null ){
            $sell_status = M("baojia_mebike.have_order")->where("status = %d and plate_no ='%s' and uid = %d",0,$plate_no,$uid)->getField("sell_status");//预约前车辆状态
            $result = M("baojia_mebike.have_order")->where("status = %d and plate_no ='%s' and uid = %d",0,$plate_no,$uid)->save(['status'=>1,'update_time'=>time()]);
            if($result){
                $res = $this->plate($plate_no);
                $up = $areaLogic->step_status(["reserve_status"=>0],$res['id'],2);

                $this->response(["code" => 1, "message" => "取消预约成功"], 'json');
            }else{
                $this->response(["code" => -1, "message" => "取消预约失败"], 'json');
            }
        }
        $res = M("baojia_mebike.have_order")->field('id,plate_no,sell_status,create_time')->where(['status'=>0])->select();
        foreach ($res as $val){
            $c_time = (time()-$val['create_time']);
            if($c_time >= 1800){
                $car_item_id = M("car_item_verify")->where(["plate_no" => $val['plate_no']])->getField("car_item_id");
                $rent_content = M("rent_content")->where(["car_item_id" => $car_item_id])->field("id,sell_status")->find(); //当前车辆状态
                $areaLogic->step_status(["reserve_status"=>0],$rent_content['id'],2);
            }
        }
    }
    //我的工单列表
    /*
     * $verify_status =1 待处理， =2进行中， =3待审核，=4 审核通过
     *
     * */
    public function work_order($uid = '', $iflogin = '',$verify_status = '',$p='5'){

        $uid = I("post.uid",0,'intval');
        if(empty($uid)){
            $this->response(["code" => -1, "message" => "uid不能为空"], 'json');
        }
        $where ="uid = {$uid}";
        $field = "id,plate_no,create_time,verify_time,create_status_code,verify_status,is_special,workload_type,remark";
        $page = "{$p},15";
        if($verify_status == 1){
            $order = "create_time desc";
        }else{
            $order = "verify_time desc";
        }
        switch ($verify_status)
        {
            case 1:
                $where .= " and verify_status in (1,2) ";
                break;
            case 2:
                $where .= " and verify_status = 3";
                break;
            case 3:
                $where .= " and verify_status = 4";
                break;
            case 4:
                $where .= " and verify_status = -2";
                break;
        }
        $result = D("DispatchOrder")->get_all($where,$field,$page,$order);
        $new = [];
        foreach($result as $k => $v){
            if($verify_status == 1){
                $result[$k]['create_time'] = date("Y-m-d H:i",$v['create_time']);
                $result[$k]['create_date'] = date("Y-m-d",$v['create_time']);
                $result[$k]['create_lasting'] = date("H:i",$v['create_time']);
            }else{
                $result[$k]['create_time'] = date("Y-m-d H:i",$v['verify_time']);
                $result[$k]['create_date'] = date("Y-m-d",$v['verify_time']);
                $result[$k]['create_lasting'] = date("H:i",$v['verify_time']);
            }
            if($v["is_special"] == 1){
                $result[$k]['title'] = "特殊";
            }else{
                $result[$k]['title'] = $this->dispatch_code($v['create_status_code']);
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

            $result[$k]['remark']   = $v["remark"]?$v["remark"]:"";
            unset($result[$k]['verify_time']);
        }

        foreach($result as $k => $v){
            if($verify_status == 1) {
                $new[$v["create_time"]][] = $v;
            }else{
                $new[$v["create_date"]][] = $v;
            }
        }

        foreach($new as $k => $v){
            $new1['records'] = $v;
            if($verify_status == 1){
                $new1["total"]   = 0;
            }else{
                $start_time = strtotime($k." 0:0:0");
                $end_time   = strtotime($k." 23:59:59");
                $total = M('baojia_mebike.dispatch_order')->where($where." and (verify_time  BETWEEN ".$start_time."  AND  $end_time)")->count(1);
                $new1["total"]   = "共".$total."辆";
            }
            if($k == date("Y-m-d")){
                $new1["format"]  = '今天';
            }else if($k == date("Y-m-d",strtotime("-1 day"))){
                $new1["format"]  = '昨天';
            }else{
                $new1["format"]  = $k;
            }
            $new2[] = $new1;
        }

        $new3["orders"] =  $new2;
        if(!empty($new)){
            $this->response(["code" => 1, "message" => "请求成功","data" => $new3], 'json');
        }else{
//            $data['wx']["title"] = "工作微信群";
//            $data['wx']["code"] = "";
//            $data['qq']["title"] = "QQ群";
//            $data['qq']["code"] = "";
            $data['wx']["title"] = "";
            $data['wx']["code"] = "";
            $data['qq']["title"] = "";
            $data['qq']["code"] = "";
            $this->response(["code" => -1, "message" => "目前还没有工单\r\n如有疑问请联系工作人员","data" => $data], 'json');
        }
//        echo "<pre>";
//        print_r($new2);
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
        $return["13"] = "特殊工单";
        $return["15"] = "存在故障";
        $return["16"] = "已寻回";
        $return["17"] = "无效盒子";
        $return["18"] = "非稳盒子";
        $return["19"] = "还车区域外";
        $return["20"] = "已检修";
        $return["21"] = "五日无单";
        $return["22"] = "4级寻车";
        $return["23"] = "3级寻车";
        $return["24"] = "2级寻车";
        $return["25"] = "1级寻车";
        $return["26"] = "用户上报";
        $return["27"] = "有电离线";
        return $return[$code];
    }
    //点击开始任务按钮
    public function update_code($uid = '21', $iflogin = '',$task_money="",$all_rent_status = "",$task_type="",$pid = "",$plate_no="",$corporation_id="",$gis_lng = '',$gis_lat = '',$uuid='ff'){

        $this->promossion($uid,$iflogin);
        $this->check_terminal($uid,$uuid);
        $areaLogic= new \Api\Logic\Area();
        $pid = $_POST["pid"];
        $task_type = $_POST["task_type"];
        if( empty($plate_no) ){
            $this->response(["code" => -1, "message" => "车牌号不能为空"], 'json');
        }
        if( empty($all_rent_status) ){
            $this->response(["code" => -1, "message" => "工单异常"], 'json');
        }
        $task_have = D("DispatchOrder")->get_one("uid ={$uid}  and plate_no = '{$plate_no}' and verify_status =2","verify_status,task_id");
        if( !empty($task_have) ){
            $this->response(["code" => -1, "message" => "有未结束任务,暂不可开始新的任务"], 'json');
        }
        //查询有无别人预约
        $order1 = M("baojia_mebike.have_order")->where("uid !={$uid} and status=0 and plate_no='{$plate_no}'")->getField('id');
        if( !empty($order1) ){
            $this->response(["code" => -1, "message" => "该车已被预约"], 'json');
        }
		//查询该车有没有进行中的工单
	    $otherworkorder = D("DispatchOrder")->get_one("plate_no = '{$plate_no}' and verify_status in(1,2)","id,verify_status,create_status_code,is_special,special_remark,special_address,uid","","create_time desc");
	    if(!empty($otherworkorder) &&  $otherworkorder["uid"] != $uid){
		   $this->response(["code" => -1, "message" => "这个任务已派给运维人员，你不能开始任务"], 'json');
	    } 
		
        $result = $this->plate($plate_no);
        $auth = D("Control")->userAuth($corporation_id,$result['id']);
        if( $auth==0 ){
            $this->response(["code" => -1, "message" => "你没有管理这辆车的权限"], 'json');
        }
		
        $is_rating = $areaLogic->is_rating($result['id']);
        if($is_rating){
            $this->ajaxReturn(["code" => -1, "message" => "车辆正在出租中,无法开始任务"], 'json');
        }
        $imei_status_info = $areaLogic->gpsStatusInfo($result['imei']);
        if(!empty($imei_status_info)){
            $gis_lat = $imei_status_info["gd_latitude"];
            $gis_lng = $imei_status_info["gd_longitude"];
        }
//        $task = D("DispatchOrder")->get_one("uid ={$uid}  and plate_no = '{$plate_no}' and verify_status in(2,3)","id");
        if(!empty($pid)){
            $workorder = D("DispatchOrder")->get_one("uid ={$uid}  and plate_no = '{$plate_no}' and verify_status =1 and type=0","verify_status,task_id,workload_type","","create_time desc");
            if(empty($workorder)){
                $this->response(["code" => -1, "message" => "暂无可处理工单"], 'json');
            }else{
                M()->startTrans();
                $res = D("DispatchOrder")->update("uid ={$uid}  and plate_no = '{$plate_no}' and verify_status =1",["verify_status"=>2]);

                $data = [
                    "user_id" => $uid,
                    "dispatch_order_id" => $pid,
                    "create_time" => time(),
                    "verify_status" => 2,
                    "lng" => $gis_lng,
                    "lat" => $gis_lat,
                    "remark" => "开始了任务"
                ];
                $add_log = M("baojia_mebike.dispatch_order_log")->add($data);
                if($workorder['task_id']==2){ //回库任务
                    $up = $areaLogic->step_status(["storage_status"=>100],$result['id'],100);
                }else if($workorder['task_id']==3){ //调度任务
                    $up = $areaLogic->step_status(["dispatch_status"=>100],$result['id'],100);
                }
                $have_order1 = M("baojia_mebike.have_order")->where("uid ={$uid} and status=0 and plate_no='{$plate_no}'")->getField('id');
                if( !empty($have_order1) ){
                    $up_order1 = M("baojia_mebike.have_order")->where("uid ={$uid} and status=0 and plate_no='{$plate_no}'")->save(['status'=>1,'update_time'=>time()]);
                    $up_order2 = $areaLogic->step_status(["reserve_status"=>0],$result['id'],0);
                }
                if($res&&$add_log){
                    M()->commit();
                    $arr["pid"] = $pid;
                    $this->response(["code" => 1, "message" => "success","data"=>$arr], 'json');
                }else{
                    M()->rollback();
                    $this->response(["code" => -1, "message" => "failed"], 'json');
                }
            }
        }else{

            $data= array (
                'rent_content_id' => $result['id'],
                'car_item_id' => $result["car_item_id"],
                'plate_no' => $plate_no,
                'create_time' => time(),
                'uid' => $uid,
                'verify_status' => 2,
                'corporation_id' => $corporation_id,
                'type' => 2,
                'price' => $task_money,
                'task_id' => $task_type,
                'create_status_code' => $all_rent_status,
            );
            M()->startTrans();
            $res = D("DispatchOrder")->add_record($data);
            $data1 = [
                "user_id" => $uid,
                "dispatch_order_id" => $res,
                "create_time" => time(),
                "verify_status" => 2,
                "lng" => $gis_lng,
                "lat" => $gis_lat,
                "remark" => "开始了任务"
            ];
            $add_log = M("baojia_mebike.dispatch_order_log")->add($data1);
            $have_order1 = M("baojia_mebike.have_order")->where("uid ={$uid} and status=0 and plate_no='{$plate_no}'")->getField('id');
            //开始任务就取消预约
            if( !empty($have_order1) ){
                $up_order1 = M("baojia_mebike.have_order")->where("uid ={$uid} and status=0 and plate_no='{$plate_no}'")->save(['status'=>1,'update_time'=>time()]);
                $up_order2 = $areaLogic->step_status(["reserve_status"=>0],$result['id'],2);
            }
            if($task_type==2){ //回库任务
                $up = $areaLogic->step_status(["storage_status"=>100],$result['id'],100);
            }else if($task_type==3){ //调度任务
                $up = $areaLogic->step_status(["dispatch_status"=>100],$result['id'],100);
            }
            if($res&&$add_log){
                M()->commit();
//                \Think\Log::write("开始任务type2" . json_encode($_POST) . "结果：" . $output, "ERR");
                $arr["pid"] = $res;
                $this->response(["code" => 1, "message" => "success","data"=>$arr], 'json');
            }else{
                M()->rollback();
                $this->response(["code" => -1, "message" => "failed"], 'json');
            }
        }

    }
    public function create_status_code($plate_no){
        $create_status_code = 12;
        $areaLogic= new \Api\Logic\Area();
        $result = $this->plate($plate_no);
        $data_status = $areaLogic->get_status($result['id']);
        if( $data_status['out_status']==100 )   {$create_status_code = 10;}
        if( $data_status['repaire_status']==100 )   {$create_status_code = 9;}
        if( $data_status['lack_power_status']==400 )   {$create_status_code = 5;}
        if( $data_status['offline_status']==100 ) {$create_status_code = 6;}
        if( $data_status['storage_status']==200 ) {$create_status_code = 11;}
        $check = $areaLogic->check_car($plate_no,$result['id']);
        if($check == 2) {$create_status_code = 7;} //两日无单
        if($check == 3) {$create_status_code = 8;} //有单无程
        if($check == 5) {$create_status_code = 7;} //五日无单
        $residual_battery = (float)$result['residual_battery'];
        if( $residual_battery >40 && $residual_battery <=50 && $data_status["sell_status"]==1){ //低电
            $create_status_code = 1;
        }
        if( $residual_battery >20 && $residual_battery <=40 && $data_status["sell_status"]==1){ //缺电
            $create_status_code = 2;
        }
        if( $residual_battery ==0 && $data_status["sell_status"]==1){ //无电
            $create_status_code = 4;
        }
        if( $residual_battery >0 && $residual_battery <=20 && $data_status["sell_status"]<>1){ //馈电
            $create_status_code = 3;
        }
        if( $data_status["sell_status"] == 1 )    {$create_status_code = 12;}
        return $create_status_code;

    }
    //任务进行中 '1' => '寻车任务','2' => '回库任务','3' => '调度任务','4' => '换电任务','5' => '检修任务',
    public function over_task($uid = '21', $iflogin = '',$id="",$task_type="",$pid = "",$plate_no="",$corporation_id="",$gis_lng = '',$gis_lat = '',$uuid='ff'){
        $this->promossion($uid,$iflogin);
        $this->check_terminal($uid,$uuid);
        $corporation_id = $_POST["corporation_id"];
        $pid = $_POST["pid"];
        $task_type = $_POST["task_type"];
        $upload_image = new \Api\Logic\UploadImage();
        if( empty($plate_no) ){
            $this->response(["code" => -1, "message" => "车牌号不能为空"], 'json');
        }
        if( empty($task_type) ){
            $this->response(["code" => -1, "message" => "参数错误"], 'json');
        }
        $result = $this->plate($plate_no);
        $auth = D("Control")->userAuth($corporation_id,$result['id']);
        if( $auth==0 ){
            $this->response(["code" => -1, "message" => "你没有管理这辆车的权限"], 'json');
        }
        if($task_type == 2){
            $operate = 42;
        }if($task_type == 3){
            $operate = 43;
        }
        if(!empty($pid)){
            $is_data = D("OperationLogging")->get_one("pid = {$pid} and operate = {$operate} and status=0","id");
        }
        $data = [
            "uid" => $uid,
            "rent_content_id" => $result['id'],
            "plate_no" => $plate_no,
            "time" => time(),
            "pid" => $pid,
            "gis_lng" => $gis_lng,
            "gis_lat" => $gis_lat
        ];
        if($task_type == 2){
            if( isset($_FILES["pic1"]) ){
                $pic1 = $upload_image->upload($_FILES["pic1"]);
                $data['pic1']=$pic1["pic1"]['short_path'];
            }
            $data['operate']=42;
        }elseif($task_type == 3){
            if( isset($_FILES["pic1"]) ){
                $pic1 = $upload_image->upload($_FILES["pic1"]);
                $data['pic1']=$pic1["pic1"]['short_path'];
            }
            $data['operate']=43;
        }


        if(!empty($is_data)){
            $res = D("OperationLogging")->update("pid = {$pid} and operate = {$operate} and status=0",$data);
            $rdata = D("OperationLogging")->get_one("pid = {$pid} and operate = {$operate} and status=0","id");
        }else{
            if( empty($data['pic1']) ){
                $this->response(["code" => -1, "message" => "图片缺失，请重新上传"], 'json');
            }
            $res = D("OperationLogging")->add_record($data);
            $rdata["id"] = $res;
        }
        if($res){
            $this->response(["code" => 1, "message" => "success","data"=>$rdata], 'json');
        }else{
            $this->response(["code" => -1, "message" => "failed"], 'json');
        }

    }
    public function check_suceess($uid="",$id = "",$task_type="",$check_type="",$gis_lng = '',$gis_lat = '',$corporation_id="0",$uuid="ff"){ //1寻车两张
	    $this->check_terminal($uid,$uuid);
        $taskSearch = new \Api\Logic\TaskSearch();
        $id = $_POST["id"];
        $corporation_id = $_POST["corporation_id"];
        $areaLogic= new \Api\Logic\Area();
        $result = D("OperationLogging")->get_one("id = {$id}","id,uid,pic1,pic2,pic3,pic4,rent_content_id,plate_no,operate,pid");
        if($task_type==3){//调度任务
            if(!empty($result)){
                if($check_type==1){
                    $check = $this->check_corporation($result["rent_content_id"],$result["pid"],$corporation_id,1);
                    if($check){
                        $data["pic1"] = "http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
//                        $update = D("OperationLogging")->update("id = {$id}",["step"=>1]);
                        $this->response(["code" => 100, "message" => "success"], 'json');
                    }else{
                        $data["pic1"] = "http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                    }
                }
                if($check_type==2){
                    $imei = $this->plate($result['plate_no']);
                    $seat = M("baojia_box.box_data",null,$this->box_config)->where(["imei"=>$imei['imei']])->getField("seat_lock_status");
                    if($seat == 1){//后仓锁开
                        $data["pic1"] = "http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                    }else{
//                        $update = D("OperationLogging")->update("id = {$id}",["step"=>2]);
                        $data["pic1"] = "http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => 100, "message" => "success","data"=>$data], 'json');
                    }
                }
                if($check_type==3){
                    if(empty($result['pic1'])){
                        $data["pic1"] = "";
                        $data["id"] = $id;
                        $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                    }else{
//                        $update = D("OperationLogging")->update("id = {$id}",["step"=>3]);
                        $data["pic1"] = "http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => 100, "message" => "success","data"=>$data], 'json');
                    }
                }
                if($check_type==4){
                    $result1 = D("OperationLogging")->get_one("status=0 and uid = {$uid} and operate = 16 and pid = ".$result['pid'],"id");
                    if(empty($result1)){
                        $data["pic1"] = "http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                    }else{
                        $data["pic1"] = "http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => 100, "message" => "success","data"=>$data], 'json');
                    }
                }
                if($check_type==5){
                    $check = $this->check_corporation($result["rent_content_id"],$result["pid"]);
                    $imei = $this->plate($result['plate_no']);
                    $seat = M("baojia_box.box_data",null,$this->box_config)->where(["imei"=>$imei['imei']])->getField("seat_lock_status");
                    $result1 = D("OperationLogging")->get_one("status=0 and uid = {$uid} and operate = 16 and pid = ".$result['pid'],"id");
                    if( $check && empty($seat) && !empty($result['pic1']) ){


                        M()->startTrans();
//                        $update = D("OperationLogging")->update("id = {$id}",["step"=>4]);
                        $update3 = D("OperationLogging")->update("uid = {$uid} and operate = 16 and status=0 and pid = ".$result['pid'],["status"=>1]);
//                        $update2 = D("OperationLogging")->update("operate = 35 and status=0",["status"=>1]);
                        $data1 = [
                            "user_id" => $uid,
                            "dispatch_order_id" => $result['pid'],
                            "create_time" => time(),
                            "verify_status" => 3,
                            "lng" => $gis_lng,
                            "lat" => $gis_lat,
                            "remark" => "结束了任务"
                        ];
                        $add_log = M("baojia_mebike.dispatch_order_log")->add($data1);
                        $out_status = M("mebike_status")->where(["rent_content_id"=>$result['rent_content_id']])->getField("out_status");
                        if($out_status == 100){
                            $res = $areaLogic->step_status(["dispatch_status" => 0,"out_status" => 0,"repaire_status" => 0], $result['rent_content_id'], 0);
                        }else{
                            $res = $areaLogic->step_status(["dispatch_status" => 0,"repaire_status" => 0], $result['rent_content_id'], 0);
                        }
                        $task_q = $taskSearch->getTask($result["rent_content_id"]);
                        $finish_status_code=implode(",",$task_q["all_rent_status"]);
                        if(empty($finish_status_code)) {$finish_status_code=12;}
                        $update1 = D("DispatchOrder")->update("id=".$result['pid'],["finish_status_code"=>$finish_status_code,"verify_status"=>3,"verify_time"=>time()]);

                        if($update1&&$update3&&$res&&!empty($add_log)){
                            M()->commit();
                            $data["pic1"] = "http://pic.baojia.com/".$result['pic1'];
                            $data["id"] = $id;
                            $this->response(["code" => 100, "message" => "success","data"=>$data], 'json');
                        }else{
                            M()->rollback();
                            $data["pic1"] = "http://pic.baojia.com/".$result['pic1'];
                            $data["id"] = $id;
                            $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                        }
                    }else{
                        $data["pic1"] = "http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => -100, "message" => "failed"], 'json');
                    }



                }

            }

        }
        if($task_type==2){//回库任务
            if(!empty($result)){
                if($check_type==1){
                    $check = $this->check_corporation($result["rent_content_id"],$result["pid"],$corporation_id,2);
                    if($check){
//                        $update = D("OperationLogging")->update("id = {$id}",["step"=>1]);
                        $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => 100, "message" => "success","data"=>$data], 'json');
                    }else{
                        $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                    }
                }
                if($check_type==2){
                    $imei = $this->plate($result['plate_no']);
                    $seat = M("baojia_box.box_data",null,$this->box_config)->where(["imei"=>$imei['imei']])->getField("seat_lock_status");
                    if($seat == 1){//后仓锁开
                        $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                    }else{
                        $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
//                        $update = D("OperationLogging")->update("id = {$id}",["step"=>2]);
                        $this->response(["code" => 100, "message" => "success","data"=>$data], 'json');
                    }
                }
                if($check_type==3){
                    if(empty($result['pic1'])){
                        $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                    }else{
                        $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
//                        $update = D("OperationLogging")->update("id = {$id}",["step"=>3]);
                        $this->response(["code" => 100, "message" => "success","data"=>$data], 'json');
                    }
                }
                if($check_type==4){
                    $result1 = D("OperationLogging")->get_one("status=0 and uid = {$uid} and operate = 16 and pid = ".$result['pid'],"id");
                    if(empty($result1)){
                        $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                    }else{
                        $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => 100, "message" => "success","data"=>$data], 'json');
                    }
                }
                if($check_type==5){
                    $check = $this->check_corporation($result["rent_content_id"],$result["pid"],$corporation_id,2);
                    $imei = $this->plate($result['plate_no']);
                    $seat = M("baojia_box.box_data",null,$this->box_config)->where(["imei"=>$imei['imei']])->getField("seat_lock_status");
                    $result1 = D("OperationLogging")->get_one("status=0 and uid = {$uid} and operate = 16 and pid = ".$result['pid'],"id");
                    if( $check && empty($seat) && !empty($result1) ){
                        M()->startTrans();
                        $update3 = D("OperationLogging")->update("uid = {$uid} and operate = 16 and status=0 and pid = ".$result['pid'],["status"=>1]);
                        $data1 = [
                            "user_id" => $uid,
                            "dispatch_order_id" => $result['pid'],
                            "create_time" => time(),
                            "verify_status" => 3,
                            "lng" => $gis_lng,
                            "lat" => $gis_lat,
                            "remark" => "结束了任务"
                        ];
                        $add_log = M("baojia_mebike.dispatch_order_log")->add($data1);
                        $res = $areaLogic->step_status(["repaire_status" => 0,"storage_status" => 100], $result['rent_content_id'], 100);
                        $task_q = $taskSearch->getTask($result["rent_content_id"]);
                        $finish_status_code=implode(",",$task_q["all_rent_status"]);
                        if(empty($finish_status_code)) {$finish_status_code=12;}
                        $update1 = D("DispatchOrder")->update("id=".$result['pid'],["finish_status_code"=>$finish_status_code,"verify_status"=>3,"verify_time"=>time()]);

                        if($update1&&$res&&!empty($add_log)){
                            M()->commit();
                            $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                            $data["id"] = $id;
                            $this->response(["code" => 100, "message" => "success","data"=>$data], 'json');
                        }else{
                            M()->rollback();
                            $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                            $data["id"] = $id;
                            $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                        }
                    }else{
                        $data["pic1"] = (empty($result['pic1'])) ? "" :"http://pic.baojia.com/".$result['pic1'];
                        $data["id"] = $id;
                        $this->response(["code" => -100, "message" => "failed","data"=>$data], 'json');
                    }

                }
            }

        }

    }
    //检测是否在库房或者网点中 type=1检测是否在网点  =2 检测是否在库房
    public function check_corporation($rentid="",$pid="",$corporation_id="",$type=""){
        if(empty($pid)||empty($rentid)){
            return false;
        }
        $res = M("baojia_mebike.dispatch_order_log")->where(["dispatch_order_id"=>$pid,"verify_status"=>2])->field("lng,lat")->find();
		
        if(!empty($res)){
            $lat = $res["lat"];
            $lng = $res["lng"];
        }else{
            return false;
        }

//        var_dump($distance);die;
        $areaLogic = new \Api\Logic\Area();
        $gps = D("Gps");
        $ren_c = M('rent_content_return_config')->where('rent_content_id = %d' , $rentid)->find();
        $ren_r = M('rent_content')->alias("rc")
            ->join("corporation cc on cc.id = rc.corporation_id ")
            ->where('rc.id= %d',$rentid)
            ->field("rc.*,cc.name")->find();
        $imei = $areaLogic->getImei($ren_r["car_item_id"]);
        $r_mode = $ren_c['return_mode'];
        if ($r_mode == 4) {
            $r_mode = 2;
        }
        if(!empty($imei)){
            $imei_status_info = $areaLogic->gpsStatusInfo($imei);
             $lat1 = $imei_status_info["gd_latitude"];
             $lng1 = $imei_status_info["gd_longitude"];
            $lat = $lat1;
            $lng = $lng1;
            $car_position = [$imei_status_info["gd_longitude"], $imei_status_info["gd_latitude"]];
        }else{
            $this->response(["code" => -1, "message" => "未获取到盒子号"], 'json');
        }
        if($type==2){   //查询库房
            $where = "";
            $where.= "status=1 ";
            if($corporation_id){
                $where.= " and corporation_id={$corporation_id} ";
            }
            $sql = "select ROUND(st_distance(point(gis_lng, gis_lat),point($lng1, $lat1))*111195,0) AS distance,radius from baojia_mebike.dispatch_store where {$where}";
            $distance = M("")->query($sql);
            foreach ($distance as $k=>$v){
                if($v["distance"]<=$v["radius"]){
                    return true;
                }
            }
            return false;
        }
        switch ($r_mode){
            case 1:
                $where = [];
                $where['a.type'] = 4;
                $where['a.data_status'] = 1;
                $where['a.status'] = 1;
                $where['a.id'] = $ren_r['corporation_id'];

                $corarr = M('corporation')->alias('a')
                    ->join('corporation_car_return_config b on a.id =b.corporation_id', 'LEFT')
                    ->join("corporation_group cg on cg.corporation_id=a.id","left")
                    ->where($where)
                    ->field("a.id,a.name,a.gis_lat,a.gis_lng,a.short_address,a.detaile_address,b.return_radius,cg.id groupid,a.group_type type")
                    ->select();
                foreach ($corarr as $kc =>$vc){
                    $no_group = M("corporation_group_area", null, $this->baojia_config)->where("groupId=".$vc['groupid'])->field("lng,lat")->select();
                    $no_group1=[];
                    foreach($no_group as $kv=>$vv){
                        $no_group1[] = [$vv["lng"],$vv["lat"]];
                    }
                    $corarr[$kc]["polygon"] = $no_group1;
                }
                break;
            case 2:
                $cor = M('corporation')->where('id=' . $ren_r['corporation_id'])->find();
                $posturl = "http://ms.baojia.com/search/near/group";//ms.baojia.com
                $post_data = ["parentId"=>$cor['parent_id'],"location"=>[$lng,$lat],"distance"=>5,"size"=>50];
                \Think\Log::write(var_export($post_data,true),'请求网点参数');
                $result_data = curl_get($posturl,json_encode($post_data),"POST");

                $result_data = json_decode($result_data,true);
                $corarr = $result_data["data"];
                break;
            case 32:

                //获取自由还车区域
                $corgroup = M('car_group_r')->where('carId=' . $ren_r['car_item_id'])->select();
                $res = array();
                if ($corgroup && count($corgroup) > 0) {
                    foreach ($corgroup as $k => $v) {
                        if(!empty($v['groupId'])){
                            $group = M('group')->where('id=' . $v['groupId'])->find();
                            $grouppoint = M('group_area')->where('groupId=' . $v['groupId'])->order('no asc,id asc')->select();
                            if ($grouppoint) {
                                $defaultpoints = "[";
                                $areapoints = array();
                                foreach ($grouppoint as $k1 => $v1) {
                                    $newpos1s = $gps->bd_encrypt($v1['lat'], $v1['lng']);
                                    $defaultpoints .= '[' . $newpos1s['lon'] . ',' . $newpos1s['lat'] . '],';
                                    array_push($areapoints, array($newpos1s['lon'], $newpos1s['lat']));
                                }
                                $defaultpoints = substr($defaultpoints, 0, -1);
                                $defaultpoints .= "]";
                                $res[$k]['area'] = $areapoints;
                                $res[$k]['name1'] = $group['name'];
                            }
                            $res[$k]['pointall'] = $defaultpoints;
                        }


                    }
                }
                break;

        }
        $boxinfo = $areaLogic->getboxinfo($rentid);
        if ($corarr && count($corarr) >= 1) {
            //检测当前车辆是否在网点内
            $corarrs = [];
            foreach($corarr as $k=>$v){
                if ($r_mode == 1) {
                    $corarrs = $corarr;
                    continue;
                }
                $pt = [(float)$v['location'][0], (float)$v['location'][1]];
                //判断网点是否在围栏内
                $isincors = $areaLogic->isInsidePolygon($pt, $boxinfo['area']);
                if($isincors){
                    $return_radius = M("corporation_car_return_config", null, $this->baojia_config)->where(["corporation_id"=>$v["groupId"]])->getField("return_radius");
                    foreach ($v["groupLocations"] as $k2=>$v2){
                        $v["groupLocations"][$k2][0] = $v2["location"][0];
                        $v["groupLocations"][$k2][1] = $v2["location"][1];
                        unset($v["groupLocations"][$k2]["location"]);
                        unset($v["groupLocations"][$k2]["sort"]);
                    }
                    $corarrs[$k]["id"] = $v["groupId"];
                    $corarrs[$k]["type"] = $v["groupType"];

                    $corarrs[$k]["gis_lat"] = $v["location"][1];
                    $corarrs[$k]["gis_lng"] = $v["location"][0];
                    $corarrs[$k]["return_radius"] = $return_radius;
                    $corarrs[$k]["short_address"] = $v["address"];
                    $corarrs[$k]["cor_logo"] = $v["url"];
                    $corarrs[$k]["name"] = $v["groupName"];
                    $corarrs[$k]["polygon"] = $v["groupLocations"];

                }
            }
        }
        $corarrs1 = array_values($corarrs);
        if($r_mode==1){  //原点还判断是否在原点网点内
            foreach ($corarrs1 as $k1=>$v1){
                $is_inside = $areaLogic->isInsidePolygon($car_position, $v1['polygon']);
                \Think\Log::write("{$pid}检测" .var_export($car_position,true)."-". $v1["id"] . "结果：" . $is_inside, "INFO");
                if($is_inside){
                    return true;
                }
            }
            return false;
        }else{
            foreach ($corarrs1 as $k1=>$v1){
                if($v1['type']==1){
                    //圆
                    $v1['juli'] = $areaLogic->getDistance($car_position[0], $car_position[1], $v1['gis_lng'], $v1['gis_lat']);
//                    $v1['juli'] = $v1['juli'] - $v1['return_radius'];
                    $v1['juli'] = $v1['juli'] - $v1['return_radius']-20;
                    \Think\Log::write("{$pid}检测1" .var_export($car_position,true)."-". $v1["id"] . "结果：" . $v1['juli'], "INFO");
                    if($v1['juli']<0){
                        return true;
                    }
                }else{
                    $is_inside = $areaLogic->isInsidePolygonExt($car_position, $v1['polygon'],0.00018);//0.00018相当于20米
//                    $v1['juli'] = $areaLogic->getDistance($car_position[0], $car_position[1], $v1['gis_lng'], $v1['gis_lat']);
                    \Think\Log::write("{$pid}检测2" .var_export($car_position,true)."-". $v1["id"] . "结果：" . $is_inside, "INFO");
                    if($is_inside){
                        return true;
                    }
                }
            }
            return false;
        }
//        $this->response(["code" => 1, "message" => "success","data"=>$corarrs1], 'json');
    }
    //可骑行区域
    /*
     * $type =2  查询任务页网点相关数据  调度任务
     * $type =3  查询库房  回库任务
     *
     * */
    public function getCrawmap($rentid = "296962",$uid = "2650355",$iflogin="",$pin=0,$gd_lng = '',$gd_lat = '',$type=0,$pid=0,$corporation_id="0"){
        $gps = D("Gps");
        $uuid = $_POST["uuid"];
        $this->check_terminal($uid,$uuid);
        if($type == 2 ){
            if(empty($pid)){
                $this->response(["code" => -1, "message" => "failed"], 'json');
            }
            $rentid = D("DispatchOrder")->get_one("id = {$pid}","rent_content_id");
            $rentid = $rentid["rent_content_id"];
            $up_data = D("OperationLogging")->get_one("rent_content_id = $rentid and uid = {$uid} and status=0 and pid={$pid} and operate=43","pic1");


        }
        if($type==3){
            if(empty($pid)){
                $this->response(["code" => -1, "message" => "failed"], 'json');
            }
            $rentid = D("DispatchOrder")->get_one("id = {$pid}","rent_content_id");
            $rentid = $rentid["rent_content_id"];
            $up_data = D("OperationLogging")->get_one("rent_content_id = $rentid and uid = {$uid} and status=0 and pid={$pid} and operate=42","pic1");


            $where = "";
            $where.=" status=1 ";
//            if(!empty($corporation_id)){
//                $where.=" and corporation_id = {$corporation_id} ";
//            }
            $corarr = M("baojia_mebike.dispatch_store")->where($where)->field("id,gis_lng,gis_lat,radius")->select();

            foreach ($corarr as $k=>$v){
                $bd = $gps->bd_encrypt($v["gis_lat"],$v["gis_lng"]);
                $corarr[$k]["bd_latitude"] = $bd["lat"];
                $corarr[$k]["bd_longitude"] = $bd["lon"];
            }
            if(empty($corarr)){
                $corarr=[];
            }
            $result = [
                'task_title' => "请将车辆托运至库房",
                "pic" => [(empty($up_data["pic1"])) ? "" :"http://pic.baojia.com/".$up_data["pic1"]],
                'corarr' => $corarr,
            ];
            $this->response(["code" => 1, "message" => "success","data"=>$result], 'json');
        }
        $this->promossion($uid,$iflogin);
        $areaLogic = new \Api\Logic\Area();
        $gps = D("Gps");
        $ren_c = M('rent_content_return_config')->where('rent_content_id = %d' , $rentid)->find();
        $ren_r = M('rent_content')->alias("rc")
            ->join("corporation cc on cc.id = rc.corporation_id ")
            ->where('rc.id= %d',$rentid)
            ->field("rc.*,cc.name")->find();
        $imei = $areaLogic->getImei($ren_r["car_item_id"]);
        $r_mode = $ren_c['return_mode'];
        if($r_mode == 1){
            $return_mode["return_mode"] = $r_mode;
            $return_mode['title'] = "原点还";
            $return_mode['desc'] = "越界车辆需拉回停车点后投放";
        }elseif($r_mode == 2){
            $return_mode["return_mode"] = $r_mode;
            $return_mode['title'] = "网点还";
            $return_mode['desc'] = "越界车辆需拉回停车点投放";
        }elseif($r_mode == 4){
            $return_mode["return_mode"] = $r_mode;
            $return_mode['title'] = "自由还";
            $return_mode['desc'] = "越界车辆需拉回界内5公里外投放";
        }
        if ($r_mode == 4) {
            $r_mode = 2;
        }
        if(!empty($imei)){
            $imei_status_info = $areaLogic->gpsStatusInfo($imei);
            $car_position = [$imei_status_info["gd_longitude"], $imei_status_info["gd_latitude"]];
            $lat = $imei_status_info["gd_latitude"];
            $lng = $imei_status_info["gd_longitude"];
        }else{
            $this->response(["code" => -1, "message" => "未获取到盒子号"], 'json');
        }
        if($pin==1){
            if( !empty($gd_lng)&&!empty($gd_lat) ){
                $lat = $gd_lat;
                $lng = $gd_lng;
            }
        }
//        if($type==2){
//            $res = M("baojia_mebike.dispatch_order_log")->where(["dispatch_order_id"=>$pid,"verify_status"=>2])->field("lng,lat")->find();
//            if(!empty($res)){
//                $lat = $res["lat"];
//                $lng = $res["lng"];
//            }
//        }


        switch ($r_mode){
            case 1:
                $where = [];
                $where['a.type'] = 4;
                $where['a.data_status'] = 1;
                $where['a.status'] = 1;
                $where['a.id'] = $ren_r['corporation_id'];

                $corarr = M('corporation')->alias('a')
                    ->join('corporation_car_return_config b on a.id =b.corporation_id', 'LEFT')
                    ->join("corporation_group cg on cg.corporation_id=a.id","left")
                    ->where($where)
                    ->field("a.id,a.name,a.gis_lat,a.gis_lng,a.short_address,a.detaile_address,b.return_radius,cg.id groupid,a.group_type type")
                    ->select();
                foreach ($corarr as $kc =>$vc){
                    $no_group = M("corporation_group_area", null, $this->baojia_config)->where("groupId=".$vc['groupid'])->field("lng,lat")->select();
                    $no_group1=[];
                    foreach($no_group as $kv=>$vv){
                        $no_group1[] = [$vv["lng"],$vv["lat"]];
                    }
                    $corarr[$kc]["polygon"] = $no_group1;
                }

                break;

            case 2:
                $cor = M('corporation')->where('id=' . $ren_r['corporation_id'])->find();
                $posturl = "http://ms.baojia.com/search/near/group";//ms.baojia.com
                $post_data = ["parentId"=>$cor['parent_id'],"location"=>[$lng,$lat],"distance"=>5,"size"=>50];
                \Think\Log::write(var_export($post_data,true),'请求网点参数');
                $result_data = curl_get($posturl,json_encode($post_data),"POST");

                $result_data = json_decode($result_data,true);
                $corarr = $result_data["data"];
//                echo "<pre>";
//                print_r($corarr);
                break;
            case 32:

                //获取自由还车区域
                $corgroup = M('car_group_r')->where('carId=' . $ren_r['car_item_id'])->select();
                $res = array();
                if ($corgroup && count($corgroup) > 0) {
                    foreach ($corgroup as $k => $v) {
                        if(!empty($v['groupId'])){
                            $group = M('group')->where('id=' . $v['groupId'])->find();
                            $grouppoint = M('group_area')->where('groupId=' . $v['groupId'])->order('no asc,id asc')->select();
                            if ($grouppoint) {
                                $defaultpoints = "[";
                                $areapoints = array();
                                foreach ($grouppoint as $k1 => $v1) {
                                    $newpos1s = $gps->bd_encrypt($v1['lat'], $v1['lng']);
                                    $defaultpoints .= '[' . $newpos1s['lon'] . ',' . $newpos1s['lat'] . '],';
                                    array_push($areapoints, array($newpos1s['lon'], $newpos1s['lat']));
                                }
                                $defaultpoints = substr($defaultpoints, 0, -1);
                                $defaultpoints .= "]";
                                $res[$k]['area'] = $areapoints;
                                $res[$k]['name1'] = $group['name'];
                            }
                            $res[$k]['pointall'] = $defaultpoints;
                        }


                    }
                }
                break;

        }
        $boxinfo = $areaLogic->getboxinfo($rentid);
//        $this->response(["code" => 1, "message" => "success","data"=>$boxinfo], 'json');
        if ($corarr && count($corarr) >= 1) {
            $corarrs = [];

            foreach($corarr as $k=>$v){

                if ($r_mode == 1) {
                    $corarrs = $corarr;
                    continue;
                }
                $pt = [(float)$v['location'][0], (float)$v['location'][1]];
                //判断网点是否在围栏内
                $isincors = $areaLogic->isInsidePolygon($pt, $boxinfo['area']);
                if($isincors){
                    $return_radius = M("corporation_car_return_config")->where(["corporation_id"=>$v["groupId"]])->getField("return_radius");
                    foreach ($v["groupLocations"] as $k2=>$v2){
                        $v["groupLocations"][$k2][0] = $v2["location"][0];
                        $v["groupLocations"][$k2][1] = $v2["location"][1];
                        unset($v["groupLocations"][$k2]["location"]);
                        unset($v["groupLocations"][$k2]["sort"]);
                    }
                    $corarrs[$k]["id"] = $v["groupId"];
                    $corarrs[$k]["type"] = $v["groupType"];

                    $corarrs[$k]["gis_lat"] = $v["location"][1];
                    $corarrs[$k]["gis_lng"] = $v["location"][0];
                    $corarrs[$k]["return_radius"] = $return_radius;
                    $corarrs[$k]["short_address"] = $v["address"];
                    $corarrs[$k]["cor_logo"] = $v["url"];
                    $corarrs[$k]["name"] = $v["groupName"];
                    $corarrs[$k]["polygon"] = $v["groupLocations"];

                }
            }
        }
        $corarrs1 = array_values($corarrs);
        $Forbid = new \Api\Logic\Forbid();
        $ForbidList = $Forbid->ForbidList($rentid);
        if($type==2){
            $task_title = "请将车辆托运至网点";
            foreach ($corarrs1 as $k2=>$v2){
                $bd = $gps->bd_encrypt($v2["gis_lat"],$v2["gis_lng"]);
                $corarrs1[$k2]["bd_latitude"] = $bd["lat"];
                $corarrs1[$k2]["bd_longitude"] = $bd["lon"];
            }
            if(empty($corarrs1)){
                $corarrs1=[];
            }
            $result = [
                'corarr' => $corarrs1,
                'task_title' => $task_title,
                "pic" => [(empty($up_data["pic1"])) ? "" :"http://pic.baojia.com/".$up_data["pic1"]]
            ];
        }
        else{
            $result = [
                'return_mode' => $return_mode,
                'car_position' => $car_position,
                'boxinfo' => $boxinfo,
                'corarr' => $corarrs1,
                'ForbidList' => empty($ForbidList) ? [] : $ForbidList,
            ];
        }

        $this->response(["code" => 1, "message" => "success","data"=>$result], 'json');
        // echo "<pre>";
        // print_r($result);die;

    }
    //禁行区域
    public function getForbid(){
        $Forbid = new \Api\Logic\Forbid();
        $ForbidList = $Forbid->ForbidList();
        $this->response(["code" => 1, "message" => "success","data"=>$ForbidList], 'json');

    }
    //运营区域
    public function get_area($corporation_id="2118",$gis_lng = '116.390498',$gis_lat = '39.979504'){
        $arealogic = new \Api\Logic\Area();
        $gps = D("Gps");
        $corporation = M("corporation")->where(["id"=>$corporation_id])->field("corporation_brand_id,city_id")->find();
//        if( !empty($gis_lng) && !empty($gis_lat) ){
//            $city_id = $arealogic->getAmapCity($gis_lng,$gis_lat);
//        }
//        echo "<pre>";
//        print_r($corporation);die;"group_id"=>8,
        if( !empty($corporation) ){
            $group_id = M("baojia_dashboard.bike_info")->where(["brand_id"=>$corporation['corporation_brand_id'],"city_id"=>$corporation['city_id']])->distinct(true)->field("group_id")->select();
        }else{
            $this->response(["code" => -1, "message" => "参数错误"], 'json');
        }
//        echo "<pre>";
//        print_r($group_id);die;
        if($group_id){

            $group_area = [];
            foreach ($group_id as $k=>$v){
                $status = M("group","",$this->zsdb)->where(["id"=>$v["group_id"]])->getField("status");
                if($status==0){
                    continue;
                }
                $res = M("group_area","",$this->zsdb)->where(["groupId"=>$v["group_id"]])->field("groupId,lng,lat")->order("no asc")->select();
                $newpos1=[];
                foreach ($res as $k1=>$v1){
                    $newpos = $gps->gcj_encrypt($v1['lat'],$v1['lng']);
                    if($newpos){
                        array_push($newpos1,array("lng"=>$newpos['lon'],"lat"=>$newpos['lat']));
                    }
                }
                array_push($group_area,$newpos1);
            }
            $this->response(["code" => 1, "message" => "success","data"=>$group_area], 'json');
        }else{
            $this->response(["code" => -1, "message" => "暂无数据"], 'json');
        }
    }












}