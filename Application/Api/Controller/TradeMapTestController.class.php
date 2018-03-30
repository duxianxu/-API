<?php
namespace Api\Controller;

class TradeMapTestController extends BController
{
    private $box_config = 'mysqli://api-baojia:CSDV4smCSztRcvVb@10.1.11.14:3306/baojia_box';
    public function getOrderTrack($order_no=""){
        $gps = D('Gps');
        //根据订单查询轨迹
        $result['status']=0;
        $result['info']='获取失败';
        $order=M('trade_order')->where("order_no='{$order_no}'")->find();
        if($order && $order['status']>=50200 && $order['hand_over_state']>1){
            $orderid=$order["id"];
            $device=M('car_item_device')->where('car_item_id='.$order['car_item_id'])->field('imei')->find();
            $starttime=$order['begin_time'];
            $endtime=$order['end_time'];
            //获取取车地址跟还车地址
            $car_return_info=M('trade_order_car_return_info')->where('order_id='.$orderid)->find();
            if($car_return_info && $car_return_info['take_lng']>0 && $car_return_info['take_lat']>0){
                $newpos = $gps->bd_encrypt($car_return_info['take_lat'],$car_return_info['take_lng']);
                $liststart['id']=0;
                //if(compare_version(I('post.version'),"3.0.0") >= 0){
                    $liststart['lat']=(float)$car_return_info['take_lat'];
                    $liststart['lon']=(float)$car_return_info['take_lng'];
                /*}else{
                $liststart['lat']=$newpos['lat'];
                $liststart['lon']=$newpos['lon'];
                }*/
                $liststart['speed']=0;
                $liststart['course']=0;
                $liststart['accstatus']=0;
                $liststart['datetime']=date('H:i:s',$order['begin_time']);
            }
            if($car_return_info && $car_return_info['return_lng']>0 && $car_return_info['return_lat']>0){
                $newpos = $gps->bd_encrypt($car_return_info['return_lat'],$car_return_info['return_lng']);
                $listend['id']=0;
                //if(compare_version(I('post.version'),"3.0.0") >= 0){
                    $listend['lat']=(float)$car_return_info['return_lat'];
                    $listend['lon']=(float)$car_return_info['return_lng'];
                /*}else{
                $listend['lat']=$newpos['lat'];
                $listend['lon']=$newpos['lon'];
                }*/
                $listend['speed']=0;
                $listend['course']=0;
                $listend['accstatus']=0;
                $listend['datetime']=date('H:i:s',$order['end_time']);
            }
            //获取指令记录，分析中途停车点
            //获取还车时的网点跟围栏数据
            $result['area']=[];
            $result['area_status']=0;
            $result['cor']=[];
            $result['cor_status']=1;
            $result['curpoint']=[];
            $trade_order_return_info=M('baojia_mebike.trade_order_return_log')->where('order_id='.$orderid)->order('version desc')->find();
            //echo "trade_order_return_info---------------------<pre>";
            //print_r($trade_order_return_info);
            if($trade_order_return_info){
                $result['area']=json_decode($trade_order_return_info['Polygon'],true);
                foreach($result['area'] as $k=>$v){
                    $wlpos = $gps->gcj_encrypt($v[1],$v[0]);
                    $wlpos1 = $gps->bd_encrypt($wlpos['lat'],$wlpos['lon']);
                    //if(compare_version(I('post.version'),"3.0.0") >= 0){
                        $result['area'][$k]=[$wlpos['lon'],$wlpos['lat']];
                    /*}else{
                    $result['area'][$k]=[$wlpos1['lon'],$wlpos1['lat']];
                    }*/
                }
                if($trade_order_return_info['resault']==0){
                    $result['area_status']=1;
                }
                //获取还车时的定位
                $curpoint=json_decode($trade_order_return_info['curpoint'],true);
                if(count($curpoint)>0){
                    $curpos = $gps->gcj_encrypt($curpoint[1],$curpoint[0]);
                    $curpos1 = $gps->bd_encrypt($curpos['lat'],$curpos['lon']);

                    //if(compare_version(I('post.version'),"3.0.0") >= 0){
                        $result['curpoint']=[$curpos['lon'],$curpos['lat']];
                    /*}else{
                    $result['curpoint']=[$curpos1['lon'],$curpos1['lat']];
                    }*/
                }
            }
            //echo "<pre>";
            //print_r($result['curpoint']);
            $rtp=M('trade_order_return_corporation')->where('order_id='.$orderid)->order('update_time desc')->find();
            if($rtp) {
                $rtppoint = json_decode($rtp['point'], true);
                if($rtppoint['d_gis_lng']>0&&$rtppoint['d_gis_lat']>0) {
                    $result['curpoint'] = [$rtppoint['d_gis_lng'], $rtppoint['d_gis_lat']];;
                }
            }
            //echo "-------------------------------<pre>";
            //print_r($result['curpoint']);
            if($rtp && $rtp['corporation_id']>0){
                $result['cor_status']=0;
                $rtppoint=json_decode($rtp['point'],true);
                $wlpos = $gps->bd_encrypt($rtppoint['gis_lat'],$rtppoint['gis_lng']);
                //if(compare_version(I('post.version'),"3.0.0") >= 0){
                    $rtppoint['corporation_center']=[(float)$rtppoint['gis_lng'],(float)$rtppoint['gis_lat']];
                /*}else{
                $rtppoint['corporation_center']=[$wlpos['lon'],$wlpos['lat']];
                }*/
                $rtppoint['type']=isset($rtppoint['type']) ? $rtppoint['type'] : 1;
                $result['cor'][]=$rtppoint;
            }
            $list=$this->get_route($device['imei'],$starttime,$endtime);
            //echo "<pre>";
            //print_r($list);die;
            if(count($list)>=1 || ($liststart && $listend)){
                $listdata=[];
                $result['status']=1;
                $result['info']='获取成功';
                //坐标转换百度
                foreach($list as $k=>$v){
                    $newpos = $gps->gcj_encrypt($v['latitude'],$v['longitude']);
                    $listdata[$k]['id']=$v['id'];
                    $newpos1 = $gps->bd_encrypt($newpos['lat'],$newpos['lon']);
                    //if(compare_version(I('post.version'),"3.0.0") >= 0){
                        $listdata[$k]['lat']=$newpos['lat'];
                        $listdata[$k]['lon']=$newpos['lon'];
                    /*}else{
                    $listdata[$k]['lat']=$newpos1['lat'];
                    $listdata[$k]['lon']=$newpos1['lon'];
                    }*/
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
                //解决Android兼容问题
                $result['curpoint']=[];
                array_unshift($listdata,$liststart);
                array_push($listdata,$listend);
                $result['listcount']=count($listdata);
                $result['list']=$listdata;
            }
        }
        $this->response($result, 'json');
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

    //获取路线
    public function get_route($imei,$beginTime=0,$endtime=0,$order=' ASC') {
        $condition="imei='$imei'";
        if($beginTime && $endtime)$condition.=" AND datetime between $beginTime AND $endtime";
        $PointArr1 = M('fed_gps_location')->where("$condition")->order('datetime asc')->select();
        //切换备份表进行查询
        $tb=M("",null,$this->box_config)->query("show tables like 'gps_location_%'");
        $bt=date('ymd',$beginTime);
        $et=date('ymd',$endtime);
        foreach($tb as $key=>$v){
            foreach($v as $k1=>$v1){
                if(substr($v1,-3)!="zid"){
                    if(trim($v1,'gps_location_')>=$bt && trim($v1,'gps_location_')>=$et){
                        $tbs1[]=$v1;
                    }
                }
            }
        }
        sort($tbs1);
        if($tbs1[0]){
            $PointArr2=M("",null,$this->box_config)->query("select * from {$tbs1[0]} where  imei='{$imei}' and longitude>0 and latitude>0 and datetime>={$beginTime} and datetime<={$endtime} order by datetime asc");
        }
        if(count($PointArr1)>0 && count($PointArr2)>0){
            $PointArr2=array_merge($PointArr2,$PointArr1);
        }elseif(count($PointArr1)>1){
            $PointArr2=$PointArr1;
        }
        return $PointArr2;
    }
}
?>
