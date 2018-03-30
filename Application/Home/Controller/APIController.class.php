<?php
/**
 * Created by PhpStorm.
 * User: CHI
 * Date: 2017/7/12
 * Time: 10:21
 */

namespace Home\Controller;
use Think\Controller\RestController;
use Home\Logic\FreeCar;

class APIController extends RestController {

    public function index()
    {
        $this->display('index');
    }

    public function loadMap()
    {
        $this->display('loadMap');
    }

    public function batterycode()
    {
        $this->display('batterycode');
    }

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
        $strSql = "select rent.id,rent.car_item_id,a.gis_lng,a.gis_lat,a.plate_no,rent.corporation_id,rcrc.return_mode,cor.name corporation_name,
                ROUND(st_distance(point(a.gis_lng, a.gis_lat),point($lngX, $latY))*111195,0) AS distance
                from rent_content_search a
                join rent_content rent on rent.id=a.rent_content_id
                LEFT JOIN rent_content_avaiable rca on rca.rent_content_id=rent.id
                left join rent_content_return_config rcrc on rcrc.rent_content_id=rent.id
                LEFT JOIN corporation cor on cor.id=rent.corporation_id
                where rent.status=2 AND rent.sell_status=1 and rca.hour_count<1
                and a.address_type=99 and a.plate_no like 'DD%' and rent.sort_id=112 and rent.car_info_id<>30150
                and rent.city_id={$city_id}
                HAVING distance BETWEEN 0 and {$radius}
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
            $result['groupAndCar'][$v['id']]['gisLng'] = (float)$v['gis_lng'];
            $result['groupAndCar'][$v['id']]['gisLat'] = (float)$v['gis_lat'];
            $result['groupAndCar'][$v['id']]['plateNo'] = $v['plate_no'];
            $result['groupAndCar'][$v['id']]['carReturnCode'] = (float)$v['return_mode'];
            $result['groupAndCar'][$v['id']]['corporationName'] = $v['corporation_name'];
            $result['groupAndCar'][$v['id']]['isPrice0'] = 0;
            $freeCar = new \Home\Logic\FreeCar();
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

        if (!empty($result)) {
            $this->response(["status" => 1, "msg" => "success", "showLevel" => 0,"price0Count"=>$price0Count,"count"=>count($result['groupAndCar']),"second"=>$second,"search_second"=>$search_second,"handle_second"=>$handle_second,"data" => $result], 'json');
        } else {
            $this->response(["status" => -1, "msg" => "附近暂无可用车辆", "showLevel" => 0, "data" => null], 'json');
        }
    }

    public function loadDetails($id=5862626,$lngX = 116.396906,$latY = 39.985818,$client_id = 218,$version ='2.2.0',$app_id = 218,$qudao_id = 'guanfang',$timestamp =0,$device_model = "",$device_os = "",$test=0)
    {
        $time_start = $this->microtime_float();
        $id= $_REQUEST['id'];
        $lngX = $_REQUEST['lngX'];
        $latY = $_REQUEST['latY'];
        if (empty($id)||empty($lngX)||empty($latY)) {
            $this->response(["status" => 1004, "msg" => "参数错误,请参考API文档", "showLevel" => 0, "data" => null], 'json');
        }
        $car_result = M('rent_content_search')->alias("a")
            ->field("rent.id,rent.car_item_id,rent.car_info_id,a.gis_lng,a.gis_lat,a.address,a.plate_no,rent.corporation_id,rcrc.return_mode,rsh.mix_mile_price,rsh.mix_minute_price,rsh.starting_price,rce.battery_capacity,rce.running_distance,ROUND(st_distance(point(a.gis_lng, a.gis_lat),point($lngX, $latY))*111195,0) AS distance")
            ->join("rent_content rent on rent.id=a.rent_content_id","")
            ->join("rent_sku_hour rsh on rent.id= rsh.rent_content_id","left")
            ->join("rent_content_ext rce on rce.rent_content_id=rent.id","left")
            ->join("rent_content_return_config rcrc on rcrc.rent_content_id=rent.id","left")
            ->join("rent_content_avaiable rca on rca.rent_content_id=rent.id","left")
            ->where("a.address_type=99 and rent.id={$id}")
            ->find();
        if($test==1){
            echo M('rent_content_search')->getLastSql();
        }
        $result = [];
        if($car_result){
            $result['carTipText'] = "等待取车期间，将为您预留10分钟免费取车时间，超时将开始计费";
            $result['meterDistance'] = (float)sprintf("%.3f", $car_result['distance']);
            $result['id'] = (float)$car_result['id'];
            $result['carItemId'] = (float)$car_result['car_item_id'];
            $result['pictureUrls'] = ["http://pic.baojia.com/b/2017/0331/2073761_201703318527.png", "http://pic.baojia.com/m/2017/0331/2073761_201703318527.png", "http://pic.baojia.com/s//2017/0331/2073761_201703318527.png"];
            $picture=M("car_info_picture")->alias("cip")
                ->join("car_item ci on ci.color=cip.car_color_id","left")
                ->where("cip.type=0 AND cip.status=2 AND cip.car_info_id={$car_result['car_info_id']} AND ci.id={$car_result['car_item_id']}")
                ->field("cip.url")
                ->find();
            if($picture) {
                $result['pictureUrls'] = ["http://pic.baojia.com/b/" . $picture['url'], "http://pic.baojia.com/m/" . $picture['url'], "http://pic.baojia.com/s/" . $picture['url']];
            }
            $result['distance'] = (float)sprintf("%.3f", $car_result['distance'] * 0.001);
            $result['distanceText'] = (float)(sprintf("%.3f", $car_result['distance'])) . "米";
            $result['gisLng'] = (float)$car_result['gis_lng'];
            $result['gisLat'] = (float)$car_result['gis_lat'];
            $result['address'] = $car_result['address'];
            $result['plateNo'] = $car_result['plate_no'];
            $corporation = M("corporation")
                ->field("id,gis_Lat gisLat,gis_Lng gisLng,name,address,car_type carType,logo")
                ->where("id ={$car_result['corporation_id']}")
                ->find();
            $result['corporationName'] = $corporation['name'];
            $result['runningDistance'] = (float)$car_result['running_distance'];
            $result['runningDistanceText'] = "续航" . $car_result['running_distance'] . "千米";
            $result['mixText'] = '{' . $car_result['mix_minute_price'] . '}{/' . 分钟 . '+}{' . $car_result['mix_mile_price'] . '}{/公里}';
            $result['mixText1'] = $car_result['mix_minute_price'] . '元/' . 分钟 . '+' . $car_result['mix_mile_price'] . '元/公里';
            $result['insurance'] = 0.5;//保险
            $result['startingPrice'] = (float)($car_result['starting_price'] + 0.5);
            $result['areaCouponText'] = "指定区域内还车5折";
            //字体颜色
            $result['return_car_font'] = [];
            if ($car_result['return_mode'] == 4) {
                $result['craw'] = "指定网点还车可享受半价优惠，超出运营区域将收取100元调度费";
                $result['return_car_font'][0]['value'] = "100元";
                $result['return_car_font'][0]['color'] = '#fc0006';
            } else if ($car_result['return_mode'] == 1) {
                $result['craw'] = "需在" . $corporation['name'] . "内还车\r\n其它地点强制还车需支付100元调度费";
                $result['return_car_font'][0]['value'] = $corporation['name'];
                $result['return_car_font'][0]['color'] = '#54a9f3';
                $result['return_car_font'][1]['value'] = "100元";
                $result['return_car_font'][1]['color'] = '#fc0006';
            } else {
                $result['craw'] = "不在指定网点还车将收取100元调度费，超出运营区域无法还车";
                $result['return_car_font'][0]['value'] = "100元";
                $result['return_car_font'][0]['color'] = '#fc0006';
            }
            $result['returnCraw'] = null;
            $result['returnCrawUrl'] = "http://m.baojia.com/rentorder/getcrawmap?show_mark=1&rentid=" . $car_result['id'];
            $result['returnCrawTitle'] = "服务区域";
            $result['vehicleType'] = null;
            $result['carReturnCode'] = (float)$car_result['return_mode'];
            $result['carLogo'] = "http://images.baojia.com/cooperation/2017/0303/1_14885517866228_m.jpg";
        }
        $time_end = $this->microtime_float();
        $second = round($time_end - $time_start,2);
        if (!empty($result)) {
            $this->response(["status" => 1, "msg" => "success", "showLevel" => 0,"second"=>$second,"data" => $result], 'json');
        } else {
            $this->response(["status" => -1, "msg" => "附近暂无可用车辆", "showLevel" => 0, "data" => null], 'json');
        }
    }

    public function xmHourBicycleOnline($lngX = 116.396906,$latY = 39.985818,$page = 1,$pageNum = 20,$hourSupport = 1,$showLevel= 0,$radius = 10,$adjustLevel = 1,$level = 16,$province = "",$city = "",$zone = "",$client_id = 218,$version = '2.2.0',$app_id = 218,$qudao_id = 'guanfang',$timestamp = 1499669461000,$device_model = "",$device_os = "",$test=0)
    {
        $time_start = $this->microtime_float();
        $lngX = $_REQUEST['lngX'];
        $latY = $_REQUEST['latY'];
        $city = $_REQUEST['city'];
        $radius = 10000;
        $days=15;
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
        $strSql = "select a.gis_lng,a.gis_lat,a.rent_content_id,a.car_item_id,a.shop_brand,a.city_id,a.car_info_name,a.zone_id,a.address,a.year_style,
                a.box_state,a.boxplus_state,a.is_urgent,a.smallest_days,a.plate_no,a.city_name,a.gearbox,a.price,a.review_count,a.model_id is_new_energy,
                a.review_star,a.owner_agree_rate,a.owner_agree_rate,a.order_success_count,a.owner_refuse_rate,a.owner_refuse_count,a.corporation_id,
                ROUND(st_distance(point(a.gis_lng, a.gis_lat),point($lngX, $latY))*111195,0) AS distance,rcrc.return_mode,
                rent.send_car_enable,FROM_UNIXTIME(rent.create_time) create_time,rsh.type AS hour_price_type,rsh.mix_time_price,
                rsh.mix_mile_price,rsh.time_price,rsh.mile_price,rsh.minute_price,rsh.mix_minute_price,rent.is_by_hour AS is_by_hour,rsh.all_day_price,
                rsh.night_price,rsh.starting_price,rsh.day_hour_price,rsh.is_handsel,rsh.is_deposit,rsh.is_insurance,
                cip.url AS hour_car_picture_url,rce.battery_capacity,rce.running_distance
                from rent_content_search a
                left join rent_sku_hour rsh on a.rent_content_id = rsh.rent_content_id
                left join rent_content_ext rce on rce.rent_content_id=a.rent_content_id
                join rent_content rent on rent.id=a.rent_content_id
                left join rent_content_return_config rcrc on rcrc.rent_content_id=a.rent_content_id
                LEFT JOIN car_info cn on cn.id=rent.car_info_id
                LEFT JOIN car_item ci on ci.id = rent.car_item_id
                LEFT JOIN car_model cm ON cm.id = cn.model_id
                LEFT JOIN car_item_color cic on cic.id = ci.color
                left join car_info_picture cip on rent.car_info_id=cip.car_info_id and cip.car_color_id=ci.color
                LEFT JOIN rent_content_avaiable rca on rca.rent_content_id=rent.id 
                where rent.status=2 AND rent.sell_status=1 and cip.type=0 and cip.status=2 and rca.hour_count<1
                and a.address_type=99 and a.plate_no like 'DD%' and rent.sort_id=112 and rent.car_info_id<>30150
                and rent.city_id={$city_id}
                HAVING distance BETWEEN 0 and {$radius}
                order by distance asc limit 200";
        $ts1 = $this->microtime_float();
        $car_result = M('')->query($strSql);
        if($test==1){
            echo M('')->getLastSql();
        }
        $te1 = $this->microtime_float();
        $shortestId = $car_result[0]['rent_content_id'];
        $result = [];
        $result['carTipText'] = "等待取车期间，将为您预留10分钟免费取车时间，超时将开始计费";
        $result['shortestId'] = (float)$shortestId;
        $price0Count=0;
        $item_count=0;
        $distance=1000;
        foreach ($car_result as $k => $v) {
            $result['groupAndCar'][$v['rent_content_id']]['type'] = 1;
            $result['groupAndCar'][$v['rent_content_id']]['meterDistance'] = (float)sprintf("%.3f", $v['distance']);//
            $result['groupAndCar'][$v['rent_content_id']]['id'] = (float)$v['rent_content_id'];
            $result['groupAndCar'][$v['rent_content_id']]['carItemId'] = (float)$v['car_item_id'];
            $result['groupAndCar'][$v['rent_content_id']]['shopBrand'] = $v['shop_brand'];
            $result['groupAndCar'][$v['rent_content_id']]['cityId'] = (float)$v['city_id'];
            $result['groupAndCar'][$v['rent_content_id']]['pictureUrls'] = ["http://pic.baojia.com/b/" . $v['hour_car_picture_url'], "http://pic.baojia.com/m/" . $v['hour_car_picture_url'], "http://pic.baojia.com/s/" . $v['hour_car_picture_url']];
            $result['groupAndCar'][$v['rent_content_id']]['distance'] = (float)sprintf("%.3f", $v['distance'] * 0.001);
            $result['groupAndCar'][$v['rent_content_id']]['distanceText'] = (float)(sprintf("%.3f", $v['distance'])) . "米";
            $result['groupAndCar'][$v['rent_content_id']]['gisLng'] = (float)$v['gis_lng'];
            $result['groupAndCar'][$v['rent_content_id']]['gisLat'] = (float)$v['gis_lat'];
            $result['groupAndCar'][$v['rent_content_id']]['carInfoName'] = $v['car_info_name'];
            $result['groupAndCar'][$v['rent_content_id']]['zoneId'] = (float)$v['zone_id'];
            $result['groupAndCar'][$v['rent_content_id']]['address'] = $v['address'];
            $result['groupAndCar'][$v['rent_content_id']]['yearStyle'] = $v['year_style'];
            $result['groupAndCar'][$v['rent_content_id']]['boxInstall'] = (float)$v['box_state'];
            $result['groupAndCar'][$v['rent_content_id']]['boxPlusInstall'] = (float)$v['boxplus_state'];
            $result['groupAndCar'][$v['rent_content_id']]['isUrgent'] = (float)$v['is_urgent'];
            $result['groupAndCar'][$v['rent_content_id']]['isRecommend'] = 0;//
            $result['groupAndCar'][$v['rent_content_id']]['smallestDays'] = (float)$v['smallest_days'];
            $result['groupAndCar'][$v['rent_content_id']]['limitDay'] = "";
            $result['groupAndCar'][$v['rent_content_id']]['limitDayText'] = "不限行";
            $result['groupAndCar'][$v['rent_content_id']]['plateNo'] = $v['plate_no'];
            $result['groupAndCar'][$v['rent_content_id']]['city'] = $v['city_name'];
            $result['groupAndCar'][$v['rent_content_id']]['gearbox'] = (float)$v['gearbox'];
            $result['groupAndCar'][$v['rent_content_id']]['transmission'] = "自动挡";//
            $result['groupAndCar'][$v['rent_content_id']]['supportHour'] = (float)$v['is_by_hour'];
            $result['groupAndCar'][$v['rent_content_id']]['price'] = (float)$v['price'];
            $result['groupAndCar'][$v['rent_content_id']]['priceText'] = $v['price'] . "元/天";
            $result['groupAndCar'][$v['rent_content_id']]['hourPrice'] = (float)$v['day_hour_price'];
            $result['groupAndCar'][$v['rent_content_id']]['reviewCount'] = (float)$v['review_count'];//评论数量
            $result['groupAndCar'][$v['rent_content_id']]['star'] = (float)$v['review_star'];   //评分
            $result['groupAndCar'][$v['rent_content_id']]['ownerAgreeRate'] = (float)$v['owner_agree_rate'];
            $result['groupAndCar'][$v['rent_content_id']]['ownerAgreeRateText'] = ($v['owner_agree_rate'] * 100) . "%接单";
            $result['groupAndCar'][$v['rent_content_id']]['orderSuccessCount'] = (float)$v['order_success_count'];  //成单数
            $result['groupAndCar'][$v['rent_content_id']]['ownerRefuseRate'] = $v['owner_refuse_rate'];
            $result['groupAndCar'][$v['rent_content_id']]['ownerRefuseCount'] = (float)$v['owner_refuse_count'];
            $result['groupAndCar'][$v['rent_content_id']]['activity'] = null;//
            $corporation = M("corporation")->field("id,gis_Lat gisLat,gis_Lng gisLng,name,address,car_type carType,logo")->where("id = " . $v['corporation_id'])->select();
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['id'] = (float)$corporation[0]['id'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['gisLat'] = (float)$corporation[0]['gisLat'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['gisLng'] = (float)$corporation[0]['gisLng'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['name'] = $corporation[0]['name'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['carType'] = $corporation[0]['carType'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['logo'] = $corporation[0]['logo'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['distanceText'] = " ";//
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['vehicleType'] = 1; //
            $result['groupAndCar'][$v['rent_content_id']]['newEnergyStatus'] = (float)$v['is_new_energy'];
            $result['groupAndCar'][$v['rent_content_id']]['supportSendHome'] = 0; //
            $result['groupAndCar'][$v['rent_content_id']]['supportSendHomeText'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['tags'] = ["不限行", ($v['owner_agree_rate'] * 100) . '%接单', "好评" . $v['review_count']];
            $result['groupAndCar'][$v['rent_content_id']]['longRentOnly'] = 0;//
            $result['groupAndCar'][$v['rent_content_id']]['longRentOnlyText'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['corpId'] = (float)$corporation[0]['parent_id'];
            $result['groupAndCar'][$v['rent_content_id']]['hourPriceType'] = (float)$v['hour_price_type'];
            $result['groupAndCar'][$v['rent_content_id']]['runningDistance'] = (float)$v['running_distance'];
            $result['groupAndCar'][$v['rent_content_id']]['runningDistanceText'] = "续航" . $v['running_distance'] . "千米";
            $result['groupAndCar'][$v['rent_content_id']]['mixText'] = '{' . $v['mix_minute_price'] . '}{/' . 分钟 . '+}{' . $v['mix_mile_price'] . '}{/公里}';
            $result['groupAndCar'][$v['rent_content_id']]['mixText1'] = $v['mix_minute_price'] . '元/' . 分钟 . '+' . $v['mix_mile_price'] . '元/公里';
            $result['groupAndCar'][$v['rent_content_id']]['allDayPrice'] = (float)$v['all_day_price'];
            $result['groupAndCar'][$v['rent_content_id']]['allDayPriceText'] = (float)$v['all_day_price'] . "/天";
            $result['groupAndCar'][$v['rent_content_id']]['nightPrice'] = (float)$v['night_price'];
            $result['groupAndCar'][$v['rent_content_id']]['nightPriceText'] = (float)$v['night_price'] . "/晚";
            $result['groupAndCar'][$v['rent_content_id']]['carAge'] = 0;//
            $result['groupAndCar'][$v['rent_content_id']]['monthPrice'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['monthPriceText'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['isLimited'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['bluetooth'] = 0;//
            $result['groupAndCar'][$v['rent_content_id']]['floatingRatio'] = (float)$v['floating_ratio'];
            $result['groupAndCar'][$v['rent_content_id']]['useNotify'] = "http://m.baojia.com/uc/rentSku/chargePriceThree?carItemId=" . $v['car_item_id'];
            $result['groupAndCar'][$v['rent_content_id']]['reduction'] = 1;//
            $result['groupAndCar'][$v['rent_content_id']]['insurance'] = 0.5;//保险
            $result['groupAndCar'][$v['rent_content_id']]['startingPrice'] = (float)($v['starting_price'] + 0.5);
            $result['groupAndCar'][$v['rent_content_id']]['areaCouponText'] = "指定区域内还车5折";
            //字体颜色
            $result['groupAndCar'][$v['rent_content_id']]['return_car_font'] = [];
            if ($v['return_mode'] == 4) {
                $result['groupAndCar'][$v['rent_content_id']]['craw'] = "指定网点还车可享受半价优惠，超出运营区域将收取100元调度费";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['value'] = "100元";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['color'] = '#fc0006';
            } else if($v['return_mode'] == 1){
                $result['groupAndCar'][$v['rent_content_id']]['craw'] = "需在".$corporation[0]['name']."内还车\r\n其它地点强制还车需支付100元调度费";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['value'] = $corporation[0]['name'];
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['color'] = '#54a9f3';
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][1]['value'] = "100元";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][1]['color'] = '#fc0006';
            }else {
                $result['groupAndCar'][$v['rent_content_id']]['craw'] = "不在指定网点还车将收取100元调度费，超出运营区域无法还车";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['value'] = "100元";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['color'] = '#fc0006';
            }
            $result['groupAndCar'][$v['rent_content_id']]['returnCraw'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['returnCrawUrl'] = "http://m.baojia.com/rentorder/getcrawmap?show_mark=1&rentid=" . $v['rent_content_id'];
            $result['groupAndCar'][$v['rent_content_id']]['returnCrawTitle'] = "服务区域";
            $result['groupAndCar'][$v['rent_content_id']]['vehicleType'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['floatingRatioString'] = " ";
            $result['groupAndCar'][$v['rent_content_id']]['isDeposit'] = (float)$v['is_deposit'];
            $result['groupAndCar'][$v['rent_content_id']]['carReturnCode'] = (float)$v['return_mode'];
            $result['groupAndCar'][$v['rent_content_id']]['isElseplaceReturnCar'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['carLogo'] = "http://images.baojia.com/cooperation/2017/0303/1_14885517866228_m.jpg";
            $result['groupAndCar'][$v['rent_content_id']]['workTime'] = "24小时营业";

            $freeCar = new \Home\Logic\FreeCar();
            if($freeCar->checkfreecar($v['car_item_id'])){
                $price0Count++;
                $result['groupAndCar'][$v['rent_content_id']]['isPrice0'] = 1;
            }

            $item_count++;
            if ($v['distance'] <= $distance) {//如果小于等于1公里 继续
                continue;
            } else {
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
        $second = round($time_end - $time_start,2);
        $t1=round($te1-$ts1,2);
        $t2=round($time_end-$te1,2);
        if (!empty($result)) {
            $this->response(["status" => 1, "msg" => "success", "showLevel" => 0,"price0Count"=>$price0Count,"count"=>count($result['groupAndCar']),"second"=>$second,"time1"=>$t1,"time2"=>$t2,"data" => $result], 'json');
        } else {
            $this->response(["status" => -1, "msg" => "附近暂无可用车辆", "showLevel" => 0, "data" => null], 'json');
        }
    }

    /*
	 *检测是否是红包车
	 *cid car_item_id
     20171016新规则:
     * 1.非有单无程的车辆
     * 2.创建时间超过15天
     * 3.72小时内无单且电量低于90%
     * 4.最后一次上架操作后有过订单的车辆
	*/
    public function checkfreecar($cid,$test=0){
        $is_free=0;
        //1.非有单无程的车辆 排除疑似故障车(有单无程车辆)
        $fault_car=M('rent_content')->alias('a')
            ->join('mileage_statistics ms ON ms.rent_content_id = a.id','LEFT')
            ->where('ms.no_mileage_num<>3 and a.car_item_id='.$cid)
            ->find();
        if($fault_car) {
            //2.创建时间超过15天
            $battery = M('rent_content')->alias('a')
                ->join('rent_content_ext rce on rce.rent_content_id=a.id', 'left')
                ->where('a.create_time<= (UNIX_TIMESTAMP(NOW()) - 86400 * 15) and a.car_item_id=' . $cid)
                ->field('rce.battery_capacity')
                ->find();
            if($battery&&$battery['battery_capacity'] < 90) {
                //3.72小时内无单且电量低于90%
                $strSql72 = "and car_item_id={$cid} and create_time>(UNIX_TIMESTAMP(NOW()) - 86400 * 3 )";
                $num72 = M('trade_order')
                    ->where("rent_type = 3 {$strSql72}")
                    ->group('rent_content_id')
                    ->count();
                if (!$num72 || $num72 == 0) {
                    //4.最后一次上架操作后有过订单的车辆
                    $log =M('rent_content')->alias('a')
                        ->join('operation_logging o ON o.rent_content_id = a.id','LEFT')
                        ->where(["o.operate" => 8,"a.car_item_id"=>$cid])
                        ->field("o.time")
                        ->order("o.time desc")
                        ->limit(1)
                        ->select();
                    if($log&&$log[0]["time"]) {
                        $order_count=M("trade_order")
                            ->where("rent_type=3 AND create_time>{$log[0]["time"]} AND car_item_id={$cid}")
                            ->count();
                        if($order_count>0){
                            $is_free = 1;
                        }
                    }
                }
            }
        }
        if($test==1){
            echo($is_free);
        }
        return $is_free;
    }

    public function generateCode($city_code=010,$begin_code=0,$count=10,$formal=0){
        $baojia_mebike="mysqli://apitest-baojia:TKQqB5Gwachds8dv@10.1.11.110:3306/baojia_mebike";
        if(strlen($city_code)==3){
            $city_code.="0";
        }
        //010000为临时号段
        for ($j = 0, $i = $begin_code; $i <($begin_code + $count); $i++, $j++) {
            $serialNumber = $i;
            if (strlen($serialNumber) < 6) {
                if (strlen($serialNumber) == 5) {
                    $serialNumber = "0" . $serialNumber;
                }
                if (strlen($serialNumber) == 4) {
                    $serialNumber = "00" . $serialNumber;
                }
                if (strlen($serialNumber) == 3) {
                    $serialNumber = "000" . $serialNumber;
                }
                if (strlen($serialNumber) == 2) {
                    $serialNumber = "0000" . $serialNumber;
                }
                if (strlen($serialNumber) == 1) {
                    $serialNumber = "00000" . $serialNumber;
                }
            }
            $batteryCode ="BD".$city_code . $serialNumber . $this->getLuhm($city_code . $serialNumber);
            $batteryCodes[$j] = $batteryCode;
            $db_code= M("cmf_battery_qrcode", null,$baojia_mebike)
                ->where("battery_number='{$batteryCode}'")
                ->find();
            if(empty($db_code)){
                //添加记录
                $qrcode = [
                    "battery_number" => $batteryCode,
                    "temporary"=>$formal?0:1//是不是临时二维码  0不是  1是
                ];
                $result=M("cmf_battery_qrcode",null,$baojia_mebike)->add($qrcode);
            }
            $exportArray[$j]["type"]="";
            $exportArray[$j]["code"]="http://xiaomi.baojia.com/index.php/Api/Operation/battery?no=".$batteryCode;
            $exportArray[$j]["shor_address"]="";
            $exportArray[$j]["file_name"]="";
        }
        $title=array("type=url","网络地址","是否使用短地址(是,否),默认否","文件名称,若为空,则使用默认");
        $type=$formal?"正式码":"临时码";
        $file_name="电池二维码".$type.$begin_code."-".($begin_code + $count);
        $this->exportexcels($exportArray,$title,$file_name);
    }

    public function exportexcels($data=array(),$title=array(),$filename='report'){
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
        header('Cache-Control: max-age=0');
        //导出xls 开始
        if (!empty($title)){
            foreach ($title as $k => $v) {
                $title[$k]=iconv("UTF-8", "GB2312",$v);
            }
            $title= implode("\t", $title);
            echo "$title\n";
        }
        if (!empty($data)){
            foreach($data as $key=>$val){
                foreach ($val as $ck => $cv) {
                    $data[$key][$ck]=iconv("UTF-8", "GB2312", $cv);
                }
                $data[$key]=implode("\t", $data[$key]);

            }
            echo implode("\n",$data);
        }
    }

    public function checkLuhmCode($code) {
        return $this->luhm($code);
    }

    public function checkBatteryCode($code) {
        $result=$this->luhm($code);
        $this->response(["status" => 1, "msg" => "success","result" => $result], 'json');
    }

    public function getLuhmCode($code) {
        $result=$this->getLuhm($code);
        $this->response(["status" => 1, "msg" => "success","result" => $result], 'json');
    }

    private function luhm($s) {
        $n = 0;
        for ($i = strlen($s); $i >= 1; $i--) {
            $index=$i-1;
            //偶数位
            if ($i % 2==0) {
                $t = $s{$index} * 2;
                if ($t > 9) {
                    $t = (int)($t/10)+ $t%10;
                }
                $n += $t;
            } else {//奇数位
                $n += $s{$index};
            }
        }
        return ($n % 10) == 0;
    }

    private function getLuhm($s) {
        $n = 0;
        for ($i = strlen($s); $i >= 1; $i--) {
            $index=$i-1;
            //偶数位
            if ($i % 2==0) {
                $t = $s{$index} * 2;
                if ($t > 9) {
                    $t = (int)($t/10)+ $t%10;
                }
                $n += $t;
            } else {//奇数位
                $n += $s{$index};
            }
        }
        $n=$n % 10;
        if($n!=0){
            $n=10-$n;
        }
        return $n;
    }

    public function loadXMList($lngX=0,$latY=0,$page=1,$pageNum=10,$hourSupport=1,$showLevel=0,$radius=10,$adjustLevel=1,$level=16,$province='北京市',$city="",$zone="",$client_id=218,$version="2.2.0",$app_id=218,$qudao_id="guanfang",$timestamp=0,$device_model="",$device_os=""){
        $city_id=1;
        header('Content-Type:text/html; charset=utf-8');
        if($lngX==0||$latY==0){
            $this->ajaxReturn("坐标参数为0");
        }
        if(empty($city)) {
            $city=$this->getAmapCity($lngX,$latY);
        }else{
            if(empty($city)){
                $city=$province;
            }
            if(strpos($city,'市')){
                $city=explode('市',$city)[0];
            }
        }
        $cityArray = M('')->query("select id,name from area_city where status=1 and name='".$city."'");
        $city_id=$cityArray[0]["id"];

        $strSql = "select ROUND(st_distance(point(a.gis_lng, a.gis_lat),point({$lngX},{$latY}))*111195,0) AS distance,
            a.*,rent.send_car_enable,if(ne.car_info_id is null, 0, 1) as is_new_energy,rsh.type as hour_price_type,
            rsh.mix_time_price,rsh.mix_mile_price,rsh.time_price,rsh.mile_price,rsh.minute_price,rsh.mix_minute_price,
            rent.is_by_hour as is_by_hour,rsh.all_day_price,rsh.night_price,rsh.is_handsel,rsh.is_deposit,rsh.is_insurance,cip.url as hour_car_picture_url,
            rent.status rent_status,rent.sell_status,rca.hour_count,rce.running_distance,rce.battery_capacity
            from rent_content_search a
            join car_item on car_item.id=a.car_item_id
            left join rent_sku_hour rsh on a.rent_content_id = rsh.rent_content_id
            left join rent_content_ext rce on rce.rent_content_id=a.rent_content_id
            join rent_content rent on rent.car_item_id=a.car_item_id
            left join (select car_info_id from car_info_value where property_id = 52 and value_id = 4185 and status = 1) ne on a.model_id = ne.car_info_id
            left join (select * from car_info_picture where type=0 and status = 2) cip on rent.id=cip.car_info_id
            LEFT JOIN rent_content_avaiable rca on rca.rent_content_id =a.rent_content_id 
            where a.status=1
            and a.city_id={$city_id} and a.address_type=99 and a.plate_no<>'' and a.sort_id=112 and rent.car_info_id<>30150
            and rca.day_count<1 and rca.hour_count<1 and rca.long_count<1
            and rce.running_distance>5
            and (st_distance(point(a.gis_lng, a.gis_lat),point({$lngX},{$latY}))/ 0.0111)  BETWEEN 0 and {$radius}
            order by distance asc
            LIMIT {$pageNum}";
        $rent_car_count = M('')->query($strSql);
        $this->ajaxReturn($rent_car_count);
    }

    public function getAmapCity($lng,$lat,$default=''){
        $res = file_get_contents('http://restapi.amap.com/v3/geocode/regeo?output=JSON&location='.$lng.','.$lat.'&key=7461a90fa3e005dda5195fae18ce521b&s=rsv3&radius=1000&extensions=base');
        $res = json_decode($res);
        if($res->info == 'OK'){
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

    public function request_post($url = '', $post_data = array()) {
        if (empty($url) || empty($post_data)) {
            return false;
        }
        $o = "";
        foreach ( $post_data as $k => $v )
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }
        $post_data = substr($o,0,-1);
        $postUrl = $url;
        $curlPost = $post_data;
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);
        return $data;
    }

    public  function testAction(){
        $url = 'http://yingyan.baidu.com/api/v3/entity/add';
        $post_data['ak']       = 'KeuOXTEW2HOWXXjgckQYd5rGKjsUg5rI';
        $post_data['service_id']      = 146154;
        $post_data['entity_name'] = 'ms_000003';
        $post_data['entity_desc']    = 'ms_000003';
        //$post_data = array();
        $res = $this->request_post($url, $post_data);
        print_r($res);
    }

    private function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    function diffBetweenTwoDays ($day1, $day2)
    {
        if ($day1 < $day2) {
            $tmp = $day2;
            $day2 = $day1;
            $day1 = $tmp;
        }
        return round(($day1-$day2)/86400,2);
    }

    public function xmHourBicycle($lngX = 116.396906,$latY = 39.985818,$page = 1,$pageNum = 20,$hourSupport = 1,$showLevel= 0,$radius = 10,$adjustLevel = 1,$level = 16,$province = "",$city = "",$zone = "",$client_id = 218,$version = '2.2.0',$app_id = 218,$qudao_id = 'guanfang',$timestamp = 1499669461000,$device_model = "",$device_os = "",$test=0)
    {
        $time_start = $this->microtime_float();
        $lngX = $_REQUEST['lngX'];
        $latY = $_REQUEST['latY'];
        $city = $_REQUEST['city'];
        $radius = 10000;
        $days=15;
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
        $strSql = "select a.gis_lng,a.gis_lat,a.rent_content_id,a.car_item_id,a.shop_brand,a.city_id,a.car_info_name,a.zone_id,a.address,a.year_style,
                a.box_state,a.boxplus_state,a.is_urgent,a.smallest_days,a.plate_no,a.city_name,a.gearbox,a.price,a.review_count,a.model_id is_new_energy,
                a.review_star,a.owner_agree_rate,a.owner_agree_rate,a.order_success_count,a.owner_refuse_rate,a.owner_refuse_count,a.corporation_id,
                ROUND(st_distance(point(a.gis_lng, a.gis_lat),point($lngX, $latY))*111195,0) AS distance,rcrc.return_mode,
                rent.car_item_id,rcrc.return_mode,rent.send_car_enable,rent.create_time,rsh.type AS hour_price_type,rsh.mix_time_price,
                rsh.mix_mile_price,rsh.time_price,rsh.mile_price,rsh.minute_price,rsh.mix_minute_price,rent.is_by_hour AS is_by_hour,rsh.all_day_price,
                rsh.night_price,rsh.starting_price,rsh.day_hour_price,rsh.is_handsel,rsh.is_deposit,rsh.is_insurance,
                cip.url AS hour_car_picture_url,rce.battery_capacity,rce.running_distance,IFNULL(ms.all_miles,-1) all_miles
                from rent_content_search a
                left join rent_sku_hour rsh on a.rent_content_id = rsh.rent_content_id
                left join rent_content_ext rce on rce.rent_content_id=a.rent_content_id
                join rent_content rent on rent.id=a.rent_content_id
                left join rent_content_return_config rcrc on rcrc.rent_content_id=a.rent_content_id
                LEFT JOIN mileage_statistics ms ON ms.rent_content_id = rent.id
                LEFT JOIN car_info cn on cn.id=rent.car_info_id
                LEFT JOIN car_item ci on ci.id = rent.car_item_id
                LEFT JOIN car_model cm ON cm.id = cn.model_id
                LEFT JOIN car_item_color cic on cic.id = ci.color
                left join car_info_picture cip on rent.car_info_id=cip.car_info_id and cip.car_color_id=ci.color
                LEFT JOIN rent_content_avaiable rca on rca.rent_content_id=rent.id 
                where rent.status=2 AND rent.sell_status=1 and cip.type=0 and cip.status=2 and rca.hour_count<1
                and a.address_type=99 and a.plate_no like 'DD%' and rent.sort_id=112 and rent.car_info_id<>30150
                and rent.city_id={$city_id}
                HAVING distance BETWEEN 0 and {$radius}
                order by distance asc limit 200";
        $ts1 = $this->microtime_float();
        $car_result = M('')->query($strSql);
        $te1 = $this->microtime_float();
        $shortestId = $car_result[0]['rent_content_id'];
        $result = [];
        $result['carTipText'] = "等待取车期间，将为您预留10分钟免费取车时间，超时将开始计费";
        $result['shortestId'] = (float)$shortestId;
        $price0Count=0;
        $item_count=0;
        $distance=1000;
        foreach ($car_result as $k => $v) {
            $result['groupAndCar'][$v['rent_content_id']]['type'] = 1;
            $result['groupAndCar'][$v['rent_content_id']]['meterDistance'] = (float)sprintf("%.3f", $v['distance']);//
            $result['groupAndCar'][$v['rent_content_id']]['id'] = (float)$v['rent_content_id'];
            $result['groupAndCar'][$v['rent_content_id']]['carItemId'] = (float)$v['car_item_id'];
            $result['groupAndCar'][$v['rent_content_id']]['shopBrand'] = $v['shop_brand'];
            $result['groupAndCar'][$v['rent_content_id']]['cityId'] = (float)$v['city_id'];
            $result['groupAndCar'][$v['rent_content_id']]['pictureUrls'] = ["http://pic.baojia.com/b/" . $v['hour_car_picture_url'], "http://pic.baojia.com/m/" . $v['hour_car_picture_url'], "http://pic.baojia.com/s/" . $v['hour_car_picture_url']];
            $result['groupAndCar'][$v['rent_content_id']]['distance'] = (float)sprintf("%.3f", $v['distance'] * 0.001);
            $result['groupAndCar'][$v['rent_content_id']]['distanceText'] = (float)(sprintf("%.3f", $v['distance'])) . "米";
            $result['groupAndCar'][$v['rent_content_id']]['gisLng'] = (float)$v['gis_lng'];
            $result['groupAndCar'][$v['rent_content_id']]['gisLat'] = (float)$v['gis_lat'];
            $result['groupAndCar'][$v['rent_content_id']]['carInfoName'] = $v['car_info_name'];
            $result['groupAndCar'][$v['rent_content_id']]['zoneId'] = (float)$v['zone_id'];
            $result['groupAndCar'][$v['rent_content_id']]['address'] = $v['address'];
            $result['groupAndCar'][$v['rent_content_id']]['yearStyle'] = $v['year_style'];
            $result['groupAndCar'][$v['rent_content_id']]['boxInstall'] = (float)$v['box_state'];
            $result['groupAndCar'][$v['rent_content_id']]['boxPlusInstall'] = (float)$v['boxplus_state'];
            $result['groupAndCar'][$v['rent_content_id']]['isUrgent'] = (float)$v['is_urgent'];
            $result['groupAndCar'][$v['rent_content_id']]['isRecommend'] = 0;//
            $result['groupAndCar'][$v['rent_content_id']]['smallestDays'] = (float)$v['smallest_days'];
            $result['groupAndCar'][$v['rent_content_id']]['limitDay'] = "";
            $result['groupAndCar'][$v['rent_content_id']]['limitDayText'] = "不限行";
            $result['groupAndCar'][$v['rent_content_id']]['plateNo'] = $v['plate_no'];
            $result['groupAndCar'][$v['rent_content_id']]['city'] = $v['city_name'];
            $result['groupAndCar'][$v['rent_content_id']]['gearbox'] = (float)$v['gearbox'];
            $result['groupAndCar'][$v['rent_content_id']]['transmission'] = "自动挡";//
            $result['groupAndCar'][$v['rent_content_id']]['supportHour'] = (float)$v['is_by_hour'];
            $result['groupAndCar'][$v['rent_content_id']]['price'] = (float)$v['price'];
            $result['groupAndCar'][$v['rent_content_id']]['priceText'] = $v['price'] . "元/天";
            $result['groupAndCar'][$v['rent_content_id']]['hourPrice'] = (float)$v['day_hour_price'];
            $result['groupAndCar'][$v['rent_content_id']]['reviewCount'] = (float)$v['review_count'];//评论数量
            $result['groupAndCar'][$v['rent_content_id']]['star'] = (float)$v['review_star'];   //评分
            $result['groupAndCar'][$v['rent_content_id']]['ownerAgreeRate'] = (float)$v['owner_agree_rate'];
            $result['groupAndCar'][$v['rent_content_id']]['ownerAgreeRateText'] = ($v['owner_agree_rate'] * 100) . "%接单";
            $result['groupAndCar'][$v['rent_content_id']]['orderSuccessCount'] = (float)$v['order_success_count'];  //成单数
            $result['groupAndCar'][$v['rent_content_id']]['ownerRefuseRate'] = $v['owner_refuse_rate'];
            $result['groupAndCar'][$v['rent_content_id']]['ownerRefuseCount'] = (float)$v['owner_refuse_count'];
            $result['groupAndCar'][$v['rent_content_id']]['activity'] = null;//
            $corporation = M("corporation")->field("id,gis_Lat gisLat,gis_Lng gisLng,name,address,car_type carType,logo")->where("id = " . $v['corporation_id'])->select();
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['id'] = (float)$corporation[0]['id'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['gisLat'] = (float)$corporation[0]['gisLat'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['gisLng'] = (float)$corporation[0]['gisLng'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['name'] = $corporation[0]['name'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['carType'] = $corporation[0]['carType'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['logo'] = $corporation[0]['logo'];
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['distanceText'] = " ";//
            $result['groupAndCar'][$v['rent_content_id']]['corporation']['vehicleType'] = 1; //
            $result['groupAndCar'][$v['rent_content_id']]['newEnergyStatus'] = (float)$v['is_new_energy'];
            $result['groupAndCar'][$v['rent_content_id']]['supportSendHome'] = 0; //
            $result['groupAndCar'][$v['rent_content_id']]['supportSendHomeText'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['tags'] = ["不限行", ($v['owner_agree_rate'] * 100) . '%接单', "好评" . $v['review_count']];
            $result['groupAndCar'][$v['rent_content_id']]['longRentOnly'] = 0;//
            $result['groupAndCar'][$v['rent_content_id']]['longRentOnlyText'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['corpId'] = (float)$corporation[0]['parent_id'];
            $result['groupAndCar'][$v['rent_content_id']]['hourPriceType'] = (float)$v['hour_price_type'];
            $result['groupAndCar'][$v['rent_content_id']]['runningDistance'] = (float)$v['running_distance'];
            $result['groupAndCar'][$v['rent_content_id']]['runningDistanceText'] = "续航" . $v['running_distance'] . "千米";
            $result['groupAndCar'][$v['rent_content_id']]['mixText'] = '{' . $v['mix_minute_price'] . '}{/' . 分钟 . '+}{' . $v['mix_mile_price'] . '}{/公里}';
            $result['groupAndCar'][$v['rent_content_id']]['mixText1'] = $v['mix_minute_price'] . '元/' . 分钟 . '+' . $v['mix_mile_price'] . '元/公里';
            $result['groupAndCar'][$v['rent_content_id']]['allDayPrice'] = (float)$v['all_day_price'];
            $result['groupAndCar'][$v['rent_content_id']]['allDayPriceText'] = (float)$v['all_day_price'] . "/天";
            $result['groupAndCar'][$v['rent_content_id']]['nightPrice'] = (float)$v['night_price'];
            $result['groupAndCar'][$v['rent_content_id']]['nightPriceText'] = (float)$v['night_price'] . "/晚";
            $result['groupAndCar'][$v['rent_content_id']]['carAge'] = 0;//
            $result['groupAndCar'][$v['rent_content_id']]['monthPrice'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['monthPriceText'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['isLimited'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['bluetooth'] = 0;//
            $result['groupAndCar'][$v['rent_content_id']]['floatingRatio'] = (float)$v['floating_ratio'];
            $result['groupAndCar'][$v['rent_content_id']]['useNotify'] = "http://m.baojia.com/uc/rentSku/chargePriceThree?carItemId=" . $v['car_item_id'];
            $result['groupAndCar'][$v['rent_content_id']]['reduction'] = 1;//
            $result['groupAndCar'][$v['rent_content_id']]['insurance'] = 0.5;//保险
            $result['groupAndCar'][$v['rent_content_id']]['startingPrice'] = (float)($v['starting_price'] + 0.5);
            $result['groupAndCar'][$v['rent_content_id']]['areaCouponText'] = "指定区域内还车5折";
            //字体颜色
            $result['groupAndCar'][$v['rent_content_id']]['return_car_font'] = [];
            if ($v['return_mode'] == 4) {
                $result['groupAndCar'][$v['rent_content_id']]['craw'] = "指定网点还车可享受半价优惠，超出运营区域将收取10元调度费";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['value'] = "10元";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['color'] = '#fc0006';
            } else if($v['return_mode'] == 1){
                $result['groupAndCar'][$v['rent_content_id']]['craw'] = "需在".$corporation[0]['name']."内还车\r\n其它地点强制还车需支付100元调度费";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['value'] = $corporation[0]['name'];
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['color'] = '#54a9f3';
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][1]['value'] = "100元";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][1]['color'] = '#fc0006';
            }else {
                $result['groupAndCar'][$v['rent_content_id']]['craw'] = "不在指定网点还车将收取10元调度费，超出运营区域无法还车";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['value'] = "10元";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['color'] = '#fc0006';
            }
            $result['groupAndCar'][$v['rent_content_id']]['returnCraw'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['returnCrawUrl'] = "http://m.baojia.com/rentorder/getcrawmap?show_mark=1&rentid=" . $v['rent_content_id'];
            $result['groupAndCar'][$v['rent_content_id']]['returnCrawTitle'] = "服务区域";
            $result['groupAndCar'][$v['rent_content_id']]['vehicleType'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['floatingRatioString'] = " ";
            $result['groupAndCar'][$v['rent_content_id']]['isDeposit'] = (float)$v['is_deposit'];
            $result['groupAndCar'][$v['rent_content_id']]['carReturnCode'] = (float)$v['return_mode'];
            $result['groupAndCar'][$v['rent_content_id']]['isElseplaceReturnCar'] = null;
            $result['groupAndCar'][$v['rent_content_id']]['carLogo'] = "http://images.baojia.com/cooperation/2017/0303/1_14885517866228_m.jpg";
            $result['groupAndCar'][$v['rent_content_id']]['workTime'] = "24小时营业";

            $result['groupAndCar'][$v['rent_content_id']]['battery'] =$v['battery_capacity'];
            $result['groupAndCar'][$v['rent_content_id']]['createTime'] =date('Y-m-d H:i:s', $v['create_time']);
            $strSqlOrder3="SELECT DISTINCT rent_content_id FROM trade_order WHERE rent_type = 3 AND create_time>(UNIX_TIMESTAMP(NOW())-86400*3) and rent_content_id={$v['rent_content_id']}";
            $order_content3 = M('')->query($strSqlOrder3);
            $order_content3=array_column($order_content3,"rent_content_id");
            $strSqlOrder2="SELECT DISTINCT rent_content_id FROM trade_order WHERE rent_type = 3 AND create_time>(UNIX_TIMESTAMP(NOW())-86400*2) and rent_content_id={$v['rent_content_id']}";
            $order_content2 = M('')->query($strSqlOrder2);
            $order_content2=array_column($order_content2,"rent_content_id");
            $strSqlOrder1="SELECT DISTINCT rent_content_id FROM trade_order WHERE rent_type = 3 AND create_time>(UNIX_TIMESTAMP(NOW())-86400) and rent_content_id={$v['rent_content_id']}";
            $order_content1 = M('')->query($strSqlOrder1);
            $order_content1=array_column($order_content1,"rent_content_id");
            $strSqlOrder0="SELECT DISTINCT rent_content_id FROM trade_order WHERE rent_type = 3 AND create_time>(UNIX_TIMESTAMP(NOW())-86400/2) and rent_content_id={$v['rent_content_id']}";
            $order_content0 = M('')->query($strSqlOrder0);
            $order_content0=array_column($order_content0,"rent_content_id");
            $diffDays=$this->diffBetweenTwoDays(time(),$v['create_time']);
            $result['groupAndCar'][$v['rent_content_id']]['diffDays'] =$diffDays;
            //创建时间大于预定天数
            if($this->diffBetweenTwoDays(time(),$v['create_time'])>=$days) {
                if (($order_content0[0]!=$v['rent_content_id']) && $v['battery_capacity'] < 60) {
                    $price0Count++;
                    $result['groupAndCar'][$v['rent_content_id']]['days']=0.5;
                    $result['groupAndCar'][$v['rent_content_id']]['isPrice0'] = 1;
                }else if (($order_content1[0]!=$v['rent_content_id']) && $v['battery_capacity'] < 70) {
                    $price0Count++;
                    $result['groupAndCar'][$v['rent_content_id']]['days']=1;
                    $result['groupAndCar'][$v['rent_content_id']]['isPrice0'] = 1;
                }else if (($order_content2[0]!=$v['rent_content_id']) && $v['battery_capacity'] < 80) {
                    $price0Count++;
                    $result['groupAndCar'][$v['rent_content_id']]['days']=2;
                    $result['groupAndCar'][$v['rent_content_id']]['isPrice0'] = 1;
                }else if (($order_content3[0]!=$v['rent_content_id'])&& $v['battery_capacity'] < 90) {
                    $price0Count++;
                    $result['groupAndCar'][$v['rent_content_id']]['days']=3;
                    $result['groupAndCar'][$v['rent_content_id']]['isPrice0'] = 1;
                }else{
                    $result['groupAndCar'][$v['rent_content_id']]['isPrice0'] = 0;
                }
            }else{
                $result['groupAndCar'][$v['rent_content_id']]['isPrice0'] = 0;
            }
            $item_count++;
            if ($v['distance'] <= $distance) {//如果小于等于1公里 继续
                continue;
            } else {
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
        if($test==1){
            if ($_SERVER['REMOTE_ADDR']) {
                $cip = $_SERVER['REMOTE_ADDR'];
            } elseif (getenv("REMOTE_ADDR")) {
                $cip = getenv("REMOTE_ADDR");
            } elseif (getenv("HTTP_CLIENT_IP")) {
                $cip = getenv("HTTP_CLIENT_IP");
            } else {
                $cip = "unknown";
            }
            echo M('')->getLastSql()."----".$cip."----".count($car_result)."----".$result['refreshDistance']."----";
        }
        $time_end = $this->microtime_float();
        $second = round($time_end - $time_start,2);
        $t1=round($te1-$ts1,2);
        $t2=round($time_end-$te1,2);
        if (!empty($result)) {
            $this->response(["status" => 1, "msg" => "success", "showLevel" => 0,"price0Count"=>$price0Count,"count"=>count($result['groupAndCar']),"second"=>$second,"time1"=>$t1,"time2"=>$t2,"data" => $result], 'json');
        } else {
            $this->response(["status" => -1, "msg" => "附近暂无可用车辆", "showLevel" => 0, "data" => null], 'json');
        }
    }
}