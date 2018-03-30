<?php
namespace Api\Controller;

class TestController extends BController
{
    private $box_config = 'mysqli://api-baojia:CSDV4smCSztRcvVb@10.1.11.14:3306/baojia_box';
    private $aliyun_config='mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc';

    public function index()
    {
        $this->display('index');
    }

    public function getOrderTrack($order_no=""){
        $gps = new \Api\Model\GpsModel();
        //根据订单查询轨迹
        $result['status']=0;
        $result['info']='获取失败';
        //trade_order status 50200=违押支付预定完成 50200=取车 80200=还车
        //trade_order hand_over_state 10200=取车	20100=还车  20200=租客结算
        $order=M('trade_order',null,$this->baojia_config)->where("order_no='{$order_no}'")->find();
        //echo "<pre>";print_r($order);die;
        if($order&& $order['hand_over_state']>1){
            // $order['status']>=50200
            $result['rent_content_id']=$order['rent_content_id'];
            $return_mode=M('rent_content_return_config',null,$this->baojia_config)->where('rent_content_id='.$order['rent_content_id'])->field('return_mode')->find();
            $result['return_mode']=$return_mode['return_mode'];
            if ($return_mode['return_mode'] == 4) {
                //自由还
                $result["return_mode_test"] ="自由还";
            } else if($return_mode['return_mode'] == 1){
                //原点还
                $result["return_mode_test"] ="原点还";
            }else {
                //区域还
                $result["return_mode_test"] ="区域还";
            }
            $result['car_item_id']=$order['car_item_id'];
            $plate_no=M('car_item_verify',null,$this->baojia_config)->where('car_item_id='.$order['car_item_id'])->field('plate_no')->find();
            $result['plate_no']=$plate_no['plate_no'];
            $result['order_status']=$order['status'];
            $result['order_id']=$order['id'];
            $device=M('car_item_device',null,$this->baojia_config)->where('car_item_id='.$order['car_item_id'])->field('imei')->find();
            $result['imei']=$device['imei'];
            $carReturn = new \Api\Logic\CarReturn();
            //取redis数据 获取车辆当前定位
            $gps_status_info=$carReturn->getXiaomiPosition($device['imei']);
            if($gps_status_info&&$gps_status_info['latitude']>0&&$gps_status_info['longitude']>0){
                $gd = $gps->gcj_encrypt($gps_status_info['latitude'],$gps_status_info['longitude']);
                $result['current_location']=[$gd['lon'],$gd['lat']];
            }
            $result['order_id']=$order["id"];
            $start_time=$order['begin_time'];
            $end_time=$order['end_time'];
            $result['start']=$start_time;
            $result['end']=$end_time;
            $result['start_time']=date("Y-m-d H:i:s",$start_time);
            $result['end_time']=date("Y-m-d H:i:s",$end_time);
            $logs =M("car_item_device_op_log")
                ->field("device_id,imei,op_command,op_status,FROM_UNIXTIME(start_time) start_time,op_result")
                ->where(["order_id"=>$order["id"]])
                ->order("start_time")
                ->select();
            //echo M("car_item_device_op_log")->getLastSql();
            //echo "<pre>";print_r($logs);die;
            $result['logs']=$logs;
            $corporation_id =M("rent_content",null,$this->baojia_config)->where(["id"=>$order["rent_content_id"]])->getField("corporation_id");
            //获取取车地址
            $car_return_info=M('trade_order_car_return_info',null,$this->baojia_config)->where('order_id='.$order["id"])->find();
            if($car_return_info && $car_return_info['take_lng']>0 && $car_return_info['take_lat']>0){
                $liststart['id']=0;
                $liststart['lat']=(float)$car_return_info['take_lat'];
                $liststart['lon']=(float)$car_return_info['take_lng'];
                $liststart['speed']=0;
                $liststart['course']=0;
                $liststart['accstatus']=0;
                $liststart['datetime']=date('H:i:s',$order['begin_time']);
            }

            $result['dots']=[];
            //获取还车地址
            $rtp=M('trade_order_return_corporation',null,$this->baojia_config)->where('order_id='.$order["id"])->order('update_time desc')->find();
            //echo "<pre>";print_r($rtp);die;
            if($rtp) {
                $rtppoint = json_decode($rtp['point'], true);
                if($rtppoint['d_gis_lng']>0&&$rtppoint['d_gis_lat']>0) {
                    //获取还车位置最近的半价还车网点
                    /*$parent_id=M("corporation",null,$this->baojia_config)->where(["id"=>$corporation_id])->getField("parent_id");
                    $dots=M('corporation')->alias('a')
                        ->join("corporation_car_return_config b ON a.id = b.corporation_id","left")
                        ->field("a.id,a.NAME,a.gis_lat,a.gis_lng,a.short_address,a.detaile_address,b.return_radius,ROUND(st_distance(point(a.gis_lng,a.gis_lat),point({$rtppoint['d_gis_lng']},{$rtppoint['d_gis_lat']}))*111195,0) AS distance")
                        ->where("a.type = 4 AND a.data_status = 1 AND a. STATUS = 1 AND a.parent_id ={$parent_id}")
                        ->order('distance')
                        ->limit(1)
                        ->select();
                    if (!empty($dots)) {
                        foreach ($dots as $k =>$v) {
                            $dot = $gps->gcj_encrypt($v['gis_lat'],$v['gis_lng']);
                            $result['dots'][$k]=$v;
                            $result['dots'][$k]['lat']=$dot['lat'];
                            $result['dots'][$k]['lng']=$dot['lon'];
                        }
                    }*/
                    //终点
                    $listend['id']=0;
                    $listend['lat']=(float)$rtppoint['d_gis_lat'];
                    $listend['lon']=(float)$rtppoint['d_gis_lng'];
                    $listend['speed']=0;
                    $listend['course']=0;
                    $listend['accstatus']=0;
                    $listend['datetime']=date('H:i:s',$order['end_time']);
                }
            }
            //获取还车时的围栏数据
            $result['area']=[];
            $result['cor']=[];
            $result['corporation_info']=[];
            $trade_order_return_info=M('baojia_mebike.trade_order_return_log',null,$this->baojia_config)->where('order_id='.$order["id"])->order('version desc')->find();
            //echo "<pre>";print_r($trade_order_return_info);die;//920054914353035
            if($trade_order_return_info){
                $result['area']=json_decode($trade_order_return_info['Polygon'],true);
                if(empty($result['area'])){
                    $result['area']=json_decode($trade_order_return_info['polygon'],true);
                }
                //echo "<pre>";print_r($result['area']);die;
                foreach($result['area'] as $k=>$v){
                    $wlpos = $gps->gcj_encrypt($v[1],$v[0]);
                    $result['area'][$k]=[$wlpos['lon'],$wlpos['lat']];
                }
                $rtppoint = json_decode($trade_order_return_info['corporation_polygon'], true);
                $result['corporation_info']=$rtppoint;
                //echo "<pre>";print_r($trade_order_return_info);die;
                //获取还车时的网点数据  trade_order_return_log corporation_polygon 还车网点区域快照
                if($rtppoint[0]["polygon"]) {
                    //echo "等于0<pre>";print_r($trade_order_return_info);die;
                    $result['cor_type'] ='2';//多边形
                    if ($trade_order_return_info && $trade_order_return_info['corporation_polygon']) {
                        $array_key=array_keys($rtppoint[0]["polygon"]);
                        $result['cor']= $rtppoint[0]["polygon"][$array_key[0]];
                    }
                }else{
                    $result['cor_type'] ='1';//圆形
                    $result['cor']= $rtppoint;
                }
            }
            //获取行驶轨迹
            $details = new \Api\Model\DetailsModel();
            $list=$details->getRoute($device['imei'],$start_time,$end_time);
            if(count($list)>=1 || ($liststart && $listend)){
                $listdata=[];
                $result['status']=1;
                $result['info']='获取成功';
                foreach($list as $k=>$v){
                    $newpos = $gps->gcj_encrypt($v['latitude'],$v['longitude']);
                    $listdata[$k]['id']=$v['id'];
                    $listdata[$k]['lat']=$newpos['lat'];
                    $listdata[$k]['lon']=$newpos['lon'];
                    $listdata[$k]['speed']=$v['speed'];
                    $listdata[$k]['course']=$v['course'];
                    $listdata[$k]['accstatus']=$v['accstatus'];
                    $listdata[$k]['datetime']=date('H:i:s',$v['datetime']);
                }
                if($listdata){
                    $firstdata=current($listdata);
                    $enddata=end($listdata);
                    if($liststart){
                        $liststart['lat']=$firstdata['lat'];
                        $liststart['lon']=$firstdata['lon'];
                    }
                    if($listend){
                        $listend['lat']=$enddata['lat'];
                        $listend['lon']=$enddata['lon'];
                    }
                }
                if(count($result['curpoint'])>0){
                    $listend['lat']=$result['curpoint'][1];
                    $listend['lon']=$result['curpoint'][0];
                }
                array_unshift($listdata,$liststart);
                array_push($listdata,$listend);
                $result['listcount']=count($listdata);
                $result['list']=$listdata;

                //获取可骑行区域
                $result['ridding_area']=[];
                if(empty($result['ridding_area'])){
                    $map = ["car_item_id"=>$order['car_item_id']];
                    $map["device_type"] = ["in",[12,14,16,18]];
                    $car_item_device = M("car_item_device",null,$this->baojia_config)->where($map)->find();
                    $imei = $car_item_device["imei"];
                    $no_zero_imei = ltrim($imei,"0");
                    $ga_list_sql = "SELECT g.id,ga.lat,ga.lng FROM car_group_r cgr
                        INNER JOIN `group` g ON g.id = cgr.groupId
                        INNER JOIN group_area ga ON ga.groupId = g.id
                        WHERE cgr.carId = '{$no_zero_imei}'
                        AND cgr.`status` = 1 AND g.`status` = 1
                        ORDER BY ga.NO";
                    $list = M("",null,$this->aliyun_config)->query($ga_list_sql);
                    if($list) {
                        foreach ($list as $k => $v) {
                            $point = $gps->gcj_encrypt($v['lat'], $v['lng']);
                            $result['ridding_area'][$k] = [$point['lon'], $point['lat']];
                        }
                    }
                }
                //获取所属站点
                $result['polygon']=[];
                $polygon = M('corporation_group',null,$this->baojia_config)->where('status=1 and corporation_id=' .$corporation_id)->find();
                if ($polygon) {
                    $polygonarr = M('corporation_group_area',null,$this->baojia_config)->where('groupId=' . $polygon['id'])->select();
                    $corporation_points = array();
                    foreach ($polygonarr as $k1 => $v1) {
                        array_push($corporation_points, array((float)$v1['lng'], (float)$v1['lat']));
                    }
                    $result['polygon'] = $corporation_points;
                }
            }
        }
        $this->response($result, 'json');
    }

    //App首页地图加载车辆
    public function loadMapBicycle($lngX = 116.396906,$latY = 39.985818,$city = "北京市",$client_id = 218,$version ='2.2.0',$app_id = 218,$qudao_id = 'guanfang',$timestamp = 1499669461000,$device_model = "",$device_os = "",$test=0)
    {
        $time_start = $this->microtime_float();
        $lngX = $_REQUEST['lngX'];
        $latY = $_REQUEST['latY'];
        $city = $_REQUEST['city'];
        $radius = 10000;
        if (empty($lngX) || empty($latY)) {
            $this->response(["status" => 1004, "msg" => "参数错误,请参考API文档", "showLevel" => 0, "data" => null], 'json');
        }
        if (empty($city)) {
            $city_id = $this->getAmapCity($lngX, $latY);
        } else {
            if (strpos($city, '市')) {
                $city = explode('市', $city)[0];
                $city_id = $this->getCity_id($city);
            }else{
                $city_id = $this->getCity_id($city);
            }
        }

        $gps = new \Api\Logic\GpsCalc();
        $gpsCoordinate = $gps->gcj_decrypt($latY,$lngX);
        $strSql = "select rent.id,rent.car_item_id,s.latitude gis_lat,s.longitude gis_lng,
            civ.plate_no,rent.corporation_id,rcrc.return_mode,cor.name corporation_name,cid.imei,
            ROUND(st_distance(point(s.longitude,s.latitude),point({$gpsCoordinate['lon']},{$gpsCoordinate['lat']}))*111195,0) AS distance
            from rent_content rent
            LEFT JOIN car_item_verify civ ON rent.car_item_id=civ.car_item_id 
            LEFT JOIN rent_content_avaiable rca ON rca.rent_content_id=rent.id
            left join rent_content_return_config rcrc ON rcrc.rent_content_id=rent.id
            LEFT JOIN corporation cor ON cor.id=rent.corporation_id
            JOIN car_item_device cid ON cid.car_item_id=rent.car_item_id
            LEFT JOIN  fed_gps_status s ON s.imei=cid.imei
            where rent.status=2 AND rent.sell_status=1 AND rca.hour_count=0
            AND civ.plate_no like 'DD%' AND rent.sort_id=112 AND rent.car_info_id<>30150
            AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL
            AND rent.city_id={$city_id}
            HAVING distance<{$radius}
            order by distance asc limit 30";
        $car_result = M('')->query($strSql);
        $search_end = $this->microtime_float();
        if($test==1){
            echo M('')->getLastSql();
        }
        $shortestId = $car_result[0]['id'];
        $result = [];
        $result['shortestId'] = (float)$shortestId;
        $price0Count=0;
        $item_count=0;
        $distance=1000;
        foreach ($car_result as $k => $v) {
            $result['groupAndCar'][$v['id']]['id'] = (float)$v['id'];
            $result['groupAndCar'][$v['id']]['carItemId'] = (float)$v['car_item_id'];
            $gdCoordinate = $gps->gcj_encrypt($v['gis_lat'],$v['gis_lng']);
            $result['groupAndCar'][$v['id']]['gisLng'] = (float)$gdCoordinate['lon'];
            $result['groupAndCar'][$v['id']]['gisLat'] = (float)$gdCoordinate['lat'];
            $result['groupAndCar'][$v['id']]['plateNo'] = $v['plate_no'];
            $result['groupAndCar'][$v['id']]['carReturnCode'] = (float)$v['return_mode'];
            $result['groupAndCar'][$v['id']]['corporationName'] = $v['corporation_name'];
            $result['groupAndCar'][$v['id']]['imei'] = $v['imei'];
            $result['groupAndCar'][$v['id']]['isPrice0'] = 0;
            $freeCar = new \Api\Logic\FreeCar();
            if ($freeCar->checkfreecar($v['car_item_id'])) {
                $price0Count++;
                $result['groupAndCar'][$v['id']]['isPrice0'] = 1;
            }

            $item_count++;
            if ($v['distance'] <= $distance) {//如果小于等于1公里 继续
                continue;
            }
            else {
                if ($item_count < 10) {//如果大于1公里 and 小于10
                    $distance = 2000;
                    if ($v['distance'] <= $distance) {//如果小于等于2公里 继续
                        continue;
                    } else {
                        if ($item_count < 10) {//如果大于2公里 and 小于10
                            $distance = 4000;
                            if ($v['distance'] <= $distance) {//如果小于等于4公里  继续
                                continue;
                            } else {
                                if ($item_count < 10) {//如果大于4公里 and 小于10
                                    $distance = 6000;
                                    if ($v['distance'] <= $distance) {//如果小于等于6公里  继续
                                        continue;
                                    } else {
                                        if ($item_count < 10) {//如果大于6公里 and 小于10
                                            $distance = 8000;
                                            if ($v['distance'] <= $distance) {//如果小于等于8公里  继续
                                                continue;
                                            } else {
                                                $distance = 10000;
                                            }
                                        } else {
                                            break;
                                        }
                                    }
                                } else {
                                    break;
                                }
                            }
                        } else {
                            break;
                        }
                    }
                } else {
                    break;
                }
            }
        }
        $result['refreshDistance'] =count($car_result)>0?$distance:$radius;
        $time_end = $this->microtime_float();
        $search_second = round($search_end - $time_start,2);
        $handle_second = round($time_end-$search_end,2);
        $second = round($time_end - $time_start,2);
        $count=count($result['groupAndCar']);
        \Think\Log::write("首页地图加载车辆".$second."秒，返回数据".$count."条，参数：".json_encode($_REQUEST), "INFO");
        if (!empty($result)) {
            $this->response(["status" => 1, "msg" => "success", "showLevel" => 0,"price0Count"=>$price0Count,"count"=>$count,"second"=>$second,"search_second"=>$search_second,"handle_second"=>$handle_second,"data" => $result], 'json');
        } else {
            $this->response(["status" => -1, "msg" => "附近暂无可用车辆", "showLevel" => 0, "data" => null], 'json');
        }
    }

    //车辆详情
    public function details($plate_no = '',$test=0){
        try {
            if (empty($plate_no)) {
                $this->ajaxReturn(["code" => 0, "message" => "参数有误"], 'json');
            }
            if (substr($plate_no, 0, 2) == 'dd') {
                $plate_no = str_replace('dd', 'DD', $plate_no);
            } elseif (substr($plate_no, 0, 2) !== 'DD') {
                $plate_no = 'DD' . $plate_no;
            }
            $car_item_id = M("car_item_verify", null, $this->baojia_config)->where(["plate_no" => $plate_no])->getField("car_item_id");
            $rent_content_id = M("rent_content", null, $this->baojia_config)->where(["car_item_id" => $car_item_id])->getField("id");
            if (empty($rent_content_id)) {
                $this->ajaxReturn(["code" => -1, "message" => "查询无此车辆"], 'json');
            }
            //限行区域
            $groupid = M("car_group_r", "", $this->aliyun_config)->field("groupId")->where("plate_no='{$plate_no}'")->find();
            $driving_area = M("group", "", $this->aliyun_config)->field("name")->where(["id" => $groupid['groupid']])->find(); //查询行驶区域名字
            $userinfo = M("baojia_cloud.group_manager", null, $this->baojia_config)->field("user_id,user_name")->where(["groupId" => $groupid['groupid']])->find(); //查询区长ID名字
            $mobile = M("ucenter_member", null, $this->baojia_config)->field("mobile")->where(["uid" => $userinfo['user_id']])->find();  //查询区长手机号
            //定位时间
            $res = M("rent_content", null, $this->baojia_config)->alias("rc")
                ->join("car_item_verify civ ON civ.car_item_id = rc.car_item_id", "left")
                ->join("rent_content_ext rce ON rce.rent_content_id = rc.id", "left")
                ->join("corporation cc on cc.id = rc.corporation_id", "left")
                ->join("car_item_device cid ON cid.car_item_id = rc.car_item_id", "left")
                ->join("rent_content_return_config rcrc ON rcrc.rent_content_id = rc.id", "left")
                ->join("car_info cn on cn.id=rc.car_info_id", "left")
                ->join("car_item ci on ci.id = rc.car_item_id", "left")
                ->join("car_model cm ON cm.id = cn.model_id", "left")
                ->join("car_item_color cic on cic.id = ci.color", "left")
                ->join("fed_gps_additional fga ON fga.imei = cid.imei", "left")
                ->join("fed_gps_status fgs ON fgs.imei = cid.imei", "left")
                ->join("car_info_picture cip on cip.car_info_id=rc.car_info_id and cip.car_color_id=ci.color", "left")
                ->field("cc.id corporation_id,cc.name,cc.group_type,rcrc.return_mode, rc.sell_status, civ.plate_no, civ.vin, cip.url picture_url, rc.shop_brand, rc.car_item_id, rc.id, rc.car_info_id, rc.status, cid.imei, fgs.latitude gis_lat, fgs.longitude gis_lng, cn.full_name, ci.`sort_name`, cic.color, cid.device_type, fga.residual_battery,fga.datetime")
                ->where("rc.id = {$rent_content_id}  AND rc.sort_id = 112 AND cip.status=2")
                ->select();
            if (empty($res[0]['imei'])) {
                $this->ajaxReturn(["code" => -1, "message" => "未获取到盒子号,请重新请求"], 'json');
            }
            $gps = new \Api\Model\GpsModel();
            $details = new \Api\Model\DetailsModel();
            $info = M("baojia_box.gps_status", null, $this->box_config)->where(["imei" => $res[0]['imei']])->field("id,imei,latitude,longitude,datetime,lastonline")->find();
            $gd = $gps->gcj_encrypt($info["latitude"], $info["longitude"]);
            $info["gd_latitude"] = $gd["lat"];
            $info["gd_longitude"] = $gd["lon"];
            $bd = $gps->bd_encrypt($info["gd_latitude"], $info["gd_longitude"]);
            $info["bd_latitude"] = $bd["lat"];
            $info["bd_longitude"] = $bd["lon"];
            $info["datetime_diff"] = $details->timeDifference($info["datetime"], time()) . "前";
            $info["datetime"] = date("Y-m-d H:i:s", $info['datetime']);
            $info["is_online"] = time() - strtotime($info['lastonline']) > 1200 ? "离线" : "在线";
            $info["location"] = $details->getAmapAddress($info["gd_longitude"], $info["gd_latitude"]);
            $pt = [$info['longitude'], $info['latitude']];
            $in_area_result = $details->isXiaomaInArea($rent_content_id, $pt, $info['imei']);

            $info['is_inarea'] = empty($in_area_result) ? "界外" : "界内";
            $jizhan = M("baojia_box.gps_status_bs", '', $this->box_config)->where(["imei" => $res[0]['imei']])->find();
            $jizhan["gd_latitude"] = $gd["lat"];
            $jizhan["gd_longitude"] = $gd["lon"];
            $jizhan['location'] = $details->getAmapAddress($gd["lon"], $gd["lat"]);
            $order_id = M('baojia_mebike.trade_order_return_log', null, $this->baojia_config)
                ->where(["rent_content_id" => $rent_content_id])
                ->order("id desc")->limit(1)->field("order_id")->find();
            $order_no = "";
            if ($order_id) {
                $order_no = M('trade_order', null, $this->baojia_config)->where(["id" => $order_id['order_id']])->field("order_no")->find();
            }
            $result["order_no"] = $order_no["order_no"];
            //用户最后还车定位轨迹
            $user_return = $details->tradeLine($rent_content_id);
            //echo "<pre>";
            //print_r($user_return); die;
            $result["id"] = $rent_content_id;
            $result["picture_url"] = "http://pic.baojia.com/b/" . $res[0]['picture_url'];
            $result["plate_no"] = $res[0]['plate_no'];
            $result["sell_status"] = $res[0]['sell_status'];
            $is_rating = "待租中";
            if ($res[0]['sell_status'] != 1) {
                $is_rating = "不可租";
            }
            $sell_status_item = [];
            $sell_status_item[0] = "人工停租";
            $sell_status_item[-4] = "馈电停租";
            $sell_status_item[-1] = "馈电停租";
            $sell_status_item[-7] = "越界停租";
            $sell_status_item[101] = "报修下架";
            $sell_status_item[-8] = "维修停租";
            $sell_status_item[-10] = "收回下架";
            $sell_status_item[-100] = "离线停租";
            $sell_status_item[-13] = "待调度";
            $result["return_mode"] = $res[0]['return_mode'];
            $result["group_type"] = $res[0]['group_type'];
            $result["corporation_id"] = $res[0]['corporation_id'];
            if ($res[0]['return_mode'] == 4) {
                //自由还
                $result["return_mode_test"] = "自由还";
            } else if ($res[0]['return_mode'] == 1) {
                //原点还
                $result["return_mode_test"] = "原点还";
            } else {
                //区域还
                $result["return_mode_test"] = "区域还";
            }
            $polygon = M('corporation_group', null, $this->baojia_config)->where('status=1 and corporation_id=' . $res[0]['corporation_id'])->find();
            if ($polygon) {
                $polygonarr = M('corporation_group_area', null, $this->baojia_config)->where('groupId=' . $polygon['id'])->select();
                $corporation_points = array();
                foreach ($polygonarr as $k1 => $v1) {
                    array_push($corporation_points, array((float)$v1['lng'], (float)$v1['lat']));
                }
                $result['polygon'] = $corporation_points;
            }
            /*if($res[0]['group_type']==1){
                $result["group_type_test"] ="圆形";
            }
            if($res[0]['group_type']==2){
                $result["group_type_test"] ="多边形";
            }*/
            isset($sell_status_item[$res[0]['sell_status']]) && $is_rating = $sell_status_item[$res[0]['sell_status']];
            $hasOrder = M("trade_order", null, $this->baojia_config)->where(" rent_content_id={$rent_content_id} and  rent_type=3 and status>=10100 and status<80200 and status<>10301 ")->find();
            if ($hasOrder) {
                $is_rating = "出租中";
            }
            $result["car_status"] = $is_rating;
            $result["device_type"] = $res[0]['device_type'];
            $result["sort_name"] = $res[0]['sort_name'];
            $result["full_name"] = $res[0]['full_name'];
            $result["color"] = $res[0]['color'];
            $result["imei"] = $res[0]['imei'];
            $result["battery_capacity"] = (float)$res[0]['residual_battery'];
            $result["datetime_diff"] = $info['datetime_diff'];
            $result["is_online"] = $info['is_online'];
            $result['user_return'] = $user_return;
            $result['return_location'] = $details->getAmapAddress($user_return['end']['lon'], $user_return['end']['lat']);
            $result["is_inarea"] = $info['is_inarea'];
            $result["car_location"] = $info['location'];
            $result["car_gd_latitude"] = $info["gd_latitude"];
            $result["car_gd_longitude"] = $info["gd_longitude"];
            $result["car_bd_latitude"] = $info["bd_latitude"];
            $result["car_bd_longitude"] = $info["bd_longitude"];
            $result["jizhan_location"] = $jizhan['location'];
            $result["driving_area"] = $driving_area['name'];
            $result["qu_manager"] = $userinfo['user_name'];
            $result["mobile"] = (float)$mobile['mobile'];
            $result["datetime"] = $info['datetime'];
            $result["car_item_id"] = $car_item_id;
            $result["lastonline"] = $info['lastonline'];
            //print_r($result); die;
            \Think\Log::write("查询车辆详情" . json_encode($_POST) . "--" . $res[0]['imei'], "INFO");
            if (is_array($result)) {
                $this->ajaxReturn(["code" => 1, "message" => "请求成功", "data" => $result], 'json');
            } else {
                $this->ajaxReturn(["code" => -1001, "message" => "失败"], 'json');
            }
        }catch (Exception $exception){
            $this->ajaxReturn(["code" => -101, "message" =>"查询失败","exception"=>$exception], 'json');
        }
    }

    public function loadRidingArea($car_item_id="",$gis_lng="",$gis_lat="",$test=0){
        try {

            $map = ["car_item_id"=>$car_item_id];
            $map["device_type"] = ["in",[12,14,16,18]];
            $car_item_device = M("car_item_device",null,$this->baojia_config)->where($map)->find();
            $imei = $car_item_device["imei"];
            $no_zero_imei = ltrim($imei,"0");
            $ga_list_sql = "SELECT g.id,ga.lat,ga.lng FROM car_group_r cgr
                        INNER JOIN `group` g ON g.id = cgr.groupId
                        INNER JOIN group_area ga ON ga.groupId = g.id
                        WHERE cgr.carId = '{$no_zero_imei}'
                        AND cgr.`status` = 1 AND g.`status` = 1
                        ORDER BY ga.NO";
            $list = M("",null,$this->aliyun_config)->query($ga_list_sql);
            if($test) {
                $area = M("car_group_r", null, $this->aliyun_config)
                    ->field("groupId")
                    ->where("carId='{$no_zero_imei}' and status=1")
                    ->find();
                echo "<pre>";
                print_r($area);
            }

            if($list) {
                $area_id = $list[0]["id"];
                $gps = new \Api\Logic\GpsCalc();
                $areapoints = array();
                $points = "";
                foreach ($list as $k => $v) {
                    $point = $gps->gcj_encrypt($v['lat'], $v['lng']);
                    $points .= $point['lon'] . ',' . $point['lat'] . ';';
                    array_push($areapoints,array($point['lon'],$point['lat']));
                }
                $pt = [$gis_lng,$gis_lat];
                $carreturn = new \Api\Logic\CarReturn();
                $isInArea= $carreturn->isInsidePolygon($pt,$areapoints);

                $this->response(['status' => 1, 'info' => '获取数据成功', 'area_id' => $area_id,'in_area'=>$isInArea,'points' => $points,'areapoints'=>$areapoints], "json");
            }else{
                $this->response(["status" => -1, "msg" => "未查询到可骑行区域信息"], 'json');
            }
        }catch (Exception $exception){
            $this->ajaxReturn(["code" => -101, "message" =>"查询失败","exception"=>$exception], 'json');
        }
    }

    public function setLocation($gis_lat,$gis_lng) {
        $mapUrl = "http://api.map.baidu.com/geocoder/v2/?output=json&ak=twxzXHKti7miy47H9uj4jCZo&location=";
        $mapData = $this->createurl($mapUrl . $gis_lat . "," . $gis_lng);
        $mapDataArr = json_decode($mapData, TRUE);
        $province=$mapDataArr["result"]["addressComponent"]["province"];
        $city = $mapDataArr["result"]["addressComponent"]["city"];
        $district = $mapDataArr["result"]["addressComponent"]["district"];
        $street = $mapDataArr["result"]["business"];
        return $mapDataArr["result"]['formatted_address'];
    }

    public function createurl($url,$post_data=array()) {
        if (!$url) {
            return;
        }
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        if($post_data){
            curl_setopt($curl_handle, CURLOPT_POST,1);
            curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $post_data);
        }else{
            curl_setopt($curl_handle, CURLOPT_POST,0);
        }
        curl_setopt($curl_handle, CURLOPT_FAILONERROR, 1);
        curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Proxy Server Check');
        $file_content = curl_exec($curl_handle);
        curl_close($curl_handle);
        return $file_content;
    }
}
?>
