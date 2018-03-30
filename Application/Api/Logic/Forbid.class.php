<?php
namespace Api\Logic;

use Think\Model;

class Forbid
{

    //禁行区列表
    public function ForbidList($rentid=0,$lng,$lat,$limit=10000)
    {
        $posturl = "http://ms.baojia.com/search/region/forbid";
        $aliyun_conn = "mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc";
        if($rentid) {
            $parent_corporation_id = M('corporation')->alias("c")
                ->join("rent_content rc on rc.corporation_id=c.id", "left")
                ->where("rc.id={$rentid}")
                ->getField("c.parent_id");

            $location = [];
            if($lng&&$lat){
                $location = [$lng,$lat];
            }
            //forbidType  禁用类型1禁还区，2禁行区
            $post_data = ["corporationParentId"=>$parent_corporation_id,"location"=>$location,"distance"=>20,"size"=>20,"forbidType"=>2];
            $result_data = curl_get($posturl,json_encode($post_data),"POST");
            $result_data = json_decode($result_data,true);
            $list = $result_data["data"];

            if ($list) {
                foreach ($list as $k => $v) {
                    foreach ($v["regionLocations"] as $k1=>$v1){
                        $v["regionLocations"][$k1][0] = $v1["location"][0];
                        $v["regionLocations"][$k1][1] = $v1["location"][1];
                        unset($v["regionLocations"][$k1]["location"]);
                        unset($v["regionLocations"][$k1]["sort"]);
                    }
                    $list[$k]["area"] = $v["regionLocations"];
                    unset($list[$k]["regionLocations"]);
                }
            }

        }else{
            $post_data = ["distance"=>20,"size"=>20,"forbidType"=>2];
            $result_data = curl_get($posturl,json_encode($post_data),"POST");
            $result_data = json_decode($result_data,true);
            $list = $result_data["data"];

            if ($list) {
                foreach ($list as $k => $v) {
                    foreach ($v["regionLocations"] as $k1=>$v1){
                        $v["regionLocations"][$k1][0] = $v1["location"][0];
                        $v["regionLocations"][$k1][1] = $v1["location"][1];
                        unset($v["regionLocations"][$k1]["location"]);
                        unset($v["regionLocations"][$k1]["sort"]);
                    }
                    $list[$k]["area"] = $v["regionLocations"];
                    unset($list[$k]["regionLocations"]);
                }
            }
        }
        return $list;
    }

    //检测是否在禁行区
    public function Forbid($rent_id,$order_id,$is_return=0)
    {
        $rent_info = M("rent_content")->where(["id"=>$rent_id])->find();

        //非小蜜单车不判断，默认在区域内
        if(!in_array($rent_info["sort_id"],[112])){
            return true;
        }

        $map = ["car_item_id"=>$rent_info["car_item_id"]];
        $map["device_type"] = ["in",[12,16,18]];
        $car_item_device = M("car_item_device")->where($map)->find();
        $imei = $car_item_device["imei"];

        //优先取redis 数据
        $carreturn = new \Api\Logic\CarReturn();
        /*
        $gps_status_info=$carreturn->getXiaomiPosition($imei);
        if(!$gps_status_info || $gps_status_info['latitude']<=0 || $gps_status_info['longitude']<=0){
            $gps_status_info = M("baojia_box.gps_status",null,"DB_CONFIG_BOX")->where(["imei"=>$imei])->find();
        }else{
            $gps_status_info['imei']=str_pad($gps_status_info['carId'], 16, '0', STR_PAD_LEFT);
            $gps_status_info['datetime']=$gps_status_info['gpsTime'];
            $gps_status_info['lastonline']=date('Y-m-d H:i:s',$gps_status_info['gpsTime']);
        }
        */
        $RenterOrderHour= new \Api\Controller\RenterOrderHourController();
        $gps_status_info=$RenterOrderHour->gpsStatusInfo($imei);
        $pt = [$gps_status_info["gd_longitude"],$gps_status_info['gd_latitude']];



        //查询所有禁行区
        $Forbid=$this->ForbidList($rent_id);
        if($Forbid){
            foreach($Forbid as $k=>$v){
                if(count($v['area']) < 3){
                    continue;
                }
                $no_group=[];
                $no_group = $v['area'];
                $result = $carreturn->isInsidePolygon($pt,$no_group);
                //$carreturn->car_range_log(1,1,$pt[0].','.$pt[1],0,$order_id);
                if($result){
                    if($is_return==1){
                        $log_str  = "|还车范围验证失败|在禁行区内";
                        $log_str .= "|pt:$pt";
                        $log_str .= "|distance:-1";
                        $log_str .= "|c_id:{$v['id']}";
                        $log_str .= "|c_radius:-1";

                        $carreturn->car_range_log(1,1,$log_str,0,$order_id);
                    }
                    return ['status'=>1,'data'=>$v];
                }
            }
            if($is_return==1){
                $log_str  = "|还车范围验证成功|在禁行区外";
                $log_str .= "|pt:$pt";

                $carreturn->car_range_log(1,1,$log_str,0,$order_id);
            }
            return false;
        }else{
            return false;
        }

    }



}
