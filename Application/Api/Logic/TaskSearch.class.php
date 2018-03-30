<?php
namespace Api\Logic;

use Think\Log;

class TaskSearch{

    /**
     * 查询车辆任务
     * @param $rent_content_id
     * @param int $test
     * @return array
     */
    public function getTask($rent_content_id=5883885,$test=0){
        $sql="SELECT rn.id rent_content_id,rn.car_info_id,rn.car_item_id,cid.imei,cor.parent_id,rn.city_id,
            IFNULL(s.latitude,0) gis_lat,IFNULL(s.longitude,0) gis_lng,civ.plate_no,
            IFNULL(ms.no_mileage_num,0) no_mileage_num,rn.status,rn.sell_status,cn.model_id,mbs.reserve_status,
            mbs.offline_status,mbs.out_status,mbs.lack_power_status,mbs.transport_status,mbs.seized_status,mbs.storage_status,
            mbs.repaire_status,mbs.rent_status,mbs.damage_status,UNIX_TIMESTAMP(mbs.sell_time) sell_time,mbs.search_status,mbs.dispatch_status
            FROM rent_content rn
            LEFT JOIN mebike_status mbs ON mbs.rent_content_id=rn.id
            LEFT JOIN mileage_statistics ms ON ms.rent_content_id = rn.id 
            LEFT JOIN car_info cn on cn.id=rn.car_info_id
            LEFT JOIN car_model cm ON cm.id = cn.model_id
            LEFT JOIN car_item car ON car.id = rn.car_item_id
            LEFT JOIN car_item_verify civ on civ.car_item_id=rn.car_item_id
            JOIN car_item_device cid ON cid.car_item_id = rn.car_item_id
            LEFT JOIN fed_gps_status s ON s.imei=cid.imei
            LEFT JOIN corporation cor on cor.id=rn.corporation_id
            WHERE rn.sort_id =112 AND rn.id={$rent_content_id}";
        //非扣押 AND mbs.seized_status=0 非入库 AND mbs.storage_status=0 非调度 AND mbs.dispatch_status=0
        $rent_content = M()->query($sql);
        //echo "<pre>";print_r($rent_content);
        $Off_hire_status=[];
        $for_rent_status=[];
        $all_rent_status=[];
        $all_rent_status_title=[];
        $result = [];
        $c=[];
        $d=[];

        if ($rent_content&&is_array($rent_content)) {
            //echo "<pre>";print_r($rent_content);
            $rent_content=$rent_content[0];
            $corporation_id=$rent_content["parent_id"];
            if(!$corporation_id||empty($corporation_id)){
                return array("code" => 0, "message" => "车辆区域或网点数据有误");
            }
            $unstable=$this->getUnstable($corporation_id,$rent_content["city_id"]);
            $invalid=$this->getInvalid($corporation_id,$rent_content["city_id"]);
            $task_type = $this->getTaskType($corporation_id);

            $car_item_id=$rent_content["car_item_id"];
            $plate_no=$rent_content["plate_no"];
            $gis_lng=$rent_content["gis_lng"];
            $gis_lat=$rent_content["gis_lat"];
            //非扣押、非入库和非调度中
            if($rent_content["seized_status"]==0&&$rent_content["storage_status"]==0) {
                //寻车 search_status	寻车状态 0-无寻车 100-一级寻车 200-二级寻车 300-三级寻车 400-四级寻车 &&$rent_content["dispatch_status"]==0
                if ($rent_content['search_status'] > 0) {
                    switch ($rent_content['search_status']) {
                        case 100:
                            //25 1级寻车
                            $r["rent_status"] = 25;
                            $r["rent_status_title"] = "1级寻车";
                            array_push($all_rent_status, $r["rent_status"]);
                            array_push($all_rent_status_title, $r["rent_status_title"]);
                            array_push($for_rent_status, $r["rent_status_title"]);
                            break;
                        case 200:
                            //24 2级寻车
                            $r["rent_status"] = 24;
                            $r["rent_status_title"] = "2级寻车";
                            array_push($all_rent_status, $r["rent_status"]);
                            array_push($all_rent_status_title, $r["rent_status_title"]);
                            array_push($for_rent_status, $r["rent_status_title"]);
                            break;
                        case 300:
                            //23 3级寻车
                            $r["rent_status"] = 23;
                            $r["rent_status_title"] = "3级寻车";
                            array_push($all_rent_status, $r["rent_status"]);
                            array_push($all_rent_status_title, $r["rent_status_title"]);
                            array_push($for_rent_status, $r["rent_status_title"]);
                            break;
                        case 400:
                            //22 4级寻车
                            $r["rent_status"] = 22;
                            $r["rent_status_title"] = "4级寻车";
                            array_push($all_rent_status, $r["rent_status"]);
                            array_push($all_rent_status_title, $r["rent_status_title"]);
                            array_push($for_rent_status, $r["rent_status_title"]);
                            break;
                    }
                } else {
                    $redis = new \Redis();
                    $redis->pconnect(C("COMMON_REDIS_HOST"), C("COMMON_REDIS_PORT"),0.5);
                    //检修(用户上报、有电离线、有单无程) 寻车>回库>调度>换电>检修
                    $inspectioned_key  = C("KEY_PREFIX"). "inspectioned_rent_content_id:".$rent_content['rent_content_id'];
                    $inspectioned =$redis->get($inspectioned_key);
                    //8 有单无程
                    if (($rent_content['sell_status'] == 1||$rent_content["reserve_status"]==100) && $rent_content['no_mileage_num'] == 3&&empty($inspectioned)) {
                        $r["rent_status"] = 8;
                        $r["rent_status_title"] = "有单无程";
                        array_push($all_rent_status, $r["rent_status"]);
                        array_push($all_rent_status_title, $r["rent_status_title"]);
                        array_push($for_rent_status, $r["rent_status_title"]);
                    }
                    //26 用户上报 repaire_status 维修状态 0-不需要修 100-小修 200-大修
                    if ($rent_content["repaire_status"] == 100) {
                        $r["rent_status"] = 26;
                        $r["rent_status_title"] = "用户上报";
                        array_push($all_rent_status, $r["rent_status"]);
                        array_push($all_rent_status_title, $r["rent_status_title"]);
                        array_push($for_rent_status, $r["rent_status_title"]);
                    }
                    //27 有电离线 offline_status 离线状态 0-在线 100-离线
                    //有电离线 offline_status 离线状态 0-在线 100-离线
                    if ($rent_content['offline_status'] == 100 && $rent_content["lack_power_status"] != 400) {
                        //27 有电离线
                        $r["rent_status"] = 27;
                        $r["rent_status_title"] = "有电离线";
                        array_push($Off_hire_status, $r["rent_status_title"]);
                        array_push($all_rent_status, $r["rent_status"]);
                        array_push($all_rent_status_title, $r["rent_status_title"]);
                    }

                    //调度(越界、还车区域外、已检修、2日无单、5日无单)
                    //21 五日无单
                    $five_noorder = false;
                    $dispatch_flag=false;
                    $order_content5=$this->getHasOrder5ByID($rent_content_id);
                    if (!$order_content5) {
                        //21 五日无单 上架时间大于5
                        if ($this->diffBetweenTwoDays(time(), $rent_content['sell_time']) >= 5&&($rent_content['sell_status'] == 1||$rent_content["reserve_status"]==100)) {
                            //$r["rent_status"] = 21;
                            //$r["rent_status_title"] = "五日无单";
                            $d["rent_status"] =$r["rent_status"] = 21;
                            $d["rent_status_title"] =$r["rent_status_title"] = "五日无单";
                            array_push($for_rent_status, $r["rent_status_title"]);
                            array_push($all_rent_status, $r["rent_status"]);
                            array_push($all_rent_status_title, $r["rent_status_title"]);
                            $five_noorder = true;
                            $dispatch_flag=true;
                        }
                    }
                    //7 两日无单
                    if (!$five_noorder) {
                        $order_content = $this->getHasOrderByID($rent_content_id);
                        if (!$order_content) {
                            //上架时间大于2
                            if ($this->diffBetweenTwoDays(time(), $rent_content['sell_time']) >= 2&&($rent_content['sell_status'] == 1||$rent_content["reserve_status"]==100)) {
                                //7 两日无单
                                //$r["rent_status"] = 7;
                                //$r["rent_status_title"] = "两日无单";
                                $d["rent_status"] =$r["rent_status"] = 7;
                                $d["rent_status_title"] =$r["rent_status_title"] = "两日无单";
                                array_push($for_rent_status, $r["rent_status_title"]);
                                array_push($all_rent_status, $r["rent_status"]);
                                array_push($all_rent_status_title, $r["rent_status_title"]);
                                $dispatch_flag=true;
                            }
                        }
                    }
                    //20 已检修需调度 repaire_status 维修状态 0-不需要修 100-小修 101-已检修需调度 200-大修 300-寻回需维修
                    if ($rent_content['repaire_status'] == 101) {
                        //10 越界
                        //$r["rent_status"] = 20;
                        //$r["rent_status_title"] = "已检修需调度";
                        $d["rent_status"] =$r["rent_status"] = 20;
                        $d["rent_status_title"] =$r["rent_status_title"] = "已检修需调度";
                        array_push($Off_hire_status, $r["rent_status_title"]);
                        array_push($all_rent_status, $r["rent_status"]);
                        array_push($all_rent_status_title, $r["rent_status_title"]);
                        $dispatch_flag=true;
                    }
                    //10 越界 out_status 越界状态 0-界内 100-网点外 200-界外
                    //if ($rent_content['out_status'] == 200 && $rent_content['repaire_status'] == 0) {
                    if ($rent_content['out_status'] == 200) {
                        //10 越界
                        //$r["rent_status"] = 10;
                        //$r["rent_status_title"] = "越界";
                        $d["rent_status"] =$r["rent_status"] = 10;
                        $d["rent_status_title"] =$r["rent_status_title"] = "越界";
                        array_push($Off_hire_status, $r["rent_status_title"]);
                        array_push($all_rent_status, $r["rent_status"]);
                        array_push($all_rent_status_title, $r["rent_status_title"]);
                        $dispatch_flag=true;
                    }
                    //19 还车区域外 out_status 越界状态 0-界内 100-网点外 200-界外
                    //if ($rent_content['out_status'] == 100 && $rent_content['repaire_status'] == 0) {
                    if ($rent_content['out_status'] == 100) {
                        //19 还车区域外
                        //$r["rent_status"] = 19;
                        //$r["rent_status_title"] = "还车区域外";
                        $d["rent_status"] =$r["rent_status"] = 19;
                        $d["rent_status_title"] =$r["rent_status_title"] = "还车区域外";
                        array_push($Off_hire_status, $r["rent_status_title"]);
                        array_push($all_rent_status, $r["rent_status"]);
                        array_push($all_rent_status_title, $r["rent_status_title"]);
                        $dispatch_flag=true;
                    }

                    //换电(低电、缺电、馈电、无电离线)
                    $change_flag = false;
                    //查询车辆是否已经换电，Redis有效时间换电后1小时
                    $Key =C("KEY_PREFIX")."changed_rent_content_id:".$rent_content['rent_content_id'];
                    $changed =$redis->get($Key);
                    if(empty($changed)) {
                        //lack_power_status	馈电状态 0-正常 100-低电 200-缺电 300-馈电 400-无电
                        if ($rent_content["lack_power_status"] == 100) {
                            //1 低电 待租且电量大于40%小于等于50%
                            //$c["rent_status"] = $r["rent_status"] = 1;
                            //$c["rent_status_title"] = $r["rent_status_title"] = "低电";
                            $r["rent_status"] = 1;
                            $r["rent_status_title"]= "低电";
                            array_push($for_rent_status, $r["rent_status_title"]);
                            array_push($all_rent_status, $r["rent_status"]);
                            array_push($all_rent_status_title, $r["rent_status_title"]);
                            $change_flag = true;
                        }
                        if ($rent_content["lack_power_status"] == 200) {
                            //2 缺电 待租且电量大于20%小于等于40%
                            //$c["rent_status"] = $r["rent_status"] = 2;
                            //$c["rent_status_title"] = $r["rent_status_title"] = "缺电";
                            $r["rent_status"] = 2;
                            $r["rent_status_title"]= "缺电";
                            array_push($for_rent_status, $r["rent_status_title"]);
                            array_push($all_rent_status, $r["rent_status"]);
                            array_push($all_rent_status_title, $r["rent_status_title"]);
                            $change_flag = true;
                        }
                        if ($rent_content['offline_status'] == 0 && $rent_content["lack_power_status"] == 400) {
                            //4 无电在线 在线且电量为0
                            //$c["rent_status"] = $r["rent_status"] = 4;
                            //$c["rent_status_title"] = $r["rent_status_title"] = "无电在线";
                            $r["rent_status"] = 4;
                            $r["rent_status_title"] = "无电在线";
                            array_push($for_rent_status, $r["rent_status_title"]);
                            array_push($all_rent_status, $r["rent_status"]);
                            array_push($all_rent_status_title, $r["rent_status_title"]);
                            $change_flag = true;
                        }
                        if ($rent_content["lack_power_status"] == 300) {
                            //3 馈电
                            //$c["rent_status"] = $r["rent_status"] = 3;
                            //$c["rent_status_title"] = $r["rent_status_title"] = "馈电";
                            $r["rent_status"]  = 3;
                            $r["rent_status_title"]= "馈电";
                            array_push($Off_hire_status, $r["rent_status_title"]);
                            array_push($all_rent_status, $r["rent_status"]);
                            array_push($all_rent_status_title, $r["rent_status_title"]);
                            $change_flag = true;
                        }
                        //离线 offline_status 离线状态 0-在线 100-离线
                        if ($rent_content['offline_status'] == 100) {
                            if ($rent_content["lack_power_status"] == 400) {
                                //5 无电离线 电量低于等于10%且离线
                                //$c["rent_status"] = $r["rent_status"] = 5;
                                //$c["rent_status_title"] = $r["rent_status_title"] = "无电离线";
                                $r["rent_status"]= 5;
                                $r["rent_status_title"] = "无电离线";
                                array_push($Off_hire_status, $r["rent_status_title"]);
                                array_push($all_rent_status, $r["rent_status"]);
                                array_push($all_rent_status_title, $r["rent_status_title"]);
                                $change_flag = true;
                            }
                        }
                    }
                    //回库(存在故障、已寻回、无效盒子、非稳盒子)-----------------
                    //16 已寻回 repaire_status 维修状态 0-不需要修 100-小修 200-大修 300-寻回需维修
                    if ($rent_content['repaire_status'] == 300) {
                        //16 已寻回
                        $r["rent_status"] = 16;
                        $r["rent_status_title"] = "已寻回";
                        array_push($Off_hire_status, $r["rent_status_title"]);
                        array_push($all_rent_status, $r["rent_status"]);
                        array_push($all_rent_status_title, $r["rent_status_title"]);
                    }
                    //15 存在故障 repaire_status 维修状态 0-不需要修 100-小修 200-大修 300-寻回需维修
                    if ($rent_content['repaire_status'] == 200) {
                        //15 存在故障
                        $r["rent_status"] = 15;
                        $r["rent_status_title"] = "存在故障";
                        array_push($Off_hire_status, $r["rent_status_title"]);
                        array_push($all_rent_status, $r["rent_status"]);
                        array_push($all_rent_status_title, $r["rent_status_title"]);
                    }
                    if ($unstable && in_array($rent_content['rent_content_id'], $unstable)) {
                        //18 非稳盒子
                        $r["rent_status"] = 18;
                        $r["rent_status_title"] = "非稳盒子";
                        array_push($for_rent_status, $r["rent_status_title"]);
                        array_push($all_rent_status, $r["rent_status"]);
                        array_push($all_rent_status_title, $r["rent_status_title"]);
                    }
                    //$invalid if ($rent_content["gis_lat"] <= 0 || $rent_content["gis_lng"] <= 0) {
                    if ($invalid && in_array($rent_content['rent_content_id'], $invalid)) {
                        //17 无效盒子
                        $r["rent_status"] = 17;
                        $r["rent_status_title"] = "无效盒子";
                        array_push($for_rent_status, $r["rent_status_title"]);
                        array_push($all_rent_status, $r["rent_status"]);
                        array_push($all_rent_status_title, $r["rent_status_title"]);
                    }
                }
                if ($r) {
                    foreach ($task_type as $k1 => $v1) {
                        /*if ($c && $v1["code_id"] == $c["rent_status"]) {
                            $c["total_money"] = $c["task_money"] = intval($v1["price"]);;
                            $c["task_type"] = $v1["task_id"];
                            $c["level"] = $v1["level"];
                            $c["task_type_title"] = $v1["task_title"];
                        }*/
                        if ($d && $v1["code_id"] == $d["rent_status"]) {
                            $d["total_money"] = $d["task_money"] = intval($v1["price"]);;
                            $d["task_type"] = $v1["task_id"];
                            $d["level"] = $v1["level"];
                            $d["task_type_title"] = $v1["task_title"];
                        }
                        if ($v1["code_id"] == $r["rent_status"]) {
                            $r["total_money"] = $r["task_money"] = intval($v1["price"]);;
                            $r["task_type"] = $v1["task_id"];
                            $r["level"] = $v1["level"];
                            $r["task_type_title"] = $v1["task_title"];
                        }
                    }
                    //echo "task_type".$r["task_type"];
                    //if ($r["task_type"] == 3 && $change_flag) {
                    //task_type 换电
                    if ($r["task_type"] == 4 && $dispatch_flag) {
                        array_push($result, $d);
                    }
                    array_push($result, $r);
                }
                $result = $this->arraySort($result, 'level', 'asc');
                $result = array_values($result);
            }
            $array_result=array("code" => 1, "message" => "任务查询成功","all_rent_status"=>$all_rent_status,"all_rent_status_title"=>$all_rent_status_title,"Off_hire_status"=>$Off_hire_status,"for_rent_status"=>$for_rent_status,"car_item_id"=>$car_item_id,"rent_content_id"=>$rent_content_id,"task"=>$result,"plate_no"=>$plate_no,"gis_lng"=>$gis_lng,"gis_lat"=>$gis_lat);
            if($test==1){
                echo "<pre>";print_r($array_result);
            }
            return $array_result;
        }else{
            return array("code" => 0, "message" => "车辆暂时没有任务");
        }
    }

    public function getPersonalTask($corporation_id,$user_id){
        $map["o.corporation_id"] =$corporation_id;
        $map["o.verify_status"] = ["in",[1,2]];
        $map["o.is_special"] =0;
        $map["o.task_id"] =array('gt',0);
        $map["o.uid"]=$user_id;
        $personalRent=M("rent_content")->alias("rn")
            ->field("o.rent_content_id,o.task_id task_type,dt.title task_type_title,o.type,
            IFNULL(s.latitude,0) gis_lat,IFNULL(s.longitude,0) gis_lng,
            o.verify_status,o.price task_money,rn.car_info_id,
            rn.car_item_id,cid.imei,civ.plate_no,rn.status,rn.sell_status,cn.model_id")
            ->where($map)
            ->join("baojia_mebike.dispatch_order o ON o.rent_content_id=rn.id","left")
            ->join("baojia_mebike.dispatch_task dt ON dt.id=o.task_id","left")
            ->join("car_info cn on cn.id=rn.car_info_id","left")
            ->join("car_model cm ON cm.id = cn.model_id","left")
            ->join("car_item car ON car.id = rn.car_item_id","left")
            ->join("car_item_verify civ on civ.car_item_id=rn.car_item_id","left")
            ->join("car_item_device cid ON cid.car_item_id = rn.car_item_id")
            ->join("fed_gps_status s ON s.imei=cid.imei","left")
            ->select();
        return $personalRent;
    }

    /**
     * 根据运营公司ID和城市ID查询车辆
     * @param int $city_id
     * @param int $corporation_id
     * @param int $test
     * @return bool|mixed|string
     */
    public function getXiaomi($city_id=1,$corporation_id=2118,$test=0){
        /*$redis = new \Redis();
        $redis->connect(C("COMMON_REDIS_HOST"), C("COMMON_REDIS_PORT"));
        $Key = C("KEY_PREFIX") . "xiaomi_rent_content:" . $city_id . "_" . $corporation_id;
        $rent_content = $redis->get($Key);
        if (empty($rent_content)) {*/
            $corporation=M("corporation")->where("parent_id={$corporation_id}")->field("id")->select();
            $map["rn.corporation_id"] = ["in",array_column($corporation, "id")];
            $map["rn.sort_id"] =112;
            $map["rn.status"] =2;
//            $map["rca.hour_count"] =array('neq',1);
            $map["rn.city_id"]=$city_id;
            $map["mbs.storage_status"] =0;
            $map["mbs.transport_status"] =0;
            $map["mbs.seized_status"] =0;
            $map["mbs.dispatch_status"] =0;
            $rent_content=M("rent_content")->alias("rn")
                ->field("rn.id rent_content_id,rn.car_info_id,rn.car_item_id,cid.imei,civ.plate_no,IFNULL(s.latitude,0) gis_lat,IFNULL(s.longitude,0) gis_lng,
            IFNULL(ms.no_mileage_num,0) no_mileage_num,rn.status,rn.sell_status,cn.model_id,mbs.reserve_status,mbs.special_status,
            mbs.offline_status,mbs.out_status,mbs.lack_power_status,mbs.transport_status,mbs.seized_status,mbs.dispatch_status,
            mbs.repaire_status,mbs.rent_status,mbs.damage_status,UNIX_TIMESTAMP(mbs.sell_time) sell_time,mbs.search_status")
                ->join("mebike_status mbs ON mbs.rent_content_id=rn.id", "left")
//                ->join("rent_content_avaiable rca ON rn.id = rca.rent_content_id", "left")
                ->join("mileage_statistics ms ON ms.rent_content_id = rn.id", "left")
                ->join("car_info cn on cn.id=rn.car_info_id", "left")
                ->join("car_model cm ON cm.id = cn.model_id", "left")
                ->join("car_item car ON car.id = rn.car_item_id", "left")
                ->join("car_item_verify civ on civ.car_item_id=rn.car_item_id", "left")
                ->join("car_item_device cid ON cid.car_item_id = rn.car_item_id")
                ->join("fed_gps_status s ON s.imei=cid.imei","left")
                ->where($map)->select();
            //查询非运输途中、扣押、非入库、非调度中
            \Think\Log::write("getXiaomi 查询数据库", "INFO");
            /*if (is_array($rent_content)) {
                $value = json_encode($rent_content);
                $redis->set($Key, $value);
                $redis->expire($Key, 120);
            }
        } else {
            if ($test == 1) {
                echo "Redis结果<pre>";
                print_r($rent_content);
            }
            $rent_content = json_decode($rent_content, true);
            \Think\Log::write("getXiaomi 查询Redis,key=".$Key, "INFO");
        }*/
        return $rent_content;
    }

    /**
     * 查询运营公司任务类型配置
     * @param int $corporation_id
     * @param int $test
     * @return bool|mixed|string
     */
    public function getTaskType($corporation_id=2118,$test=0){
        $redis = new \Redis();
        $redis->connect(C("COMMON_REDIS_HOST"), C("COMMON_REDIS_PORT"));
        $Key =C("KEY_PREFIX")."TaskType:".$corporation_id;
        $value =$redis->get($Key);
        if(empty($value)) {
            $sql="SELECT dct.id,dct.code_id,dct.task_id,dct.data_type,dct.corporation_id,
            dct.price,dct.show,dt.title task_title,dt.level,dc.title status_title 
            FROM baojia_mebike.dispatch_code_task dct 
            LEFT JOIN baojia_mebike.dispatch_task dt ON dt.id=dct.task_id
            LEFT JOIN baojia_mebike.dispatch_code dc ON dc.id=dct.code_id
            WHERE dct.corporation_id={$corporation_id} AND dct.data_type=0 ORDER BY dt.level DESC,dct.price DESC";
            //非运输途中和非入库不查
            $rent_content = M()->query($sql);
            if (is_array($rent_content)) {
                $value=json_encode($rent_content);
                $redis->set($Key,$value);
                $redis->expire($Key, 120);
                if($test==1){
                    echo "查询结果<pre>";print_r($rent_content);
                }
                return $rent_content;
            }
        }else{
            if($test==1) {
                echo "Redis结果<pre>";print_r($value);
            }
            $value = json_decode($value, true);
        }
        return $value;
    }

    /**
     * 查询最近1小时完成换电任务的车辆
     * @param $corporation_id
     * @return array|mixed
     */
    public function getChanged($corporation_id){
        $changed = M("operation_logging")
            ->where("corporation_id={$corporation_id} and status=1 and operate=44 and time>=(UNIX_TIMESTAMP(NOW())-3600)")
            ->field("rent_content_id")->select();
        if ($changed && is_array($changed)) {
            $changed = array_column($changed, "rent_content_id");
        }
        return $changed;
    }

    /**
     * 查询最近1小时内完成检修任务的车辆
     * @param $corporation_id
     * @return array|mixed
     */
    public function getInspectioned($corporation_id){
        $inspectioned = M("operation_logging")
            ->where("corporation_id={$corporation_id} and status=1 and operate=30 and time >=(UNIX_TIMESTAMP(NOW())-3600)")
            ->field("rent_content_id")->select();
        if ($inspectioned && is_array($inspectioned)) {
            $inspectioned = array_column($inspectioned, "rent_content_id");
        }
        return $inspectioned;
    }

    /**
     * 查询出租中车辆
     * @param $corporation_id
     * @return mixed
     */
    public function getInRenting($corporation_id){
//        $corporation=M("corporation")->where("parent_id={$corporation_id}")->field("id")->select();
//        $map["rc.corporation_id"] = ["in",array_column($corporation, "id")];
//        $map["rca.hour_count"] =1;
//        $in_renting = M("rent_content_avaiable")->alias("rca")
//            ->join("rent_content rc ON rc.id=rca.rent_content_id","left")
//            ->where($map)->field("rca.rent_content_id")->select();
//        if ($in_renting && is_array($in_renting)) {
//            $in_renting = array_column($in_renting, "rent_content_id");
//        }
        $corporation=M("corporation")->where("parent_id={$corporation_id}")->field("id")->select();
        if($corporation){
            $map["rc.corporation_id"] = ["in",array_column($corporation, "id")];
        }
        $map["tr.rent_type"] = 3;
        $map["_string"] = "tr.STATUS>= 10100 AND tr.STATUS < 80200 AND tr.STATUS!=10301";
        $map["rc.sort_id"] = 112;
        $in_renting = M("trade_order")->alias("tr")
            ->join("rent_content rc ON rc.id=tr.rent_content_id","left")
            ->where($map)->field("rc.id")->select();
        if ($in_renting && is_array($in_renting)) {
            $in_renting = array_column($in_renting, "id");
        }
        return $in_renting;
    }

    /**
     * 查询已派给他人和他人已抢单的车辆
     * @param $corporation_id
     * @param $user_id
     * @return array|mixed
     */
    public function getDispatched($corporation_id,$user_id){
        $dispatched =M("baojia_mebike.dispatch_order")->field("rent_content_id")
            ->where("verify_status IN(1,2) AND is_special=0 AND task_id>0 AND uid<>{$user_id} AND corporation_id={$corporation_id}")
            ->select();
        if ($dispatched && is_array($dispatched)) {
            $dispatched = array_column($dispatched, "rent_content_id");
        }
        return $dispatched;
    }

    /**
     * 查询非稳盒子 25
     * @param int $corporation_id
     * @param int $test
     * @return array|bool|mixed|string
     */
    public function getUnstable($corporation_id=2118,$city_id=1,$test=0){
        $redis = new \Redis();
        $redis->connect(C("COMMON_REDIS_HOST"), C("COMMON_REDIS_PORT"));
        $Key =C("KEY_PREFIX")."unstable_".$corporation_id;
        $value =$redis->get($Key);
        //echo "<pre>";print_r($value);die;
        if(empty($value)) {
            $order_content=[];
            $link=C("STATISTICS_LINK")."/mebike/newlist.do?cityId={$city_id}&companyId={$corporation_id}&key=25";
            //$res = $taskSearch->curl_request($link);
            $res = file_get_contents($link);
            $res = json_decode($res);
            if($res) {
                foreach ($res->value as $k => $v) {
                    array_push($order_content, $v->rentid);
                }
            }
            if ($order_content) {
                //$value=json_encode($order_content[0]);
                $value=json_encode($order_content);
                $redis->set($Key,$value);
                $redis->expire($Key, 600);
                if($test==1) {
                    echo "查询结果<pre>";print_r($order_content);
                }
            }
            return $order_content;
        }else{
            $value = json_decode($value, true);
        }
        if($test==1) {
            echo "Redis结果<pre>";print_r($value);
        }
        return $value;
    }

    /**
     * 查询无效盒子 28
     * @param int $corporation_id
     * @param int $test
     * @return array|bool|mixed|string
     */
    public function getInvalid($corporation_id=2118,$city_id=1,$test=0){
        $redis = new \Redis();
        $redis->connect(C("COMMON_REDIS_HOST"), C("COMMON_REDIS_PORT"));
        $Key =C("KEY_PREFIX")."invalid_".$corporation_id;
        $value =$redis->get($Key);
        //echo "<pre>";print_r($value);die;
        if(empty($value)) {
            $order_content=[];
            $link=C("STATISTICS_LINK")."/mebike/newlist.do?cityId={$city_id}&companyId={$corporation_id}&key=28";
            $res = file_get_contents($link);
            $res = json_decode($res);
            if($res) {
                foreach ($res->value as $k => $v) {
                    array_push($order_content, $v->rentid);
                }
            }
            if ($order_content) {
                $value=json_encode($order_content);
                $redis->set($Key,$value);
                $redis->expire($Key, 600);
                if($test==1) {
                    echo "查询结果<pre>";print_r($order_content);
                }
            }
            return $order_content;
        }else{
            $value = json_decode($value, true);
        }
        if($test==1) {
            echo "Redis结果<pre>";print_r($value);
        }
        return $value;
    }

    /**
     * 查询五日无单 23
     * @param int $city_id
     * @param int $corporation_id
     * @param int $test
     * @return array|bool|mixed|string
     */
    public function getFiveDayNoOrder($city_id=255,$corporation_id=2719,$test=0){
        $order_content=[];
        $link=C("STATISTICS_LINK")."/mebike/newlist.do?cityId={$city_id}&companyId={$corporation_id}&key=23";
        $res = file_get_contents($link);
        $res = json_decode($res);
        //echo "<pre>";print_r($res);
        if($res) {
            foreach ($res->value as $k => $v) {
                array_push($order_content, $v->rentid);
            }
        }
        return $order_content;
    }

    /**
     * 查询两日无单 22
     * @param int $city_id
     * @param int $corporation_id
     * @param int $test
     * @return array|bool|mixed|string
     */
    public function getTwoDayNoOrder($city_id=255,$corporation_id=2719,$test=0){
        $order_content=[];
        $link=C("STATISTICS_LINK")."/mebike/newlist.do?cityId={$city_id}&companyId={$corporation_id}&key=22";
        $res = file_get_contents($link);
        $res = json_decode($res);
        if($res) {
            foreach ($res->value as $k => $v) {
                array_push($order_content, $v->rentid);
            }
        }
        return $order_content;
    }

    /**
     * 根据运营公司ID和城市ID查询两日有单的车辆rent_content_id
     * @param int $city_id
     * @param int $corporation_id
     * @param int $test
     * @return array|bool|mixed|string
     */
    public function getHasOrder($city_id=255,$corporation_id=2719,$test=0){
        $redis = new \Redis();
        $redis->connect(C("COMMON_REDIS_HOST"), C("COMMON_REDIS_PORT"));
        $Key =C("KEY_PREFIX")."two_day_has_order_".$city_id."_".$corporation_id;
        \Think\Log::write("getHasOrder key:" . $Key, "INFO");
        $value =$redis->get($Key);
        if(empty($value)) {
            $strSqlOrder = "SELECT DISTINCT rent_content_id FROM trade_order 
                        WHERE create_time > (UNIX_TIMESTAMP(NOW()) - 86400 * 2)
                        and rent_type = 3 AND rent_content_id in(
                        SELECT rn.id FROM rent_content rn
                        LEFT JOIN car_item_verify civ on rn.car_item_id=civ.car_item_id
                        LEFT JOIN corporation cor on cor.id=rn.corporation_id
                        WHERE rn.sort_id =112 and rn.status=2 
                        AND rn.city_id={$city_id} AND cor.parent_id={$corporation_id})";
            $order_content = M()->query($strSqlOrder);
            if ($order_content) {
                $order_content = array_column($order_content, "rent_content_id");
                $value=json_encode($order_content);
                $redis->set($Key,$value);
                $redis->expire($Key, 120);
                if($test==1) {
                    echo "查询结果<pre>";print_r($order_content);die;
                }
                return $order_content;
            }
        }else{
            $value = json_decode($value, true);
        }
        if($test==1) {
            echo "Redis结果<pre>";print_r($value);die;
        }
        return $value;
    }

    /**
     * 根据运营公司ID和城市ID查询五日有单的车辆rent_content_id
     * @param int $city_id
     * @param int $corporation_id
     * @param int $test
     * @return array|bool|mixed|string
     */
    public function getHasOrder5($city_id=255,$corporation_id=2719,$test=0){
        $redis = new \Redis();
        $redis->connect(C("COMMON_REDIS_HOST"), C("COMMON_REDIS_PORT"));
        $Key =C("KEY_PREFIX")."five_day_has_order_".$city_id."_".$corporation_id;
        \Think\Log::write("getHasOrder5 key:" . $Key, "INFO");
        $value =$redis->get($Key);
        //echo "<pre>";print_r($value);die;
        if(empty($value)) {
            $strSqlOrder = "SELECT DISTINCT rent_content_id FROM trade_order 
                        WHERE create_time > (UNIX_TIMESTAMP(NOW()) - 86400 * 5)
                        AND rent_type = 3 AND rent_content_id in(
                        SELECT rn.id FROM rent_content rn
                        LEFT JOIN car_item_verify civ on rn.car_item_id=civ.car_item_id
                        LEFT JOIN corporation cor on cor.id=rn.corporation_id
                        WHERE rn.sort_id =112 AND rn.status=2 
                        AND rn.city_id={$city_id} AND cor.parent_id={$corporation_id})";
            $order_content = M()->query($strSqlOrder);
            //echo "<pre>";print_r($order_content);
            if ($order_content) {
                $order_content = array_column($order_content, "rent_content_id");
                $value=json_encode($order_content);
                $redis->set($Key,$value);
                $redis->expire($Key, 120);
                if($test==1) {
                    echo "查询结果<pre>";print_r($order_content);die;
                }
                return $order_content;
            }
        }else{
            $value = json_decode($value, true);
        }
        if($test==1) {
            echo "Redis结果<pre>";print_r($value);die;
        }
        return $value;
    }

    /**
     * 查询车辆是否两日无单
     * @param int $rent_content_id
     * @return bool
     */
    public function getHasOrderByID($rent_content_id=5883885){
        //排除预约单
        $strSqlOrder = "SELECT DISTINCT rent_content_id FROM trade_order WHERE create_time > (UNIX_TIMESTAMP(NOW()) - 86400 * 2) AND rent_type = 3 AND status>50200 AND hand_over_state>10200 AND rent_content_id={$rent_content_id}";
        $order_content = M()->query($strSqlOrder);
        if ($order_content&&is_array($order_content)) {
            return true;
        }
        return false;
    }

    /**
     * 查询车辆是否五日无单
     * @param int $rent_content_id
     * @return bool
     */
    public function getHasOrder5ByID($rent_content_id=5883885){
        //排除预约单
        $strSqlOrder = "SELECT DISTINCT rent_content_id FROM trade_order WHERE create_time > (UNIX_TIMESTAMP(NOW()) - 86400 * 5) AND rent_type = 3 AND status>50200 AND hand_over_state>10200 AND rent_content_id={$rent_content_id}";
        $order_content = M()->query($strSqlOrder);
        if ($order_content&&is_array($order_content)) {
            return true;
        }
        return false;
    }

    /**
     * curl 请求
     * @param $url                      访问的URL
     * @param string $post              post数据(不填则为GET)
     * @param string $cookie            提交的$cookies
     * @param int $returnCookie         是否返回$cookies
     * @return mixed|string
     */
    function curl_request($url,$post='',$cookie='', $returnCookie=0){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl, CURLOPT_REFERER, "http://XXX");
        if($post) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        if($cookie) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_error($curl);
        }
        curl_close($curl);
        if($returnCookie){
            list($header, $body) = explode("\r\n\r\n", $data, 2);
            preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
            $info['cookie']  = substr($matches[1][0], 1);
            $info['content'] = $body;
            return $info;
        }else{
            return $data;
        }
    }

    /**
     * 逆地理编码
     * @param $lng
     * @param $lat
     * @param string $default
     * @return mixed|string
     */
    /*public function getAmapAddress($lng,$lat,$default=''){
        $res = file_get_contents('http://restapi.amap.com/v3/geocode/regeo?output=JSON&location='.$lng.','.$lat.'&key=7461a90fa3e005dda5195fae18ce521b&s=rsv3&radius=1000&extensions=base');
        $res = json_decode($res);
        if($res->info == 'OK'){
            $province=$res->regeocode->addressComponent->province;
            $city=$res->regeocode->addressComponent->city;
            $district=$res->regeocode->addressComponent->district;
            $default=$res->regeocode->formatted_address;
            if($province) {
                $default = str_replace($province, "", $default);
            }
            if(count($city)){
                $default=str_replace($city,"",$default);
            }
            if($district){
                $default=str_replace($district,"",$default);
            }
        }
        return $default;
    }*/

    /**
     * 数组排序
     * @param $arr
     * @param $keys
     * @param string $type
     * @return array
     */
    public function arraySort($arr, $keys, $type = 'asc') {
        $keysvalue = $new_array = array();
        foreach ($arr as $k => $v){
            $keysvalue[$k] = $v[$keys];
        }
        $type == 'asc' ? asort($keysvalue) : arsort($keysvalue);
        reset($keysvalue);
        foreach ($keysvalue as $k => $v) {
            $new_array[$k] = $arr[$k];
        }
        return $new_array;
    }

    /**
     * 两个时间相差天数
     * @param $day1
     * @param $day2
     * @return float|int
     */
    public function diffBetweenTwoDays ($day1, $day2)
    {
        if ($day1 < $day2) {
            $tmp = $day2;
            $day2 = $day1;
            $day1 = $tmp;
        }
        return ($day1-$day2)/86400;
    }
}
