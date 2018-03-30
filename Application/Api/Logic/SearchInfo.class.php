<?php
/**
 * Created by PhpStorm.
 * User: CHI
 * Date: 2018/1/10
 * Time: 18:41
 */

namespace Api\Logic;

class SearchInfo
{
    /**
     * 车辆及运维权限判断
     * @param $corporation_id
     * @param $rent_content_id
     * @return int
     */
    public function carAuth($corporation_id,$rent_content_id){
        if(empty($corporation_id)||empty($rent_content_id)){
            return 0;
        }
        $id =M("rent_content")->where(["id"=>$rent_content_id])->getField("corporation_id");
        $parent_id=M("corporation")->where(["id"=>$id])->getField("parent_id");
        if($corporation_id==$parent_id){
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 获取gps定位
     * @param int rent_id
     * @return array
     */
    public function getGpsImei($rent_id =322028){
        $car_item_id=M("rent_content")->where("id={$rent_id}")->getField("car_item_id");
        $imei=M("car_item_device")->where("car_item_id={$car_item_id}")->getField("imei");
        return $imei;
    }


    /**
     * 查询车牌号
     * @param $car_item_id
     * @return mixed
     */
    public function getPlateNo($car_item_id){
        $plate_no = M("car_item_verify")->where(["car_item_id"=>$car_item_id])->getField("plate_no");
        return $plate_no;
    }

    /**
     * 查询车辆是否出租中
     * @param $rent_content_id
     * @return mixed
     */
    public function isInRenting($rent_content_id=5886371){
        $rent_content_id = M("trade_order")
            ->where("rent_content_id={$rent_content_id} and rent_type=3 and status>=10100 and status<80200 and status<>10301 ")
            ->getField("rent_content_id");
        return $rent_content_id;
    }

    /**
     * 查询员工类型
     * role_type 角色类型：1=运维 2=调度 3=整备 4=库管
     * job_type 1=全职 2=兼职
     * @param $user_id
     * @param $corporation_id
     * @return mixed
     */
    public function getUserType($user_id=2630751,$corporation_id=2118){
        $result=M("baojia_mebike.repair_member")->field("user_id,role_type,job_type,corporation_id")
            ->where("user_id={$user_id} and corporation_id={$corporation_id} and status=1")->find();
        if($result) {
            $city_id = M("corporation")->where("id={$result["corporation_id"]}")->getField("city_id");
            $result["city_id"]=$city_id;
            $city_name = M("area_city")->where("id={$city_id}")->getField("name");
            $result["city"]=$city_name;
        }
        return $result;
    }

    /**
     * 查询盒子gps状态
     * @param $imei
     * @return mixed
     */
    public function gpsStatusInfo($imei){
        $info = M("baojia_box.gps_status",null,C('DB_CONFIG_BOX'))->field("latitude,longitude")->where(["imei"=>$imei])->find();
        $gps = new \Api\Model\GpsModel();
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_latitude"] = $gd["lat"];
        $info["gd_longitude"] = $gd["lon"];
        $bd = $gps->bd_encrypt($info["gd_latitude"],$info["gd_longitude"]);
        $info["bd_latitude"] = $bd["lat"];
        $info["bd_longitude"] = $bd["lon"];
        return $info;
    }

    /**
     * 获取盒子定位 优先取redis
     * @param $imei
     * @return bool|mixed
     */
    public function gpsStatusInfoR($imei)
    {
        //优先取redis
        $info = $this->getXiaomiPosition($imei);
        if (!$info || $info['latitude'] <= 0 || $info['longitude'] <= 0) {
            $sql = "SELECT imei,datetime,lastonline,longitude,latitude FROM gps_status WHERE imei={$imei["imei"]}";
            $info = M("", null,C('DB_CONFIG_BOX'))->query($sql);
            if ($info && is_array($info)) {
                $info = $info[0];
            }
        } else {
            $info['imei'] = str_pad($info['carId'], 16, '0', STR_PAD_LEFT);
            $info['datetime'] = $info['gpsTime'];
            $info['lastonline'] = date('Y-m-d H:i:s', $info['gpsTime']);
        }
        $gps = new \Api\Model\GpsModel();
        $gd = $gps->gcj_encrypt($info["latitude"], $info["longitude"]);
        $info["gd_latitude"] = $gd["lat"];
        $info["gd_longitude"] = $gd["lon"];
        return $info;
    }

    /**
     * 获取小安设备redis 定位数据
     * @param $imei
     * @return bool|mixed
     */
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

    /**
     * 查询车辆坐标和最近上线时间
     * @param string $imei
     * @return null|string
     */
    public function gpsStatusByImeis($imei="0867010032524739"){
        if(empty($imei)){
            return "";
        }
        $map["imei"] =$imei;
        $order = " lastonline desc ";
        $info = M("baojia_box.gps_status",null,C('DB_CONFIG_BOX'))
            ->field("longitude,latitude,lastonline update_time")
            ->where($map)->order($order)->find();
        if(!$info){
            return null;
        }
        $gps = new \Api\Model\GpsModel();
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_lat"] = floatval($gd["lat"]);
        $info["gd_lng"] = floatval($gd["lon"]);
        $bd = $gps->bd_encrypt($info["gd_lat"],$info["gd_lng"]);
        $info["bd_lat"] = $bd["lat"];
        $info["bd_lng"] = $bd["lon"];
        return $info;
    }

    /**
     * 查询基站信息
     * @param imei
     * @return null|string
     */
    public function gpsStatusBSByImeis($imei){
        if(empty($imei)){
            return "";
        }
        $map["imei"]=$imei;
        $order = " lastonline desc ";
        $info = M("baojia_box.gps_status_bs",null,C('DB_CONFIG_BOX'))
            ->field("longitude,latitude,lastonline update_time")
            ->where($map)->order($order)->find();
        if(!$info){
            return null;
        }
        $gps = new \Api\Model\GpsModel();
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_lat"] = floatval($gd["lat"]);
        $info["gd_lng"] = floatval($gd["lon"]);
        $bd = $gps->bd_encrypt($info["gd_latitude"],$info["gd_longitude"]);
        $info["bd_lat"] = $bd["lat"];
        $info["bd_lng"] = $bd["lon"];
        return $info;
    }

    /**
     * 查询最近取车信息
     * @param rent_id
     * @return mixed
     */
    public function getLastAppCoordinate($rent_id){
        $co=M('baojia_mebike.trade_order_return_log')
            ->field("curpoint,FROM_UNIXTIME(time) update_time")
            ->where("rent_content_id={$rent_id}")->order("time desc")->find();
        $coordinate["update_time"]=$co["update_time"];
        if($co&&$co["curpoint"]){
            $json=json_decode($co["curpoint"]);
            if($json[0]>0){
                $coordinate["longitude"]=$json[0];
                $coordinate["latitude"]=$json[1];
            }else{
                $coordinate["longitude"]=null;
                $coordinate["latitude"]=null;
            }
        }
        return $coordinate;
    }


}