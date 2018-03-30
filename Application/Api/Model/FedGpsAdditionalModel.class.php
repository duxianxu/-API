<?php
namespace Api\Model;
use Think\Model;

class FedGpsAdditionalModel extends Model{
    private  $ol_table = "operation_logging";
    private  $mebike_s_table = 'mebike_status';
    //根据设备号查询电量
    public  function  electricity_info($iemi){
        $result = [];
        $map['imei'] = $iemi;
        $res = M('fed_gps_additional')->field('residual_battery')->where($map)->find();
        if($res){
            $result['residual_battery1'] = $res['residual_battery'];
        }
        $res2 = M('gps_additional','',C('DB_CONFIG_BOX'))->field('residual_battery')->where($map)->find();
        if($res2){
            $result['residual_battery2'] = $res2['residual_battery'];
        }
        if($result){
            return $result;
        }else{
            return "";
        }
    }
	
	//统计当天完成的车辆数
	public  function   theDayCarNum($uid,$type){
		$model = M($this->ol_table);
        $start_time = strtotime(date("Y-m-d",time())." 0:0:0");
        $end_time   = strtotime(date("Y-m-d",time())." 23:59:59");
        
        $lmap['time'] = array('between',array($start_time,$end_time));
        $lmap['uid']  = $uid?$uid:"";
		if($type == 2){
			$lmap['operate'] = 3;
		}else{
			$lmap['status'] = array('neq',2);
            $lmap['operate'] = array('in',array(-1,1,2));
		}
        $resday = $model->where($lmap)->count(1);
		if($resday){
			if($type==2){
				$prompt = "完成了回收，工作记录+1\r\n这是今天的第".$resday."辆车，继续加油";
			}else{
				$prompt = "完成换电，工作记录+1\r\n这是今天的第".$resday."辆车，继续加油";
			}
			return ['CarNum'=>$resday,"prompt"=>$prompt];
		}else{
			return $resday;
		}
	}

	/**查询未完成的操作记录
     *uid   用户ID
     *rent_content_id  车辆ID
     * operate         记录类型  33=开始换电记录 34=开仓锁
     */

    public  function   undone_operation($uid,$rent_content_id,$operate){
        $sMap["uid"] = $uid;
        $sMap["rent_content_id"] = $rent_content_id;
        $sMap["operate"] = $operate;
        $sMap["status"]  = 0;
        $start_hd = M($this->ol_table)->field("id,uid,rent_content_id,operate")->where($sMap)->find();
        return $start_hd;
    }

    /**
     *操作记录
     *uid   用户ID
     *rent_content_id  车辆ID
     *plate_no         车牌号
     *gis_lng          经度
     *gis_lat          纬度
     *car_status       解绑原因   11=车辆损坏  12=盒子损坏  13=其他
     *desc             解绑原因  其他  内容
     *operate          日志类型     7 车辆未找到 12 设防  13 撤防 14 启动 16 手动矫正 17 自动矫正  19 需排查里的位置矫正 20=绑定盒子 21=解绑盒子  23=故障排查无此故障  24=故障排查有此故障  32=验收入库  34=开舱锁  45 = 关仓 46 = 重启盒子
     *pid              工单id
     * source          操作此接口来源 2=故障排查矫正  4=换电矫正 5=寻车矫正 6=检修矫正
     */
    public  function  operation_log($uid,$rent_content_id,$plate_no,$gis_lng,$gis_lat,$operate,$corporation_id=0,$car_status=0,$desc='',$pid=0,$source=0){
        if($uid && $rent_content_id && $operate){

            $model = M($this->ol_table);
            if(in_array($operate,[33,34])){
                $map['rc.id'] = $rent_content_id;
                $reslut = M('rent_content')->alias('rc')->field('rc.id,cid.imei,cid.device_type,rc.car_item_id')
                    ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id')
                    ->where($map)->find();
                $before_battery = $this->electricity_info($reslut['imei']);
                if($before_battery['residual_battery2']){
                    $date['before_battery'] = $before_battery['residual_battery2'];
                }
            }
//            if($operate == 34){    //库管换电流程
//                $date['operate'] = 0;
//                $date['step'] = 1;
//            }else
            if($source){
                $date['operate_source'] = 2;
            }
            $date['operate'] = $operate;
            $date['uid'] = $uid;
            if($rent_content_id != -10){
                $date['rent_content_id'] = $rent_content_id;
            }
            if($car_status){
                $date['car_status'] = $car_status;
            }
            if($operate == 21){
                $date['status'] = 1;
            }
            if($pid){
                $date['pid']    = $pid;
            }else{
				$is_own = M("baojia_mebike.dispatch_order")->where("verify_status IN(1,2) AND rent_content_id={$rent_content_id} AND uid={$uid}")->order("verify_status desc")->getField("id");
				if($is_own){
				   $date['pid']    = $is_own;
				}
			}
            $date['desc']    = $desc;
            $date['corporation_id']   = $corporation_id?$corporation_id:0;
            $date['plate_no']= $plate_no;
            $date['gis_lng'] = $gis_lng;
            $date['gis_lat'] = $gis_lat;
            $date['time'] = time();
            $res = $model->add($date);
            return $res;
        } else{
            return  false;
        }
    }
    //查询车最后一次上架时间
    public  function  carShelves($rent_content_id){
        $top_frame_time = D("OperationLogging")->field("time")
            ->where("rent_content_id = {$rent_content_id} and operate = 8")->order("time desc")->getField("time");
        return  $top_frame_time;
    }

    //查询该车是否有需排查故障
    public  function  isFaultScreening($rent_content_id=0,$uid=0){
         /*$map["rsk.rent_content_id"] = $rent_content_id;
         $map["rsk.operate_status"]  = 12;
         $map["xr.status"]  = 1;
         $isFault = M("xiaomi_repairs")->alias("xr")->field("DISTINCT(xr.uid),xr.operate,xr.id")
             ->join("rent_sku_hour rsk on xr.rent_content_id = rsk.rent_content_id","left")
             ->where($map)->limit(3)->select();*/
		$map["xr.rent_content_id"] = $rent_content_id;
       
        $map["xr.status"]  = 1;
        $isFault = M("xiaomi_repairs")->alias("xr")->field("DISTINCT(xr.uid),xr.operate,xr.id")
             ->where($map)->limit(3)->select(); 
         if(count($isFault) >= 3){
             $user_id = array_column($isFault,"uid");
             $repairs_id = array_column($isFault,"id");
             return ["operate"=>$isFault[0]["operate"],"user_id"=>$user_id,"repairs_id"=>$repairs_id];
         }else{
             //查询有没有未完成的需排查任务
             $oModel = M($this->ol_table);
             $operation = $oModel->field('id,plate_no,rent_content_id,operate')->where("uid={$uid} and operate in(23,24) and status=0")->find();
             if(!empty($operation["id"])){
                 return true;
             }
             return false;
         }
        /*$map["rent_content_id"] = $rent_content_id;
        $map["status"]  = array('in',array(0,1));
        $map["operate"] = array('gt',0);
        $isFault = M("xiaomi_repairs")->field("operate,count(operate) total")
            ->where($map)->group("operate")->having("total >= 3")->order("create_time desc")->select();

        $operate = array();
        foreach ($isFault as $val){
            $fMap["rent_content_id"] = $rent_content_id;
            $fMap["operate"] = $val["operate"];
            $fMap["status"] = array('in',array(0,1));
            $uFault = M("xiaomi_repairs")->field("DISTINCT uid")
                ->where($fMap)->order("create_time desc")->limit(3)->select();
            if(count($uFault) >= 3){
                $user_id = array_column($uFault,"uid");
                $operate =  ["operate"=>$val["operate"],"user_id"=>$user_id];
                break;
            }
        }

        if(count($operate) > 0){
            return $operate;
        }else{
            //查询有没有未完成的需排查任务
            $oModel = M('operation_logging');
            $operation = $oModel->field('id,plate_no,rent_content_id,operate')->where("uid={$uid} and operate in(23,24) and status=0")->find();
//            echo $oModel->getLastSql();
//            var_dump($operation);
            if(!empty($operation["id"])){
                return true;
            }
            return false;
        }*/
    }
	
    //查询车辆规则
    public   function   carRule($rent_content_id){
        //查询是不是无电离线
        $map['rc.id'] = $rent_content_id;
        $map['rc.sell_status'] = -100;
        $map['residual_battery'] = array('ELT',10);
        $res = M('rent_content')->alias('rc')
            ->field('rc.sell_status,IFNULL(fg.residual_battery,0) residual_battery')
            ->join('car_item_device cid ON rc.car_item_id = cid.car_item_id','left')
            ->join('fed_gps_additional fg ON fg.imei = cid.imei','left')
            ->where($map)->find();
        return  $res;
    }

    //换电日志
    //operate          日志类型   1=开舱锁  2=点击完成换电 3=点击设防
    public  function  exchangeLog($uid,$rent_content_id,$plate_no,$gis_lng,$gis_lat,$operate){
        $model = M('huandian_log','','mysql://apitest-baojia:TKQqB5Gwachds8dv@10.1.11.110:3306/baojia_mebike#utf8');
        $date['operate'] = $operate;
        $date['uid'] = $uid;
        $date['rent_content_id'] = $rent_content_id;
        $date['plate_no'] = $plate_no;
        $date['gis_lng'] = $gis_lng;
        $date['gis_lat'] = $gis_lat;
        $date['time'] = time();
        $res = $model->add($date);
        return $res;
    }

    //查询我有没有可操作的工单
    public   function   ownWorkOrder($uid,$rent_content_id,$corporation_id=0){
         $corporation = [2118,10258];
         $user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
         if(in_array($corporation_id,$corporation) && $user_arr['role_type'] == '运维'){
             $model = M('baojia_mebike.dispatch_order');
             $map['uid'] = $uid;
             $map['rent_content_id'] = $rent_content_id;
             $map['corporation_id']  = $corporation_id;
             $map['verify_status']   = array('in',array(1,2,3));
             $result = $model->where($map)->find();
             return $result;
         }else{
             return  true;
         }
    }

    public  function  carCurrentStatus($rent_content_id=0){
        $model = M("rent_content");
        $model2 = M("trade_order");
        $result = $model->alias('rc')->field('rc.id,rc.status,rc.sell_status,rc.create_time,fga.residual_battery,ms.no_mileage_num')
            ->join("car_item_device  cid  ON  rc.car_item_id = cid.car_item_id","left")
            ->join("fed_gps_additional  fga  ON  cid.imei = fga.imei","left")
            ->join("mileage_statistics  ms ON ms.rent_content_id = rc.id","left")
            ->where("rc.id={$rent_content_id}")->find();
        //查询两日无单
        if($result){
            $order_arr = $model2
                ->where("create_time > (UNIX_TIMESTAMP(NOW()) - 86400 * 2) and rent_type = 3 AND rent_content_id={$result["id"]}")
                ->count(1);
        }

        $res = array();
        if($result['status'] == 2 && $result['sell_status'] == -100 && $result['residual_battery'] > 10){
            //离线下架 2  电量大于10%
            $res["rent_status"] = "离线下架";
        }
        if ($result['status'] == 2 && $result['sell_status'] == -100 && $result['residual_battery'] <= 10) {
            //无电离线 -1 电量低于等于10%且离线
            $res["rent_status"] = "无电离线";
        }elseif ($result['status'] == 2 && $result['sell_status'] == 1 && $result['no_mileage_num'] == 3) {
            //故障 4 有单无程
            $res["rent_status"] = "有单无程";
        }elseif($result['status'] == 2 && $result['sell_status'] == 1 && ($order_arr < 1) && ($this->diffBetweenTwoDays(time(), $result['create_time']) >= 4)){
            //两日无单 3
            $res["rent_status"] = "两日无单";
        }elseif ($result['status'] == 2 && $result['sell_status'] == -7) {
            //越界下架 6
            $res["rent_status"] = "越界下架";
        }elseif ($result['status'] == 2 && $result['sell_status'] == -8) {
            //待大修 7
            $res["rent_status"] = "待大修";
        }elseif ($result['status'] == 2 && (($result['sell_status'] == -1 || $result['sell_status'] == -5) && $result['residual_battery'] > 0)) {
            //馈电下架 10
            $res["rent_status"] = "馈电下架";
        }elseif ($result['status'] == 2 && ($result['sell_status'] == 1 || $result['sell_status'] == -1 || $result['sell_status'] == -5) && $result['residual_battery'] == 0) {
            //无电 11
            $res["rent_status"] = "无电";
        }

        if ($result['status'] == 2 && $result['sell_status'] == 1 && $result['residual_battery'] > 20 && $result['residual_battery'] <= 40) {
            //缺电 9
            $res["rent_status"] = "缺电";
        }
        return  $res;
    }


    function diffBetweenTwoDays ($day1, $day2)
    {
        if ($day1 < $day2) {
            $tmp = $day2;
            $day2 = $day1;
            $day1 = $tmp;
        }
        return ($day1-$day2)/86400;
    }

    //是否满足车辆上架
    public  function  is_car_status($rent_content_id){
        $param = "offline_status,out_status,lack_power_status,transport_status,repaire_status,seized_status,reserve_status,storage_status,rent_status,damage_status,scrap_status,other_status,dispatch_status";
        $data = M($this->mebike_s_table)->where(["rent_content_id"=>$rent_content_id])->field($param)->find();
        $sell_status = array();
        foreach ($data as $d =>$v) {
            if($d == "out_status" && $v == 200){
                $sell_status[$d]["value"] = $v;
                $sell_status[$d]["name"]  = $this->mebike_status($d,$v);
            }else if($d == "lack_power_status" && in_array($v,[300,400])){
                $sell_status[$d]["value"] = $v;
                $sell_status[$d]["name"]  = $this->mebike_status($d,$v);
            }else if($d == "repaire_status" && in_array($v,[200,300])){
                $sell_status[$d]["value"] = $v;
                $sell_status[$d]["name"]  = $this->mebike_status($d,$v);
            }else if(!in_array($d,['out_status','lack_power_status','repaire_status','dispatch_status']) && !empty($v)){
                $sell_status[$d]["value"] = $v;
                $sell_status[$d]["name"]  = $this->mebike_status($d,$v);
            }
        }
        return  $sell_status;
    }

    public  function  mebike_status($status,$v){
        $res["offline_status"] = "离线";
        $res["out_status"]     = "越界";
        if($status == "lack_power_status" && $v==300){
            $res["lack_power_status"] = "馈电";
        }elseif ($status == "lack_power_status" && $v==400){
            $res["lack_power_status"] = "无电";
        }
        $res["transport_status"]  = "运输中";
        if($status == "repaire_status" && $v==200){
            $res["repaire_status"]    = "大修";
        }elseif($status == "repaire_status" && $v==300){
            $res["repaire_status"]    = "寻回需维修";
        }
        $res["repaire_status"]    = "待维修拉回";
        $res["seized_status"]     = "被扣押";
        $res["put_status"]        = "待投放";
        $res["reserve_status"]    = "预约换电中";
        $res["storage_status"]    = "收回入库";
        $res["rent_status"]       = "出租中";
        $res["damage_status"]     = "损坏不可租";
        $res["scrap_status"]      = "报废";
        $res["other_status"]      = "其他";
        return  $res[$status]?$res[$status]:"";
    }

    /***
      *任务日志添加
     * uid           用户id
     *taskId         任务id
     *verify_status  审核状态
     * lng           经度
     * lat           纬度
     * remark        备注
     **/
    public   function   taskLog_add($uid,$taskId,$verify_status,$lng='',$lat='',$remark=''){
         $model = M("baojia_mebike.dispatch_order_log");
         $data["user_id"] = $uid;
         $data["dispatch_order_id"] = $taskId;
         $data["verify_status"] = $verify_status;
         $data["lng"] = $lng?$lng:I("request.gis_lng");
         $data["lat"] = $lat?$lat:I("request.gis_lat");
         $data["remark"] = $remark;
         $data["create_time"] = time();
         return  $model->add($data);
    }

}
