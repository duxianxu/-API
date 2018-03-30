<?php
/**
 * Created by PhpStorm.
 * User: abel
 * Date: 2016/5/14
 * Time: 8:31
 */

namespace Api\Logic;


use User\Api\Api;

class CarReturn
{
    private $location_car = [];//车辆实时位置
    private $location_take_corporation = [];//取车网点位置
    private $location_return_corporation = [];//还车网点位置
    private $location_post = [];//客户端返回小指针位置

    public $free_corporation_id = 1748;//自由还 ->任意停车场

    private $compare_version_4_1_0 = "4.2.0";

    private $is_selected_show_free = 1;//是否在选择网点显示自由还

    public $return_range_msg = "";

    private $app_version = "";
    //获取自由还停车网点id
    public function getFreeCorporationId(){
        return $this->free_corporation_id;
    }

    //获取自由还停车网点信息
    public function getFreeCorporationInfo(){
        $corporation_info = M("corporation")->where(["id"=>$this->free_corporation_id])->find();
        
        return $corporation_info;
    }

    public function __construct(){
        
        $this->app_version = I("get.version");
        if(C("ISTEST") == 1){
            $this->free_corporation_id = 1197;
        }
    }
    /**
     * 得到支持异地还车的网点
     * @param int $rentId 车辆id
     * @param double $lng 当前车辆经度
     * @param double $lat 当前车辆纬度
     * @param int $radius 网点查找半径
     * @param int $page
     * @param int $size
     * @return array
     */
    public function getStations($rentId, $lng, $lat, $radius = 5, $page, $size)
    {
        if($radius<1){$radius=5;}
		//$radius=$radius/10000;
		$rawSize = $size;
        if ($page == 1) {
            //$size = $size + 1;
        }
        $corps = null;
        $rentConfig = M('rent_content_return_config')->where(['rent_content_id' => $rentId])->find();
        $corpM = M('corporation');
        $rent = M('rent_content')->where(['id' => $rentId])->find();

        $takeCorpId = $rent['location_corportation_id'];
        $location_corportation_id = $rent["location_corportation_id"];
        if (empty($takeCorpId)) {
            $takeCorpId = $rent['corporation_id'];
        }
        // if(empty($takeCorpId)){
        //     return false;
        // }
        $takeCorp = $corpM->where(['id' => $takeCorpId])->find();
        /*if (empty($lng) || empty($lat)) {
            $lng = $takeCorp['gis_lng'];
            $lat = $takeCorp['gis_lat'];
        }*/
        $return_mode = $rentConfig['return_mode'];

        if ($rent['corporation_id'] > 0) {  //企业
            //如果是自由还或者宝驾共享车站还，则先变更为网点还，如果公司开启了支持自由还，则支持原先的设置。
            if(in_array($return_mode, [4,16])){

                $return_mode = 2;
                $parent_corporation_id = M("corporation")->where(["id"=>$rent['corporation_id']])->getField("parent_id");
                if($parent_corporation_id > 0){
                    $is_share_station = M("corporation")->where(["id"=>$parent_corporation_id])->getField("is_share_station");

                    if($is_share_station){
                        $return_mode = $rentConfig['return_mode'];
                    }
                    

                }
            }

            //异地还（同公司网点）
            if ($return_mode == 2) {
                if ($rent['corporation_id'] > 0) {
                    $corp = $corpM->where(['id' => $rent['corporation_id']])->find();
                    if ($corp) {
                       
                        if ($lng != 0 || $lat != 0) {
							//$ju1 = "ASIN(SQRT(POW(SIN(({$lat}-a.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(a.gis_lat*PI()/180)*POW(SIN(({$lng}-a.gis_lng)*PI()/360),2)))";
                            $ju = "ASIN(SQRT(POW(SIN(({$lat}-a.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(a.gis_lat*PI()/180)*POW(SIN(({$lng}-a.gis_lng)*PI()/360),2))) as juli";
                            $corps = $corpM->alias('a')
                                ->field("a.*,{$ju}")
                                ->join('corporation_car_return_config b on b.corporation_id=a.id')
                                ->where(" a.status=1 and a.data_status=1  and a.city_id={$rent['city_id']} and a.parent_id={$corp['parent_id']} and a.type=4 and b.acctept_car_type in (1,2)")
                                ->order('juli')
                                ->page($page, $size)
                                ->select();
                        }
                    }
                }
            }
            //自由还
            if ($return_mode == 4 || $return_mode == 16) {
                if ($lng != 0 || $lat != 0) {
                    $where = "a.city_id={$rent['city_id']} and a.type=4 and b.acctept_car_type=2";
                    $corp = $corpM->where(['id' => $rent['corporation_id']])->find();
                    //$ju1 = "ASIN(SQRT(POW(SIN(({$lat}-a.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(a.gis_lat*PI()/180)*POW(SIN(({$lng}-a.gis_lng)*PI()/360),2)))";
					if ($corp) {
                        $where = " a.status=1 and  a.data_status=1 and a.city_id={$rent['city_id']} and a.type=4 and ((a.parent_id={$corp['parent_id']} and b.acctept_car_type=1) or b.acctept_car_type=2)";
                    }
                    $ju = "ASIN(SQRT(POW(SIN(({$lat}-a.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(a.gis_lat*PI()/180)*POW(SIN(({$lng}-a.gis_lng)*PI()/360),2))) as juli";
                    $corps = $corpM->alias('a')
                        ->field("a.*,{$ju}")
                        ->join('corporation_car_return_config b on b.corporation_id=a.id')
                        ->where($where)
                        ->order('juli')
                        ->page($page, $size)
                        ->select();
                }
            }

            //异地还（同公司部分网点）
            if ($return_mode == 8) {
                if ($rent['corporation_id'] > 0) {
                    $corp = $corpM->where(['id' => $rent['corporation_id']])->find();
                    if ($corp) {
                        if ($lng != 0 || $lat != 0) {
							//$ju1 = "ASIN(SQRT(POW(SIN(({$lat}-a.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(a.gis_lat*PI()/180)*POW(SIN(({$lng}-a.gis_lng)*PI()/360),2)))";
                            $ju = "ASIN(SQRT(POW(SIN(({$lat}-a.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(a.gis_lat*PI()/180)*POW(SIN(({$lng}-a.gis_lng)*PI()/360),2))) as juli";
                            $rentStationM = M('rent_content_return_station');
                            $corps = $rentStationM
                                ->alias('c')
                                ->join('corporation a on a.id=c.corporation_id')
                                ->join('corporation_car_return_config b on b.corporation_id=a.id')
                                ->where("c.rent_content_id={$rentId} and c.status=1 and  a.status=1 and  a.data_status=1 and  a.city_id={$rent['city_id']} and a.parent_id={$corp['parent_id']} and a.type=4 and b.acctept_car_type in (1,2)")
                                ->field("a.*,{$ju}")
                                ->order('juli')
								->page($page, $size)
                                ->select();
                        }
                    }
                }
            }
        } else {  //个人

            if ($return_mode == 4) {   //自由还
                if ($lng != 0 || $lat != 0) {

                    //rent_content_return_station
					//$ju1 = "ASIN(SQRT(POW(SIN(({$lat}-b.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(b.gis_lat*PI()/180)*POW(SIN(({$lng}-b.gis_lng)*PI()/360),2)))";
                    $ju = "ASIN(SQRT(POW(SIN(({$lat}-b.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(b.gis_lat*PI()/180)*POW(SIN(({$lng}-b.gis_lng)*PI()/360),2))) as juli";
                    $corps = M("rent_content_return_station")->alias("a")
                        ->join("corporation b on a.corporation_id=b.id ")
						->where("a.rent_content_id= {$rentId} and a.status=1 and a.data_status= 1 and b.city_id = {$rent['city_id']} and b.type= 4 ")
                        //->where(array("a.rent_content_id" => $rentId, "a.status" => 1, " a.data_status" => 1,"b.city_id" => $rent['city_id'], "b.type" => 4,$ju1=>array('lt',$radius)))
                        ->field("b.*,{$ju}")
                        ->order('juli')
                        ->page($page, $size)
                        ->select();
                    //$where = "a.city_id={$rent['city_id']} and a.type=4 and b.acctept_car_type=2)";
                }
            }

        }
        $data = null;
        $result = [];
        $count = 0;


        $julikm = 0;
        $fee['distance'] = "0.0km";
        $fee['return_radius'] = "0";
        $fee['return_fee'] = "0.00";
        $fee['service_price'] = "0.00";
        
		if($page<=1 && $return_mode!=32){
        if($location_corportation_id > 0){
            $data[] = [
                'id' => $takeCorp['id'],
                'name' => $takeCorp['name'],
                'juli' => '0km',
                'address' => (string)($takeCorp['detaile_address'] ? $takeCorp['detaile_address'] : $takeCorp['address']),
                'gis_lng' => $takeCorp['gis_lng'],
                'gis_lat' => $takeCorp['gis_lat'],
                'fee' => $fee,
                'is_default' => 1,
                "return_type" => 1,
            ];
        }else{
			/*
            $search_info = $this->getSearch($rentId);
            if(!empty($search_info)){
                $data[] = [
                    'id' => "0",
                    'name' => $search_info["address"],
                    'juli' => '0km',
                    'address' => $search_info["address"],
                    'gis_lng' => $search_info['gis_lng'],
                    'gis_lat' => $search_info['gis_lat'],
                    'fee' => $fee,
                    'is_default' => 1,
                    "return_type" => 1,
                ];
            }
            */   
        }
        }
        //支持自由还的车辆  
        if($return_mode == 4 && $this->is_selected_show_free == 1){

            $free_corporation_info = $this->getFreeCorporationInfo();
            $data[] = [
                "id" => $free_corporation_info["id"],
                "name" => "任意合法停车位",
                "juli" => "",
                "address" => "只限取车城市中心50公里范围内还车",//$free_corporation_info["address"],
                "gis_lng" => "",
                "gis_lat" => "",
                "fee" => $fee,
                "is_default" => 0,
                "return_type" => 1,
            ];
        }


        //车辆当前位置
        $gis_lng = 0;
        $gis_lat = 0;
        $location = $this->getCarLocation($rent['car_item_id']);
        if ($location) {
            $gis_lng = $location['gis_lng'];
            $gis_lat = $location['gis_lat'];
        }

        $total_return_count = 10;
        $corporation_count_max = 5;
        $corporation_count = 0;
        $park_stations_count = 0;//停车场网点
        
        if ($corps) {
            foreach ($corps as $returnCorp) {
                /*
				if ($returnCorp['id'] == $takeCorp['id']) {
                    continue;
                }
                if($corporation_count >= ($corporation_count_max - 1) && $rentConfig['return_mode'] == 4 ){
                    continue;
                }
                $corporation_count++;
                */ 
                $returnFee = $this->calculateFee($takeCorp, $returnCorp, array(), $rentId);
                $result['id'] = $returnCorp['id'];
                $result['name'] = $returnCorp['name'];
                //$julikm = $returnCorp['juli'] * 12756.276;
                //$distance = number_format(round($julikm, 1), 1, '.', ''); //四舍五入保留一位小数
                //$result['juli'] = $distance . "km";

                if ($gis_lng > 0 && $gis_lat > 0) {
                    $distance = $this->getDistance($gis_lng, $gis_lat, $returnCorp['gis_lng'], $returnCorp['gis_lat']) / 1000; //km
                } else {
                    $distance = $returnFee['distance'];
                }
                $result['juli'] = number_format(round($distance, 2), 1, '.', '') . 'km';

                /*if ($distance >= 0.1) {
                    $result['juli'] = $distance . "km";
                } else if ($distance > 0) {
                    $result['juli'] = (number_format(round($julikm, 2), 2, '.', '') * 1000) . "m";
                } else {
                    $result['juli'] = "10m";
                }*/
                $result['address'] = $returnCorp['detaile_address'];
                $result['gis_lng'] = $returnCorp['gis_lng'];
                $result['gis_lat'] = $returnCorp['gis_lat'];
                $result['fee'] = $returnFee;
                $result['return_type'] = 1;
                $data[] = $result;

                $count++;
                if ($page == 1 && $count == $rawSize) {
                    break;
                }
            }
        }
        
        // $park_stations_count = $total_return_count - $corporation_count - 1;
        // if($park_stations_count > 0 && $rentConfig['return_mode'] == 4 ){
        //     $park_stations = $this->getAmapList($lng.",".$lat,"停车场",$park_stations_count);
        //     if(count($park_stations["pois"])>0){
        //         foreach ($park_stations["pois"] as $c_key => $c_val) {
        //             # code...
        //             $result = [];
        //             $result["id"] = $c_val["id"];
        //             $result["name"] = $c_val["name"];
                    
        //             $result["address"] = $c_val["address"];

        //             $location_park = explode(",", $c_val["location"]);
        //             $result["gis_lng"] = $location_park[0];
        //             $result["gis_lat"] = $location_park[1];

        //             $returnConfig = [];
                    
        //             //计算正常异地还车费
        //             //$feeInfo = $this->calculateFee($takeCorp, $returnCorp, $returnConfig);
        //             $returnCorp["gis_lng"] = $location_park[0];
        //             $returnCorp["gis_lat"] = $location_park[1];
        //             $returnCorp["id"] = $c_val["id"];
        //             $returnCorp["is_park_station"] = 1;
        //             $feeInfo = $this->calculateFeeByLocation($gis_lng, $gis_lat, $returnCorp, $returnConfig, $rentId);
        //             $returnFee = $feeInfo['return_fee'];
                    

        //             $serviceFee = 0.00; //还车手续费

        //             $distanceExtMeter = 0;
        //             if ($gis_lng > 0 && $gis_lat > 0) {
        //                 //计算额外异地还车费
        //                 $distance = $this->getDistance($gis_lng, $gis_lat,$location_park[0], $location_park[1]) / 1000; //km
        //                 $distanceExtMeter = $this->getDistance($gis_lng, $gis_lat, $location_park[0], $location_park[1]);
        //                 if ($distanceExtMeter > $returnRadius) {
        //                     $serviceFee = max(0, $returnConfig['service_price']);
        //                     $offset = ($distanceExtMeter - $returnRadius) / 1000;//超出还车点距离

        //                     if ($returnConfig['mile_price'] > 0) {
        //                         $returnFee = $returnFee + $offset * $returnConfig['mile_price'];
        //                     }
        //                 }
        //             }

        //             $fee['distance'] = number_format(round($distance, 2), 1, '.', '');//取车网点和还车网点的距离km
        //             $fee['distance_ext'] = round($distanceExtMeter / 1000, 2);//当前位置和还车网点的距离km
        //             $fee['return_radius'] = round($returnRadius,1);//网点的有效还车半径
        //             $fee['return_fee'] = number_format(round($returnFee, 2), 2, '.', '');//还车服务费
        //             $fee['service_price'] = number_format(round($feeInfo["service_price"], 2), 2, '.', '');//还车手续费
        //             $fee['error'] = 0;
        //             $fee['msg'] = '';
        //             $result["juli"] = $fee['distance'] . 'km';
        //             $result['return_type'] = 2;
        //             $result["fee"] = $fee;

        //             $data[] = $result;
        //         }
        //     }
        // }


        return $data;
    }

    public function getStationsForOrder($orderId, $lng, $lat, $radius = 0, $page, $size)
    {
        $rawSize = $size;
        if ($page == 1) {
            $size = $size + 1;
        }
        
        

        $order = M('trade_order')->where(['id' => $orderId])->find();

        // $location = $this->getCarLocation($order['car_item_id']);
        // if ($location) {
        //     $lng = $location['gis_lng'];
        //     $lat = $location['gis_lat'];
        // }

        
        $rentId = $order['rent_content_id'];
        $corps = null;
        $orderConfig = M('trade_order_car_return_info')->where(['order_id' => $orderId])->find();
        $corpM = M('corporation');

        $rentConfig = M('rent_content_return_config')->where(['rent_content_id' => $rentId])->find();
        $rent = M('rent_content')->where(['id' => $rentId])->find();

        $takeCorpId = $orderConfig['take_corporation_id'];

        //$rent = M('rent_content')->where(['id' => $rentId])->find();

        if (empty($takeCorpId)) {
            // $takeCorpId = $rent['location_corportation_id'];
            // if (empty($takeCorpId)) {
                $takeCorpId = $rent['corporation_id'];
            // }
        }
        
        $takeCorp = $corpM->where(['id' => $takeCorpId])->find();

        //\Think\Log::write('订单选择还车网点----takeid=' . $takeCorp['id'], 'INFO');
        /*if (empty($lng) || empty($lat)) {
            $lng = $takeCorp['gis_lng'];
            $lat = $takeCorp['gis_lat'];
        }*/

        $return_mode = $rentConfig['return_mode'];
        if ($rent['corporation_id'] > 0) {  //企业
            //如果是自由还或者宝驾共享车站还，则先变更为网点还，如果公司开启了支持自由还，则支持原先的设置。
            if(in_array($return_mode, [4,16])){

                $return_mode = 2;
                $parent_corporation_id = M("corporation")->where(["id"=>$rent['corporation_id']])->getField("parent_id");
                if($parent_corporation_id > 0){
                    $is_share_station = M("corporation")->where(["id"=>$parent_corporation_id])->getField("is_share_station");

                    if($is_share_station){
                        $return_mode = $rentConfig['return_mode'];
                    }
                    

                }
            }
            //异地还（同公司网点）
            if ($return_mode == 2 || $return_mode == 32) {
                if ($rent['corporation_id'] > 0) {
                    $corp = $corpM->where(['id' => $rent['corporation_id']])->find();
                    if ($corp) {

                        if ($lng != 0 || $lat != 0) {
                            $ju = "ASIN(SQRT(POW(SIN(({$lat}-a.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(a.gis_lat*PI()/180)*POW(SIN(({$lng}-a.gis_lng)*PI()/360),2))) as juli";
                            $corps = $corpM->alias('a')
                                ->field("a.*,{$ju}")
                                ->join('corporation_car_return_config b on b.corporation_id=a.id')
                                ->where(" a.status=1  and  a.data_status=1 and a.city_id={$rent['city_id']} and a.parent_id={$corp['parent_id']} and a.type=4 and b.acctept_car_type in (1,2)")
                                ->order('juli')
                                ->page($page, $size)
                                ->select();
                        }
                    }
                }
            }
            //自由还
            if ($return_mode == 4 || $return_mode == 16) {
                if ($lng != 0 || $lat != 0) {
                    $where = "a.city_id={$rent['city_id']} and a.type=4 and b.acctept_car_type=2";
                    $corp = $corpM->where(['id' => $rent['corporation_id']])->find();
                    if ($corp) {
                        $where = " a.status=1 and  a.data_status=1  and a.city_id={$rent['city_id']} and a.type=4 and ((a.parent_id={$corp['parent_id']} and b.acctept_car_type=1) or b.acctept_car_type=2)";
                    }
                    $ju = "ASIN(SQRT(POW(SIN(({$lat}-a.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(a.gis_lat*PI()/180)*POW(SIN(({$lng}-a.gis_lng)*PI()/360),2))) as juli";
                    $corps = $corpM->alias('a')
                        ->field("a.*,{$ju}")
                        ->join('corporation_car_return_config b on b.corporation_id=a.id')
                        ->where($where)
                        ->order('juli')
                        ->page($page, $size)
                        ->select();
                }
            }

            //异地还（同公司部分网点）
            if ($return_mode == 8) {
                if ($rent['corporation_id'] > 0) {
                    $corp = $corpM->where(['id' => $rent['corporation_id']])->find();
                    if ($corp) {
                        if ($lng != 0 || $lat != 0) {
                            $ju = "ASIN(SQRT(POW(SIN(({$lat}-a.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(a.gis_lat*PI()/180)*POW(SIN(({$lng}-a.gis_lng)*PI()/360),2))) as juli";
                            $rentStationM = M('rent_content_return_station');
                            $corps = $rentStationM
                                ->alias('c')
                                ->join('corporation a on a.id=c.corporation_id')
                                ->join('corporation_car_return_config b on b.corporation_id=a.id')
                                ->where("c.rent_content_id={$rentId} and c.status=1 and  a.status=1 and  a.data_status=1 and a.city_id={$rent['city_id']} and a.parent_id={$corp['parent_id']} and a.type=4 and b.acctept_car_type in (1,2)")
                                ->field("a.*,{$ju}")
                                ->order('juli')
                                ->select();
                        }
                    }
                }
            }
        } else {  //个人

            if ($return_mode == 4) {   //自由还
                if ($lng != 0 || $lat != 0) {

                    //rent_content_return_station
                    $ju = "ASIN(SQRT(POW(SIN(({$lat}-b.gis_lat)*PI()/360),2)+COS({$lat}*PI()/180)*COS(b.gis_lat*PI()/180)*POW(SIN(({$lng}-b.gis_lng)*PI()/360),2))) as juli";
                    $corps = M("rent_content_return_station")->alias("a")
                        ->join("corporation b on a.corporation_id=b.id ")
                        ->where(array("a.rent_content_id" => $rentId, "a.status" => 1, " a.data_status" => 1,"b.city_id" => $rent['city_id'], "b.type" => 4))
                        ->field("b.*,{$ju}")
                        ->order('juli')
                        ->page($page, $size)
                        ->select();
                    //$where = "a.city_id={$rent['city_id']} and a.type=4 and b.acctept_car_type=2)";
                }
            }

        }
        $data = null;
        $result = [];
        $count = 0;

        $gis_lng = 0;
        $gis_lat = 0;
        $location = $this->getCarLocation($order['car_item_id']);
        if ($location) {
            $gis_lng = $location['gis_lng'];
            $gis_lat = $location['gis_lat'];
        }

        //$julikm = 0;
        $lfee['distance'] = "0.0km";
        $lfee['return_radius'] = "0";
        $lfee['return_fee'] = "0.00";
        $lfee['service_price'] = "0.00";
        $data[] = [
            'id' => $takeCorp['id'],
            'name' => $takeCorp['name'],
            'juli' => '0km',
            'address' => $takeCorp['detaile_address'],
            'gis_lng' => $takeCorp['gis_lng'],
            'gis_lat' => $takeCorp['gis_lat'],
            'fee' => $lfee,
            'is_default' => 1,
            'return_type' => 1
        ];
        //如果没有还车网点
        $is_default = 0;
        if(empty($orderConfig["return_corporation_id"])&& $orderConfig["is_parade"] == 1){
            $data = [];
            $is_default = 1;
        }


        if($orderConfig["is_parade"] == 1){
            $ext_info = M("trade_order_ext")->where(["order_id"=>$orderId])->find();

            $renterOrderLogic = new \Api\Logic\RenterOrder();
            $address_info = $renterOrderLogic->OrderAddress($orderId);

            $data[] = [
                'id' => -1,
                'name' => "原取车地点",
                'juli' => '0km',
                'address' => $address_info['address'],
                'gis_lng' => $address_info['gis_lng'],
                'gis_lat' => $address_info['gis_lat'],
                'fee' => $lfee,
                'is_default' => $is_default,
            ];    
        }

        
        //支持自由还的车辆  
        if(compare_version($this->app_version, $this->compare_version_4_1_0) >= 0 && $return_mode == 4 && $this->is_selected_show_free == 1){

            $free_corporation_info = $this->getFreeCorporationInfo();
            $data[] = [
                "id" => $free_corporation_info["id"],
                "name" => "任意合法停车位",
                "juli" => "",
                "address" => "只限取车城市中心50公里范围内还车",//$free_corporation_info["address"],
                "gis_lng" => "",
                "gis_lat" => "",
                "fee" => $lfee,
                "is_default" => $free_corporation_info["id"] == $returnCorp['id'] ? 1 : 0,
                "return_type" => 1,
            ];
        }

        $total_return_count = 10;
        $corporation_count_max = 5;
        $corporation_count = 0;
        $park_stations_count = 0;//停车场网点
        if ($corps) {
            foreach ($corps as $returnCorp) {
                if ($returnCorp['id'] == $takeCorp['id']) {
                    continue;
                }
                if($corporation_count >= ($corporation_count_max - 1) && $rentConfig['return_mode'] == 4 ){
                    continue;
                }
                $corporation_count++;

                //$returnMode = $orderConfig['return_mode'];//1=原网点还车,2=异地还车(同公司网点),4=自由还车
                //$acceptType = $returnCorp['acctept_car_type'];//收车类型0=不接受其他网点车辆，1=只接受本公司网点车辆,2=接受社会车辆

                $returnRadius = $returnCorp['return_radius'];


                if ($takeCorp) {
                    $take_lng = $takeCorp['gis_lng'];
                    $take_lat = $takeCorp['gis_lat'];
                } else {
                    $take_lng = $orderConfig['take_device_lng'];//取车经度
                    $take_lat = $orderConfig['take_device_lat'];//取车纬度

                    if (empty($take_lng)) {
                        $take_lng = $orderConfig['take_lng'];
                        $take_lat = $orderConfig['take_lat'];
                    }
                }

                $returnConfig = $this->getReturnConfig($returnCorp['id']);

                //计算正常异地还车费
                //$feeInfo = $this->calculateFee($takeCorp, $returnCorp, $returnConfig);
                $feeInfo = $this->calculateFeeByLocation($take_lng, $take_lat, $returnCorp, $returnConfig, $rentId);
                $returnFee = $feeInfo['return_fee'];
                

                $serviceFee = 0.00; //还车手续费

                $distanceExtMeter = 0;
                if ($gis_lng > 0 && $gis_lat > 0) {
                    //计算额外异地还车费
                    $distance = $this->getDistance($gis_lng, $gis_lat, $take_lng, $take_lat);

                    $distanceExtMeter = $this->getDistance($gis_lng, $gis_lat, $returnCorp['gis_lng'], $returnCorp['gis_lat']);
                    if ($distanceExtMeter > $returnRadius) {
                        $serviceFee = max(0, $returnConfig['service_price']);
                        $offset = ($distanceExtMeter - $returnRadius) / 1000;//超出还车点距离

                        if ($returnConfig['mile_price'] > 0) {
                            $returnFee = $returnFee + $offset * $returnConfig['mile_price'];
                        }
                    }
                }

                $fee['distance'] = number_format(round($distance, 2), 1, '.', '');//取车网点和还车网点的距离km
                $fee['distance_ext'] = round($distanceExtMeter / 1000, 2);//当前位置和还车网点的距离km
                $fee['return_radius'] = round($returnRadius,1);//网点的有效还车半径
                $fee['return_fee'] = number_format(round($returnFee, 2), 2, '.', '');//还车服务费
                $fee['service_price'] = number_format(round($serviceFee, 2), 2, '.', '');//还车手续费
                $fee['error'] = 0;
                $fee['msg'] = '异地还车(同公司网点)';


                $result['id'] = $returnCorp['id'];
                $result['name'] = $returnCorp['name'];

                //$julikm = $returnCorp['juli'] * 12756.276;
                //$distance = number_format(round($julikm, 1), 1, '.', ''); //四舍五入保留一位小数
                //$result['juli'] = $distance . "km";

                $result['juli'] = $fee['distance_ext'] . 'km';
                /*if ($distance >= 0.1) {
                    $result['juli'] = $distance . "km";
                } else if ($distance > 0) {
                    $result['juli'] = (number_format(round($julikm, 2), 2, '.', '') * 1000) . "m";
                } else {
                    $result['juli'] = "10m";
                }*/
                $result['address'] = $returnCorp['detaile_address'];
                $result['gis_lng'] = $returnCorp['gis_lng'];
                $result['gis_lat'] = $returnCorp['gis_lat'];
                $result['fee'] = $fee;
                $result['return_type'] = 1;
                $data[] = $result;
                //\Think\Log::write('订单选择还车网点----returnid=' . json_encode($result), 'INFO');

                $count++;
                if ($page == 1 && $count == $rawSize) {
                    break;
                }
            }
        }
        // $park_stations_count = $total_return_count - $corporation_count - 1;
        // if($park_stations_count > 0 && $rentConfig['return_mode'] == 4 ){
        //     $park_stations = $this->getAmapList($lng.",".$lat,"停车场",$park_stations_count);
        //     if(count($park_stations["pois"])>0){
        //         foreach ($park_stations["pois"] as $c_key => $c_val) {
        //             # code...
        //             $result = [];
        //             $result["id"] = $c_val["id"];
        //             $result["name"] = $c_val["name"];
                    
        //             $result["address"] = $c_val["address"];

        //             $location_park = explode(",", $c_val["location"]);
        //             $result["gis_lng"] = $location_park[0];
        //             $result["gis_lat"] = $location_park[1];

        //             $returnConfig = $this->getReturnConfig($returnCorp['id']);
        //             //计算正常异地还车费
        //             //$feeInfo = $this->calculateFee($takeCorp, $returnCorp, $returnConfig);
        //             $returnCorp["gis_lng"] = $location_park[0];
        //             $returnCorp["gis_lat"] = $location_park[1];
        //             $returnCorp["id"] = $c_val["id"];
        //             $returnCorp["is_park_station"] = 1;
        //             $feeInfo = $this->calculateFeeByLocation($take_lng, $take_lat, $returnCorp, $returnConfig, $rentId);
        //             $returnFee = $feeInfo['return_fee'];
                    

        //             $serviceFee = 0.00; //还车手续费

        //             $distanceExtMeter = 0;
        //             if ($gis_lng > 0 && $gis_lat > 0) {
        //                 //计算额外异地还车费
        //                 $distance = $this->getDistance($gis_lng, $gis_lat,$location_park[0], $location_park[1]) / 1000; //km
        //                 $distanceExtMeter = $this->getDistance($gis_lng, $gis_lat, $location_park[0], $location_park[1]);
        //                 if ($distanceExtMeter > $returnRadius) {
        //                     $serviceFee = max(0, $returnConfig['service_price']);
        //                     $offset = ($distanceExtMeter - $returnRadius) / 1000;//超出还车点距离

        //                     if ($returnConfig['mile_price'] > 0) {
        //                         $returnFee = $returnFee + $offset * $returnConfig['mile_price'];
        //                     }
        //                 }
        //             }

        //             $fee['distance'] = number_format(round($distance, 2), 1, '.', '');//取车网点和还车网点的距离km
        //             $fee['distance_ext'] = round($distanceExtMeter / 1000, 2);//当前位置和还车网点的距离km
        //             $fee['return_radius'] = round($returnRadius,1);//网点的有效还车半径
        //             $fee['return_fee'] = number_format(round($returnFee, 2), 2, '.', '');//还车服务费
        //             $fee['service_price'] = number_format(round($serviceFee, 2), 2, '.', '');//还车手续费
        //             $fee['error'] = 0;
        //             $fee['msg'] = '';
        //             $result["juli"] = $fee['distance'] . 'km';
        //             $result['return_type'] = 2;
        //             $result["fee"] = $fee;

        //             $data[] = $result;
        //         }
        //     }
        // }
        return $data;
    }

    /**
     * 根据车辆当前的经纬度，计算到指定还车网点的还车费用
     * @param double $lng 经度
     * @param double $lat 纬度
     * @param mixed $returnCorp 还车网点
     * @param mixed $returnConfig 网点异地还车的相关配置
     * @return mixed
     */
    public function calculateFeeByLocation($lng, $lat, $returnCorp, $returnConfig = null, $rentId = 0)
    {
        //计算距离
        $distance = $this->getDistance($lng, $lat, $returnCorp['gis_lng'], $returnCorp['gis_lat']) / 1000; //km
        $returnFee = 0.00;  //还车费
        $serviceFee = 0.00;

        //如果是自由还
        if($returnCorp["is_park_station"] == 1 && $distance > 0){

            $config_free = M("config")->where(["name"=>'RENT_HOUR_FREE_SET'])->field("value")->find();
            $arr_config = explode("|", $config_free["value"]);
            $arr_config["return_radius"] = $arr_config[0];//还车半径
            $arr_config["service_price"] = $arr_config[1];//手续费
            $arr_config["mile_price"]    = $arr_config[2];//还车公里价
            $arr_config["base_mile"]     = $arr_config[3];//免费公里数

            
                
            // var_dump($corporation_config["mile_price_free"]);
            if($distance > $arr_config["base_mile"] && $arr_config["mile_price"] > 0){
                $returnFee = ($distance - $arr_config['base_mile']) * $arr_config['mile_price'];

            }
            
            $serviceFee = $returnConfig['service_price'] = $arr_config["service_price"];
            

        }elseif ($distance > 0) {
            if (empty($returnConfig)) {
                $returnConfig = $this->getReturnConfig($returnCorp['id']);
            }
            //计费方式0=免费,1=起步价+超公里价
            if ($returnConfig['return_charge_mode'] == 1) {

                //取还车网点相同，或还车网点是车辆所属网点，不收取费用
                if ($rentId > 0) {
                    $rentContentModel = new \Home\Model\RentContentModel();
                    $rentContent = $rentContentModel->get_model("id={$rentId}", "location_corportation_id,corporation_id");
                    if ($rentContent['location_corportation_id'] > 0) {
                        $realTakeCorpId = $rentContent['location_corportation_id'];
                    } else {
                        $realTakeCorpId = $rentContent['corportation_id'];
                    }
                    $oldTakeCorpId = $rentContent['corportation_id'];
                }
                if ($realTakeCorpId == $returnCorp['id'] || $oldTakeCorpId == $returnCorp['id']) {
                    $returnFee = 0.00;
                } else {
                    $returnFee = max(0, $returnConfig['base_price']);//还车费=基础费+里程费
                    if ($distance > $returnConfig['base_miles'] && $returnConfig['mile_price'] > 0) {
                        $returnFee = $returnFee + ($distance - $returnConfig['base_miles']) * $returnConfig['mile_price'];
                    }
                }
            }

        }
        $result['distance'] = round($distance, 2);//取车网点和还车网点的距离km
        $result['return_radius'] = round($returnConfig['return_radius'],2);//网点的有效还车半径
        $result['return_fee'] = number_format(round($returnFee, 2), 2, '.', '');//还车服务费
        $result['service_fee'] = number_format(round($serviceFee, 2), 2, '.', '');//还车手续费
        $result['service_price'] = number_format(round($returnConfig['service_price'], 2), 2, '.', '');//还车手续费
        return $result;
    }

    /**
     * 计算网点之间的还车费用
     * @param mixed $takeCorp 取车网店
     * @param mixed $returnCorp 还车网点
     * @param mixed $returnConfig 网点异地还车的相关配置
     * @return mixed
     */
    public function calculateFee($takeCorp, $returnCorp, $returnConfig = null, $rentId)
    {
        if (!is_array($returnConfig)) {
            $returnConfig = $this->getReturnConfig($returnCorp['id']);
        }
        if ($takeCorp['id'] == $returnCorp['id']) {
            $result['distance'] = number_format(0, 2, '.', '');//取车网点和还车网点的距离km
            $result['return_radius'] = $returnConfig['return_radius'];//网点的有效还车半径
            $result['return_fee'] = 0.00;//还车服务费
            $result['service_fee'] = 0.00;
            $result['service_price'] = round($returnConfig['service_price'], 2);//还车手续费
            return $result;
        }
        return $this->calculateFeeByLocation($takeCorp['gis_lng'], $takeCorp['gis_lat'], $returnCorp, $returnConfig, $rentId);
    }

    /**
     *
     * 计算订单还车的相关费用
     * @param int $orderId
     * @param double $lng 车辆当前经度
     * @param double $lat 车辆当前纬度
     * @param int $corporationId
     * @return array
     */
    public function calculateFeeByOrderId($orderId, $lng, $lat, $corporationId)
    {
        $corporationM = M('corporation');

        $order = M('trade_order')->where(['id' => $orderId])->find();
        $rentId = $order['rent_content_id'];
        $orderReturnInfo = M('trade_order_car_return_info')->where(['order_id' => $orderId])->find();
        $rentContent = M('rent_content')->where(['id' => $rentId])->find();
        $returnCorp = $corporationM->where(['id' => $corporationId])->find();//还车网点


        $takeCorpId = $orderReturnInfo['take_corportation_id'];

        if (empty($takeCorpId)) {
            $takeCorpId = $rentContent['location_corportation_id'];
            if (empty($takeCorpId)) {
                $takeCorpId = $rentContent['corporation_id'];
            }
        }

        $result = [
            'error' => 0,
            'msg' => '',
            'return_fee' => 0,  //还车费
            'service_fee' => 0,  //手续费
            'distance' => 0,
            'distance_ext' => 0,
            'return_radius' => 0
        ];

        $takeCorp = $corporationM->where(['id' => $takeCorpId])->find();//取车网点
        //$rentReturnConfig = M('rent_content_return_config')->where(['' => $rentId])->find();
        $returnConfig = $this->getReturnConfig($corporationId);


        if (!$returnConfig) {
            $result['error'] = 1;
            $result['msg'] = '网点收车信息不存在';
            return $result;
        }

        $result['return_radius'] = $returnConfig['return_radius'];

        if (!$orderReturnInfo) {
            $result['error'] = 1;
            $result['msg'] = '没有相关订单信息';
            return $result;
        }

        $returnMode = $orderReturnInfo['return_mode'];//1=原网点还车,2=异地还车(同公司网点),4=自由还车
        $acceptType = $returnConfig['acctept_car_type'];//收车类型0=不接受其他网点车辆，1=只接受本公司网点车辆,2=接受社会车辆 

        if ($acceptType == 0 && $takeCorpId != $corporationId && $corporationId != $this->getFreeCorporationId()) {
            $result['error'] = 1;
            $result['msg'] = '此网点不接收其他网点车辆';
            return $result;
        }

        if ($returnMode == 1) {
            $result['error'] = 2;
            $result['msg'] = '原网点还车';
            return $result;
        }

        //异地还车(同公司网点)
        if ($returnMode == 2 || ($acceptType == 1 && $takeCorpId != $corporationId)) {
            $carCorp = $corporationM->where(['id' => $rentContent['corporation_id']])->find();
            if ($returnCorp['parent_id'] != $carCorp['parent_id']) {
                $result['error'] = 2;
                $result['msg'] = '不是同公司网点';
                return $result;
            }
        }

        //自由还车
        if ($returnMode == 4) {
        }
        \Think\Log::write('订单更改还车网点----takeid=' . $takeCorp['id'], 'INFO');

        $returnRadius = $returnConfig['return_radius'];

        if ($takeCorp) {
            $take_lng = $takeCorp['gis_lng'];
            $take_lat = $takeCorp['gis_lat'];
        } else {
            $take_lng = $orderReturnInfo['take_device_lng'];//取车经度
            $take_lat = $orderReturnInfo['take_device_lat'];//取车纬度

            if (empty($take_lng)) {
                $take_lng = $orderReturnInfo['take_lng'];
                $take_lat = $orderReturnInfo['take_lat'];
            }
        }
        //取还车网点相同，或还车网点是车辆所属网点，不收取费用
        if ($takeCorpId == $corporationId || $rentContent['corporation_id'] == $corporationId) {
            $returnFee = 0.00;
        } else {
            //计算正常异地还车费
            //$feeInfo = $this->calculateFee($takeCorp, $returnCorp, $returnConfig);
            $feeInfo = $this->calculateFeeByLocation($take_lng, $take_lat, $returnCorp, $returnConfig, $rentId);
            $returnFee = $feeInfo['return_fee'];
            //$distance = $feeInfo['distance'];
        }


        $serviceFee = 0.00; //还车手续费

        $gis_lng = 0;
        $gis_lat = 0;
        $location = $this->getCarLocation($order['car_item_id']);
        if ($location) {
            $gis_lng = $location['gis_lng'];
            $gis_lat = $location['gis_lat'];
        }
        $distanceExtMeter = 0;
        if ($gis_lng > 0 && $gis_lat > 0) {
            //计算额外异$distanceExtMeter地还车费
            $distanceExtMeter = $this->getDistance($gis_lng, $gis_lat, $returnCorp['gis_lng'], $returnCorp['gis_lat']);
            if ($distanceExtMeter > $returnRadius) {
                $serviceFee = max(0, $returnConfig['service_price']);
                $offset = ($distanceExtMeter - $returnRadius) / 1000;//超出还车点距离

                if ($returnConfig['mile_price'] > 0) {
                    if(!($takeCorpId == $corporationId || $rentContent['corporation_id'] == $corporationId)){
                        $returnFee = $returnFee + $offset * $returnConfig['mile_price'];
                    }
                }
            }
        }

        //$result['distance'] = round($distance, 2);//取车网点和还车网点的距离km
        $result['distance'] = number_format(round($distanceExtMeter / 1000, 2), 2, '.', '');//当前位置和还车网点的距离km
        $result['distance_ext'] = number_format(round($distanceExtMeter / 1000, 2), 2, '.', '');//当前位置和还车网点的距离km
        $result['return_radius'] = $returnRadius;//网点的有效还车半径
        $result['return_fee'] = number_format(round($returnFee, 2), 2, '.', '');//还车服务费
        $result['service_fee'] = number_format(round($serviceFee, 2), 2, '.', '');//还车手续费
        $result['error'] = 0;
        $result['msg'] = '异地还车(同公司网点)';

        \Think\Log::write('订单更改还车网点----returnid=' . json_encode($result), 'INFO');

        return $result;
    }

    /**
     * 得到网点异地还车的相关配置
     * @param int $corporationId 网点id
     * @return mixed|null
     */
    public function getReturnConfig($corporationId)
    {
        $corpReturnConfig = M('corporation_car_return_config')->where(['corporation_id' => $corporationId])->find();
        if (!$corpReturnConfig) {
            return null;
        }
        $sysReturnConfig = M('corporation_car_return_config')->where(['corporation_id' => 0])->find();

        if ($corpReturnConfig['return_radius'] < 0) {
            $corpReturnConfig['return_radius'] = $sysReturnConfig['return_radius'];
        }

        if ($corpReturnConfig['base_miles'] < 0) {
            $corpReturnConfig['base_miles'] = $sysReturnConfig['base_miles'];
        }

        if ($corpReturnConfig['base_price'] < 0) {
            $corpReturnConfig['base_price'] = $sysReturnConfig['base_price'];
        }

        if ($corpReturnConfig['mile_price'] < 0) {
            $corpReturnConfig['mile_price'] = $sysReturnConfig['mile_price'];
        }

        if ($corpReturnConfig['service_price'] < 0) {
            $corpReturnConfig['service_price'] = $sysReturnConfig['service_price'];
        }

        return $corpReturnConfig;
    }

    /**
     * 得到两点间距离
     * @param double $lng1
     * @param double $lat1
     * @param double $lng2
     * @param double $lat2
     * @return float|int
     */
    function getDistance($lng1, $lat1, $lng2, $lat2)
    {    //将角度转为狐度
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
        查看车辆是否支持所选还车网点
    */
    public function checkReturnCorporation($rent_id,$return_corp_id){

        $result = [];

        $rc_config_info = M("rent_content_return_config")->alias("rcrc")
                                                         ->where(["rcrc.rent_content_id"=>$rent_id])
                                                         ->find();

        $rc_info = M("rent_content")->alias("rc")
                                    ->where(["rc.id"=>$rent_id])
                                    ->find();

        $return_mode = $rc_config_info["return_mode"];

        $result["return_mode"] = $return_mode;
        //如果是原网点还车，校验一下
        if($return_mode == 1){
            $take_corp_id = $rc_info["location_corportation_id"] ? $rc_info["location_corportation_id"] : $rc_info["corportation_id"];
            if($take_corp_id != $return_corp_id){
                $result["status"] = 0;
                $result["info"] = "非原网点";
                return $result;
            }
        }

        //如果是同公司网点还车 ，校验一下是否同公司网点
        if($return_mode == 2){

            $corp_info = M("corporation")->alias("c")
                                         ->where(['id'=>$rc_info["corporation_id"]])
                                         ->find();

            $parent_id = $corp_info["parent_id"];

            $return_corp_info = M("corporation_car_return_config")->alias("ccrc")
                                                                  ->where(["corporation_id"=>$return_corp_id])
                                                                  ->find();

            if(!in_array($return_corp_info["acctept_car_type"],[1])){
                $result["status"] = 0;
                $result["info"] = "非同公司网点";
                return $result;
            }
        }

        //自由还
        if($return_mode == 4){
            if($return_corp_id != $this->getFreeCorporationId()){
                $result["status"] = 0;
                $result["info"] = "非自由还网点";
                return $result;
            }
        }

        //
        if($return_mode == 8){
            
            $corp_info = M("corporation")->alias("c")
                                         ->where(['id'=>$rc_info["corporation_id"]])
                                         ->find();

            $parent_id = $corp_info["parent_id"];

            $return_corp_info = M("corporation_car_return_config")->alias("ccrc")
                                                                  ->where(["corporation_id"=>$return_corp_id])
                                                                  ->find();

            if(!in_array($return_corp_info["acctept_car_type"],[1])){
                $result["status"] = 0;
                $result["info"] = "非同公司网点";
                return $result;
            }
        }

        return ["status"=>1,"info"=>"校验成功"];


    }
    /**
     * 检测是否在有效还车范围内
     * @param mixed $returnCorp 还车网点
     * @param int $carItemId 车辆id
     * @param double $gis_lng
     * @param double $gis_lat
     * @return bool true超范围，false在有效范围内
     */
    public function checkReturnRange($returnCorp, $carItemId, $gis_lng, $gis_lat,$is_return=0)
    {
        if (C("ISTEST") == "1") {
            //return false;
        }
		$log_str=$returnCorp["id"].'|'.$carItemId.'|'.$gis_lng.','.$gis_lat;
        $this->car_range_log(0,0,$log_str); 
		
        //一度车辆不验证还车范围
        $rent_content_info = M("rent_content")->where(["car_item_id"=>$carItemId])->find();
        $car_owner_id = $rent_content_info["user_id"];
		
        if($rent_content_info["sort_id"] == 112){
			$is_free_area=false;
			if($returnCorp["return_mode"]==32){
				$is_free_area = $this->isInFreeArea($carItemId);
				\Think\Log::write("--还车范围检查1111, ".$is_free_area, 'INFO');
			}
            if($is_free_area){
                return false;
            }else{
                if(I("get.orderId") > 0){
                    $order = M("trade_order")->where(["id"=>I("get.orderId")])->find();
					if($is_return==1){
						$is_xiaomi_in_area = (new \Api\Logic\RenterOrderHour())->isXiaomiInArea($order['id'],$order['rent_content_id'],1);
						if(!$is_xiaomi_in_area){//返回false 不在网点直接返回true，在网点返回网点信息
							$is_xiaomi_in_area=true;
						}
						return $is_xiaomi_in_area;
                    }else{
						$is_xiaomi_in_area = (new \Api\Logic\RenterOrderHour())->isXiaomiInArea($order['id'],$order['rent_content_id'],3);
						if($is_xiaomi_in_area){
							return false;
						} 
					}					
                }
                
            }    
        }
		 if($returnCorp["id"] == $this->getFreeCorporationId() || $returnCorp["return_mode"]==4){
            $this->car_range_log(1,1,"|还车范围验证成功|1");
            return false;
        }
		
        /* 自由还不检查还车范围 */
        if($returnCorp["id"] == $this->getFreeCorporationId()){
            $this->car_range_log(1,1,"|还车范围验证成功|1");
            return false;
        }
		if(empty($returnCorp["id"])){
            $this->car_range_log(1,1,"|还车范围验证成功|8");
            return false;
        }

        $log_str = "";
        //实时地址与网店的配置文件进行比较
        //$lastLocation = $this->getCarLocation($carItemId);
        $configM = M('corporation_car_return_config');
        $corpReturnConfig = $configM->where(['corporation_id' => $returnCorp['id']])->find();
        $radius = $corpReturnConfig['return_radius'];

        if (!is_array($corpReturnConfig)) {
            \Think\Log::write("--还车范围检查,不能还车, 还车网点配置为空 corpid=" . $returnCorp['id'], 'INFO');
        } else {
            //\Think\Log::write("--还车范围检查,不能还车, 还车网点 corpid=" . $returnCorp['id'], 'INFO');
        }

        $is_polygon = 0;
        if($returnCorp["id"] > 0){
            $group_poly = M("corporation_group")->where(["corporation_id"=>$returnCorp['id'],"status"=>1])->find();

            if(is_array($group_poly)){
                $is_polygon = 1;
            }
        }

        $locations = $this->getCarAllLocation($carItemId,1,1);
        if ($locations) {
            $log = '';
            foreach ($locations as $lastLocation) {
                $dev_lng = $lastLocation['gis_lng'];
                $dev_lat = $lastLocation['gis_lat'];
                //计算距离
                if($is_polygon == 1){
                    if($this->polygonReturnCheck($returnCorp['id'],$dev_lng,$dev_lat)){
                        return false;
                    }
                }else{
                    $distance = $this->getDistance($dev_lng, $dev_lat, $returnCorp['gis_lng'], $returnCorp['gis_lat']);
                    //与要求的还车半径对比
                    $this->return_range_msg .= "|1|".$dev_lng."|".$dev_lat."|".$returnCorp['gis_lng']."|".$returnCorp['gis_lat']."|".$distance."|".$radius;
                    if ($distance < $radius) {
                        \Think\Log::write("--[验证设备经纬度：可以还车] datetime={$lastLocation['update_time']} imei={$lastLocation['imei']} org={$lastLocation['org_lng']},{$lastLocation['org_lat']} device_gps={$dev_lng},{$dev_lat} mobile_gps={$gis_lng},{$gis_lat}  corp={$returnCorp['gis_lng']},{$returnCorp['gis_lat']} dis={$distance} radius={$radius}", 'INFO');
                        $this->car_range_log(1,1,"|还车范围验证成功|2");
                        return false;
                    }else{
                        $log_str .= "|还车范围验证失败|3";
                        $log_str .= "|dev_lng:".$dev_lng;
                        $log_str .= "|dev_lat:".$dev_lat;
                        $log_str .= "|returnCorp[gis_lng]:".$returnCorp['gis_lng'];
                        $log_str .= "|returnCorp[gis_lat]:".$returnCorp['gis_lat'];
                        $log_str .= "|distance:".$distance;
                        $log_str .= "|radius:".$radius;
                    }
                    $log = $log . "--caritemid={$carItemId}还车检查,不能还车,datetime={$lastLocation['update_time']} imei={$lastLocation['imei']} org={$lastLocation['org_lng']},{$lastLocation['org_lat']} device_gps={$dev_lng},{$dev_lat} mobile_gps={$gis_lng},{$gis_lat}  corp={$returnCorp['gis_lng']},{$returnCorp['gis_lat']} dis={$distance} radius={$radius}--";    
                }
                

                /* //如果设备最后定位在5分钟内，返回 ，不继续判断手机定位
                 if (abs(time() - $lastLocation['update_time']) < 600) {
                     return true;
                 }*/
            }
            $this->car_range_log(1,0,$log_str);
            \Think\Log::write($log, 'INFO');
            return true;
        } else {

            $log_str .= "|还车范围验证失败|4";
            $log_str .= "|获取车辆定位信息失败";
            \Think\Log::write("--还车范围检查,不能还车, 无设备定位数据 carItemId=" . $carItemId, 'INFO');
        }

        $distance = 0;
        if ($gis_lng > 0 && $gis_lat > 0) {
			//百度转高德
			$ChangeGps = new \Api\Service\PartnerDevice();
			$stationGps = $ChangeGps->bd_decrypt($gis_lat,$gis_lng);
			$gis_lng = $stationGps['lat'];
			$gis_lat = $stationGps['lon'];
            if($is_polygon == 1){
                if($this->polygonReturnCheck($returnCorp['id'],$gis_lng,$gis_lat)){
                    return false;
                }
            }else{
                //计算距离
                $distance = $this->getDistance($gis_lng, $gis_lat, $returnCorp['gis_lng'], $returnCorp['gis_lat']);
                //与要求的还车半径对比
                $this->return_range_msg .= "|2|".$distance."|".$radius;
                if ($distance < $radius) {
                    \Think\Log::write("--[验证传入经纬度：可以还车] mobile_gps={$gis_lng},{$gis_lat}  corp={$returnCorp['gis_lng']},{$returnCorp['gis_lat']} dis={$distance} radius={$radius}", 'INFO');
                    $this->car_range_log(1,1,"|还车范围验证成功|5");
                    return false;
                }
                $log_str .= "|还车范围验证失败|6";
                $log_str .= "|gis_lng:".$gis_lng;
                $log_str .= "|gis_lat:".$gis_lat;
                $log_str .= "|returnCorp[gis_lng]:".$returnCorp['gis_lng'];
                $log_str .= "|returnCorp[gis_lat]:".$returnCorp['gis_lat'];
                $log_str .= "|distance:".$distance;
            }
            
            // $log_str .= "|radius:".$radius;
        }

        //获取手机的定位信息
        $lon = I('request.gis_lng');
        $lat = I('request.gis_lat');
        if ($lon > 0 && $lat > 0) {
			//百度转高德
			$ChangeGps = new \Api\Service\PartnerDevice();
			$stationGps = $ChangeGps->bd_decrypt($lat,$lon);
			$lon = $stationGps['lat'];
			$lat = $stationGps['lon'];
            if($is_polygon == 1){
                if($this->polygonReturnCheck($returnCorp['id'],$lon,$lat)){
                    return false;
                }
            }else{
                //计算距离
                $this->return_range_msg .= "|3|".$distance."|".$radius;
                $distance = $this->getDistance($lon, $lat, $returnCorp['gis_lng'], $returnCorp['gis_lat']);
                //与要求的还车半径对比
                if ($distance < $radius) {
                    \Think\Log::write("--[验证手机经纬度：可以还车] mobile_gps={$lon},{$lat}  corp={$returnCorp['gis_lng']},{$returnCorp['gis_lat']} dis={$distance} radius={$radius}", 'INFO');
                    $this->car_range_log(1,1,"|还车范围验证成功|7");
                    return false;
                }
                $log_str .= "|还车范围验证失败|8";
                $log_str .= "|lon:".$lon;
                $log_str .= "|lat:".$lat;
                $log_str .= "|returnCorp[gis_lng]:".$returnCorp['gis_lng'];
                $log_str .= "|returnCorp[gis_lat]:".$returnCorp['gis_lat'];
                $log_str .= "|distance:".$distance;
                $log_str .= "|radius:".$radius;
            }
            
        }
        \Think\Log::write("--还车范围检查,不能还车, mobile_gps={$gis_lng},{$gis_lat} mobile_gps2={$lon},{$lat} corp={$returnCorp['gis_lng']},{$returnCorp['gis_lat']} dis={$distance} radius={$radius}--", 'INFO');
        $this->car_range_log(1,0,$log_str);
        return true;
    }


    /**
     * 检测是否在有效取车范围内
     * @param int $rentId
     * @param int $carItemId 车辆id
     * @param double $lng
     * @param double $lat
     * @return bool true超范围，false在有效范围内
     */
    public function checkTakeRange($rentId, $carItemId, $lng, $lat)
    {
        if(C("ISTEST") == 1){
            return false;
        }
        $lastLocation = $this->getCarLocation($carItemId);
        $takeRadius = M("rent_content_return_config")->where(array("rent_content_id" => $rentId))->getField("take_radius");

        $log_str = "";
        if ($lastLocation) {
            //计算距离
            $distance = $this->getDistance($lastLocation['gis_lng'], $lastLocation['gis_lat'], $lng, $lat);
            //与要求的还车半径对比
            \Think\Log::write("查看距离问题distance={$distance}---takeRadius={$takeRadius}", \think\Log::INFO);
            if ($distance < $takeRadius) {
                $this->car_range_log(0,1,"|取车范围验证成功|1|");
                return false;
            }

            $log_str .= "|取车范围验证失败|4|";
            $log_str .= "|lastLocation[gis_lng]:".$lastLocation['gis_lng'];
            $log_str .= "|lastLocation[gis_lat]:".$lastLocation['gis_lat'];
            $log_str .= "|lng:".$lng;
            $log_str .= "|lat:".$lat;
            $log_str .= "|distance:".$distance;
            $log_str .= "|takeRadius:".$takeRadius;

            //获取手机的定位信息
            $lon = I('request.gis_lng');
            $lat = I('request.gis_lat');
            if ($lon > 0 && $lat > 0) {
                //计算距离
                $distance = $this->getDistance($lon, $lat, $lastLocation['gis_lng'], $lastLocation['gis_lat']);
                //与要求的还车半径对比
                if ($distance < $takeRadius) {
                    $this->car_range_log(0,1,"|取车范围验证成功|2|");
                    return false;
                }

                $log_str .= "|取车范围验证失败|5|";
                $log_str .= "|lon:".$lon;
                $log_str .= "|lat:".$lat;
                $log_str .= "|distance:".$distance;
                $log_str .= "|takeRadius:".$takeRadius;
            }
        }else{
            $log_str .= "|取车范围验证失败|6|";
            $log_str .= "|未获取到有效车辆位置";
                
        }

        $this->car_range_log(0,0,$log_str);
        return true;
    }

    /**
     * 检测是否在有效还车范围内,并返回还车经纬度
     * @param mixed $returnCorp 还车网点
     * @param int $carItemId 车辆id
     * @return mixed ['statue','lat','lon']
     */
    public function checkRangeAndGetLocation($returnCorp, $carItemId)
    {
        //实时地址与网店的配置文件进行比较
        $lastLocation = $this->getCarLocation($carItemId);
        $corpReturnConfig = M('corporation_car_return_config')->where(['corporation_id' => $returnCorp['id']])->find();

        $result['status'] = 0;
        $result['lat'] = 0;
        $result['lon'] = 0;
        if ($lastLocation) {
            $result['lat'] = $lastLocation['latitude'];
            $result['lon'] = $lastLocation['longitude'];

            //计算距离
            $distance = $this->getDistance($lastLocation['longitude'], $lastLocation['latitude'], $returnCorp['gis_lng'], $returnCorp['gis_lat']);
            //与要求的还车半径对比
            if ($distance < $corpReturnConfig['return_radius']) {
                $result['status'] = 1;
                return $result;
            }
        }

        //获取手机的定位信息
        $lon = I('request.gis_lng');
        $lat = I('request.gis_lat');
        if ($lon > 0 && $lat > 0) {
            //计算距离
            $distance = $this->getDistance($lon, $lat, $returnCorp['gis_lng'], $returnCorp['gis_lat']);
            //与要求的还车半径对比
            if ($distance < $corpReturnConfig['return_radius']) {
                $result['status'] = 1;
                $result['lat'] = $lat;
                $result['lon'] = $lon;
            }
        }

        return $result;
    }

    /**
     * 获取车辆最新的位置,转换原始坐标为高德坐标
     * @param int $carItemId 车辆id
     * @return array
     */
    function getCarLocation($carItemId)
    {
        $carDeviceM = new CarDevice();
        $location = $carDeviceM->getLastGpsLocation($carItemId);

        if ($location) {
            $loc = GeoUtils::transform($location['lat'], $location['lng']);
            return [
                'imei' => $location['imei'],
                'org_lng' => $location['lng'],
                'org_lat' => $location['lat'],
                'gis_lng' => $loc[1],
                'gis_lat' => $loc[0],
                'update_time' => $location['update_time'],//最后定位时间
                'online_time' => $location['online_time']//最后在线/心跳时间
            ];
        }

        return null;
    }

    /*
        is_order 是否按update_time 排序
        length 选取数量
    */
    function getCarAllLocation($carItemId,$is_order = 0,$length = 0)
    {
        $carDeviceM = new CarDevice();
        $locations = $carDeviceM->getAllLastGpsLocation($carItemId);

        $list = null;
        if ($locations) {
            foreach ($locations as $location) {
                $loc = GeoUtils::transform($location['lat'], $location['lng']);
                $list[] = [
                    'imei' => $location['imei'],
                    'org_lng' => $location['lng'],
                    'org_lat' => $location['lat'],
                    'gis_lng' => $loc[1],
                    'gis_lat' => $loc[0],
                    'update_time' => $location['update_time'],//最后定位时间
                    'online_time' => $location['online_time']//最后在线/心跳时间
                ];
            }

        }

        if(!empty($list) && $is_order == 1){
            $update_time = [];
            foreach ($list as $key => $value) {
                # code...
                $update_time[] = $value["update_time"];
            }
            array_multisort($update_time, SORT_DESC, $list);
            if($length > 0 && count($list) > $length){
                $list = array_slice($list, 0,$length);
            }
        }

        return $list;
    }

    /*
    当前车辆是否支持自由还
    */
    public function isReturnFree(){
        return true;
    }

    public function getAmapList($location,$key_words = "停车场",$offset=10){

        $url = "http://restapi.amap.com/v3/place/around?";

        $param = [];
        $param["key"] = "7461a90fa3e005dda5195fae18ce521b";
        $param["location"] = $location;
        $param["output"] = "json";
        $param["radius"] = "10000";
        $param["types"] = $key_words;
        $param["sortrule"] = "distance";
        $param["offset"] = $offset;

        $url .= http_build_query($param);
        
        $info = file_get_contents($url);
        if(!$info){
            return [];
        }

        $info = json_decode($info,true);
        foreach ($info["pois"] as $key => &$value) {
            # code...
            
            if(is_array($value["address"])){
                $value["address"] = "";
            }
        }
        return $info;
        // echo $info;
    }

    public function getAmapDetail($id){

        $url = "http://restapi.amap.com/v3/place/detail?";

        $param = [];
        $param["key"] = "7461a90fa3e005dda5195fae18ce521b";
        $param["output"] = "json";
        $param["id"] = $id;

        $url .= http_build_query($param);
        
        $info = file_get_contents($url);
        
        return json_decode($info,true);
        
    }

    public function logic_version(){
        echo "2016-11-19 15:43:55";
    }

    public function getReturnMode($rent_id,$order_id = 0){
        if($order_id > 0){
            $return_mode = M("trade_order_car_return_info")->where(["order_id"=>$order_id])->getField("return_mode");
            if($return_mode > 0){
                return $return_mode;
            }
        }

        $return_mode = M("rent_content_return_config")->where(["rent_content_id"=>$rent_id])->getField("return_mode");
        return $return_mode;
    }

    public function getSearch($rent_id = 0,$address_type = 99){

        $search_info = M("rent_content_search")->alias("rcs")
                                               ->where(["rent_content_id"=>$rent_id,"address_type"=>$address_type])
                                               ->find();
        return $search_info;
    }

    /*
    取还车日志
    */
    public function car_range_log($type = 0,$result = 0,$desc = "",$uid = 0,$order_id = 0){

        $data = [];
        $_GET['uid']=I("get.uid") ? I("get.uid") : I("post.uid");
		$_GET['orderId']=I("get.orderId") ? I("get.orderId") : I("post.orderId");
        $data['uid']      = $uid ? $uid : I("get.uid");// int(10) NOT NULL DEFAULT '0',
        $data['order_id'] = $order_id ? $order_id : I("get.orderId");// int(10) NOT NULL,
        $data['type']     = $type;// tinyint(4) NOT NULL DEFAULT '0' COMMENT '操作类型0=取车1=还车',
        $data['result']   = $result;// tinyint(4) NOT NULL DEFAULT '0' COMMENT '0=失败1=成功',
        $data['desc']     = $desc;// varchar(1000) NOT NULL DEFAULT '' COMMENT '取还车描述',
        $result = M("car_range_log")->add($data);

        return $result;
    }

    /*
        是否在自由还区域内
    */
    public function isInFreeArea($car_item_id){

        $fr_str = "自由还区域验证";
        
        $sql = "SELECT
                    ga.groupId,
                    ga.lng,
                    ga.lat,
                    ga.`no`
                FROM
                    car_group_r cgr
                INNER JOIN group_area ga ON ga.groupId = cgr.groupId
                WHERE
                    cgr.carId = {$car_item_id}
                    and cgr.`status` = 1
                order by ga.`no`";

        
        $no_list = M()->query($sql);

        $no_group = [];
        if($no_list){
            foreach ($no_list as $key => $value) {
                # code...
                $no_group[$value["groupId"]][] = [$value["lng"],$value["lat"]];
            }

            if(count($no_group) > 0){

                //如果不在范围，再根据车辆位置验证一次
                
                
                $address_info = $this->getCarLocation($car_item_id);
                
                $gis_lng = $address_info["gis_lng"];
                $gis_lat = $address_info["gis_lat"];    
                
                $fr_str .= "|经纬度1:".$gis_lng."|".$gis_lat;
                $pt = [$gis_lng,$gis_lat];
                


                foreach ($no_group as $key => $value) {
                    # code...

                    if(count($value) < 3){
                        continue;
                    }

                    $result = $this->isInsidePolygon($pt,$value);
                    
                    if($result){
                        $this->car_range_log(1,$result,$fr_str);
                        return $result;    
                    }
                    
                }
            }
        }
        
        $this->car_range_log(1,0,$fr_str);
        return false;
    }

    /*
        @pt[0] lng
        @pt[1] lat

        @poly 
    */
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

   public function version(){
       echo '2017-03-28 16:26:39';
   }

   
   /*
        扩展，新的判断不再是单判断小马，根据sort id 进行不同判断
   */
   public function isXiaomaInArea($rent_id,$order_id = 0,$isfee=0){

        $rent_info = M("rent_content")->where(["id"=>$rent_id])->find();
        //非小马单车不判断，默认在区域内

        if(!in_array($rent_info["sort_id"],[112,113])){
            return true;
        }
        

        $map = ["car_item_id"=>$rent_info["car_item_id"]];
        $map["device_type"] = ["in",[12,14,16,18]];
        $car_item_device = M("car_item_device")->where($map)->find();
        $imei = $car_item_device["imei"];
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
        if($list){
            $poly = [];
            foreach ($list as $key => $value) {
                # code...
                $poly[] = [$value["lng"],$value["lat"]];
            }

            if($rent_info["sort_id"] == 113){
                $position = $this->getXiaomaPosition($imei);    
                $pt = [$position["lng"],$position["lat"]];
            }elseif($rent_info["sort_id"] == 112){
				//优先取redis 数据
				$gps_status_info=$this->getXiaomiPosition($imei);
				if(!$gps_status_info || $gps_status_info['latitude']<=0 || $gps_status_info['longitude']<=0){
					$gps_status_info = M("baojia_box.gps_status",null,"DB_CONFIG_BOX")->where(["imei"=>$imei])->find();
				}else{
					$gps_status_info['imei']=str_pad($gps_status_info['carId'], 16, '0', STR_PAD_LEFT);
					$gps_status_info['datetime']=$gps_status_info['gpsTime'];
					$gps_status_info['lastonline']=date('Y-m-d H:i:s',$gps_status_info['gpsTime']);
				}	
                 
                $pt = [$gps_status_info["longitude"],$gps_status_info['latitude']];
            }
            

            if(count($poly) > 3 && $pt[0] > 0 && $pt[1] > 0){
                 //var_dump($pt);exit;
                // var_dump($poly);
                $result = $this->isInsidePolygon($pt,$poly);
                if(!$result){
                    $new_pt_move = [];
					if($isfee==1){
						$new_pt_move[] = [0,0];
						$new_pt_move[] = [0,0];
						$new_pt_move[] = [0,0];
						$new_pt_move[] = [0,0];
                    }else{
						$new_pt_move[] = [0.0009 ,0];
						$new_pt_move[] = [-0.0009,0];
						$new_pt_move[] = [0, 0.0009];
						$new_pt_move[] = [0,-0.0009];
					}
					foreach ($new_pt_move as $key => $value) {
                        # code...
                        $new_pt = [$pt[0] + $value[0],$pt[1] + $value[1]];
                        if($this->isInsidePolygon($new_pt,$poly)){
                            $result = true;
                            break;
                        }

                    }
                }
                if($order_id > 0){
                    //记录log
                    $rdata['rent_content_id']=$rent_id;
                    $rdata['curpoint']=json_encode($pt);
                    $rdata['Polygon']=json_encode($poly);
                    $rdata['time']=time();
                    $rdata['user_id']=isset($_POST['uid']) ? $_POST['uid'] : 0;
                    $rdata['resault']=0;
                    $rdata['order_id'] = $order_id;
                    $rdata['return_polygon'] = json_encode($this->getReturnPolygon($rent_info["car_item_id"]));
                    if($result){
                        $rdata['resault']=1;
                    }
                    
                    $r=M('baojia_mebike.trade_order_return_log')->add($rdata);
                }
				
				
                return $result;
            }


        }


        //此处判断是否在区域内

        return true;
   }

   //还车区域
   private function getReturnPolygon($car_item_id){
        $sql = "SELECT
                    ga.groupId,
                    ga.lng,
                    ga.lat,
                    ga.`no`
                FROM
                    car_group_r cgr
                INNER JOIN group_area ga ON ga.groupId = cgr.groupId
                WHERE
                    cgr.carId = {$car_item_id}
                    and cgr.`status` = 1
                order by ga.`no`";

        
        $no_list = M()->query($sql);

        $no_group = [];
        if($no_list){
            foreach ($no_list as $key => $value) {
                # code...
                $no_group[$value["groupId"]][] = [$value["lng"],$value["lat"]];
            }
        }
        return $no_group;
   }

   public function getXiaomaPosition($imei){
        $Redis = new \Redis();
        $Redis->connect("10.1.11.82", 6379);
        
        $key = "prod:boxxmadata".$imei;
        $result = $Redis->get($key);

        if(!$result){
            return false;
        }

        $result = json_decode($result,true);
        return $result;
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

   //
   public function polygonReturnCheck($corporation_id,$gis_lng,$gis_lat){

        $inner = new \Api\Logic\Inner();
        $inner_param = [];
        $inner_param["corporation_id"] = $corporation_id;
        $inner_param["lng"]     = $gis_lng;
        $inner_param["lat"]     = $gis_lat;
        $is_in = $inner->inCtl("car")->inAct("isInCorporationPolygon",$inner_param);
        if($is_in["data"]["is_in"] == 1){
            $this->car_range_log(1,1,"|还车范围验证成功|10");
            return true;
        }else{
            $this->car_range_log(1,1,"|还车范围验证失败|11");
            return false;
        }

   }








}
