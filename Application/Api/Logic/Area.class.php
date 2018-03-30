<?php
namespace Api\Logic;
use Think\Model;

class Area{
    //判断是否在区域内
    public function isXiaomaInArea($rent_id,$pt,$imei,$has_poly = 0){
        // $rent_info = M("baojia.rent_content",null)->where(["id"=>$rent_id])->find();
        // //非小马单车不判断，默认在区域内
        // if(!in_array($rent_info["sort_id"],[112])){
        //     return true;
        // }
//        $map = ["car_item_id"=>$rent_info["car_item_id"]];
        $map["device_type"] = ["in",[12,14,16,18,9]];
        //$car_item_device = M("baojia.car_item_device",null)->where($map)->find();
        //$imei = $car_item_device["imei"];
        $no_zero_imei = ltrim($imei,"0");
        $aliyun_conn = "mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc";

        $ga_list_sql = "SELECT
                            ga.lat,
                            ga.lng
                        FROM
                            car_group_r cgr
                        INNER JOIN `group` g ON g.id = cgr.groupId
                        INNER JOIN group_area ga ON ga.groupId = g.id
                        WHERE
                            cgr.carId = '{$no_zero_imei}'
                        AND cgr.`status` = 1
                        AND g.`status` = 1
                        ORDER BY
                            ga. NO;
                        ";
        $list = M("",null,$aliyun_conn)->query($ga_list_sql);
        M("baojia.action",null,$this->baojia_config)->find();
        if($list){
            $poly = [];
            foreach ($list as $key => $value) {
                # code...
                $poly[] = [$value["lng"],$value["lat"]];
            }
            if(count($poly) > 3 && $pt[0] > 0 && $pt[1] > 0){
                // var_dump($pt);
                // var_dump($poly);
                $result = $this->isInsidePolygon($pt,$poly);
                return $result;
            }
        }
        if($has_poly == 1){
            return empty($list) ? 0 : 1;
        }
        //此处判断是否在区域内
        return true;
    }
    public function isInsidePolygon($pt, $poly){
        $l = count($poly);
        $j = $l - 1;
        $c = false;
        for ($i = -1; ++$i < $l;$j = $i)
            (($poly[$i][1] <= $pt[1] && $pt[1] < $poly[$j][1]) || ($poly[$j][1] <= $pt[1] && $pt[1] < $poly[$i][1]))
            &&
            ($pt[0] < ($poly[$j][0] - $poly[$i][0]) * ($pt[1] - $poly[$i][1]) / ($poly[$j][1] - $poly[$i][1]) + $poly[$i][0])
            &&
            ($c = !$c);
        return $c;

    }

    public function timediff($ent_time, $start_time) {
        $string = "";
        $time = abs($ent_time - $start_time);
        $start = 0;
        $y = floor($time / 31536000);
        if ($start || $y) {
            $start = 1;
            $time -= $y * 31536000;
            if ($y)
                $string .= $y . "年";
        }
        $m = floor($time / 2592000);
        if ($start || $m) {
            $start = 1;
            $time -= $m * 2592000;
            if ($m)
                $string .= $m . "月";
        }
        $d = floor($time / 86400);
        if ($start || $d) {
            $start = 1;
            $time -= $d * 86400;
            if ($d)
                $string .= $d . "天";
        }
        $h = floor($time / 3600);
        if ($start || $h) {
            $start = 1;
            $time -= $h * 3600;
            if ($h)
                $string .= $h . "时";
        }
        $s = floor($time / (60));
        if ($start || $s) {
            $start = 1;
            $time -= $s * 60;
            if ($s)
                $string .= $s . "分钟";
        }
        if (empty($string)) {
            return abs($ent_time - $start_time) . '秒';
        }
        return $string;
    }
    //判断是否有工单操作当前车辆  1
    /*
     * 1 派单给自己可操作性的车辆 2 派单给自己不可操作
     *  6未指派丢失车辆 3未指派正常车辆 4未指派异常车辆
     * */
    public function check_work($uid="",$plate_no="",$rent_content_id=""){
        $workorder = D("DispatchOrder")->get_one("plate_no = '{$plate_no}' and uid = {$uid} and verify_status in(1,2,3)","uid,verify_status","","create_time desc");
        if(!empty($workorder)){  //指派给自己的车辆
//                if( $workorder["verify_status"] == 1 || $workorder["verify_status"] == 2|| $workorder["verify_status"] == 3){
                    return 1; //有权限操作
//                }else{
//                    return 2; //无权限操作
//                }


        }else{
            $workorder1 = D("DispatchOrder")->get_one("plate_no = '{$plate_no}' and verify_status in(1,2,3)","uid,verify_status","","create_time desc");

            if(empty($workorder1)){ //未派单车辆
                $operate_status = M("rent_sku_hour")->where(["rent_content_id" => $rent_content_id])->getField('operate_status');
                if ($operate_status == 6) {
                    return 6; //丢失车辆
                }
                $a = $this->check_car($plate_no,$rent_content_id);
                if($a == 1){
                    return 3;  //未派单正常车辆
                }else{
                    return 4;  //未派单异常车辆
                }
            }else{
                return 5;// 指派给别人的车辆
            }


        }


    }
    //检查车辆是否是正常车辆
    public function check_car($plate_no="",$rent_content_id=""){
           $a = M("trade_order")
            ->where("create_time > (UNIX_TIMESTAMP(NOW()) - 86400 * 2)and rent_type = 3 and rent_content_id = {$rent_content_id}")->field("id")
            ->select();
           $sql = "SELECT
                    rn.car_info_id,
                    rn.id rent_content_id,
                    rn.sort_id,
                    rn.create_time,
                    rn.car_item_id,
                    civ.plate_no,
                    ms.no_mileage_num,
                    fga.datetime,
                    IFNULL(fga.residual_battery, 0) residual_battery,
                    rn.corporation_id,
                    rn.city_id,
                    rn.update_time,
                    rn. STATUS,
                    rn.sell_status,
                    rsh.operate_status,
                    cm.id model_id
                FROM
                    rent_content rn
                LEFT JOIN rent_content_return_config rcc ON rcc.rent_content_id = rn.id
                LEFT JOIN mileage_statistics ms ON ms.rent_content_id = rn.id
                LEFT JOIN car_info cn ON cn.id = rn.car_info_id
                LEFT JOIN car_model cm ON cm.id = cn.model_id
                LEFT JOIN car_item car ON car.id = rn.car_item_id
                LEFT JOIN rent_sku_hour rsh ON rn.id = rsh.rent_content_id
                LEFT JOIN car_item_verify civ ON civ.car_item_id = rn.car_item_id
                JOIN car_item_device cid ON cid.car_item_id = rn.car_item_id
                LEFT JOIN fed_gps_additional fga ON cid.imei = fga.imei
                LEFT JOIN corporation cor ON cor.id = rn.corporation_id
                AND cor.city_id = rn.city_id
                WHERE
                    rn.sort_id = 112
                AND rn. STATUS = 2
                AND rn.sell_status = 1
                AND residual_battery >= 40
                AND civ.plate_no = '{$plate_no}'";
        $res = M()->query($sql);
        if( $res && !empty($res) ){
            if(empty($a)){
                if ($this->diffBetweenTwoDays(time(), $res[0]['create_time']) >= 2 ) {
                    return 2;  //两日无单
                }
                if ($this->diffBetweenTwoDays(time(), $res[0]['create_time']) >= 5 ) {
                    return 5;  //五日无单
                }
            }
            if ($res[0]['no_mileage_num'] == 3 ) {
                return 3;  //有单无程
            }
            return 1;
        }
        return -1;

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
    //根据公司返回需换电电量标准 //北京小蜜和小马
    public function need_change($corporation_id=""){
        if( $corporation_id ==2118 ||$corporation_id ==10258){
            return 55;
        }else{
            return 45;
        }
    }
    //判断是否出租中的车辆
    public function is_rating($rent_content_id=""){
        $hasOrder = M("trade_order")->where(" rent_content_id={$rent_content_id} and rent_type=3 and status>=10100 and status<80200 and status<>10301 ")->find();
        if( $hasOrder && !empty($hasOrder) ){
            return true;
        }else{
            return false;
        }
    }
    //获取盒子定位
    public function gpsStatusInfo($imei){
        // 优先取redis 数据
         $box_config = 'mysqli://api-baojia-2:CSDV4smCSztRcvVb@10.1.11.14:3306/baojia_box';
//        $info=$this->getXiaomiPosition($imei);
//        if(!$info || $info['latitude']<=0 || $info['longitude']<=0){
            $info = M("baojia_box.gps_status",null,$box_config)->where(["imei"=>$imei])->find();
//        }else{
//            $info['imei']=str_pad($info['carId'], 16, '0', STR_PAD_LEFT);
//            $info['datetime']=$info['gpsTime'];
//            $info['lastonline']=date('Y-m-d H:i:s',$info['gpsTime']);
//        }
        $gps = D("Gps");
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_latitude"] = $gd["lat"];
        $info["gd_longitude"] = $gd["lon"];

        $bd = $gps->bd_encrypt($info["gd_latitude"],$info["gd_longitude"]);
        $info["bd_latitude"] = $bd["lat"];
        $info["bd_longitude"] = $bd["lon"];

        return $info;
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
    public function getImei($car_item_id){

        $map = ["car_item_id"=>$car_item_id];
        $map["device_type"] = ["in",[12,14,16,18]];
        $device_info = M("car_item_device")->where($map)->find();
        return $device_info["imei"] ? $device_info["imei"] : "";
    }

    /*
     name 是否上下左右4个点有一个点在区域中
  */
    public function isInsidePolygonExt($pt, $poly,$latLngNumber = 0.0009){

        if($this->isInsidePolygon($pt,$poly)){

            return true;
        }
        $new_pt_move = [];
        $new_pt_move[] = [0,0];
        $new_pt_move[] = [$latLngNumber ,0];
        $new_pt_move[] = [-$latLngNumber,0];
        $new_pt_move[] = [0, $latLngNumber];
        $new_pt_move[] = [0,-$latLngNumber];

        foreach ($new_pt_move as $key => $value) {
            # code...
            $new_pt = [$pt[0] + $value[0],$pt[1] + $value[1]];
            if($this->isInsidePolygon($new_pt,$poly)){
                $result = true;
                return $result;
            }
        }

        if($this->isInsidePolygonExtOffSet($pt,$poly,$latLngNumber)){
            return true;
        }
        return false;

    }

    /*
         判断偏移范围
    */
    public function isInsidePolygonExtOffSet($pt,$poly,$latLngNumber = 0.0009){

        $length = count($poly);


        for($i = 0;$i< $length;$i++){

            $next_i = $i+1;
            $next_i = $next_i >= $length ? 0 : $next_i;

            $line = [$poly[$i],$poly[$next_i]];
            $distance = $this->distancePoint2Line($pt,$line);

            if($distance <= $latLngNumber){

                return 1;
            }
        }
        return 0;

    }
    /**
     * 计算某个经纬度的周围某段距离的正方形的四个点
     * @param lng float 经度
     * @param lat float 纬度
     * @param distance float 该点所在圆的半径，该圆与此正方形内切，默认值为0.5千米
     * @return array 正方形的四个点的经纬度坐标
     */
    function returnSquarePoint($lng, $lat,$distance = 0.5){

        $dlng =  2 * asin(sin($distance / (2 * EARTH_RADIUS)) / cos(deg2rad($lat)));
        $dlng = rad2deg($dlng);

        $dlat = $distance/EARTH_RADIUS;
        $dlat = rad2deg($dlat);

        return array(
            'left-top'=>array('lat'=>$lat + $dlat,'lng'=>$lng-$dlng),
            'right-top'=>array('lat'=>$lat + $dlat, 'lng'=>$lng + $dlng),
            'left-bottom'=>array('lat'=>$lat - $dlat, 'lng'=>$lng - $dlng),
            'right-bottom'=>array('lat'=>$lat - $dlat, 'lng'=>$lng + $dlng)
        );
    }
    public function getDistance($lng1, $lat1, $lng2, $lat2){    //将角度转为狐度
        $radLat1 = deg2rad($lat1);//deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
        return $s;
    }
    /*
      计算坐标到多边形的距离
 */
    public function getDistancePolygon($pt,$poly){

        $length = count($poly);

        $juli=9999999999999;
        for($i = 0;$i< $length;$i++){

            $next_i = $i+1;
            $next_i = $next_i >= $length ? 0 : $next_i;

            $line = [$poly[$i],$poly[$next_i]];
            $distance = $this->distancePoint2Line($pt,$line);
            $juli=min($distance,$juli);
        }
        return $juli;

    }
    public function distancePoint2Line($pt,$line){

        $pt2a_distance = $this->getDistanceV2($pt,$line[0]);

        $pt2b_distance = $this->getDistanceV2($pt,$line[1]);

        $a2b_distance  = $this->getDistanceV2($line[0],$line[1]);



        $result_a = pow($pt2a_distance, 2) + pow($a2b_distance,2) > pow($pt2b_distance,2);
        $result_b = pow($pt2b_distance, 2) + pow($a2b_distance,2) > pow($pt2a_distance,2);

        if($result_a && $result_b){

            $height = $this->triangleHeigth([$pt2a_distance,$pt2b_distance,$a2b_distance]);
            return $height;
        }else{
            return min($pt2a_distance,$pt2b_distance);
        }


    }
    /**
     * 得到两点间距离
     * @param double $lng1
     * @param double $lat1
     * @param double $lng2
     * @param double $lat2
     * @return float|int
     */
    public function getDistanceV2($lnglat1, $lnglat2){    //将角度转为狐度
        $lng1 = $lnglat1[0];
        $lat1 = $lnglat1[1];
        $lng2 = $lnglat2[0];
        $lat2 = $lnglat2[1];
        $radLat1 = deg2rad($lat1);//deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 57.2956;
        return $s;
    }

    public function triangleHeigth($long_arr,$width_index = 2){

        $long = $long_arr[0] + $long_arr[1] + $long_arr[2];
        $area = sqrt(($long/2-$long_arr[0]) * ($long/2-$long_arr[1]) * ($long/2-$long_arr[2]) * $long/2);
        $height = $area * 2 / $long_arr[$width_index];

        return $height;
    }
    //获取设备信息
    public function getboxinfo($rid){
        $car_item_device = M('car_item_device');
        $fed_gps_additional = M('fed_gps_additional');
        $ridinfo=M('rent_content')->where('id='.$rid)->find();
        //读取围栏
        $boxarr=$car_item_device->where('car_item_id='.$ridinfo['car_item_id'].' and device_type in(12,14,16,18)')->find();
        if($boxarr){
            $zsdb='mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc';
            if($boxarr['device_type']!=14){
                $boxarr['imei']=substr($boxarr['imei'],1);
            }
//            $macinfo['imei']=$boxarr['imei'];
//            $macinfo['deviceid']=$boxarr['id'];
//            if($ridinfo && $ridinfo['sort_id']==113){
//                //单车增加获取微电云马蹄锁mac地址
//                $xmaDevice = new \Api\Service\XMADevice();
//                $macinfos=$xmaDevice->execute($boxarr['imei'], 'M');
//                if($macinfos['code']==0){
//                    $macarr=str_split($macinfos['res']['mac'],2);
//                    $macinfo['mac']=implode(':',$macarr);
//                    $macinfo['mal']=$macinfos['res']['mac'];
//                }
//                $macinfo['imei']=str_replace('wdy','',$boxarr['imei']);
//            }
//            $res['macinfo']=$macinfo;
            $group_car=M('car_group_r',null,$zsdb)->where("carId='{$boxarr['imei']}'")->find();
            if($_GET['istest']){echo M('car_group_r',null,$zsdb)->getLastSql();print_R($group_car);}
            if($group_car){
                if(!empty($group_car['groupId'])){
                    $group=M('group',null,$zsdb)->where("id={$group_car['groupId']}")->find();
                }
                $grouppoint=M('group_area',null,$zsdb)->where("groupId='{$group_car['groupid']}'")->order('no asc,id asc')->select();
                $gps = D("Gps");
                $areapoints = array();
                foreach($grouppoint as $k=>$v){
                    $newpos = $gps->gcj_encrypt($v['lat'],$v['lng']);

                    $newpos1 = $gps->bd_encrypt($newpos['lat'],$newpos['lon']);
                    array_push($areapoints,array($newpos['lon'],$newpos['lat']));

                }
                $res['area']= $areapoints;
            }
        }
        //$this->response($res, 'json');
        return $res;

    }
    //同步车辆状态sell_status 和子表mebike_status
    /*
     * $param (Array)= 需要同步的字段【一维数组】
     *上架某状态  $key=0 下架车辆 $key=100  $key=2  上架车辆不修改sell_time
     * */
    public function step_status($param,$rent_content_id,$key){
        $sell_status = M("rent_content")->where(["id"=>$rent_content_id])->getField("sell_status");
        if($key==100){
            if($sell_status==1){
                $data = ["sell_status"=>100,"update_time" => time()];
                $Model = M();
                $Model->startTrans();  //开启事务
                $res = $Model->table('rent_content')->where(["id"=>$rent_content_id])->save($data);
                $param["sell_status"] = 100;
                $res1 = $Model->table('mebike_status')->where(["rent_content_id"=>$rent_content_id])->save($param);
                if($res&&$res1){
                    $Model->commit();
                    return true;
                }else{
                    $Model->rollback();
                    return false;
                }
            }else{
                $param["sell_status"] = 100;
                $res1 = M("mebike_status")->where(["rent_content_id"=>$rent_content_id])->save($param);
                if($res1){
                    return true;
                }else{
                    return false;
                }
            }

        }else{
            $res1 = M("mebike_status")->where(["rent_content_id"=>$rent_content_id])->save($param);
            if($res1){
                $is_sell = $this->is_sell($rent_content_id); //上架车辆
                if($is_sell==true){
                    $Model = M();
                    $Model->startTrans();  //开启事务
                    $param["sell_status"] = 1;
                    if($key==0){
                        $param["sell_time"] = date("Y-m-d H:i:s",time());
                        $data1 = ["sell_status"=>1,"update_time" => time()];
                    }else{
                        $data1 = ["sell_status"=>1];
                    }

                    $userRes = $Model->table('rent_content')->where(["id"=>$rent_content_id])->save($data1);
                    $keyRes = $Model->table('mebike_status')->where(["rent_content_id"=>$rent_content_id])->save($param);
                    $za = $Model->table('mebike_status')->getLastSql();
//                    \Think\Log::write($za,'预约sql'.$key);
                    if($userRes && $keyRes){
                        $Model->commit();
                        return true;
                    }else{
                        $Model->rollback();
                        return false;
                    }
                }
                return true;
            }
            return false;


        }
    }
    public function is_sell($rent_content_id){
        $param = "offline_status,out_status,lack_power_status,transport_status,repaire_status,seized_status,reserve_status,storage_status,rent_status,damage_status,scrap_status,other_status,search_status,dispatch_status,special_status";
        $data = M("mebike_status")->where(["rent_content_id"=>$rent_content_id])->field($param)->find();
        foreach ($data as $d =>$v) {
            if($d == "out_status"){
                if($v==200){
                    return false;
                }
            }elseif($d == "lack_power_status"){
                if($v>200){
                    return false;
                }
            }elseif($d == "repaire_status"){
                if($v>101){
                    return false;
                }
            }elseif(!empty($v)){
                return false;
            }
        }
        return true;
    }

    public function get_status($rent_content_id){
        $param = "sell_status,offline_status,out_status,lack_power_status,transport_status,repaire_status,seized_status,reserve_status,storage_status,rent_status,damage_status,scrap_status,other_status,search_status,dispatch_status,special_status";
        $data_status = M("mebike_status")->where(["rent_content_id"=>$rent_content_id])->field($param)->find();
        return $data_status;
    }
    //实时请求盒子数据
    public function imei_status($imei=""){
        if(empty($imei)){
            return true;
        }
        $no_zero_imei = ltrim($imei, "0");
        $data['carId'] = $no_zero_imei;
        $data['cmd'] = 'statusQuery';
        $data["type"] = 34;
        $r = $this->post1($data);
        if($r["rtCode"] <> 0){
            return false;
        }
        sleep(1);
        $data1['carId'] = $no_zero_imei;
        $data1['key'] = $r['msgkey'];
        $data1['cmd'] = "resultQuery";
        $r1 = $this->post1($data1);
        if($r1["rtCode"] <> 0){
            return false;
        }
        return $r1;

    }
    //网关post请求
    public function post1($data)
    {

        $postUrl = "http://wg.baojia.com/simulate/service";
        /*if($nosign){
            $postUrl = $this->url2;
        }*/
        $json = json_encode($data);
//        return $json;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Content-Length: '.strlen($json)
            ]
        );
        $output = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($output, true);
        return $data;
    }

    /**
     * 使用高德地图逆地理编码
     * @param double $lon 经度
     * @param double $lat 维度
     * @return mixed|null
     */
    public function regeo($lng, $lat)
    {
        //return [];
        $key ='1ade8c3663433fd83607e226e2f70dbc';
        $url = 'http://restapi.amap.com/v3/geocode/regeo?output=JSON&location='.$lng.','.$lat.'&key='.$key.'&radius=1000&extensions=all';
        $res = file_get_contents($url);
        \Think\Log::write("请求高德逆地理编码,lng=".$lng.",lat=".$lat.",返回结果:".$res, "INFO");
        $res = json_decode($res);
        return $res;
    }


    /**
     * 根据坐标获取地址
     * @param $lng
     * @param $lat
     * @param string $default
     * @return mixed|string
     */
    public function GetAmapAddress($lng,$lat,$default=''){
        $res = $this->regeo($lng, $lat);
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
    }

    public function getAmapCity($lng,$lat,$default=''){
        $res =$this->regeo($lng, $lat);
        if($res&&($res->info == 'OK')){
            $default=explode('市',$res->regeocode->addressComponent->city)[0];
            if(empty($default)){
                $default=explode('市',$res->regeocode->addressComponent->province)[0];
            }
        }
        return $this->getCity_id($default);
    }

    public function getCity_id($default='北京'){
        $city_id = M("area_city")
            ->FIELD("id,name")
            ->where("name like '%{$default}%' and status = 1")
            ->find();
        return $city_id['id'];
    }
}
?>