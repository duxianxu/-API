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
class BatteryController extends BController {

    
	private $baojia_mebike= 'mysql://apitest-baojia:TKQqB5Gwachds8dv@10.1.11.110:3306/baojia_mebike#utf8';

	public   function   aaaaa(){
	    phpinfo();
    }

     /****
     *查询电池是否在库里
     * uid   用户ID
     * battery_number  电池编号
     * type           1新电池入库检测   2 库管端电池领取检测  3 库管端电池归还检测 4 换电电池检测
     ***/
     public  function  batterySelect($uid=2693568,$battery_number='BD01000100030',$type=4){
        $user_arr = $this->userIinfo($uid);
        if($user_arr){
            $vbNumber = $this->verifyBatteryNumber($battery_number);
            if($vbNumber == 0){
                $this->ajaxReturn(["code" => 0, "message" => "电池编码格式不正确"], 'json');
            }else if($vbNumber == -1){
                $this->ajaxReturn(["code" => 0, "message" => "电池编码是伪造的"], 'json');
            }else if($vbNumber == -2 && $type != 4){
                $this->ajaxReturn(["code" => 0, "message" => "临时码不可以入库"], 'json');
            }
            if($type == 1){
                $bMap['battery_number'] = $battery_number;
                $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->field("id,battery_number")->where($bMap)->find();
				
                if($battery_arr){
                    $this->ajaxReturn(["code" => 0, "message" => "该新电池已经入库了"], 'json');
                }else{
                    $this->ajaxReturn(["code" => 1, "message" => "该新电池可以入库"], 'json');
                }
            }elseif ($type == 2){
                $bMap['battery_number'] = $battery_number;
                $bMap['battery_type']   = 0;
                $bMap['battery_status'] = array('in',array(1));
                $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->field("id,battery_number")->where($bMap)->find();
                if($battery_arr){
                    $this->ajaxReturn(["code" => 1, "message" => "该电池可以领取"], 'json');
                }else{
                    $this->ajaxReturn(["code" => 0, "message" => "该电池还没有入库"], 'json');
                }
            }else if($type == 3){
                $bMap['battery_number'] = $battery_number;
                $bMap['battery_type']   = 0;
                $bMap['battery_status'] = array('in',array(2,3));
                $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->field("id,battery_number")->where($bMap)->find();
                if($battery_arr){
                    $this->ajaxReturn(["code" => 1, "message" => "该电池可以归还"], 'json');
                }else{
                    $this->ajaxReturn(["code" => 0, "message" => "该电池不可归还或还没有入库"], 'json');
                }
            }else if($type == 4){
                $bMap['battery_number'] = $battery_number;
                $bMap['battery_status'] = 2;
                $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->field("id,battery_number")->where($bMap)->find();
                if($battery_arr){
                    $this->ajaxReturn(["code" => 1, "message" => "该电池可以换电"], 'json');
                }else{
                    $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->field("id,battery_number")->where(['battery_number'=>$battery_number])->find();
					
                    if($battery_arr){
                        $this->ajaxReturn(["code" => 1, "message" => "该电池可以换电"], 'json');
                    }else{
                        $this->ajaxReturn(["code" => 0, "message" => "该电池不可以换电"], 'json');
                    }
                }
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }


    /****
     **新电池入库单接口
     * uid  用户ID
     * batteryNumber  电池编号  多个用逗号隔开
     * type   状态   1新电池入库 2 库管（员工领用） 3 员工归还  4运维确认领用
     * borderId     订单ID   只有运维人员确认领用和归还
     **/
    public  function   batteryOrder($uid=2683922,$batteryNumber='',$type=4,$borderId=203){
        $user_arr = $this->userIinfo($uid);
        $model = M('cmf_battery_order',"",$this->baojia_mebike);
        if($user_arr){
            if($batteryNumber){
                $batteryNumber = explode(',',$batteryNumber);
                //检测是不是合格电池编码
                foreach($batteryNumber as $bk=>$bv){
                    $vbNumber = $this->verifyBatteryNumber($bv);
                    if($vbNumber != 1){
                        unset($batteryNumber[$bk]);
                    }
                }
                if($type == 1){
                    //判断是否是库管
                    if($user_arr['role'] != 4){
                        $this->ajaxReturn(["code" => 0, "message" => "该用户没有权限"], 'json');
                    }
                    $exist_battery = [];
                    $batteryId     = [];
                    foreach ($batteryNumber as $val){
                        $bMap['battery_number'] = $val;
//                        $bMap['battery_status'] = 1;
                        $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->where($bMap)->getField('battery_number');

                        if($battery_arr){
                            $exist_battery[] = $battery_arr;
                        }else{
                            $data['battery_number'] = $val;
                            $data['uid'] = $user_arr['user_id'];
                            $data['wsid'] = $user_arr['station_id'];
                            $data['battery_holders'] = $user_arr['user_name'];
                            $data['battery_holders_role'] = $user_arr['role_type'];
                            $data['battery_status'] = 1;
                            $data['sweep_time'] = time();
                            $batteryId[] = M('cmf_battery',"",$this->baojia_mebike)->add($data);
                        }
                    }
                    if($batteryId){
                        //新电池入库订单
                        $oData['order_number'] = date("YmdHis",time());
                        $oData['uid'] = $user_arr['user_id'];
                        $oData['wsId'] = $user_arr['station_id'];
                        $oData['dp_role'] = $user_arr['role_type'];
                        $oData['battery_num'] = count($batteryId);
                        $oData['determine_people'] = $user_arr['user_name'];
                        $oData['order_status'] = 1;
                        $oData['order_type'] = 1;
                        $oData['order_time'] = time();
                        $oData['confirm_time'] = time();
                        $result = $model->add($oData);
                        if($result){
                            foreach ($batteryId as $vv){
                                //电池关联表数据添加
                                $boData['borderId'] = $result;
                                $boData['batteryId'] = $vv;
                                $bor_arr = M('cmf_battery_order_relation', "", $this->baojia_mebike)->add($boData);
                            }
                            //查询当前添加订单信息
                            $orderInfo = $this->batteryOrderInfo($result, 1);
                            $this->ajaxReturn(["code" => 1, "message" => "新电池入库成功",'data'=>['exist_battery'=>$exist_battery,'orderInfo'=>$orderInfo]], 'json');
                        }else{
                            $this->ajaxReturn(["code" => 0, "message" => "新电池入库失败"], 'json');
                        }
                    }else{
                        $this->ajaxReturn(["code" => 404, "message" => "该电池都已存在",'data'=>['exist_battery'=>$exist_battery]], 'json');
                    }
                }else if($type == 2){
                    //判断是否是库管
                    if($user_arr['role'] != 4){
                        $this->ajaxReturn(["code" => 0, "message" => "该用户没有权限"], 'json');
                    }
                    $batteryId     = [];
                    foreach ($batteryNumber as $val){
                        $bMap['battery_number'] = $val;
                        $bMap['battery_status'] = array('in',array(1));
                        $bMap['battery_type']   = 0;
                        $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->where($bMap)->getField('id');
                        if($battery_arr){
                            $data['wsid'] = $user_arr['station_id'];
                            $data['rid']  = $user_arr['user_id'];
                            $data['battery_holders'] = $user_arr['user_name'];
                            $data['battery_holders_role'] = $user_arr['role_type'];
                            $data['battery_type'] = 1;
                            M('cmf_battery',"",$this->baojia_mebike)->where(['id'=>$battery_arr])->save($data);
                            $batteryId[] = $battery_arr;
                        }
                    }
                    if($batteryId){
                        //电池领用订单
                        $oData['order_number'] = date("YmdHis",time());
                        $oData['uid'] = $user_arr['user_id'];
                        $oData['wsId'] = $user_arr['station_id'];
                        $oData['dp_role'] = $user_arr['role_type'];
                        $oData['battery_num'] = count($batteryId);
                        $oData['determine_people'] = $user_arr['user_name'];
                        $oData['order_status'] = 3;
                        $oData['order_type'] = 0;
                        $oData['order_time'] = time();
                        $oData['confirm_time'] = time();
                        $result = $model->add($oData);
                        if($result){
                            foreach ($batteryId as $vv){
                                //电池关联表数据添加
                                $boData['borderId'] = $result;
                                $boData['batteryId'] = $vv;
                                M('cmf_battery_order_relation', "", $this->baojia_mebike)->add($boData);
                            }
                            //生成二维码
                            $qrcode_url = $this->qrcode_url($result);
                            $model->where(['id'=>$result])->setField('qrcode_url',$qrcode_url);
                            //查询当前添加订单信息
                            $orderInfo = $this->batteryOrderInfo($result, 2);
                            $this->ajaxReturn(["code" => 1, "message" => "电池领用成功",'data'=>['exist_battery'=>[],'orderInfo'=>$orderInfo]], 'json');
                        }else{
                            $this->ajaxReturn(["code" => 0, "message" => "电池领用失败"], 'json');
                        }
                    }else{
                        $this->ajaxReturn(["code" => 0, "message" => "该电池不存在"], 'json');
                    }
                }else if($type == 3){
                    //判断是否是库管
                    if($user_arr['role'] != 4){
                        $this->ajaxReturn(["code" => 0, "message" => "该用户没有权限"], 'json');
                    }
                    $bMap['battery_number'] = array('in',$batteryNumber);
                    $bMap['battery_status'] = array('in',array(2,3));
                    $bMap['battery_type']   = 0;
                    $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->field('id,battery_number')->where($bMap)->select();
                    $batteryId = array_column($battery_arr,'id');
                    if($batteryId){
                        //电池归还订单
                        $oData['order_number'] = date("YmdHis",time());
                        $oData['uid'] = $user_arr['user_id'];
                        $oData['wsId'] = $user_arr['station_id'];
                        $oData['dp_role'] = $user_arr['role_type'];
                        $oData['battery_num'] = count($batteryId);
                        $oData['determine_people'] = $user_arr['user_name'];
                        $oData['order_status'] = 4;
                        $oData['order_type'] = 0;
                        $oData['order_time'] = time();
                        $oData['confirm_time'] = time();
                        $result = $model->add($oData);
                        if($result){
                            foreach ($batteryId as $vv){
                                //更该电池时时状态
                                $data['wsid'] = $user_arr['station_id'];
                                $data['rid']  = $user_arr['user_id'];
                                $data['battery_holders'] = $user_arr['user_name'];
                                $data['battery_holders_role'] = $user_arr['role_type'];
                                $data['battery_type'] = 1;
                                M('cmf_battery',"",$this->baojia_mebike)->where(['id'=>$vv])->save($data);
                                //电池关联表数据添加
                                $boData['borderId'] = $result;
                                $boData['batteryId'] = $vv;
                                M('cmf_battery_order_relation', "", $this->baojia_mebike)->add($boData);
                            }
                            //生成二维码
                            $qrcode_url = $this->qrcode_url($result);
                            $model->where(['id'=>$result])->setField('qrcode_url',$qrcode_url);
                            //查询当前添加订单信息
                            $orderInfo = $this->batteryOrderInfo($result, 3);
                            $this->ajaxReturn(["code" => 1, "message" => "电池归还成功",'data'=>['exist_battery'=>[],'orderInfo'=>$orderInfo]], 'json');
                        }else{
                            $this->ajaxReturn(["code" => 0, "message" => "电池归还失败"], 'json');
                        }
                    }else{
                        $this->ajaxReturn(["code" => 0, "message" => "归还电池不存在"], 'json');
                    }
                }else if($type == 4){
                    if(!empty($borderId)) {
                        $order_arr = $model->field('id,order_status')->where(['id' => $borderId,'order_type'=>0])->find();
                        if($order_arr){
                            if($order_arr['order_status'] == 3 && $order_arr['order_type'] == 1){
                                $this->ajaxReturn(["code" => 0, "message" => "该电池已领取过"], 'json');
                            }
                            $bMap['bo.borderId'] = $order_arr['id'];
                            $bMap['o.battery_number'] = array('in',$batteryNumber);
                            $bMap['o.battery_status'] = 1;
                            $bMap['o.battery_type']   = 1;
                            $battery_arr = M('cmf_battery_order_relation',"",$this->baojia_mebike)->alias('bo')
                                ->join('baojia_mebike.cmf_battery o on o.id=bo.batteryId','left')
                                ->field('o.id,o.battery_number')
                                ->where($bMap)->select();
                            if($battery_arr){
                                $batteryId = array_column($battery_arr,'id');
                                $oData['order_status'] = 3;
                                $oData['order_type']   = 1;
                                $oData['uid'] = $uid;
                                $oData['wsId'] = $user_arr['station_id'];
                                $oData['leader'] = $user_arr['user_name'];
                                $oData['role']   = $user_arr['role_type'];
                                $oData['battery_num'] = count($batteryId);
                                $oData['confirm_time'] = time();
                                $ressult = $model->where(['id'=>$order_arr['id']])->save($oData);
                                if($ressult){
                                    //更新电池状态
                                    $cbMap['id'] = array('in',$batteryId);
                                    M('cmf_battery', "", $this->baojia_mebike)->where($cbMap)->setField(['battery_status' => 2,'battery_type'=>0,'battery_holders'=>$user_arr['user_name'],'battery_holders_role'=>$user_arr['role_type']]);
                                    //查询当前添加订单信息
                                    $orderInfo = $this->batteryOrderInfo($borderId, 4);
                                    $this->ajaxReturn(["code" => 1, "message" => "领取成功", 'data' => $orderInfo], 'json');
                                }else{
                                    $this->ajaxReturn(["code" => 0, "message" => "领取失败"], 'json');
                                }
                            }else{
                                $this->ajaxReturn(["code" => 0, "message" => "领取电池不存在或电池不属于该订单"], 'json');
                            }
                        }else{
                            $this->ajaxReturn(["code" => 0, "message" => "该订单不存在"], 'json');
                        }
                    }else{
                        $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
                    }
                }else if($type == 5){
                    if(!empty($borderId)) {
                        $order_arr = $model->field('id,order_status')->where(['id' => $borderId,'order_type'=>0])->find();
                        if($order_arr){
                            if($order_arr['order_status'] == 4 && $order_arr['order_type'] == 1){
                                $this->ajaxReturn(["code" => 0, "message" => "该电池已领归还了"], 'json');
                            }
                            $bMap['bo.borderId'] = $order_arr['id'];
                            $bMap['o.battery_number'] = array('in',$batteryNumber);
                            $bMap['o.battery_status'] = array('in',array(2,3));
                            $bMap['o.battery_type']   = 1;
                            $battery_arr = M('cmf_battery_order_relation',"",$this->baojia_mebike)->alias('bo')
                                ->join('baojia_mebike.cmf_battery o on o.id=bo.batteryId','left')
                                ->field('o.id,o.battery_number')
                                ->where($bMap)->select();
                            if($battery_arr){
                                $batteryId = array_column($battery_arr,'id');
                                $oData['order_status'] = 4;
                                $oData['order_type']   = 1;
                                $oData['uid'] = $uid;
                                $oData['wsId'] = $user_arr['station_id'];
                                $oData['returnees'] = $user_arr['user_name'];
                                $oData['role']   = $user_arr['role_type'];
                                $oData['battery_num'] = count($batteryId);
                                $oData['confirm_time'] = time();
                                $ressult = $model->where(['id'=>$order_arr['id']])->save($oData);
                                if($ressult){
                                    //更新电池状态
                                    $cbMap['id'] = array('in',$batteryId);
                                    M('cmf_battery', "", $this->baojia_mebike)->where($cbMap)->setField(['battery_status' => 1,'battery_type'=>0,'battery_holders'=>$user_arr['user_name'],'battery_holders_role'=>$user_arr['role_type']]);
                                    //查询当前添加订单信息
                                    $orderInfo = $this->batteryOrderInfo($borderId, 5);
                                    $this->ajaxReturn(["code" => 1, "message" => "归还成功", 'data' => $orderInfo], 'json');
                                }else{
                                    $this->ajaxReturn(["code" => 0, "message" => "归还失败"], 'json');
                                }
                            }else{
                                $this->ajaxReturn(["code" => 0, "message" => "归还电池不存在或电池不属于该订单"], 'json');
                            }
                        }else{
                            $this->ajaxReturn(["code" => 0, "message" => "该订单不存在"], 'json');
                        }
                    }else{
                        $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
                    }

                }

            }else{
                $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }
    /****
      *新电池入库
     * uid   用户ID
     * battery_number  电池编号
     ***/
    public  function   newBattery_add($uid=2875213,$battery_number='12347777'){
          $user_arr = $this->userIinfo($uid);
          if($user_arr){
              //判断是否是库管
              if($user_arr['role'] != 4){
                  $this->ajaxReturn(["code" => 0, "message" => "该用户没有权限"], 'json');
              }
              if($battery_number){
                  $bmap['battery_number'] = $battery_number;
                  $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->field("id,battery_number")->where($bmap)->find();

                  if($battery_arr){
                      $this->ajaxReturn(["code" => 0, "message" => "该电池已入库"], 'json');
                  }
                  $data['battery_number'] = $battery_number;
                  $data['uid'] = $user_arr['user_id'];
                  $data['wsid'] = $user_arr['station_id'];
                  $data['battery_holders'] = $user_arr['user_name'];
                  $data['battery_holders_role'] = $user_arr['role_type'];
                  $data['battery_status'] = 0;
                  $data['sweep_time'] = time();
                  $res = M('cmf_battery',"",$this->baojia_mebike)->add($data);
                  if($res){
                      $map['uid'] = $uid;
                      $map['wsid'] = $user_arr['station_id'];
                      $map['battery_status'] = 0;
                      $result = M('cmf_battery',"",$this->baojia_mebike)->field("id,battery_number")->where($map)->select();
                      foreach ($result  as  $key=>&$val){
                          $val['number'] = $key+1;
                          $val['battery_number'] = '编号'.$val['battery_number'];
                      }
                      $this->ajaxReturn(["code" => 1, "message" => "数据接收成","data"=>$result], 'json');
                  }else{
                      $this->ajaxReturn(["code" => 0, "message" => "入库失败"], 'json');
                  }
              }else{
                  $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
              }
          }else{
              $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
          }
    }

    /****
      *电池新电池入库领用归还
      * uid   用户ID
      * battery_number  电池编号
      * type        状态  1新电池入库 2库管端领用 3归还  4运维确认领用
      *borderId     订单ID   只有运维人员确认领用和归还
     **/
    public   function  batteryReceiveReturn($uid=2875213,$battery_number='12352222',$type=1,$borderId=0){
        $user_arr = $this->userIinfo($uid);
        if($user_arr){
			file_put_contents($_SERVER['DOCUMENT_ROOT']."电池口参数.txt",json_encode($_POST).PHP_EOL, FILE_APPEND);
            if($battery_number && $type){
                if($type==1){
                    $this->newBattery_add($uid,$battery_number);die;
                }else if($type==2){
                    //判断是否是库管
                    if($user_arr['role'] != 4){
                        $this->ajaxReturn(["code" => 0, "message" => "该用户没有权限"], 'json');
                    }
                    $bmap['battery_status'] = 1;
                    $battery_status = 1;
                    $battery_type = 1;
                }else if($type==3){
                    $bmap['battery_status'] = 2;
                    $battery_type = 1;
                }else if($type==4){
                    $bmap['battery_status'] = 1;
                    $battery_status = 1;
                    $battery_type = 2;
                }else{
                    $bmap['battery_status'] = '无';
                    $battery_type = 0;
                }
                $bmap['battery_number'] = $battery_number;
                $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->field("id,battery_number,battery_type")->where($bmap)->find();
                if($battery_arr){
                    if($type==2){
                        if ($battery_arr['battery_type'] == 1) {
                            $this->ajaxReturn(["code" => 0, "message" => "该电池库管端领用中"], 'json');
                        }else{
                            $map['id'] = $battery_arr['id'];
                            $res = M('cmf_battery', "", $this->baojia_mebike)->where($map)->setField(['rid' => $uid, 'battery_type' => $battery_type]);
                            if ($res) {
                                $result = $this->batteryLists($uid,$battery_status,$battery_type);
                                $this->ajaxReturn(["code" => 1, "message" => "领取成功", "data" => $result,"borderId"=>""], 'json');
                            } else {
                                $this->ajaxReturn(["code" => 0, "message" => "领取失败"], 'json');
                            }
                        }
                    }else if($type==4){
                        if(empty($borderId)){
                            $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
                        }
                        if ($battery_arr['battery_type'] == 2) {
                            $this->ajaxReturn(["code" => 0, "message" => "该电池运维端领用中"], 'json');
                        }
                        $map['id'] = $battery_arr['id'];
                        $res = M('cmf_battery', "", $this->baojia_mebike)->where($map)->setField(['rid' => $uid,'battery_type'=>$battery_type]);
                        if ($res) {
                            $result = $this->batteryLists($uid,$battery_status,$battery_type);
                            $this->ajaxReturn(["code" => 1, "message" => "领取成功", "data" => $result,"borderId"=>$borderId], 'json');
                        } else {
                            $this->ajaxReturn(["code" => 0, "message" => "领取失败"], 'json');
                        }
                    }
                }else{
                    $this->ajaxReturn(["code" => 0, "message" => "该电池还没有入库"], 'json');
                }
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

    /***
      *电池入库领取归还扫码列表
     */
    private   function  batteryLists($uid,$battery_status,$battery_type){
        $rMap['rid'] = $uid;
        $rMap['battery_status'] = $battery_status;
        $rMap['battery_type'] = $battery_type;
        $result = M('cmf_battery', "", $this->baojia_mebike)->field("id,battery_number")->where($rMap)->select();
        foreach ($result as $key => &$val) {
            $val['number'] = $key + 1;
            $val['battery_number'] = '编号' . $val['battery_number'];
        }
        return  $result;
    }


    /****
      *  领用归还（取消操作）
      *  uid  用户ID
      *  订单ID
     ***/
    public   function   batteryCancel($uid=2700910,$borderId=1){
        $user_arr = $this->userIinfo($uid);
        if($user_arr){
            //判断是否是库管
            if($user_arr['role'] != 4){
                $this->ajaxReturn(["code" => 0, "message" => "该用户没有权限"], 'json');
            }
            $model = M('cmf_battery_order',"",$this->baojia_mebike);
            $oMap['o.uid'] = $uid;
            $oMap['o.id'] = $borderId;
            $oMap['o.order_status'] = array('in',array(0,1,3,4));
            $oMap['o.order_type'] = 0;
            $order_arr = $model->alias('o')->field('o.id,bo.batteryId')
                ->join('baojia_mebike.cmf_battery_order_relation bo on o.id=bo.borderId','right')
                ->where($oMap)->select();
            if($order_arr){
                $batteryId = array_column($order_arr, 'batteryid');
                $bMap['id'] = array('in',$batteryId);
                $battery_arr = M('cmf_battery',"",$this->baojia_mebike)->where($bMap)->setField('battery_type',0);
                if($battery_arr){
                    $map['id'] = $borderId;
                    $res = $model->where($map)->setField('order_status',2);
                    if($res){
                        $this->ajaxReturn(["code" => 1, "message" => "取消成功"], 'json');
                    }else{
                        $this->ajaxReturn(["code" => 0, "message" => "取消失败"], 'json');
                    }
                }else{
                    $this->ajaxReturn(["code" => 0, "message" => "取消失败"], 'json');
                }
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "该电池领用单不存在"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

    /****
      *移除新电池入库
     * uid   用户ID
     * batteryId  电池ID
     * type        状态   1新电池入库 2领用 3归还
     **/
    public  function newBatteryDel($uid=2875213,$batteryId=24,$type=1){
        $user_arr = $this->userIinfo($uid);
        if($user_arr){
            $map['id'] = $batteryId;
            $result = M('cmf_battery',"",$this->baojia_mebike)->field('id,battery_status')->where($map)->find();

            if($result){
                if($type == 1 && $result['battery_status'] == 0){
                    $res = M('cmf_battery',"",$this->baojia_mebike)->where($map)->delete();
                }else if($type == 2 && $result['battery_status'] == 1){
                    $res = M('cmf_battery',"",$this->baojia_mebike)->where($map)->setField('battery_type',0);
                }else if($type == 3 && $result['battery_status'] == 2){
                    $res = M('cmf_battery',"",$this->baojia_mebike)->where($map)->setField('battery_type',0);
                }else{
                    $res = false;
                }
                if($res){
                    $this->ajaxReturn(["code" => 1, "message" => "移除成功"], 'json');
                }else{
                    $this->ajaxReturn(["code" => 0, "message" => "移除失败"], 'json');
                }
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "该电池记录不存在"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

    //查询用户信息
    public  function  userIinfo($uid=2875213){
       /* $model = M('baojia_mebike.repair_member');
        $map['user_id'] = $uid;
        $user = $model->field('user_id,user_name')->where($map)->find();
        if($user){
            $user['role_type'] = "库管";
        }
        return $user;*/
       $model = M('baojia_mebike.cmf_user');
       $map['user_id'] = $uid;
       $user = $model->field('user_id,user_name,role_type,station_id,city_id')->where($map)->find();
	  
       if($user){
           $user['role'] = $user['role_type'];
           if($user['role_type'] == 1){
               $user['role_type'] = '运维';
           }else if($user['role_type'] == 2){
               $user['role_type'] = '调度';
           }else if($user['role_type'] == 3){
               $user['role_type'] = '整备';
           }else if($user['role_type'] == 4){
               $user['role_type'] = '库管';
           }
       }
       return $user;
    }

    //查看新入库电池
    public   function  newBatteryList($uid=2875213){
        $user_arr = $this->userIinfo($uid);
        if($user_arr){
            $map['uid'] = $uid;
            $map['wsid'] = $user_arr['station_id'];
            $map['battery_status'] = 0;
            $res = M('cmf_battery',"",$this->baojia_mebike)->field("id,battery_number")->where($map)->select();
            $result = [];
            if($res){
                foreach ($res  as  $key=>&$val){
                    $val['number'] = $key+1;
                    $val['battery_number'] = '编号'.$val['battery_number'];
                }
                $result['battery_list'] = $res;
                $result['user_name'] = $user_arr['user_name'];
                $result['count'] = M('cmf_battery',"",$this->baojia_mebike)->where($map)->count();
            }
            $this->ajaxReturn(["code" => 1, "message" => "数据接收成","data"=>$result], 'json');
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

    /****
     **新电池入库单接口
     * uid  用户ID
     *batteryId  电池ID  多个用逗号隔开
     * type   状态   1新电池入库 2 库管（员工领用） 3 员工归还  4运维确认领用
     * borderId     订单ID   只有运维人员确认领用和归还
     **/
     /*public  function   batteryOrder($uid=2875213,$batteryId='10,11',$type=1,$borderId=0){
         $user_arr = $this->userIinfo($uid);
         $model = M('cmf_battery_order',"",$this->baojia_mebike);
         if($user_arr){
             if($batteryId){
                 $batteryId = explode(',',$batteryId);
                 $map['id'] = array('in',$batteryId);
                 if($type == 1){
                     //判断是否是库管
                     if($user_arr['role'] != 4){
                         $this->ajaxReturn(["code" => 0, "message" => "该用户没有权限"], 'json');
                     }
                     $map['battery_status'] = 0;
                     $order_status = 1;
                 }else if($type == 2){
                     //判断是否是库管
                     if($user_arr['role'] != 4){
                         $this->ajaxReturn(["code" => 0, "message" => "该用户没有权限"], 'json');
                     }
                     $map['battery_status'] = 1;
                     $map['battery_type'] = 1;
                     $order_status = 0;
                 }else if($type == 4){
                     $map['battery_status'] = 1;
                     $map['battery_type'] = 2;
                     $order_status = 1;
                 }else{
                     $map['battery_status'] = '无';
                     $order_status = 0;
                 }
                 $res = M('cmf_battery',"",$this->baojia_mebike)->field("id")->where($map)->select();
                 if($res){
                     if($type == 2) {
//                         $oMap['o.uid'] = $uid;
//                         $oMap['o.wsid'] = $user_arr['station_id'];
                         $oMap['bo.batteryId'] = array('in', $batteryId);
                         $oMap['o.order_status'] = 0;
                         $order_arr = $model->alias('o')->field('o.id,o.wsId,bo.batteryId')
                             ->join('baojia_mebike.cmf_battery_order_relation bo on o.id=bo.borderId', 'right')
                             ->where($oMap)->find();
                         if ($order_arr) {
                             $this->ajaxReturn(["code" => 0, "message" => "该订单已领用"], 'json');
                         }
                     }else if($type == 4){
                         if(!empty($borderId)){
                             $oData['order_status'] = 3;
                             $oData['uid'] = $uid;
                             $oData['wsId'] = $user_arr['station_id'];
                             $oData['leader'] = $user_arr['user_name'];
                             $oData['role'] = $user_arr['role_type'];
                             $oData['battery_num'] = count($res);
                             $oData['confirm_time'] = time();
                             $ressult = $model->where(['id'=>$borderId])->save($oData);
                             if($ressult){
                                 //更新电池状态
                                 M('cmf_battery', "", $this->baojia_mebike)->where($map)->setField(['battery_status' => 2,'battery_holders'=>$user_arr['user_name'],'battery_holders_role'=>$user_arr['role_type']]);
                                 //查询当前添加订单信息
                                 $orderInfo = $this->batteryOrderInfo($borderId, 4);
                                 $this->ajaxReturn(["code" => 1, "message" => "领取成功", 'data' => $orderInfo], 'json');
                             }else{
                                 $this->ajaxReturn(["code" => 0, "message" => "领取失败"], 'json');
                             }
                         }else{
                             $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
                         }
                     }
                     $data['order_number'] = date("YmdHis",time());
                     $data['uid'] = $user_arr['user_id'];
                     $data['wsId'] = $user_arr['station_id'];
                     $data['dp_role'] = $user_arr['role_type'];
                     $data['battery_num'] = count($res);
                     $data['determine_people'] = $user_arr['user_name'];
                     $data['order_status'] = $order_status;
                     $data['order_time'] = time();
                     $data['confirm_time'] = time();
                     $ressult = $model->add($data);
                     if($ressult){
                         if($type == 1) {
                             //更新电池状态
                             $battery_arr = M('cmf_battery', "", $this->baojia_mebike)->where($map)->setField(['battery_status' => 1]);
                             if ($battery_arr) {
                                 foreach ($res as $val) {
                                     if (!empty($val['id'])) {
                                         //电池关联表数据添加
                                         $rMap['borderId'] = $ressult;
                                         $rMap['batteryId'] = $val['id'];
                                         M('cmf_battery_order_relation', "", $this->baojia_mebike)->add($rMap);
                                     }
                                 }
                                 //查询当前添加订单信息
                                 $orderInfo = $this->batteryOrderInfo($ressult, 1);
                                 $this->ajaxReturn(["code" => 1, "message" => "入库成功", 'data' => $orderInfo], 'json');
                             }
                         }else if($type == 2){
                             foreach ($res as $val) {
                                 if (!empty($val['id'])) {
                                     //电池关联表数据添加
                                     $rMap['borderId'] = $ressult;
                                     $rMap['batteryId'] = $val['id'];
                                     M('cmf_battery_order_relation', "", $this->baojia_mebike)->add($rMap);
                                 }
                             }
                             //查询当前添加订单信息
                             $orderInfo = $this->batteryOrderInfo($ressult, 2);
                             $this->ajaxReturn(["code" => 1, "message" => "入库成功", 'data' => $orderInfo], 'json');
                         }
                     }else{
                         $this->ajaxReturn(["code" => 0, "message" => "入库失败"], 'json');
                     }
                 }else{
                     $this->ajaxReturn(["code" => 0, "message" => "电池不存在"], 'json');
                 }
             }else{
                 $this->ajaxReturn(["code" => 0, "message" => "参数错误"], 'json');
             }
         }else{
             $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
         }
     }*/
	 
	 /***
        *订单扫码确认和电池流转记录详
        * uid   用户ID
        * borderId    订单ID
      **/
     public   function   orderScanCode($uid=2875215,$borderId=3){
         $user_arr = $this->userIinfo($uid);
         if($user_arr){
             $model = M('cmf_battery_order',"",$this->baojia_mebike);
             $model2 = M('cmf_battery_order_relation',"",$this->baojia_mebike);
             $map['id'] = $borderId;
             $result = $model->field('id,order_status')->where($map)->find();
             if($result){
                  $omap['cor.borderId'] = $result['id'];
                  $res = $model2->alias('cor')
                      ->field('bo.id,bo.battery_number')
                      ->join("baojia_mebike.cmf_battery bo on cor.batteryId=bo.id",'left')
                      ->where($omap)
                      ->select();
                  $battery = [];
                  if($res){
                      $battery['batteryList'] = $res;
                      $battery['borderId']    = $result['id'];
					  $battery['order_status']  = $result['order_status'];
                      $battery['numberTitle'] = "编号";
					  if($result['order_status'] == 1){
                          $battery['title'] = "本次入库的电池";
                      }else if($result['order_status'] == 3){
                          $battery['title'] = "本次领取的电池";
                      }else{
                          $battery['title'] = "本次归还的电池";
                      }
                  }
                 $this->ajaxReturn(["code" => 1, "message" => "数据接收成功",'data'=>$battery], 'json');
             }else{
                 $this->ajaxReturn(["code" => 0, "message" => "该订单不存在"], 'json');
             }
         }else{
             $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
         }
     }

     /****
       *电池流转记录
      *  uid   用户ID
	  *  userName     用户名
      *  time  下单时间
      *  status      订单状态  1 电池入库单   3 电池领用单
      *  page     页码
      **/
     public   function  borderLists($uid=2683922,$userName='',$time=0,$status=0,$page=0){
		 
         $user_arr = $this->userIinfo($uid);
         $model = M('cmf_battery_order',"",$this->baojia_mebike);
         if($user_arr){
             //判断是否是库管
             if($user_arr['role'] == 4){
                $map['wsid'] = $user_arr['station_id'];
				if($userName){
                    $Umodel = M('cmf_user',"",$this->baojia_mebike);
                    $uMap['user_name'] = array('like','%'.$userName.'%');
                    $user = $Umodel->field('id')->where($uMap)->select();
                    $uid_arr = array_column($user,'id');
                    $map['uid'] = array('in',$uid_arr);
                }
             }else{
                 $map['uid'] = $user_arr['user_id'];
             }
             if($time){
                 $start_time = strtotime(date("Y-m-d",$time)." 0:0:0");
                 $end_time   = strtotime(date("Y-m-d",$time)." 23:59:59");
                 $map['order_time'] = array('between',array($start_time,$end_time));
             }
             if($status){
                $map['order_status'] =  $status;
             }else{
                $map['order_status'] =  array('in',array(1,3,4));
             }
			 $map['order_type'] = 1;
			 $count = $model->field('id')->where($map)->count();
             $page = $page?$page:1;
             $page_size = 5;
             $offset = $page_size * ($page - 1);
             $result = $model->field('id,order_status')->where($map)->order('order_time desc')->limit($offset,$page_size)->select();
			
             $res = [];
             if($result){
                foreach ($result as $val) {
                    if($val['order_status'] == 1){
                        $order_status  = 1;
                    }else if($val['order_status'] == 3){
                        $order_status = 4;
                    }else if($val['order_status'] == 4){
						$order_status = 5;
					}
					$OrderInfo = $this->batteryOrderInfo($val['id'],$order_status);
                    if(!empty($order_status) && $OrderInfo){
                        $res[] = $OrderInfo;
                    }
                }
             }
			 if(!$res){
                 $page = ceil($count/5);
             }
             $this->ajaxReturn(["code" => 1, "message" => "数据接收成功",'data'=>$res,'page'=>$page], 'json');
         }else{
             $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
         }
     }
	 
	 /***
     *电池订单详情
     * uid        用户ID
     *borderId    电池订单ID
     *type        订单类型   1 入库单  2 领取单  3 归还单  4运维人员确认领取
     **/
    public   function  batteryOrderDetails($uid=2875213,$borderId = 21,$type=2){
        $user_arr = $this->userIinfo($uid);
        if($user_arr){
            $result = $this->batteryOrderInfo($borderId,$type);
            $this->ajaxReturn(["code" => 1, "message" => "数据接收成功",'data'=>$result], 'json');
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

     /***
       *电池订单详情
       *borderId    电池订单ID
      *type         订单类型   1 入库单  2 领取单  3 归还单  4运维人员确认领取 5运维人员确认归还
      **/
     public   function  batteryOrderInfo($borderId = 3,$type=1){
        $map['id'] = $borderId;
        if($type == 1) {
            $map['order_status'] = 1;
        }else if($type == 2){
            $map['order_status'] = 3;
            $map['order_type']   = 0;
        }else if($type == 3){
            $map['order_status'] = 4;
            $map['order_type']   = 0;
        }else if($type == 4){
            $map['order_status'] = 3;
            $map['order_type']   = 1;
        }else if($type == 5){
            $map['order_status'] = 4;
            $map['order_type']   = 1;
        }else{
            $map['order_status'] = "无";
        }
        $res = M('cmf_battery_order',"",$this->baojia_mebike)->field('id,order_number,order_time,wsId,battery_num,determine_people,leader,returnees,order_status,order_type,qrcode_url')->where($map)->find();

        $result = [];
        if($res){
            //查询站点名称
            $sMap['id'] = $res['wsid'];
            $station = M('cmf_work_station',"",$this->baojia_mebike)->getField('station_name');
            $result['order_number_title'] = "单号：";
            $result['borderId'] = $res['id'];
            $result['order_number'] = $res['order_number'];
            $result['order_status'] = $res['order_status'];
            $result['qrcode_url']   = $res['qrcode_url']?'http://'.$_SERVER['HTTP_HOST'].$res['qrcode_url']:"";
            if($type == 1){
                $result['title'] = "电池入库单";
                $result['status'] = 1;
                $result['order_time_title'] = "入库时间：";
                $result['site_title'] = "入库站点：";
                $result['battery_num_title'] = "入库数量：";
                $result['user_title'] = "入库人：";
                $result['user_name'] = $res['determine_people'];
            }else if ($type == 2){
                $result['title'] = "电池领用单";
				if($res['order_status'] == 2){
					$result['status'] = 2;
				}else{
					$result['status'] = 0;
				}
                $result['order_time_title'] = "领取时间：";
                $result['site_title'] = "领取站点：";
                $result['battery_num_title'] = "领取数量：";
                $result['user_title'] = "确认人：";
                $result['user_name'] = '等待确认';
            }else if ($type == 4){
                $result['title'] = "电池领用单";
                if($res['order_status'] == 2){
					$result['status'] = 2;
				}else{
					$result['status'] = 1;
				}
                $result['order_time_title'] = "领取时间：";
                $result['site_title'] = "领取站点：";
                $result['battery_num_title'] = "领取数量：";
                $result['user_title'] = "领取人：";
                $result['user_name'] = $res['leader'];
            }else{
                $result['title'] = "电池归还单";
				if($res['order_status'] == 2){
					$result['status'] = 2;
				}else{
					if($res['order_type'] > 0){
						$result['status'] = 1;
					}else{
						$result['status'] = 0;
					}
				}
                $result['order_time_title'] = "归还时间：";
                $result['site_title'] = "归还站点：";
                $result['battery_num_title'] = "归还数量：";
                $result['user_title'] = "归还人：";
                $result['user_name'] = $res['returnees']?$res['returnees']:"等待确认";
            }
            $result['order_time'] = $res["order_time"]?date("Y-m-d H:i",$res["order_time"]):"";
            $result['site'] = $station?$station:"";
            $result['battery_num'] = $res['battery_num']."块";
        }
//         echo "<pre>";
//         print_r($result);
        return $result;
    }
	 
	 //工作站电池统计接口
    public  function  stationCount($uid=2693568){
        $user_arr = $this->userIinfo($uid);
        if($user_arr) {
            $model = M('cmf_battery',"",$this->baojia_mebike);
            $uModel = M('cmf_user',"",$this->baojia_mebike);
            //判断是否是库管
            if($user_arr['role'] == 4){
                $map['wsid'] = $user_arr['station_id'];
                $map['battery_status'] = array('in',array(1,2,3));
                $tBattery = $model->where($map)->count();
                $cMap['wsid'] = $user_arr['station_id'];
                $cMap['battery_status'] = 2;
                $cBattery = $model->where($cMap)->count();
                $kMap['wsid'] = $user_arr['station_id'];
                $kMap['battery_status'] = array('in',array(1,3));
                $kBattery = $model->where($kMap)->count();
                //查询工作站的人数
                $uCount = $uModel->where(['station_id'=>$user_arr['station_id'],'status'=>1,'user_status'=>1,'deleted'=>0])->count('id');
                $result['uCount']   = $uCount;
                $result['tBattery'] = $tBattery;
                $result['cBattery'] = $cBattery;
                $result['kBattery'] = $kBattery;
                $result['yd_number'] = 0;
                $result['wd_number'] = 0;
                $result['unit'] = '块';
            }else{
                $map['rid'] = $user_arr['user_id'];
                $map['battery_status'] = array('in',array(2,3));
                $battery = $model->field('electricity_status,count(id) num')->where($map)->group('electricity_status')->select();

                $result['uCount']   = 0;
                $result['tBattery'] = 0;
                $result['cBattery'] = 0;
                $result['kBattery'] = 0;
                $result['yd_number'] = $battery[0]['num']?$battery[0]['num']:0;
                $result['wd_number'] = $battery[1]['num']?$battery[1]['num']:0;
                $result['unit'] = '块';
            }
            $this->ajaxReturn(["code" => 1, "message" => "数据接收成功","data"=>$result], 'json');
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }
	
	//查询运维端我的电池记录接口
    public  function  selectBatteryList($uid=2693568,$page=0){
        $user_arr = $this->userIinfo($uid);
        if($user_arr) {
            $model = M('cmf_battery',"",$this->baojia_mebike);
            $map['rid'] = $user_arr['user_id'];
            $map['battery_status'] = array('in',array(2,3));
            $count = $model->where($map)->count();
            $page = $page?$page:1;
            $page_size = 15;
            $offset = $page_size * ($page - 1);
            $battery = $model->field('electricity_status,battery_number')->where($map)->limit($offset,$page_size)->select();
            $result = [];
            if($battery){
                $result['battery_list'] =  $battery;
                $result['total'] =  $count;
                $result['page']  =  $page;
            }
            $this->ajaxReturn(["code" => 1, "message" => "数据接收成功",'data'=>$result], 'json');
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }
	 
	 /****
     *换电操作步骤接口
     * uid     用户ID
     * operationId  操作记录ID
     *step    1=打开电池仓 2=（无电）确认下一步 3=检验电量 4=（有电）确认下一步 5=完成换电拍照页 6=设防页
     *battery_number   电池编号
     **/
    public   function  electricityStep($uid=2658265,$operationId=90306,$step=4,$battery_number='BD01000100980'){
        $user_arr = $this->userIinfo($uid);
        if($user_arr) {
            if ($operationId && $step) {
                $model = M('operation_logging');
                $model2 = M('cmf_battery','',$this->baojia_mebike);
                $model3 = M('baojia.rent_content','',$this->baojia_mebike);

                $map['id'] = $operationId;
                $ol_arr = $model->where($map)->find();
                if ($ol_arr) {
                    if ($step == 2) {
                        if (empty($battery_number)) {
                            $this->ajaxReturn(["code" => 0, "message" => "参数不完整"], 'json');
                        }
                        $vbNumber = $this->verifyBatteryNumber($battery_number);
                        if($vbNumber == 0){
                            $this->ajaxReturn(["code" => 0, "message" => "电池编码格式不正确"], 'json');
                        }else if($vbNumber == -1){
                            $this->ajaxReturn(["code" => 0, "message" => "电池编码是伪造的"], 'json');
                        }else if($vbNumber == -2){
                            $lsBattery = $model2->where(['battery_number'=>$battery_number,'battery_status'=>4])->getField('battery_number');
                            if($lsBattery){
                                $this->ajaxReturn(["code" => 0, "message" => "该临时码已经存在"], 'json');
                            }else{
                                //查询有点电池编号
                                $battery_number_rear = $model->where(['id' => $ol_arr['id'],'battery_number_rear'=>$battery_number])->getField('battery_number_before');
                                if($battery_number_rear){
                                    $this->ajaxReturn(["code" => 0, "message" => "两次扫描电池码一样"], 'json');
                                }
                                $data['battery_number'] = $battery_number;
                                $data['uid'] = $user_arr['user_id'];
                                $data['wsid'] = $user_arr['station_id'];
                                $data['battery_holders'] = $user_arr['user_name'];
                                $data['battery_holders_role'] = $user_arr['role_type'];
                                $data['battery_status'] = 4;
                                $data['sweep_time'] = time();
                                $batteryAdd = $model2->add($data);
                                if($batteryAdd){
                                    $model->where(['id' => $ol_arr['id']])->setField(['battery_number_before' => $battery_number, 'step' => 2]);
                                    $this->ajaxReturn(["code" => 1, "message" => "确认无电电池编号成功"], 'json');
                                }else{
                                    $this->ajaxReturn(["code" => 0, "message" => "确认无电电池编号失败"], 'json');
                                }
                            }
                        }else{
                            //查询无电电池是不是在车上
                           /* $cMap['id'] = $ol_arr['rent_content_id'];
                            $cMap['battery_number'] = $battery_number;
                            $car_arr = $model3->where($cMap)->getField('battery_number');
                            if($car_arr){

                            }else{
                                $this->ajaxReturn(["code" => 0, "message" => "该车旧电池不能取出换电"], 'json');
                            }*/
                            $zsBattery = $model2->where(['battery_number'=>$battery_number,'battery_status'=>4])->getField('battery_number');
                            if($zsBattery){
                                $res = $model->where(['id' => $ol_arr['id']])->setField(['battery_number_before' => $battery_number, 'step' => 2]);
                                if ($res) {
                                    $bdata['rid'] = $user_arr['user_id'];
                                    $bdata['wsid'] = $user_arr['station_id'];
                                    $bdata['battery_holders'] = $user_arr['user_name'];
                                    $bdata['battery_holders_role'] = $user_arr['role_type'];
                                    $bdata['battery_status']     = 3;
                                    $bdata['battery_type']       = 0;
                                    $bdata['electricity_status'] = 1;
                                    $model2->where(['battery_number'=>$battery_number])->save($bdata);
                                    $this->ajaxReturn(["code" => 1, "message" => "确认无电电池编号成功"], 'json');
                                } else {
                                    $this->ajaxReturn(["code" => 0, "message" => "确认无电电池编号失败"], 'json');
                                }
                            }else{
                                $this->ajaxReturn(["code" => 0, "message" => "该无电电池不能换电"], 'json');
                            }
                        }
                    }else if ($step == 4) {
                        if (empty($battery_number)) {
                            $this->ajaxReturn(["code" => 0, "message" => "参数不完整"], 'json');
                        }
                        $vbNumber = $this->verifyBatteryNumber($battery_number);
                        if($vbNumber == 0){
                            $this->ajaxReturn(["code" => 0, "message" => "电池编码格式不正确"], 'json');
                        }else if($vbNumber == -1){
                            $this->ajaxReturn(["code" => 0, "message" => "电池编码是伪造的"], 'json');
                        }else if($vbNumber == -2){
                            $this->ajaxReturn(["code" => 0, "message" => "临时码不能换电"], 'json');
                        }else{
                            //查询该电池码是否在其他车上（待写）
                            /*$cMap['battery_number'] = $battery_number;
                            $car_arr = $model3->where($cMap)->getField('battery_number');
                            if($car_arr){
                                $this->ajaxReturn(["code" => 0, "message" => "该新电池已经在车上不能换电"], 'json');
                            }*/
                            $zsBattery = $model2->where(['rid'=>$user_arr['user_id'],'battery_number'=>$battery_number,'battery_status'=>2])->getField('battery_number');
                            if($zsBattery){
                                //查询无电电池编号
                                $battery_number_before = $model->where(['id' => $ol_arr['id'],'battery_number_before'=>$battery_number])->getField('battery_number_before');
                                if($battery_number_before){
                                    $this->ajaxReturn(["code" => 0, "message" => "两次扫描电池码一样"], 'json');
                                }
                                $res = $model->where(['id' => $ol_arr['id']])->setField(['battery_number_rear' => $battery_number, 'step' => 4]);
                                if ($res) {
                                    //把新电池放在车上
                                   // $model3->where(['id'=>$ol_arr['rent_content_id']])->setField('battery_number',$battery_number);
                                    $model2->where(['battery_number'=>$battery_number,'battery_status'=>2])->setField('battery_status',4);
                                    $this->ajaxReturn(["code" => 1, "message" => "确认有电电池编号成功"], 'json');
                                } else {
                                    $this->ajaxReturn(["code" => 0, "message" => "确认有电电池编号失败"], 'json');
                                }
                            }else{
                                $this->ajaxReturn(["code" => 0, "message" => "该电池不是自己领取不能换电"], 'json');
                            }
                        }
                    }
                } else {
                    $this->ajaxReturn(["code" => 0, "message" => "该操作记录不存在"], 'json');
                }
            } else {
                $this->ajaxReturn(["code" => 0, "message" => "参数不完整"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }


    //查询换电未完成操作记录
   public   function   operatingSelect($uid=1598809){
        $user_arr = $this->userIinfo($uid);
        if($user_arr) {
			$gps  = D('Gps');
            $model = M('operation_logging','',$this->baojia_config);
            $model2 = M('cmf_report_record','',$this->baojia_mebike);
            $map['uid'] = $uid;
            $map['operate'] = 0;
            $result = $model->field('id,rent_content_id,step,plate_no,gis_lng,gis_lat')->where($map)->order('id desc')->limit(1)->select();
            if($result){

                $operating = [];
                $areaLogic= new \Api\Logic\Area();
                $address = $areaLogic->GetAmapAddress($result[0]['gis_lng'],$result[0]['gis_lat']);
				$bd_gis  = $gps->bd_encrypt($result[0]['gis_lat'],$result[0]['gis_lng']);
                $operating['id'] = $result[0]['id'];
                $operating['rent_content_id'] = $result[0]['rent_content_id'];
                $operating['plate_no'] = $result[0]['plate_no'];
                //查询有没有电池上报记录
                $rMap['operationid']    = $result[0]['id'];
                $report = $model2->field('id,status')->where($rMap)->order('report_time desc')->limit(1)->select();
                if($report && $result[0]['step'] < 3){
                    if($report[0]['status'] == 1){
                        $operating['finishStep']  = 8;
                    }else if($report[0]['status'] == 3){
                        $operating['finishStep']  = 9;
                    }else if($report[0]['status'] == 2){
                        $operating['finishStep']  = 11;
                    }else{
                        $operating['finishStep']  = 10;
                    }
                    $operating['reportId']    = $report[0]['id'];
                }else{
                    $operating['finishStep']  = $result[0]['step'];
                    $operating['reportId']    = 0;
                }
                $operating['address']  = $address;
                $operating['gis_lng']  = $result[0]['gis_lng'];
                $operating['gis_lat']  = $result[0]['gis_lat'];
				$operating['bis_lng']  = $bd_gis['lon'];
                $operating['bis_lat']  = $bd_gis['lat'];
                $operating['title']    = '未完成换电前往处理';
                $operating['anniu'][0]['status'] = 1;
                $operating['anniu'][0]['anTitle'] = '立即前往';
                $operating['anniu'][1]['status'] = 2;
                $operating['anniu'][1]['anTitle'] = '继续为该车换电';
                $this->ajaxReturn(["code" => 1, "message" => "数据接收成功","data"=>$operating], 'json');
            }else{
                $this->ajaxReturn(["code" => 2, "message" => "没有未完成换电记录"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

	 
	 //查询订单时时状态
     public  function  orderStatus($borderId=22){
         $map['id'] = $borderId;
         $res = M('cmf_battery_order',"",$this->baojia_mebike)->field('order_status,order_type,uid')->where($map)->find();
         if($res){
             $user = $this->userIinfo($res['uid']);
             $res['user_name'] = $user['user_name'];
             unset($res['uid']);
             $this->ajaxReturn(["code" => 1, "message" => "数据接收成功","data"=>$res], 'json');
         }else{
             $this->ajaxReturn(["code" => 0, "message" => "该订单不存在"], 'json');
         }
     }

     //库管端查看未完成的领用、归还单
    public   function   noSelectOrder($uid=2683922){
        $user_arr = $this->userIinfo($uid);
        if($user_arr) {
            //判断是否是库管
            if($user_arr['role'] != 4){
                $this->ajaxReturn(["code" => 0, "message" => "该用户没有权限"], 'json');
            }
            $map['order_status'] = array('in',array(3,4));
            $map['order_type']   = 0;
            $res = M('cmf_battery_order',"",$this->baojia_mebike)->field('id,order_status')->where($map)->find();
            if($res){
                if($res['order_status'] == 3){
                    $type = 2;
                }elseif ($res['order_status'] == 4){
                    $type = 3;
                }
                $result = $this->batteryOrderInfo($res['id'],$type);
                $this->ajaxReturn(["code" => 1, "message" => "数据接收成功",'data'=>$result], 'json');
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "没有未完成的订单"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

    /***
     *电池丢失上报
     * uid       用户ID
     * operationId        操作记录ID
     * reportId           电池丢失上报id   退回修改重新上报电池丢失传值
     * imgId             上报图片id多张用逗号隔开
     * rent_content_id   车辆ID
     * plate_no          车牌号
     * gis_lng           经度
     * gis_lat           纬度
     **/
    public  function  batteryLostReport($uid=1598809,$rent_content_id=314889,$plate_no='DD922205',$gis_lng=116.3904435221354,$gis_lat=39.9795849609375,$imgId='31,32,33,34,35',$operationId=0,$reportId=0){
        $areaLogic= new \Api\Logic\Area();
        $user_arr = $this->userIinfo($uid);
        if($user_arr) {
            $model = M('cmf_report_record',"",$this->baojia_mebike);
            $model2 = M('cmf_report_img',"",$this->baojia_mebike);
            if($rent_content_id && $plate_no && $gis_lng && $imgId && $operationId){
               if($reportId){
                    $rMap['id'] = $reportId;
                    $rMap['uid'] = $uid;
                    $rMap['status'] = 4;
                    $result = $model->where($rMap)->getField('id');
                    if($result){
                       $imgId = array_filter(explode(",",$imgId));
                       if(count($imgId) > 5){
                           $this->ajaxReturn(["code" => 0, "message" => "上传图片不能超过五张"], 'json');
                       }
                       foreach ($imgId as $key=>$val){
                           $iMap['id'] = $val;
                           $iMap['report_id']  = 0;
                           $iMap['img_status'] = 2;
                           $img_arr = $model2->where($iMap)->getField('id');
                           if($img_arr){
                               $model2->where(['id'=>$val])->setField(['report_id'=>$result,'img_status'=>0,'sort'=>$key+1]);
                           }else{
                               $model2->where(['report_id'=>$result,'img_status'=>1])->setField(['img_status'=>3]);
                           }
                       }
                       $model->where(['id'=>$result])->setField(['status'=>1,'report_time'=>time()]);
                       $this->ajaxReturn(["code" => 1, "message" => "上报成功","data"=>['reportId'=>$result]], 'json');
                    }else{
                       $this->ajaxReturn(["code" => 0, "message" => "该上报记录不存在"], 'json'); 
                    }
                }else {
                   $map['rc.id'] = $rent_content_id;
                   $reslut = M('rent_content')->alias('rc')->field('rc.id,cid.imei,cid.device_type,rc.car_item_id')
                       ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
                       ->where($map)->find();
                   if ($reslut) {
                       $rMap['uid'] = $uid;
                       $rMap['operationid'] = $operationId;
                       $rMap['status'] = array('neq',2);
                       $result = $model->where($rMap)->getField('id');

                       if ($result) {
                           $this->ajaxReturn(["code" => 0, "message" => "该车你已上报"], 'json');
                       }

                       $imgId = array_filter(explode(",", $imgId));
                       if(count($imgId) > 5){
                           $this->ajaxReturn(["code" => 0, "message" => "上传图片不能超过五张"], 'json');
                       }
                       //查询当前电量
                       $dianliang = D('FedGpsAdditional')->electricity_info($reslut['imei']);
                       $data['uid'] = $uid;
                       $data['station_id'] = $user_arr['station_id'];
                       $data['operationid'] = $operationId;
                       $data['rent_content_id'] = $rent_content_id;
                       $data['residual_battery'] = $dianliang['residual_battery2'];
                       $data['plate_no'] = $plate_no;
                       $data['gis_lng'] = $gis_lng;
                       $data['gis_lat'] = $gis_lat;
                       $data['address'] = $areaLogic->GetAmapAddress($gis_lng, $gis_lat);
                       $data['report_time'] = time();
                       $report = $model->add($data);
                       if ($report) {
                           foreach ($imgId as $key=>$val){
                               $iMap['id'] = $val;
                               $iMap['report_id']  = 0;
                               $iMap['img_status'] = 2;
                               $img_arr = $model2->where($iMap)->getField('id');
                               if($img_arr){
                                   $model2->where(['id'=>$val])->setField(['report_id'=>$report,'img_status'=>0,'sort'=>$key+1]);
                               }
                           }
                           $this->ajaxReturn(["code" => 1, "message" => "上报成功", "data" => ['reportId' => $report]], 'json');
                       } else {
                           $this->ajaxReturn(["code" => 0, "message" => "上报失败"], 'json');
                       }
                   } else {
                       $this->ajaxReturn(["code" => 0, "message" => "该车不存在"], 'json');
                   }
               }
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "参数不完整"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

    /***
      * 查询电池上报退回修改
     *  uid      用户id
     *  reportId   电池丢失上报id
     */
    public  function  batteryReportFeedback($uid=1598809,$reportId=5){
        $user_arr = $this->userIinfo($uid);
        if($user_arr) {
            $model = M('cmf_report_record',"",$this->baojia_mebike);
            $model2 = M('cmf_report_img',"",$this->baojia_mebike);
            if($reportId) {
                $map['id'] = $reportId;
                $map['uid'] = $uid;
                /*$result = $model->alias('r')
                    ->field('r.remark,r.status,ri.id,ri.img_url,img_status,desc')
                    ->join('cmf_report_img ri on r.id = ri.report_id','right')
                    ->where($map)->select();*/
                $result = $model->field('id,operationid,remark,status')->where($map)->find();
                $res = [];
                if($result){
                    if($result['status'] == 2){
                        $res['title']  = "电池丢失上报审核通过";
                        $res['content']= "可放入电池完成换电";
                        $res['button'] = "继续换电";
                        $res['operationId'] = $result['operationid']?$result['operationid']:'';
                        $this->ajaxReturn(["code" => 1, "message" => "审核通过","data"=>$res], 'json');
                    }else if($result['status'] == 4){
                        $res['title']  = "审核被驳回";
                        $res['content']= "以下照片不符合规则请重新上传";
                        $res['button'] = "重新上传";
                        $res['remark'] = $result['remark'];
                        $res['operationId'] = $result['operationid']?$result['operationid']:'';
                        //查询电池丢失图片
                        $gMap['report_id'] = $result['id'];
                        $gMap['img_status']= array('in',array(0,1));
                        $img_arr = $model2->field('id,img_url,img_status,desc')->where($gMap)->order('sort asc')->select();
                        if($img_arr){
                            foreach ($img_arr as &$val) {
                                $val['img_url'] = $val['img_url']?"http://".$_SERVER['HTTP_HOST']."/".$val['img_url']:"";
                                $val['desc']    = $val['desc']?$val['desc']:"";
                            }
                            $res['imgList'] = $img_arr;
                        }else{
                            $res['imgList'] = [];
                        }
                        $this->ajaxReturn(["code" => 3, "message" => "退回修改","data"=>$res], 'json');
                    }else if($result['status'] == 3){
                        $res['title']  = "审核被驳回";
                        $res['content']= "电池被盗情况存在疑点\r\n暂时不能进行其他操作，请于风控经理联系";
                        $res['button'] = "拨打电话";
                        $res['user_name'] = "李辉";
                        $res['tel']    = "18612464193";
                        $res['operationId'] = $result['operationid']?$result['operationid']:'';
                        $this->ajaxReturn(["code" => 2, "message" => "已驳回","data"=>$res], 'json');
                    }else{
                        $res['title']  = "等待审核";
                        $res['content']= "请等待审核结果后进行操作...";
                        $res['operationId'] = $result['operationid']?$result['operationid']:'';
                        $this->ajaxReturn(["code" => 4, "message" => "等待审核","data"=>$res], 'json');
                    }
                }else{
                    $this->ajaxReturn(["code" => 1, "message" => "数据接收成功","data"=>$res], 'json');
                }
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "参数不完整"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }


    public  function  qrcode_url($borderId=0){
         $sevePath = './Public/img/qrcode/'.'battery_'.$borderId.'.png';
         $josn = json_encode(array('borderId'=>$borderId));
         qrcode($josn,$sevePath,6);
         return trim($sevePath,'.');
    }

    /****
     * 图片上传接口
     * uid          用户ID
     * picture      上传的图片
     */
    public  function  imgUpload($uid){
        $user_arr = $this->userIinfo($uid);
        if($user_arr) {
            $model = M('cmf_report_img',"",$this->baojia_mebike);
            $pic = A('Yunwei')->upload();
            $data['uid']     = $user_arr['user_id'];
            $data['img_url'] = $pic;
            $data['img_status'] = 2;
            $res = $model->add($data);
            if($res){
                $this->ajaxReturn(["code" => 1, "message" => "上传成功",'data'=>['imgId'=>$res]], 'json');
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "上传失败"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

    /***
     *验证电池编号和真伪查询
     *batteryNumber   电池编码
     **/
    public   function   verifyBatteryNumber($batteryNumber){
        $bNumber = trim($batteryNumber,'BD');
        $is_battery = batteryLuhm($bNumber);
        if($is_battery){
            $bnMap['battery_number'] = $batteryNumber;
            $bn_arr = M('cmf_battery_qrcode',"",$this->baojia_mebike)->field('battery_number,temporary')->where($bnMap)->find();
            if($bn_arr){
                if($bn_arr['temporary'] == 1){
                    return  -2;     //临时码
                }else{
                    return  1;      //正式码
                }
            }else{
                return  -1;       //电池编码是伪造的
            }
        }else{
            return  0;          //电池编码格式不正确
        }
    }

    /****
     *换电排行榜
     * uid         用户ID
     * hdTime     换电时间
     * page       页码
     * day_month  默认不传或传0 日排行榜  1= 月排行榜
     * corporation_id   所属公司 ID
     **/
    public   function  replacementList($uid=828606,$hdTime=0,$page=0,$day_month=0,$corporation_id=7901,$uuid='yy'){
        $user_arr = $this->beforeUserInfo($uid,$corporation_id);
	
        if($user_arr) {
            $this->check_terminal($uid,$uuid);
            if($day_month == 1){
                $month_arr = $this->monthLeaderBoards($uid,$hdTime,$page,$corporation_id);
                $this->ajaxReturn(["code" => 1, "message" => "数据接收成功",'data'=>$month_arr], 'json');
            }
            $model = M('operation_logging');
            $model2 = M('baojia_mebike.cmf_ranking_day_count');
            $lModel = M('baojia_mebike.cmf_ranking_like');
//            $lModel = M('cmf_ranking_like','',$this->baojia_mebike);
            if ($hdTime) {
                $startTime = strtotime(date('Y-m-d', $hdTime) . " 0:0:0");
                $endTime = strtotime(date('Y-m-d', $hdTime) . " 23:59:59");
                $map['o.time'] = array('between', array($startTime, $endTime));
                $lMap['like_time'] = array('between', array($startTime, $endTime));
                $oMap['like_time'] = array('between', array($startTime, $endTime));
                $cMap['ol.count_time'] = array('between', array($startTime, $endTime));
                $ownMap['like_time'] = array('between', array($startTime, $endTime));
            } else {
                $startTime = strtotime(date('Y-m-d', time()) . " 0:0:0");
                $endTime = strtotime(date('Y-m-d', time()) . " 23:59:59");
                $map['o.time'] = array('between', array($startTime, $endTime));
                $lMap['like_time'] = array('between', array($startTime, $endTime));
                $oMap['like_time'] = array('between', array($startTime, $endTime));
                $ownMap['like_time'] = array('between', array($startTime, $endTime));
                $cMap['ol.count_time'] = array('between', array($startTime, $endTime));
            }
            if($corporation_id){
                $cMap['c.id'] = $corporation_id;
            }
			$cMap['u.status'] = 1;
            $page = $page ? $page : 1;
            $page_size = 15;
            $offset = $page_size * ($page - 1);
            // $map['o.status'] = array('neq', 2);
            // $map['o.operate'] = array('in', array(-1, 1, 2));
            //当前时间大于2018-03-14
            if(time() > 1520956800){
                $map['_string'] = " o.operate = 44 and o. STATUS = 1 ";
            }else{
                $map['_string'] = " ((o.operate IN (- 1, 1, 2) and o. STATUS <> 2) or (o.operate = 44 and o. STATUS = 1))";
            }
			// $where1 = "((o.operate IN (- 1, 1, 2) and o. STATUS <> 2) or (o.operate = 44 and o. STATUS = 1))";
            $result = $model2->alias('ol')->field('c.city_id,u.user_name,u.role_type,ol.uid,ol.total')
                ->join('baojia_mebike.repair_member u on ol.uid = u.user_id','left')
                ->join('corporation c on c.id = u.corporation_id','left')
                ->where($cMap)->order('ol.total desc')->limit($offset, $page_size)->select();
			
            //查询自己的排名
            $allRankings = $model2->alias('ol')->field('c.city_id,u.user_name,u.role_type,ol.uid,ol.total')
                ->join('baojia_mebike.repair_member u on ol.uid = u.user_id','left')
                ->join('corporation c on c.id = u.corporation_id','left')
                ->where($cMap)->order('ol.total desc')->select();
            if(!$result) {
                if($corporation_id){
                    $subQuery = M()->table('operation_logging o')->field('o.uid,count(1) total')->where($map)->group('o.uid')->order('total desc')->buildSql();
                    $result = M()->table($subQuery.' ol')->field('c.city_id,u.user_name,u.role_type,ol.uid,ol.total')
                        ->join('baojia_mebike.repair_member u on ol.uid = u.user_id','left')
                        ->join('corporation c on c.id = u.corporation_id','left')
                        ->where("c.id=".$corporation_id." and u.status=1")->order('ol.total desc')->limit($offset, $page_size)->select();
                    
					//查询自己的排名
                    $allRankings = M()->table($subQuery.' ol')->field('ol.uid,ol.total')
                        ->join('baojia_mebike.repair_member u on ol.uid = u.user_id','left')
                        ->join('corporation c on c.id = u.corporation_id','left')
                        ->where("c.id=".$corporation_id." and u.status=1")->order('ol.total desc')->select();
                }else{
                    $subQuery = M()->table('operation_logging o')->field('o.uid,count(1) total')->where($map)->group('o.uid')->order('total desc')->buildSql();
                    $result = M()->table($subQuery.' ol')->field('c.city_id,u.user_name,u.role_type,ol.uid,ol.total')
                        ->join('baojia_mebike.repair_member u on ol.uid = u.user_id','left')
                        ->join('corporation c on c.id = u.corporation_id','left')->limit($offset, $page_size)->select();
                    //查询自己的排名
                    $allRankings = M()->table($subQuery.' ol')->field('ol.uid,ol.total')
                        ->join('baojia_mebike.repair_member u on ol.uid = u.user_id','left')
                        ->join('corporation c on c.id = u.corporation_id','left')->select();
                }
            }
            $ownRankings = 0;
            $ownTotal    = 0;
            if($allRankings){
                $all_uid = array_column($allRankings, 'uid');
                $all_number = array_column($allRankings, 'total');
                $own_uid = array_keys($all_uid,$uid);
                if($own_uid){
                    $ownRankings = $own_uid[0]+1;
                    $ownTotal    = $all_number[$own_uid[0]];
                }else{
                    $ownRankings = count($all_uid) + 1;
                }
            }
            $res = [];
            $res['ownRankings']['user_name'] = $user_arr['user_name'];
            $res['ownRankings']['role_type'] = $user_arr['role_type'];
            //查询城市名称
            $own_city = M('area_city')->where(['id'=>$user_arr['city_id']])->getField('name');
            $res['ownRankings']['city_name'] = $own_city?$own_city:"";
            $res['ownRankings']['uid']       = $user_arr['user_id'];
            $res['ownRankings']['total']     = $ownTotal;
            if($ownTotal >= 40){
                $res['ownRankings']['standard'] = 1;
            }else{
                $res['ownRankings']['standard'] = 0;
            }
            $res['ownRankings']['ranking']   = $ownRankings;
            //查询自己的点赞数
            $oMap['praise_people'] = $uid;
            $oMap['day_month']     = 1;
            $own_like = $lModel->where($oMap)->count(1);

            if($own_like){
                $res['ownRankings']['is_like']   = 1;
            }else{
                $res['ownRankings']['is_like']   = 0;
            }
            $res['ownRankings']['like_number'] = $own_like;
            if($result){
                foreach ($result as $key=>&$val){

                    //查询排行榜用户信息
//                    $rankingsUser = M('baojia_mebike.cmf_user')->field('user_name,role_type,city_id')->where(['user_id'=>$val['uid']])->find();
//                    $rankingsUser = $this->beforeUserInfo($val['uid']);
//
//                    $val['user_name'] = $rankingsUser['user_name']?$rankingsUser['user_name']:"";
//                    $val['role_type'] = $rankingsUser['role_type'];
                    if($val['role_type'] == 1){
                        $val['role_type'] = '运维';
                    }else if($val['role_type'] == 2){
                        $val['role_type'] = '调度';
                    }else if($val['role_type'] == 3){
                        $val['role_type'] = '整备';
                    }else if($val['role_type'] == 4){
                        $val['role_type'] = '库管';
                    }else{
                        $val['role_type'] = '无';
                    }
                    //查询城市名称
                    $city_name = M('area_city')->where(['id'=>$val['city_id']])->getField('name');
                    $val['city_name'] = $city_name?$city_name:"";
                    //查看点赞数量
                    $lMap['praise_people'] = $val['uid'];
                    $lMap['day_month']     = 0;
                    $like = $lModel->where($lMap)->count(1);
                    //查看我有没有给他点赞
                    $ownMap['uid'] = $uid;
                    $ownMap['praise_people'] = $val['uid'];
                    $ownMap['day_month']     = 0;
                    $ownLike = $lModel->where($ownMap)->getField('id');
                    if($ownLike){
                        $val['is_like']  = 1;
                    }else{
                        $val['is_like']  = 0;
                    }
                    $val['like_number'] = $like;
                    //查询是否达标
                    if($val['total'] >= 40){
                        $val['standard'] = 1;
                    }else{
                        $val['standard'] = 0;
                    }
                    //排名
                    $val['ranking'] = ($key+1)+$offset;
                    unset($val['city_id']);
                }
                $res['allRankings'] = $result;
            }
            $res['page'] = $page;
            $this->ajaxReturn(["code" => 1, "message" => "数据接收成功",'data'=>$res], 'json');
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

    /****
     *换电月排行榜
     ***/
    public  function   monthLeaderBoards($uid=0,$hdTime=0,$page=0,$corporation_id=0){
        $user_arr = $this->beforeUserInfo($uid,$corporation_id);
        if ($hdTime) {
            $firstday = date('Y-m-01', $hdTime);
            $startTime = strtotime($firstday . " 0:0:0");
            $endTime = strtotime(date('Y-m-d', strtotime("$firstday +1 month -1 day")) . " 23:59:59");
            $lMap['like_time'] = array('between', array($startTime, $endTime));
            $oMap['like_time'] = array('between', array($startTime, $endTime));
            $ownMap['like_time'] = array('between', array($startTime, $endTime));
            $cMap['o.count_time'] = array('between', array($startTime, $endTime));
        } else {
            $firstday = date('Y-m-01', time());
            $startTime = strtotime($firstday . " 0:0:0");
            $endTime = strtotime(date('Y-m-d', strtotime("$firstday +1 month -1 day")) . " 23:59:59");
            $lMap['like_time'] = array('between', array($startTime, $endTime));
            $oMap['like_time'] = array('between', array($startTime, $endTime));
            $ownMap['like_time'] = array('between', array($startTime, $endTime));
            $cMap['o.count_time'] = array('between', array($startTime, $endTime));
        }
        $model = M('baojia_mebike.cmf_ranking_day_count');
        $lModel = M('baojia_mebike.cmf_ranking_like');
//        $lModel = M('cmf_ranking_like','',$this->baojia_mebike);
        $page = $page ? $page : 1;
        $page_size = 15;
        $offset = $page_size * ($page - 1);
        $subQuery = M()->table('baojia_mebike.cmf_ranking_day_count o')->field('o.uid,sum(o.total) total')->where($cMap)->group('o.uid')->order('total desc')->buildSql();
//        $result = $model->field('uid,sum(total) total')->where($cMap)->order('total desc')->group('uid')->limit($offset, $page_size)->select();
        //查询自己的排名
//        $allRankings = $model->field('uid,sum(total) total')->where($cMap)->order('total desc')->group('uid')->select();
        if($corporation_id){
            $result = M()->table($subQuery.' ol')->field('c.city_id,u.user_name,u.role_type,ol.uid,ol.total')
                ->join('baojia_mebike.repair_member u on ol.uid = u.user_id','left')
                ->join('corporation c on c.id = u.corporation_id','left')
                ->where("c.id=".$corporation_id." and u.status=1")->order('ol.total desc')->limit($offset, $page_size)->select();
            //查询自己的排名
            $allRankings = M()->table($subQuery.' ol')->field('ol.uid,ol.total')
                ->join('baojia_mebike.repair_member u on ol.uid = u.user_id','left')
                ->join('corporation c on c.id = u.corporation_id','left')
                ->where("c.id=".$corporation_id." and u.status=1")->order('ol.total desc')->select();
        }else{
            $result = M()->table($subQuery.' ol')->field('c.city_id,u.user_name,u.role_type,ol.uid,ol.total')
                ->join('baojia_mebike.repair_member u on ol.uid = u.user_id','left')
                ->join('corporation c on c.id = u.corporation_id','left')->limit($offset, $page_size)->select();
            //查询自己的排名
            $allRankings = M()->table($subQuery.' ol')->field('ol.uid,ol.total')
                ->join('baojia_mebike.repair_member u on ol.uid = u.user_id','left')
                ->join('corporation c on c.id = u.corporation_id','left')->select();
        }
        $ownRankings = 0;
        $ownTotal    = 0;
        if($allRankings){
            $all_uid = array_column($allRankings, 'uid');
            $all_number = array_column($allRankings, 'total');
            $own_uid = array_keys($all_uid,$uid);
            if($own_uid){
                $ownRankings = $own_uid[0]+1;
                $ownTotal    = $all_number[$own_uid[0]];
            }else{
                $ownRankings = count($all_uid) + 1;
            }
        }

        $res = [];
        $res['ownRankings']['user_name'] = $user_arr['user_name'];
        $res['ownRankings']['role_type'] = $user_arr['role_type'];
        //查询城市名称
        $own_city = M('area_city')->where(['id'=>$user_arr['city_id']])->getField('name');
        $res['ownRankings']['city_name'] = $own_city?$own_city:"";
        $res['ownRankings']['uid']       = $user_arr['user_id'];
        $res['ownRankings']['total']     = $ownTotal;
        $res['ownRankings']['standard']  = 1;
        $res['ownRankings']['ranking']   = $ownRankings;
        //查询自己的点赞数
        $oMap['praise_people'] = $uid;
        $oMap['day_month']     = 1;
        $own_like = $lModel->where($oMap)->count(1);

        if($own_like){
            $res['ownRankings']['is_like']   = 1;
        }else{
            $res['ownRankings']['is_like']   = 0;
        }
        $res['ownRankings']['like_number'] = $own_like;
        if($result){
            foreach ($result as $key=>&$val){
                //查询排行榜用户信息
//                $rankingsUser = $this->beforeUserInfo($val['uid']);
//                $val['user_name'] = $rankingsUser['user_name']?$rankingsUser['user_name']:"";
//                $val['role_type'] = $rankingsUser['role_type'];
                if($val['role_type'] == 1){
                    $val['role_type'] = '运维';
                }else if($val['role_type'] == 2){
                    $val['role_type'] = '调度';
                }else if($val['role_type'] == 3){
                    $val['role_type'] = '整备';
                }else if($val['role_type'] == 4){
                    $val['role_type'] = '库管';
                }else{
                    $val['role_type'] = '无';
                }
                //查询城市名称
                $city_name = M('area_city')->where(['id'=>$val['city_id']])->getField('name');
                $val['city_name'] = $city_name?$city_name:"";
                //查看点赞数量
                $lMap['praise_people'] = $val['uid'];
                $lMap['day_month']     = 1;
                $like = $lModel->where($lMap)->count(1);
                //查看我有没有给他点赞
                $ownMap['uid'] = $uid;
                $ownMap['praise_people'] = $val['uid'];
                $ownMap['day_month']     = 1;
                $ownLike = $lModel->where($ownMap)->getField('id');
                if($ownLike){
                    $val['is_like']  = 1;
                }else{
                    $val['is_like']  = 0;
                }
                $val['like_number'] = $like;
                //查询是否达标
                $val['standard'] = 1;
                //排名
                $val['ranking'] = ($key+1)+$offset;
                unset($val['city_id']);
            }
            $res['allRankings'] = $result;
        }
        $res['page'] = $page;
        return   $res;
    }


    /****
     *换电排行榜点赞
     * uid     用户ID
     * praisePeopleId   被点赞人ID
     * likeTime         点赞时间
     * day_month  默认不传或传0 日排行榜  1= 月排行榜
     **/
    public  function   rankingLike($uid=0,$praisePeopleId=0,$likeTime=0,$day_month=0,$uuid='yy'){
        $user_arr    = $this->beforeUserInfo($uid);
        $raisePeople = $this->beforeUserInfo($praisePeopleId);
        if($user_arr && $raisePeople) {
            $this->check_terminal($uid,$uuid);
            $lModel = M('baojia_mebike.cmf_ranking_like');
//            $lModel = M('cmf_ranking_like','',$this->baojia_mebike);
            if($uid == $praisePeopleId){
                $this->ajaxReturn(["code" => 0, "message" => "自己不能给自己点赞"], 'json');
            }else{
                if($day_month == 1){
                    $likeTime = $likeTime?$likeTime:time();
                    $firstday = date('Y-m-01', $likeTime);
                    $startTime = strtotime($firstday . " 0:0:0");
                    $endTime = strtotime(date('Y-m-d', strtotime("$firstday +1 month -1 day")) . " 23:59:59");
                }else{
                    $likeTime = $likeTime?$likeTime:time();
                    $startTime = strtotime(date('Y-m-d', $likeTime) . " 0:0:0");
                    $endTime = strtotime(date('Y-m-d', $likeTime) . " 23:59:59");
                }

                if($startTime > time()){
                    $this->ajaxReturn(["code" => 0, "message" => "点赞时间不能大于当天时间"], 'json');
                }
                $map['uid'] = $uid;
                $map['praise_people'] = $praisePeopleId;
                $map['day_month'] = $day_month;
                $map['like_time'] = array('between', array($startTime, $endTime));
                $like =  $lModel->where($map)->getField('praise_people');
                if($like){
                    $this->ajaxReturn(["code" => 0, "message" => "你已经给该用户点赞过了"], 'json');
                }else{
                    $data['uid'] = $user_arr['user_id'];
                    $data['praise_people'] = $praisePeopleId;
                    $data['day_month']     = $day_month;
                    $data['like_time']     = $likeTime;
                    $res = $lModel->add($data);
                    if($res){
                        $this->ajaxReturn(["code" => 1, "message" => "点赞成功","data"=>['likeId'=>$res]], 'json');
                    }else{
                        $this->ajaxReturn(["code" => 0, "message" => "点赞失败"], 'json');
                    }
                }
            }
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "该用户不存在"], 'json');
        }
    }

    //老板查询用户信息
    public   function   beforeUserInfo($uid=0,$corporation_id=0){
        $model = M('baojia_mebike.repair_member');
        if($corporation_id){
            $map['u.corporation_id'] = $corporation_id;
        }
        $map['u.user_id'] = $uid;
        $map['u.status']    = 1;
        $user = $model->alias('u')->field('u.user_id,u.user_name,u.role_type,c.city_id')
            ->join('corporation c on c.id=u.corporation_id','left')
            ->where($map)->find();
        if($user){
            if($user['role_type'] == 1){
                $user['role_type'] = '运维';
            }else if($user['role_type'] == 2){
                $user['role_type'] = '调度';
            }else if($user['role_type'] == 3){
                $user['role_type'] = '整备';
            }else if($user['role_type'] == 4){
                $user['role_type'] = '库管';
            }else{
                $user['role_type'] = '无';
            }
        }
        return $user;
    }


    public   function  chaxun($lat='39.858969826924',$lng='116.34348256963'){

        /* M("operation_logging")->where("id=158698")->setField("pic1","");
         $where['a.data_status'] = 1;
         $where['a.status'] = 1;
         $where['a.parent_id'] = 2118;

         $ju = "ROUND(6378.138*2*ASIN(SQRT(POW(SIN( ({$lat}*PI()/180-a.gis_lat*PI()/180)/2),2)+COS({$lat}*PI()/180)*COS(a.gis_lat*PI()/180)* POW(SIN( ({$lng}*PI()/180-a.gis_lng*PI()/180)/2),2)))*1000) as juli";
         $corarr = M('corporation')->alias('a')
             ->join('corporation_car_return_config b on a.id =b.corporation_id', 'LEFT')
             ->where($where)
             ->field("a.id,a.name,a.gis_lat,a.gis_lng,a.short_address,a.detaile_address,b.return_radius,{$ju}")
             ->order('juli')
             ->having("juli<5000")
             ->page(1, 20)
             ->select();
         echo  M('corporation')->getLastSql();die;*/

//        $url = 'http://api.baojia.com/vxmtest/Api/MemberOrder/Index';
        $url = 'http://10.1.11.34:8309/api/user/quickLoginCode';
//        $post_data['rent_content_id'] = 5866841;
//        $post_data['residual_battery'] = 37;
//        $post_data['uid'] = 2836059;
//        $post_data['plate_no'] = 'DD958669';
        $post_data = 'mobile="17778081557"';
//        $post_data = 'app_id=218&iflogin=YjBmMDQ0N2ZmZWQwZjMwNTZlZTkwNWUwOWFjMWNiN2U%3D&sign=721b1adf087054e2aee948e16c38244e&version=3.3.1&uid=2683922&timestamp=1511950896527&gis_lng=116.3904294162326&gis_lat=39.97956651475695&orderId=5568490&device_os=iOS&client_id=218&qudao_id=guanfang&session_id=%2FiXPY6RZRPyEAW13GP9OQ%2FZvIx0FkBcY6Ms%2FL%2Fcc33R%2F6nXzWj5EcA%3D%3D&gt_id=15393c209d328d319b3d2fcba458e0e9&device_model=&user_token=Q3Z4QVhhR0RKL0hRS1EwQ3NSbzhsbk80bmhsRzBsYTUvMXVNWUJ6RE5FYlR1cFU2dkMrRUhRPT0%3D';
        $result = $this->control_post($post_data,$url);

        print_r($result);
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

}