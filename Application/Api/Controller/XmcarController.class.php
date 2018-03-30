<?php
namespace Api\Controller;

use Think\Controller\RestController;
use Api\Logic\Gps;
use Api\Logic\FreeCar;
use Think\Exception;

class XmcarController extends BController
{

    //private $search_url ="http://192.168.3.219:8081/mongo/searchBikeAndCar";
    private $search_url ="http://ms.baojia.com/mongo/searchBikeAndCar";

    /**
     * App首页地图加载车辆
     */
    public function loadMapBicycle($lngX = 116.396906,$latY = 39.985818,$city = "北京市",$client_id = 218,$version ='2.2.0',$app_id = 218,$qudao_id = 'guanfang',$timestamp = 1499669461000,$device_model = "",$device_os = "",$test=0)
    {
        $time_start = $this->microtime_float();
        $lngX = $_REQUEST['lngX'];
        $latY = $_REQUEST['latY'];
        $radius = 10000;
        if (empty($lngX) || empty($latY)) {
            $this->response(["status" => 1004, "msg" => "参数错误,请参考API文档", "showLevel" => 0, "data" => null], 'json');
        }
        $post_fields["longitude"]=$lngX;
        $post_fields["latitude"]=$latY;
        $car_result=$this->post($this->search_url,$post_fields);
        //echo "<pre>";print_r($car_result);die;
        $search_end = $this->microtime_float();
        $result = [];
        $result['shortestId'] =0;
        if($car_result&&$car_result["data"]&&$car_result["data"]["carList"]){
            $car_result=$car_result["data"]["carList"];
            $shortestId = $car_result[0]['rentcontentId'];
            $result['shortestId'] = (float)$shortestId;
            $price0Count=0;
            $item_count=0;
            $distance=1000;
            foreach ($car_result as $k => $v) {
                $corporation_parent_id=$v['corporationParentId'];
                if(empty($corporation_parent_id)){
                    continue;
                }
                $car_item_id=$v['car_item_id'];
                $imei = M("car_item_device")
                    ->where("car_item_id={$car_item_id}")
                    ->getField("imei");
                if (empty($imei)) {
                    continue;
                }
                $rent_content_id=$v['rentcontentId'];
                $result['groupAndCar'][$rent_content_id]['id'] = (float)$rent_content_id;
                $result['groupAndCar'][$rent_content_id]['carItemId'] = (float)$car_item_id;
                $result['groupAndCar'][$rent_content_id]['gisLng'] = (float)$v['location'][0];
                $result['groupAndCar'][$rent_content_id]['gisLat'] = (float)$v['location'][1];
                //$plate_no=M("car_item_verify")->where(["car_item_id"=>$car_item_id])->getField("plate_no");
                $result['groupAndCar'][$rent_content_id]['plateNo'] =$v['plateNo'];
                $result['groupAndCar'][$rent_content_id]['carReturnCode'] = (float)$v['returnMode'];
                $result['groupAndCar'][$rent_content_id]['corporationParentId'] =$corporation_parent_id;
                $result['groupAndCar'][$rent_content_id]['corporationName'] =$v['corporationName'];
                /*$corporation_id=M("rent_content")->where(["id"=>$rent_content_id])->getField("corporation_id");
                $corporation=M('corporation')
                    ->field("name,parent_id")
                    ->where("id={$corporation_id}")
                    ->find();
                if($corporation){
                    $result['groupAndCar'][$rent_content_id]['corporationName'] =$corporation["name"];
                }*/
                $result['groupAndCar'][$rent_content_id]['imei'] = $v['imei'];
                $result['groupAndCar'][$rent_content_id]['distance'] =$v['distance'];
                $result['groupAndCar'][$rent_content_id]['isPrice0'] = 0;
                $freeCar = new \Api\Logic\FreeCar();
                if ($freeCar->checkfreecar($rent_content_id,$v['corporationParentId'])) {
                    $price0Count++;
                    $result['groupAndCar'][$rent_content_id]['isPrice0'] = 1;
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
            \Think\Log::write("首页地图加载车辆".$second."秒，查询时间".$search_second."秒，处理时间".$handle_second."秒,返回数据".$count."条，参数：".json_encode($_REQUEST), "INFO");
            if (!empty($result)) {
                $this->response(["status" => 1, "msg" => "success", "showLevel" => 0,"price0Count"=>$price0Count,"count"=>$count,"second"=>$second,"search_second"=>$search_second,"handle_second"=>$handle_second,"data" => $result], 'json');
            } else {
                $this->response(["status" => -1, "msg" => "附近暂无可用车辆", "showLevel" => 0, "data" => ["shortestId"=>0,"refreshDistance"=>10000]], 'json');
            }
        }else{
            $this->response(["status" => -1, "msg" => "附近暂无可用车辆", "showLevel" => 0, "data" => ["shortestId"=>0,"refreshDistance"=>10000]], 'json');
        }
    }

    //post请求
    public function post($postUrl,$data)
    {
        $postUrl=$postUrl;
        $json=json_encode($data,JSON_UNESCAPED_UNICODE);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            [
                'Content-Type:application/json;charset=utf-8',
                'Content-Length: '.strlen($json),
                'appid:3',
                'brandType:100000'
            ]
        );
        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($output, true);
        $data["code"] = $httpCode;
        return $data;
    }

    /**
     * App首页地图加载车辆
     */
    public function loadMapBicycle1($lngX = 116.396906,$latY = 39.985818,$city = "北京市",$client_id = 218,$version ='2.2.0',$app_id = 218,$qudao_id = 'guanfang',$timestamp = 1499669461000,$device_model = "",$device_os = "",$test=0)
    {
        try {
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
                } else {
                    $city_id = $this->getCity_id($city);
                }
            }

            $gps = new \Api\Logic\GpsCalc();
            $gpsCoordinate = $gps->gcj_decrypt($latY, $lngX);
            $strSql = "select rent.id,rent.car_item_id,IFNULL(s.latitude,0) gis_lat,IFNULL(s.longitude,0) gis_lng,
            civ.plate_no,rent.corporation_id,rcrc.return_mode,cor.name corporation_name,cid.imei,
            ROUND(st_distance(point(s.longitude,s.latitude),point({$gpsCoordinate['lon']},{$gpsCoordinate['lat']}))*111195,0) AS distance
            from rent_content rent
            LEFT JOIN car_item_verify civ ON rent.car_item_id=civ.car_item_id 
            LEFT JOIN rent_content_avaiable rca ON rca.rent_content_id=rent.id
            left join rent_content_return_config rcrc ON rcrc.rent_content_id=rent.id
            LEFT JOIN corporation cor ON cor.id=rent.corporation_id
            JOIN car_item_device cid ON cid.car_item_id=rent.car_item_id
            LEFT JOIN fed_gps_status s ON s.imei=cid.imei
            where rent.status=2 AND rent.sell_status=1 AND rca.hour_count=0
            AND (civ.plate_no like 'DD%' OR civ.plate_no like 'XM%') AND rent.sort_id=112 AND rent.car_info_id<>30150
            AND IFNULL(s.latitude,0)>0 AND IFNULL(s.longitude,0)>0
            AND rent.city_id={$city_id}
            HAVING distance<{$radius}
            order by distance asc limit 30";
            $car_result = M('')->query($strSql);
            $search_end = $this->microtime_float();
            if ($test == 1) {
                echo M('')->getLastSql();
            }
            $shortestId = $car_result[0]['id'];
            $result = [];
            $result['shortestId'] = (float)$shortestId;
            $price0Count = 0;
            $item_count = 0;
            $distance = 1000;
            foreach ($car_result as $k => $v) {
                $result['groupAndCar'][$v['id']]['id'] = (float)$v['id'];
                $result['groupAndCar'][$v['id']]['carItemId'] = (float)$v['car_item_id'];
                $gdCoordinate = $gps->gcj_encrypt($v['gis_lat'], $v['gis_lng']);
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
            $result['refreshDistance'] = count($car_result) > 0 ? $distance : $radius;
            $time_end = $this->microtime_float();
            $search_second = round($search_end - $time_start, 2);
            $handle_second = round($time_end - $search_end, 2);
            $second = round($time_end - $time_start, 2);
            $count = count($result['groupAndCar']);
            \Think\Log::write("首页地图加载车辆" . $second . "秒，返回数据" . $count . "条，参数：" . json_encode($_REQUEST), "INFO");
            if (!empty($result)) {
                $this->response(["status" => 1, "msg" => "success", "showLevel" => 0, "price0Count" => $price0Count, "count" => $count, "second" => $second, "search_second" => $search_second, "handle_second" => $handle_second, "data" => $result], 'json');
            } else {
                $this->response(["status" => -1, "msg" => "附近暂无可用车辆", "showLevel" => 0, "data" => []], 'json');
            }
        }catch (Exception $exception){
            $this->response(["status" => -1, "msg" => "查询失败", "showLevel" => 0, "data" => [],"exception"=>$exception], 'json');
        }
    }

    /**
     * App根据车辆id获取车辆详细信息
     */
    public function loadDetails($id=5862626,$lngX = 116.396906,$latY = 39.985818,$client_id = 218,$version ='2.2.0',$app_id = 218,$qudao_id = 'guanfang',$timestamp =0,$device_model = "",$device_os = "",$test=0)
    {
        $insurance = 0.5;
        $time_start = $this->microtime_float();
        $id = $_REQUEST['id'];
        $lngX = $_REQUEST['lngX'];
        $latY = $_REQUEST['latY'];
        if (empty($id) || empty($lngX) || empty($latY)) {
            $this->response(["status" => 1004, "msg" => "参数错误,请参考API文档", "showLevel" => 0, "data" => null], 'json');
        }
        $hour_count = M('rent_content_avaiable')->where("rent_content_id={$id}")->getField("hour_count");
        if ($hour_count && $hour_count == 1) {
            $this->response(["status" => -1, "msg" => "抱歉,车辆不可用", "showLevel" => 0, "data" => []], 'json');
        }

        $gps = new \Api\Logic\GpsCalc();
        $gpsCoordinate = $gps->gcj_decrypt($latY, $lngX);

        $rent = M("rent_content")->field("id,car_item_id,car_info_id,corporation_id")->where("id={$id}")->find();
        if (empty($rent)) {
            $this->response(["status" => -1, "msg" => "抱歉,车辆不可用", "showLevel" => 0, "data" => []], 'json');
        }
        $imei = M("car_item_device")->where("car_item_id={$rent["car_item_id"]}")->getField("imei");
        if (empty($imei)) {
            $this->response(["status" => -1, "msg" => "抱歉,车辆不可用", "showLevel" => 0, "data" => []], 'json');
        }
        $plate_no = M("car_item_verify")->where("car_item_id={$rent["car_item_id"]}")->getField("plate_no");
        if (empty($plate_no)) {
            $this->response(["status" => -1, "msg" => "抱歉,车辆不可用", "showLevel" => 0, "data" => []], 'json');
        }
        $rent_ext = M("rent_content_ext")->field("battery_capacity,running_distance")->where("rent_content_id={$id}")->find();
        $price = M("rent_sku_hour")->field("mix_mile_price,mix_minute_price,starting_price")->where("rent_content_id={$id}")->find();
        $return_mode = M("rent_content_return_config")->where("rent_content_id={$id}")->getField("return_mode");
        $location = M("fed_gps_status")->field("longitude gis_lng,latitude gis_lat,ROUND(st_distance(point(longitude,latitude),point({$gpsCoordinate['lon']},{$gpsCoordinate['lat']}))*111195,0) AS distance")->where("imei='{$imei}'")->find();

        $result = [];
        $result['carTipText'] = "等待取车期间，将为您预留10分钟免费取车时间，超时将开始计费";
        $result['meterDistance'] = (float)sprintf("%.3f", $location['distance']);
        $result['id'] = (float)$rent['id'];
        $result['carItemId'] = (float)$rent['car_item_id'];
        $result['pictureUrls'] = ["http://pic.baojia.com/b/2017/0331/2073761_201703318527.png", "http://pic.baojia.com/m/2017/0331/2073761_201703318527.png", "http://pic.baojia.com/s//2017/0331/2073761_201703318527.png"];

        $color = M("car_item")->where("id={$rent["car_item_id"]}")->getField("color");
        $picture=M("car_info_picture")->where("type=0 AND status=2 AND car_info_id=30059 AND car_color_id={$color}")
            ->getField("url");
        if ($picture) {
            $result['pictureUrls'] = ["http://pic.baojia.com/b/" . $picture, "http://pic.baojia.com/m/" . $picture, "http://pic.baojia.com/s/" . $picture];
        }
        $result['distance'] = (float)sprintf("%.3f", $location['distance'] * 0.001);
        $result['distanceText'] = (float)(sprintf("%.3f", $location['distance'])) . "米";
        $gdCoordinate = $gps->gcj_encrypt($location['gis_lat'], $location['gis_lng']);
        $result['gisLng'] = (float)$gdCoordinate['lon'];
        $result['gisLat'] = (float)$gdCoordinate['lat'];
        $address=$gps->GetAmapAddress($gdCoordinate['lon'], $gdCoordinate['lat']);
        $result['address'] =$address?"":$address;
        $result['plateNo'] = $plate_no;
        $corporation = M("corporation")
            ->field("id,parent_id,gis_Lat gisLat,gis_Lng gisLng,name,address,car_type carType,logo")
            ->where("id ={$rent['corporation_id']}")->find();
        $result['corporationName'] = $corporation['name'];

        $result['imei'] = $imei;
        $no_zero_imei = ltrim($imei, "0");
        $aliyun_conn = "mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc";
        $result['areaId'] = "0";
        $area = M("car_group_r", null, $aliyun_conn)
            ->where("carId='{$no_zero_imei}' and status=1")->getField("groupId");
        if ($area) {
            $result['areaId'] =$area?$area:0;
        }
        $result['runningDistance'] = (float)$rent_ext['running_distance'];
        $result['runningDistanceText'] = "续航" . $rent_ext['running_distance'] . "千米";
        $result['mixText'] = '{' . $price['mix_minute_price'] . '}{/' . 分钟 . '+}{' . $price['mix_mile_price'] . '}{/公里}';
        $result['mixText1'] = $price['mix_minute_price'] . '元/' . 分钟 . '+' . $price['mix_mile_price'] . '元/公里';
        $result['insurance'] = $insurance;//保险
        $result['startingPrice'] = (float)($price['starting_price'] + $insurance);
        $result['areaCouponText'] = "指定区域内还车5折";
        $result['chargingRule1'] = $price['mix_minute_price'] . '元/' . 分钟 . '+' . $price['mix_mile_price'] . '元/公里';
        $result['chargingRule2'] = '最低消费' . $price['startingPrice'] . '元(含' . $insurance . '元保险费)';
        $result['attention1'] = '需在【可骑行区域】内骑行';
        $result['attention2'] = '【可骑行区域外】小蜜单车无法供电';
        $result['isPrice0'] = 0;
        $corporation_parent_id =$corporation["parent_id"]?$corporation["parent_id"]:0;
        $freeCar = new \Api\Logic\FreeCar();
        if ($freeCar->checkfreecar($id,$corporation_parent_id)) {
            $result['chargingRule2'] = "前一小时免费骑行";
            $result['isPrice0'] = 1;
        }
        //字体颜色
        $result['return_car_font'] = [];
        if ($return_mode== 4) {//自由还
            $result['returnMode1']['content'] = '自由还:可在【可骑行区域】内任意地点还车';
            $result['returnMode2']['content'] = '还车至指定停车点半价';
            $result['returnMode3']['content'] = '【可骑行区域外】还车需支付' . C('XM_DispatchFee') . '元调度费';
            $result['returnMode3']['value'] = C('XM_DispatchFee') . "元";
            $result['returnMode3']['color'] = '#fc0006';
            $result['craw'] = "指定网点还车可享受半价（注：享受日封顶优惠后将不再享受此优惠），超出运营区域将收取" . C('XM_DispatchFee') . "元调度费";
            $result['return_car_font'][0]['value'] = C('XM_DispatchFee') . "元";
            $result['return_car_font'][0]['color'] = '#fc0006';
        } else if ($return_mode== 1) {//原点还
            $result['returnMode1']['content'] = '需还车至【' . $corporation['name'] . '】内';
            $result['returnMode2']['content'] = '【非指定停车点】还车需支付' . C('XM_DispatchFee') . '元调度费';
            $result['returnMode2']['value'] = C('XM_DispatchFee') . "元";
            $result['returnMode2']['color'] = '#fc0006';
            $result['returnMode3']['content'] = '【可骑行区域外】无法还车';
            $result['craw'] = "需在" . $corporation['name'] . "内还车\r\n其它地点强制还车需支付" . C('XM_DispatchFee') . "元调度费";
            $result['return_car_font'][0]['value'] = $corporation['name'];
            $result['return_car_font'][0]['color'] = '#54a9f3';
            $result['return_car_font'][1]['value'] = C('XM_DispatchFee') . "元";
            $result['return_car_font'][1]['color'] = '#fc0006';
        } else {//网点还
            $result['returnMode1']['content'] = '需还车至【指定停车点】';
            $result['returnMode2']['content'] = '【非指定停车点】还车需支付' . C('XM_DispatchFee') . '元调度费';
            $result['returnMode2']['value'] = C('XM_DispatchFee') . "元";
            $result['returnMode2']['color'] = '#fc0006';
            $result['returnMode3']['content'] = '【可骑行区域外】无法还车';
            $result['craw'] = "不在指定网点还车将收取" . C('XM_DispatchFee') . "元调度费，超出运营区域无法还车";
            $result['return_car_font'][0]['value'] = C('XM_DispatchFee') . "元";
            $result['return_car_font'][0]['color'] = '#fc0006';
        }
        $result['returnCraw'] = null;
        $result['returnCrawUrl'] = "http://m.baojia.com/rentorder/getcrawmap?show_mark=1&rentid=" . $id;
        $result['returnCrawTitle'] = "服务区域";
        $result['vehicleType'] = null;
        $result['carReturnCode'] = (float)$return_mode;
        $result['carLogo'] = "http://images.baojia.com/cooperation/2017/0303/1_14885517866228_m.jpg";
        $time_end = $this->microtime_float();
        $second = round($time_end - $time_start, 2);
        if (!empty($result)) {
            $this->response(["status" => 1, "msg" => "success", "showLevel" => 0, "second" => $second, "data" => $result], 'json');
        } else {
            $this->response(["status" => -1, "msg" => "抱歉,车辆不可用", "showLevel" => 0, "data" => []], 'json');
        }
    }

    /**
     * APP车辆--地图
     */
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
                rent.send_car_enable,rent.create_time,rsh.type AS hour_price_type,rsh.mix_time_price,
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
                $result['groupAndCar'][$v['rent_content_id']]['craw'] = "指定网点还车可享受半价（注：享受日封顶优惠后将不再享受此优惠），超出运营区域将收取".C('XM_DispatchFee')."元调度费";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['value'] = C('XM_DispatchFee')."元";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['color'] = '#fc0006';
            } else if($v['return_mode'] == 1){
                $result['groupAndCar'][$v['rent_content_id']]['craw'] = "需在".$corporation[0]['name']."内还车\r\n其它地点强制还车需支付".C('XM_DispatchFee')."元调度费";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['value'] = $corporation[0]['name'];
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['color'] = '#54a9f3';
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][1]['value'] = C('XM_DispatchFee')."元";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][1]['color'] = '#fc0006';
            }else {
                $result['groupAndCar'][$v['rent_content_id']]['craw'] = "不在指定网点还车将收取".C('XM_DispatchFee')."元调度费，超出运营区域无法还车";
                $result['groupAndCar'][$v['rent_content_id']]['return_car_font'][0]['value'] = C('XM_DispatchFee')."元";
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

            $freeCar = new \Api\Logic\FreeCar();
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

    private function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
    //优惠券图片
    public function code_detail($uid=1, $p=1 ,$limit = 10,$client_id = 218,$version = '2.2.0',$app_id = 218,$qudao_id = 'guanfang',$timestamp = 1499669461000,$device_model = "",$device_os = ""){
        $data = [];
        $data['price'] = $data['price'] = sprintf("%.2f",2.00);
        $data['name'] = "优惠券";
        $data['text'] = "邀请好友，好友与你各得";
        $data['text_number'] = "5张骑行优惠券";
        $data['pic'] = "http://pic.baojia.com/xm_activity/img_code/code.png";
        $data['H5_url'] = "";
        $data['share_title'] = "老司机给你10元带你骑小蜜单车";
        $data['share_desc'] = "现在注册小蜜单车立得10元";
        $data['share_pic'] = "http://pic.baojia.com/xm_activity/img_code/64c40b559919c90bf52e849f770442af.png";
        $this->response(["status" => 1, "info" => "获取成功", "data" => $data], 'json');
    }
    //小蜜活动数据
    public function xiaomi_activity($uid=1, $p=1 ,$limit = 10,$client_id = 218,$version = '2.2.0',$app_id = 218,$qudao_id = 'guanfang',$timestamp = 1499669461000,$device_model = "",$device_os = ""){
        $p = I('post.page') ? I('post.page') : 1;
        $limit = I('post.limit') ? I('post.limit') : 10;
        $new_limit = ($p - 1) * $limit;
        if( empty($uid) ){
            $this->response(["status" => -1, "info" => "请登录查看相关活动"], 'json');
        }
        $data = M("xiaomi_activity")->field("id,pic1,pic2,width,height,redirect,content")->where("status<3")->limit($new_limit, $limit)->order("sort asc")->select();
        if( empty($data) ){
            $this->response(["status" => -1,"info" => "暂无活动"], 'json');
        }
        foreach ($data as $k => $v) {
            $data[$k]['pic1'] = "http://pic.baojia.com".$v['pic1'];
            $data[$k]['width'] = (float)$v['width'];
            $data[$k]['height'] = (float)$v['height'];
        }
        $this->response(["status" => 1,"info" => "获取成功", "p"=>(float)$p, "data" => $data], 'json');
    }
    //我的优惠券
    public function mycode( $mobile = "", $uid=1, $p=1 ,$limit = 10,$client_id = 218,$version = '2.2.0',$app_id = 218,$qudao_id = 'guanfang',$timestamp = 1499669461000,$device_model = "",$device_os = ""){
        $p = I('post.page') ? I('post.page') : 1;
        $limit = I('post.limit') ? I('post.limit') : 10;
        $uid = I('post.uid');
        $new_limit = ($p - 1) * $limit;
        $uKey = C('KEY');
        $mobile = decrypt($uKey, $mobile);
        $mobile = trim($mobile);
        if( empty($mobile) ){
            $this->response(["status" => -1, "info" => "账户信息有误"], 'json');
        }
        if( empty($uid) ){
            $this->response(["status" => -1, "info" => "请登录后查看你的优惠券"], 'json');
        }
        $res = M("ucenter_member")->where(["uid"=>$uid,"mobile"=>$mobile])->getField("uid");
        if( empty($res) ) {
            $this->response(["status" => -1, "info" => "账户信息有误请重新登录"], 'json');
        }
        $time = time();
        $model = M("xiaomi_code");
        $model->where("uid = {$uid} and overtime<={$time}")->save(["status"=>2]); //过期优惠券修改状态
        $where = " uid = '{$uid}' and status <> 3 ";
        $result1 = $model
            ->where($where)
            ->field("id,uid,price,over_time,invite,beinvite,status")
            ->order("over_time asc")
            ->limit($new_limit, $limit)
            ->select();
        if(empty($result1)){
            $this->response(["status" => 0, "info" => "暂无可用优惠券"], 'json');
        }
        foreach ($result1 as $k => $v) {
            if( $v['invite']==$v['uid'] ){
                $result1[$k]['access'] = "邀请赠送优惠券";
            }
            if( $v['beinvite']==$v['uid'] ){
                $result1[$k]['access'] = "注册赠送优惠券";
            }
            $result1[$k]['over_time'] = "有效期至".date("Y-m-d",$v['over_time']);
            $result1[$k]['name'] = "优惠券";
            $result1[$k]['status'] = $v['status'];
            $result1[$k]['price'] = sprintf("%.2f",$v['price']);
        }
        $this->response([ "status" => 1,"info" => "success", "p"=>(float)$p ,"data"=>$result1], 'json');
    }
    //关于我们
    public function aboutUs($version = ""){
        $data[0]["icon"] = "http://pic.baojia.com/xm_activity/img_code/iconWeChat.png";
        $data[0]["key"] = "微信公众号";
        $data[0]["value"] = "小蜜单车";
        $data[0]["alert"] = 0;
        $data[0]["url"] = 0;
        $data[1]["icon"] = "http://pic.baojia.com/xm_activity/img_code/iconCall.png";
        $data[1]["key"] = "客服热线";
        $data[1]["value"] = "400-808-1651";
        $data[1]["alert"] = 1;
        $data[1]["url"] = 0;
        $data[2]["icon"] = "http://pic.baojia.com/xm_activity/img_code/iconMaintenance.png";
        $data[2]["key"] = "报修热线";
        $data[2]["value"] = "010-57958773";
        $data[2]["alert"] = 1;
        $data[2]["url"] = 0;
        $data[3]["icon"] = "http://pic.baojia.com/xm_activity/img_code/iconMail.png";
        $data[3]["key"] = "客服邮箱";
        $data[3]["value"] = "service@mebike.cc";
        $data[3]["alert"] = 0;
        $data[3]["url"] = 0;
        $data[4]["icon"] = "http://pic.baojia.com/xm_activity/img_code/iconWebsite.png";
        $data[4]["key"] = "官方网站";
        $data[4]["value"] = "www.mebike.cc";
        $data[4]["alert"] = 0;
        $data[4]["url"] = 1;
        $data[5]["icon"] = "http://pic.baojia.com/xm_activity/img_code/iconJoin.png";
        $data[5]["key"] = "招商加盟";
        $data[5]["value"] = "wechat@13911669640";
        $data[5]["alert"] = 0;
        $data[5]["url"] = 0;
        $this->response(["status"=>1,"info"=>"success","data"=>$data],"json");
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

    /*public function GetAmapAddress($lng,$lat,$default=''){
        $res = file_get_contents('http://restapi.amap.com/v3/geocode/regeo?output=JSON&location='.$lng.','.$lat.'&key=7461a90fa3e005dda5195fae18ce521b&s=rsv3&radius=1000&extensions=base');
        $res = json_decode($res);//return $res;
        if($res->info == 'OK'){
            //echo "<pre>";print_r($res);
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
}

?>
