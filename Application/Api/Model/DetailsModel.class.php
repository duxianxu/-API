<?php
namespace Api\Model;
use Think\Model;

class DetailsModel extends Model
{

    public function __construct()
    {

    }

    /**
     * 查询车辆位置和电量
     * @param $imei
     * @return mixed
     */
    public function getPositionAndElectricity($imei)
    {
        $xiaoan=$this->getXiaomiPosition($imei);
        $result["residual_battery"]=50;
        $result["gis_lng"]=0;
        $result["gis_lat"]=0;
        if($xiaoan){
            $result["residual_battery"]=((float)$xiaoan["dumpEle"])*100;
            $result["gis_lng"]=$xiaoan["longitude"];
            $result["gis_lat"]=$xiaoan["latitude"];
        }
        return $result;
    }


    //获取小安设备redis 定位数据
    public function getXiaomiPosition($imei)
    {
        $Redis = new \Redis();
        $Redis->pconnect('10.1.11.83', 36379, 0.5);
        $Redis->AUTH('oXjS2RCA1odGxsv4');
        $Redis->SELECT(2);
        $key = "prod:boxxan_" . ltrim($imei, 0);
        $result = $Redis->get($key);
        if (!$result) {
            return false;
        }
        $result = json_decode($result, true);
        return $result;
    }

    //还车时盒子是否异常判断,redis读写
    public function carDeviceIsexception($order_id,$value = 0){

        $Redis = new \Redis();
        $Redis->connect(C("REDIS_HOST"), C("REDIS_PORT"));

        $key = "car_device_exception_".(int)C("ISTEST")."_".$order_id;
        if($value > 0){
            $redisResult = $Redis->set($key, $value);
        }else{
            $redisResult = $Redis->get($key);
        }
        return $redisResult;

    }

    //根据imei查询车辆定位
    public function gpsStatusInfo($imei)
    {
        $info = M("baojia_box.gps_status", null, C("DB_CONFIG_BOX"))->where(["imei" => $imei])->find();
        $gps = new \Api\Model\GpsModel();
        $gd = $gps->gcj_encrypt($info["latitude"], $info["longitude"]);
        $info["gd_latitude"] = $gd["lat"];
        $info["gd_longitude"] = $gd["lon"];
        $bd = $gps->bd_encrypt($info["gd_latitude"], $info["gd_longitude"]);
        $info["bd_latitude"] = $bd["lat"];
        $info["bd_longitude"] = $bd["lon"];
        $info["datetime_diff"] = $this->timeDifference($info["datetime"], time());
        return $info;
    }

    public function tradeLine($rent_content_id){
        $return = [];
        $gps = new \Api\Model\GpsModel();
        $box_config=C("DB_CONFIG_BOX");
        $orderid =M('baojia_mebike.trade_order_return_log')->where(["rent_content_id"=>$rent_content_id])->order("id desc")->limit(1)->field("order_id")->find();
        if(empty($orderid)){
            return $return;
        }
        $order=M('trade_order')->where("id=".$orderid['order_id'])->find();
        $beginTime=$order['begin_time'];
        $endtime=$order['end_time'];
        $device=M('car_item_device')->where('car_item_id='.$order['car_item_id'])->field('imei')->find();
        $car_return_info=M('trade_order_car_return_info')->where('order_id='.$orderid['order_id'])->find();

        if($car_return_info && $car_return_info['take_lng']>0 && $car_return_info['take_lat']>0){
            $liststart['lat']=(float)$car_return_info['take_lat'];
            $liststart['lon']=(float)$car_return_info['take_lng'];
        }
        $order_return_corporation=M('trade_order_return_corporation')->where('order_id='.$orderid['order_id'])->order('update_time desc')->find();
        if($order_return_corporation) {
            $point = json_decode($order_return_corporation['point'], true);
            if($point['d_gis_lng']>0&&$point['d_gis_lat']>0) {
                $listend['lat']=$point['d_gis_lat'];
                $listend['lon']=$point['d_gis_lng'];
            }
        }
        $imei = $device['imei'];
        $condition="imei='$imei'";
        if($beginTime && $endtime)$condition.=" AND datetime between $beginTime AND $endtime";
        $PointArr1 = M('fed_gps_location')->where("$condition")->order('datetime asc')->select();
        // $res = M('fed_gps_location')->getLastsql();
        //切换备份表进行查询
        $tb=M("",null,$box_config)->query("show tables like 'gps_location_%'");
        $bt=date('ymd',$beginTime);
        $et=date('ymd',$endtime);
        foreach($tb as $key=>$v){
            foreach($v as $k1=>$v1){
                if (is_numeric(substr($v1,-6))) {
                    if((int)trim($v1,'gps_location_')>=$bt && (int)trim($v1,'gps_location_')<=$et){
                        $tbs1[]=$v1;
                    }
                }
            }

        }
        sort($tbs1);
        if($tbs1[0]){
            $PointArr2=M("",null,$box_config)->query("select latitude,longitude from {$tbs1[0]} where  imei='{$imei}' and longitude>0 and latitude>0 and datetime>={$beginTime} and datetime<={$endtime} order by datetime asc");
        }
        if(count($PointArr1)>0 && count($PointArr2)>0){
            $PointArr2=array_merge($PointArr2,$PointArr1);
        }elseif(count($PointArr1)>1){
            $PointArr2=$PointArr1;
        }
        $return['start'] = $liststart;
        $return['end'] = $listend;
        foreach ($PointArr2 as $k => $v) {
            $gd = $gps->gcj_encrypt($v["latitude"],$v["longitude"]);
            $PointArr2[$k]['latitude'] = $gd['lat'];
            $PointArr2[$k]['longitude'] = $gd['lon'];
        }
        $return['track'] = $PointArr2;
        return $return;
    }

    public function getRoute($imei,$beginTime=0,$endtime=0,$order=' ASC') {
        $condition="imei='$imei'";
        if($beginTime && $endtime)$condition.=" AND datetime between $beginTime AND $endtime";
        $box_config=C("DB_CONFIG_BOX");
        $PointArr1 = M('fed_gps_location')->where("$condition")->order('datetime asc')->select();
        //echo "<pre>";print_r($PointArr1);
        //切换备份表进行查询
        $tb=M("",null,$box_config)->query("show tables like 'gps_location_%'");
        $bt=(int)date('ymd',($beginTime-86400*5));
        $et=(int)date('ymd',($endtime+86400*5));
        foreach($tb as $key=>$v){
            foreach($v as $k1=>$v1){
                if (is_numeric(substr($v1,-6))) {
                    if((int)trim($v1,'gps_location_')>=$bt && (int)trim($v1,'gps_location_')<=$et){
                        $tbs1[]=$v1;
                    }
                }
            }
        }
        sort($tbs1);
        //echo count($tbs1);
        //echo "<pre>";print_r($tbs1);
        $return_point_array=[];
        foreach ($tbs1 as $k => $v) {
            $PointArr=M("",null,$box_config)->query("select id,latitude,longitude,speed,course,accstatus,datetime from {$v} where  imei='{$imei}' and longitude>0 and latitude>0 and datetime>={$beginTime} and datetime<={$endtime} order by datetime asc");
            if(count($return_point_array)>0&&count($PointArr)>0) {
                $return_point_array = array_merge($return_point_array,$PointArr);
            }elseif(count($return_point_array)==0&&count($PointArr)>0){
                $return_point_array=$PointArr;
            }
        }
        if(count($return_point_array)>0&&count($PointArr1)>0) {
            $return_point_array = array_merge($return_point_array, $PointArr1);
        }elseif(count($return_point_array)==0&&count($PointArr1)>0){
            $return_point_array=$PointArr1;
        }
        return $return_point_array;
    }


    public function isXiaomaInArea($rent_id,$pt,$imei,$has_poly = 0){
        $rent_info = M("rent_content")->where(["id"=>$rent_id])->find();
        $map = ["car_item_id"=>$rent_info["car_item_id"]];
        $map["device_type"] = ["in",[12,14,16,18,9]];
        $no_zero_imei = ltrim($imei,"0");
        $ga_list_sql = "SELECT ga.lat, ga.lng FROM car_group_r cgr
                        INNER JOIN `group` g ON g.id = cgr.groupId
                        INNER JOIN group_area ga ON ga.groupId = g.id
                        WHERE cgr.carId = '{$no_zero_imei}'
                        AND cgr.`status` = 1 AND g.`status` = 1 ORDER BY ga. NO";
        $list = M("",null,C("BAOJIA_LINK_DC"))->query($ga_list_sql);
        if($list){
            $poly = [];
            foreach ($list as $key => $value) {
                $poly[] = [$value["lng"],$value["lat"]];
            }
            if(count($poly) > 3 && $pt[0] > 0 && $pt[1] > 0){
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

    //判断是否在区域内
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

    //计算距离
    public function distance($latA, $lonA, $latB, $lonB)
    {
        $earthR = 6371000.;
        $x = cos($latA * $this->PI / 180.) * cos($latB * $this->PI / 180.) * cos(($lonA - $lonB) * $this->PI / 180);
        $y = sin($latA * $this->PI / 180.) * sin($latB * $this->PI / 180.);
        $s = $x + $y;
        if ($s > 1) $s = 1;
        if ($s < -1) $s = -1;
        $alpha = acos($s);
        $distance = $alpha * $earthR;
        return $distance;
    }

    //当前时间毫秒数
    public function microtimeFloat()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    //计算时间差
    public function timeDifference($ent_time, $start_time)
    {
        $time = abs($ent_time - $start_time);
        $start = 0;
        $string = "";
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

    public function getAuth()
    {
        $year = date("Y");
        $month = date("m");
        $week = date('w'); //得到今天是星期几
        $hour = date("H"); //小时
        $date_now = date('j'); //得到今天是几号
        $we = ceil($date_now / 7); //计算是第几个星期几
        if (($week == 3 && $we == 3 && $hour >= 10) || $we > 3 || ($week > 3 && $we == 3)) {
            $authpa = dechex(($year * $month * 5));//.toString(16);
        } else {
            if ($month == 1) {
                $month = 12;
                $year = $year - 1;
                $authpa = dechex(($year * $month * 5));//.toString(16);
            } else {
                $authpa = dechex(($year * $month * 5));
            }
        }
        return "http://xmtest.baojia.com?auth=" . $authpa;
    }

    //电压转换电量
    public function getDumpEle($voltage)
    {
        $v = ($voltage - 43) / (0.12);
        $battery = 0;
        if ($voltage > 53) { // 53 ~
            $battery = 1;
        } else if ($voltage >= 43 && $voltage <= 55) { //43~55
            $battery = $v / 100;
        } else if ($voltage < 43 && $voltage > 10) { //43~10
            $battery = -1;
        }
        return sprintf('%.2f', $battery);
    }

    //根据高德经纬度获取城市
    public function getAmapCity($lng, $lat, $default = '北京')
    {
        $time_start = $this->microtimeFloat();
        $url = 'http://restapi.amap.com/v3/geocode/regeo?output=JSON&location=' . $lng . ',' . $lat . '&key=7461a90fa3e005dda5195fae18ce521b&s=rsv3&radius=1000&extensions=base';
        $res = file_get_contents($url);
        $res = json_decode($res);
        if ($res->info == 'OK') {
            $default = explode('市', $res->regeocode->addressComponent->city)[0];
            if (empty($default)) {
                $default = explode('市', $res->regeocode->addressComponent->province)[0];
            }
        }
        $time_end = $this->microtimeFloat();
        $second = round($time_end - $time_start, 2);
        \Think\Log::write("根据坐标取城市，" . $default . "，请求地址：" . $url . "，耗时：" . $second . "秒，返回数据：" . json_encode($res), "INFO");
        //echo $default;
        return $default;
    }

    //根据高德经纬度获取地址
    public function getAmapAddress($lng, $lat, $default = '')
    {
        $res = file_get_contents('http://restapi.amap.com/v3/geocode/regeo?output=JSON&location=' . $lng . ',' . $lat . '&key=7461a90fa3e005dda5195fae18ce521b&s=rsv3&radius=1000&extensions=base');
        $res = json_decode($res);
        if ($res->info == 'OK') {
            $default = $res->regeocode->formatted_address;
        }
        return $default;
    }

    //版本比较 0为大于 1为小于
    public  function  versionCompare($version='1.1',$pastVersion='1.1.1'){
        $version = explode('.',$version);
        $pastVersion = explode('.',$pastVersion);
        if($version==$pastVersion){
            $isForced = 1;
        }else {
            if (intval($version[0]) < intval($pastVersion[0])) {
                $isForced = 1;
            } else if (intval($version[0]) > intval($pastVersion[0])) {
                $isForced = 0;
            } else {
                if (intval($version[1]) < intval($pastVersion[1])) {
                    $isForced = 1;
                } else if (intval($version[1]) > intval($pastVersion[1])) {
                    $isForced = 0;
                } else {
                    if (intval($version[2]) < intval($pastVersion[2])) {
                        $isForced = 1;
                    } else if (intval($version[2]) > intval($pastVersion[2])) {
                        $isForced = 0;
                    } else {
                        $isForced = 0;
                    }
                }
            }
        }
        return $isForced;
        //$this->ajaxReturn(["code" => 1, "message" =>$isForced],json);
    }

    function curl_post($url,$data){ // 模拟提交数据函数
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($curl, CURLOPT_COOKIEFILE, $GLOBALS['cookie_file']); // 读取上面所储存的Cookie信息
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno'.curl_error($curl);
        }
        curl_close($curl); // 关键CURL会话
        return $tmpInfo; // 返回数据
    }
}