<?php
/**
 * Created by PhpStorm.
 * User: abel
 * Date: 2016/5/14
 * Time: 8:31
 */

namespace Api\Logic;

use User\Api\Api;

class CarStart
{

    //盒子解绑
    public function hzdelete($id,$qy_uid){
        $deleteMsg = "必须写清楚解绑原因，如有乱写或与实际情况不符，一经查实，严惩不贷！";
        $where = [];
        $where['id'] = ['eq', $id];
        $car_item_device = M('car_item_device')->where($where)->find();
        $sid = $car_item_device['track_sid'];
        if ($car_item_device['pay_status'] > 0) {
            //判断是否退款
            $where = [];
            $where['item_device_id'] = ['eq', $id];
            $where['deposit_return'] = ['eq', 0];
            $where['pay_status'] = ['gt', 0];
            $where['status'] = ['eq', 3];
            $where['sid'] = $sid;
            $car_item_device_track = M('car_item_device_track')->field('deposit_return,sid,son_device_imei,device_type')->where($where)->order("sid desc")->find();
            if ($car_item_device_track) {
                $sid = $car_item_device_track['sid'];
                if ($car_item_device['donate'] != 2) {
                    $this->drawback($car_item_device_track['sid']);
                }
            }

            $arr = [];
            $arr['id'] = $car_item_device['id'];
            $arr['city_id'] = $car_item_device['city_id'];
            $arr['owner_id'] = $car_item_device['owner_id'];
            $arr['trade_order'] = $car_item_device['trade_order'];
            $arr['car_item_id'] = $car_item_device['car_item_id'];
            $arr['imei'] = $car_item_device['imei'];
            $arr['mobile'] = $car_item_device['mobile'];
            $arr['usercar_id'] = $car_item_device['usercar_id'];
            $arr['create_time'] = $car_item_device['create_time'];
            $arr['start_time'] = $car_item_device['start_time'];
            $arr['end_time'] = $car_item_device['end_time'];
            $arr['service_name'] = $car_item_device['service_name'];
            $arr['service_update_time'] = $car_item_device['service_update_time'];
            $arr['service_remark'] = $car_item_device['service_remark'];
            $arr['status'] = $car_item_device['status'];
            $arr['device_type'] = $car_item_device['device_type'];
            $arr['brand_code'] = $car_item_device['brand_code'];
            $arr['pay_status'] = $car_item_device['pay_status'];
            $arr['receive_uid'] = $car_item_device['receive_uid'];
            $arr['install_status'] = $car_item_device['install_status'];
            $arr['pic_status'] = $car_item_device['pic_status'];
            $arr['deposit_money'] = $car_item_device['deposit_money'];
            $arr['post_time'] = time();
            $returns = M('car_item_device_history')->add($arr);
            if ($returns) {
                //更新数据
                $data = array(
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
                    'deposit_money' => 0,
                );
                $rtn2 = M("CarItemDevice")->where("id=" . $id)->save($data);
                if ($car_item_device_track['son_device_imei']) {
                    M("CarItemDevice")->where("imei=" . $car_item_device_track['son_device_imei'])->save($data);
                }
                if ($rtn2) {
                    //清空car_item box
                    $this->delectCarBox($car_item_device['car_item_id'], $car_item_device['device_type']);
                    //判断新表中是否有数据
                    $where = [];
                    $where['item_device_id'] = ['eq', $id];
                    $where['sid'] = ['eq', $sid];
                    $result = M('car_item_device_track')->where($where)->order("sid desc")->find();
                    //
                    if (in_array($car_item_device["device_type"], [99, 18])) {
                        $this->unbindThirdDevice($id, $car_item_device['imei'], $car_item_device['car_item_id'], $car_item_device["device_type"]);
                    }
                    if ($result) {
                        //判断支付方式
                        if ($result['pay_status'] == 2) {
                            $remark = "待确认解绑";
                        } else {
                            $remark = "盒子解绑";
                        }
                        $data = [];
                        $data['install_status'] = 2;
                        $data['status'] = 5;
                        $data['offline_uid'] = $qy_uid;
                        $data['offline_time'] = time();
                        //解绑是去掉app待补录状态
                        if ($result['data_type'] == 8) {
                            $data['data_type'] = 0;
                        }
                        if (!$sid)
                            $sid = $result['sid'];
                        $rtn1 = M('car_item_device_track')->where("sid=" . $sid)->save($data);
                        if ($rtn1) {
                            $this->InsertDeviceLog($result['device_imei'], $result['sid'], 0, 7, $deleteMsg, $car_item_device['owner_id'], $qy_uid);
                            $rtn_arr['status'] = 20;
                            $rtn_arr['sid'] = '';
                            return $rtn_arr;
                        } else {
                            $rtn_arr['status'] = -50;
                            $rtn_arr['sid'] = '';
                            return $rtn_arr;
                        }
                    } else {
                        $this->InsertDeviceLog($car_item_device['imei'], 0, 0, 7, "盒子解绑", $car_item_device['owner_id'], $qy_uid);
                        $rtn_arr['status'] = 20;
                        $rtn_arr['sid'] = '';
                        return $rtn_arr;
                    }
                }
            } else {
                $rtn_arr['status'] = -40;
                $rtn_arr['sid'] = '';
                return $rtn_arr;
            }
        } else {
            $track_arr = M("car_item_device_track")->where("sid=$sid and item_device_id=" . $id)->find();
            if (!$track_arr) {
                $data = array(
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
                    'model_type' => '',
                    'deposit_money' => 0,
                );
                $rtn3 = M("CarItemDevice")->where("id=" . $id)->save($data);
                if ($rtn3) {
                    $this->delectCarBox($car_item_device['car_item_id'], $car_item_device['device_type']);
                    $this->InsertDeviceLog($car_item_device['imei'], $sid, 0, 7, $deleteMsg, $track_arr['car_owner_uid'], $qy_uid);
                    $rtn_arr['status'] = 20;
                    $rtn_arr['sid'] = '';
                    return $rtn_arr;
                } else {
                    $rtn_arr['status'] = -20;
                    $rtn_arr['sid'] = '';
                    return $rtn_arr;
                }
            } else {
                $rtn_arr['status'] = -30;
                $rtn_arr['sid'] = '';
                return $rtn_arr;
            }
        }
    }


    //盒子退款
    public function drawback($cid) {
        $NowTime = time();
        $where = [];
        $where['sid'] = ['eq', $cid];
        $where['status'] = ['eq', 3];
        $car_item_device_track = M('car_item_device_track')->where($where)->find();
        $money = $car_item_device_track['deposit_money'];
        $uid = $car_item_device_track['car_owner_uid'];
        if ($car_item_device_track['sid']) {
            if ($uid) {
                $data = [];
                $data['status'] = 4;
                //待审退款
                if ($car_item_device_track['pay_status'] == 2 && $car_item_device_track['sale_type'] == 0) {
                    $data['deposit_return'] = 1;
                    $data['deposit_return_time'] = $NowTime;
                    $data['deposit_return_money'] = $money;
                    $data['pay_mode'] = 1;
                    $data['pay_status'] = 1;
                    //作废充值记录
                    $trade_payment_id = $car_item_device_track['payment_id'];
                    if ($trade_payment_id) {
                        M("trade_payment")->where("id=" . $trade_payment_id)->save(array("pay_status" => -2));
                    }
                    $where_r = [];
                    $where_r['user_id'] = ['eq', $uid];

                    $data_cash['total_amount'] = array('exp', "total_amount + $money");
                    $data_cash['locked_amount'] = array('exp', "locked_amount - $money");
                    $data_cash['update_time'] = $NowTime;
                    $rtn = M('member_cash_balance')->where($where_r)->save($data_cash);
                    if ($rtn) {
                        $select_cash = M('member_cash_balance')->where($where_r)->find();
                        //盒子退款日志
                        $data_log['user_id'] = $uid;
                        $data_log['cash_balance_type'] = 1;
                        $data_log['amount'] = $money;
                        $data_log['trade_order_id'] = 0;
                        $data_log['total_amount'] = $select_cash['total_amount'];
                        $data_log['log_code'] = 10088;
                        $data_log['log_content'] = 10088;
                        $data_log['cash_type'] = 3;
                        $data_log['action_type'] = 2;
                        $data_log['action_time'] = date("Y-m-d H:i:s");
                        $data_log['trade_payment_id'] = 0;
                        $data_log['summary'] = "退款(盒子)";
                        M('member_cash_log')->add($data_log);
                    }
                    $data['refund_status'] = 0;
                } else {
                    $data['refund_status'] = 1;
                }
                $update_data = M('car_item_device_track')->where($where)->save($data);
                if ($update_data) {
                    if ($car_item_device_track['pay_status'] != 2) {
                        $itemDeviceId = $car_item_device_track['item_device_id'];
                        if ($itemDeviceId > 0) {
                            M("car_item_device")->where("id={$itemDeviceId}")->save(array("refund_status" => 1));
                        }
                    }
                }
            }
        }
    }

    public function delectCarBox($carid, $type) {
        $carItemDevice = M("car_item_device")->where("device_type=$type and usercar_id>0 and car_item_id=" . $carid)->count();
        if ($carItemDevice < 1) {
            if ($type == 1 || $type == 41) {
                M("car_item")->where("id=" . $carid)->save(array("box_install" => 0));
            } else {
                M("car_item")->where("id=" . $carid)->save(array("boxplus_install" => 0));
            }
        }
    }

    /*
       解绑互联互通盒子
   */
    public function unbindThirdDevice($device_id = 0,$imei = '',$car_item_id = 0,$device_type = 0){

        \Think\Log::write("box_config".C('DB_CONFIG_BOX'), 'INFO');
        \Think\Log::write("unbindThirdDevice".$device_id."|".$car_item_id."|".$imei, 'INFO');
        if($device_id <= 0 || $imei == '' || $car_item_id <= 0){
            \Think\Log::write("-1", 'INFO');
            return -1;
        }
        $no_zero_imei = ltrim($imei,0);

        $result1 = M("baojia.car_item_device")->where(["id"=>$device_id])->delete();
        $result2 = M("baojia_cloud.car_device_bind")->where(["bj_device_id"=>$device_id])->delete();
        $result3 = M("baojia_cloud.device")->where(["bj_device_id"=>$device_id])->delete();
        if($device_type == 99){
            $result4 = M("baojia_cloud.car")->where(["car_item_id"=>$car_item_id])->save(["rent_content_id"=>0,"car_item_id"=>0]);
        }
        $result5 = M("baojia_box.gps_usercar","","DB_CONFIG_BOX")->where(["imei"=>$imei])->delete();
        $car_group_r = M("car_group_r","","BAOJIA_LINK_DC")->where(["carId"=>$no_zero_imei])->find();
        $result6 = -1;
        if($car_group_r){
            $result6 = M("car_group_r","","BAOJIA_LINK_DC")->where(["id"=>$car_group_r["id"]])->delete();
        }

        $corporation_car_group = M("corporation_car_group","","BAOJIA_LINK_DC")->where(["carId"=>$imei])->find();

        if($corporation_car_group){
            $result7 = M("corporation_car_group","","BAOJIA_LINK_DC")->where(["id"=>$corporation_car_group["id"]])->delete();
        }


        \Think\Log::write("unbindThirdDevice::result".$result1."|".$result2."|".$result3."|".$result4."|".$result5."|".$result6."|".$result7, 'INFO');
        return 1;
    }

    //盒子操作记录日志
    public function InsertDeviceLog($imei, $sid, $type, $code_type, $remark = '', $owner_id = 0,$qy_uid) {
        $data = [
            "track_sid" => $sid,
            "owner_id" => $owner_id,
            "imei" => $imei,
            "operate_uid" => $qy_uid,
            "remark" => $remark,
            "type" => $type,
            "code_type" => $code_type,
            "create_time" => date("Y-m-d H:i:s")
        ];
        $rtn = M("car_item_device_log")->add($data);
        return $rtn;
    }

    //车辆下线
    public function verify_status($rcId=0,$user_id=0,$verify_status=0) {
        if(empty($rcId) || empty($user_id)){
            return ['code'=>0,'message'=>'参数错误'];
        }

        $data = array();
        $data['verify_by'] = $user_id;
        $data['verify_time'] = time();

        $map = array();
        $map['id'] = array('eq', $rcId);

        //查看审核前实地验证状态
        $list = M("rent_content")->field('id,status,city_id')->where($map)->find();
        if(!$list){
            return ['code'=>0,'message'=>'车辆不存在'];
        }
        //记录车辆上下线操作日志
        $log_data = array();
        $log_data['check_type'] = 1;
        $log_data['city_id'] = $list['city_id'];
        $log_data['rent_content_id'] = $list['id'];
        $log_data['check_uid']  = $user_id;
        $log_data['check_time'] = time();

        $log_data['pre_status'] = 2;
        $log_data['current_status'] = $verify_status;
        $log_data['remark'] = '';
        $this->addOnlineStatusLog($log_data);

        $data['status'] = 0;
        $data['offline_time']  = time();
        $data['offline_uid']   = $user_id;
        $data['submit_status'] = 0;
        $data['submit_time'] = 0;
        $data['submit_by'] = 0;
        $result = M('rent_content')->where($map)->save($data);
        if($result){
            return ['code'=>1,'message'=>'车辆下线成功'];
        }else{
            return ['code'=>0,'message'=>'车辆下线失败'];
        }
    }

    //回收站处理 $ids 车辆id  $user_id 操作用户id   $status  状态为 -2 车辆移至回收站
    public function setRecycle($ids=0,$user_id=0,$status=-2) {
        if (empty($ids) || empty($user_id)) {
            return ['code'=>0,'message'=>'参数错误'];
        }

        $mod = M('rent_content');
        $car_item_id = $mod->getFieldById($ids, 'car_item_id'); //车辆ID
        //检测上线车辆不能进入回收站
        $current_status = $mod->getFieldById($ids, 'status');
        if ($current_status == 2) {
            return ["code"=>0,"message"=>"已上线的车辆不能被进入回收站！"];
        }
        //检测当车源有绑定的盒子时，不能放入回收站，提示“该有绑定的盒子，不能放入回收站”
        $car_item_device_number = M("car_item_device")->field("device_type")->where("car_item_id=" . $car_item_id)->select();
        $i_o = $i_t = $i_m = 0;
        if ($car_item_device_number) {
            foreach ($car_item_device_number as $rs) {
                if ($rs['device_type'] == 1) {
                    $i_o++;
                } else if ($rs['device_type'] == 3) {
                    $i_m++;
                } else {
                    $i_t++;
                }
            }
        }
        if ($i_o || $i_m || $i_t) {
            return ["code"=>0,"message"=>"该车辆有绑定的盒子，不能放入回收站!"];
        }

        //检测当车源存在有效订单时不能放入回收站（已取消订单、失效订单除外）
        $check_condition = array();
        $check_condition['car_item_id'] = array('eq', $car_item_id);
        $check_condition['_string'] = "status >=10000 and status!=10301";
        $check_condition['owner_settle_state'] = array('neq', 1);
        $car_current_order = M('trade_order')->where($check_condition)->select();

        if (is_array($car_current_order) && !empty($car_current_order)) {
            return ["code"=>0,"message"=>"该车辆当前存在交易订单记录，不能放入回收站!"];
        }

        $map['id'] = array('eq', $ids);
        $data = array();
        $data['status'] = $status;

        //车辆信息
        $list = $mod->field('id,status,city_id')->where($map)->find();

        if ($mod->where($map)->save($data)) {//车辆进入回收站成功
            //清除掉车牌号码，并设置行驶证的状态为待审
            //$car_item_id = $mod->getFieldById($ids, 'car_item_id'); //车辆ID
            if ($car_item_id) {

                $this->mebikeRecycleHandle($ids);

                $condition = array();
                $condition['car_item_id'] = array('eq', $car_item_id);
                $data = array();
                $data['plate_no'] = '';
                $data['vehicle_license_status'] = 1;

                $car_item_verify_info = M('car_item_verify')->where($condition)->field("plate_no")->find();
                $plate_no = $car_item_verify_info["plate_no"];
                $this->toHistory($car_item_id,$plate_no,0,$user_id);
                M('car_item_verify')->where($condition)->save($data);

                //记录车辆实地操作日志
                $log_data = array();
                $log_data['check_type'] = 8;
                $log_data['city_id'] = $list['city_id'];
                $log_data['rent_content_id'] = $list['id'];
                $log_data['check_uid']  = $user_id;
                $log_data['check_time'] = time();
                $log_data['pre_status'] = $list['status'];
                $log_data['current_status'] = $status;
                $log_data['remark'] = '';
                $this->addOnlineStatusLog($log_data);
            }
            return ["code"=>1,"message"=>"移至回收站成功"];
        } else {
            return ["code"=>1,"message"=>"移至回收站成功"];
        }
    }

    //记录座驾上下线操作日志
    public function addOnlineStatusLog($log_data = array()) {
        $mod = M('rent_verify_log');
        $data = $log_data;
        $result = $mod->add($data);
    }

    /*
        小蜜单车回收相关处理
    */
    public function mebikeRecycleHandle($rent_content_id){

        \Think\Log::write("mebikeRecycleHandle:".$rent_content_id, 'INFO');
        $rent_info = M("rent_content")->where(["id"=>$rent_content_id])->field("corporation_id,car_item_id")->find();
        $corporation_id = $rent_info["corporation_id"];
        if($corporation_id > 0){
            $corporation_info = M("corporation")->where(["id"=>$corporation_id])->field("parent_id")->find();
            if($corporation_info["parent_id"] > 0){
                $corporation_info_parent = M("corporation")->where(["id"=>$corporation_info["parent_id"]])->field("corporation_brand_id")->find();
                if($corporation_info_parent["corporation_brand_id"] == "100000"){
                    $car_item_verify_info = M("car_item_verify")->where(["car_item_id"=>$rent_info["car_item_id"]])->field("plate_no")->find();
                    $plate_no = $car_item_verify_info["plate_no"];
                    if(!empty($plate_no)){
                        $count = M("baojia_cloud.car")->where(["plate_no"=>$plate_no])->count();
                        if($count > 0){
                            M("baojia_cloud.car")->where(["plate_no"=>$plate_no])->delete();
                        }
                    }

                }
            }
        }

        \Think\Log::write("mebikeRecycleHandle:all|".$corporation_id."|".$corporation_info["parent_id"]."|".$corporation_info_parent["corporation_brand_id"]."|".$plate_no."|".$count, 'INFO');
    }

    //记录历史记录
    public function toHistory($car_item_id,$plate_no,$car_status=0,$user_id){

        $data = [];
        $data['car_item_id']       = $car_item_id;// int(10) NOT NULL DEFAULT '0',
        $data['plate_no']          = $plate_no;// varchar(20) NOT NULL DEFAULT '',
        $data['car_status']        = $car_status;// tinyint(4) NOT NULL DEFAULT '0' COMMENT '0=回收1=还原',
        $data['operation_user_id'] = $user_id;// int(10) NOT NULL DEFAULT '0',
        $data['update_time']       = time();// int(10) NOT NULL DEFAULT '0',

        $id = M("car_item_history")->add($data);
        \Think\Log::write("car_item_history:".M()->getLastSql(), 'INFO');

        return $id;
    }


}
