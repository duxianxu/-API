<?php
/**
 * Created by Notepad++.
 * User: jidaoxing
 * Date: 2017/8/29
 * Time: 16:27
 */

namespace Api\Logic;
use Think\Model;

class GpsCalc {

    private $PI = 3.14159265358979324;

    private $x_pi = 0;

    static $bd_detail_list = [];
    private $keys = [
        '3eb136542e99e2565cd6388bb5578397',
        'ec8ab4be86af75936ba44f2561a4a43f',
        'f252f8670ecd50966959861af63ed988',
    ];

    public function __construct(){

        $this->x_pi = 3.14159265358979324 * 3000.0 / 180.0;

    }

    //WGS-84 to GCJ-02
    public function gcj_encrypt($wgsLat, $wgsLon) {

        if ($this->outOfChina($wgsLat, $wgsLon)){

            return array('lat' => $wgsLat, 'lon' => $wgsLon);

        }

        $d = $this->delta($wgsLat, $wgsLon);

        return array('lat' => $wgsLat + $d['lat'],'lon' => $wgsLon + $d['lon']);

    }

    //WGS-84 to GCJ-02
    public function gcj_encrypt_v2($wgsLon,$wgsLat) {

        if ($this->outOfChina($wgsLat, $wgsLon)){

            return array('lat' => $wgsLat, 'lon' => $wgsLon);

        }

        $d = $this->delta($wgsLat, $wgsLon);

        return array( $wgsLon + $d['lon'],$wgsLat + $d['lat']);

    }

    //WGS-84 to GCJ-02
    public function gcj_encrypt_v3($arr) {

        $wgsLon = $arr[0];
        $wgsLat = $arr[1];
        if ($this->outOfChina($wgsLat, $wgsLon)){

            return array('lat' => $wgsLat, 'lon' => $wgsLon);

        }

        $d = $this->delta($wgsLat, $wgsLon);

        return array( $wgsLon + $d['lon'],$wgsLat + $d['lat']);

    }

    //GCJ-02 to WGS-84
    public function gcj_decrypt($gcjLat, $gcjLon) {

        if ($this->outOfChina($gcjLat, $gcjLon)){

            return array('lat' => $gcjLat, 'lon' => $gcjLon);

        }
         
        $d = $this->delta($gcjLat, $gcjLon);

        return array('lat' => $gcjLat - $d['lat'], 'lon' => $gcjLon - $d['lon']);

    }

    //GCJ-02 to WGS-84 exactly
    public function gcj_decrypt_exact($gcjLat, $gcjLon) {

        $initDelta = 0.01;
        $threshold = 0.000000001;
        $dLat = $initDelta; $dLon = $initDelta;
        $mLat = $gcjLat - $dLat; $mLon = $gcjLon - $dLon;
        $pLat = $gcjLat + $dLat; $pLon = $gcjLon + $dLon;
        $wgsLat = 0; $wgsLon = 0; $i = 0;
        while (TRUE) {
            $wgsLat = ($mLat + $pLat) / 2;
            $wgsLon = ($mLon + $pLon) / 2;
            $tmp = $this->gcj_encrypt($wgsLat, $wgsLon);
            $dLat = $tmp['lat'] - $gcjLat;
            $dLon = $tmp['lon'] - $gcjLon;
            if ((abs($dLat) < $threshold) && (abs($dLon) < $threshold))
                break;
 
            if ($dLat > 0) $pLat = $wgsLat; else $mLat = $wgsLat;
            if ($dLon > 0) $pLon = $wgsLon; else $mLon = $wgsLon;
 
            if (++$i > 10000) break;
        }
        //console.log(i);
        return array('lat' => $wgsLat, 'lon'=> $wgsLon);
    }

    //GCJ-02 to BD-09
    public function bd_encrypt($gcjLat, $gcjLon) {
        if(empty($gcjLon) || empty($gcjLat)){
            return -1;
        }
        $x = $gcjLon; $y = $gcjLat;  
        $z = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * $this->x_pi);  
        $theta = atan2($y, $x) + 0.000003 * cos($x * $this->x_pi);  
        $bdLon = $z * cos($theta) + 0.0065;  
        $bdLat = $z * sin($theta) + 0.006; 
        return array('lat' => $bdLat,'lon' => $bdLon);
    }

    //GCJ-02 to BD-09
    public function bd_encrypt_v2($gcjLon,$gcjLat) {

        $x = $gcjLon; $y = $gcjLat;  
        $z = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * $this->x_pi);  
        $theta = atan2($y, $x) + 0.000003 * cos($x * $this->x_pi);  
        $bdLon = $z * cos($theta) + 0.0065;  
        $bdLat = $z * sin($theta) + 0.006; 
        return array($bdLon,$bdLat);
    }

    //GCJ-02 to BD-09
    public function bd_encrypt_v3($arr) {

        $gcjLon = $arr[0];
        $gcjLat = $arr[1];
        $x = $gcjLon; $y = $gcjLat;  
        $z = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * $this->x_pi);  
        $theta = atan2($y, $x) + 0.000003 * cos($x * $this->x_pi);  
        $bdLon = $z * cos($theta) + 0.0065;  
        $bdLat = $z * sin($theta) + 0.006; 
        return array($bdLon,$bdLat);
    }

    //WGS-84 to BD-09
    public function bd_encrypt_v6($arr) {

        $arr = $this->gcj_encrypt_v3($arr);
        return $this->bd_encrypt_v3($arr);
        
    }

    //BD-09 to GCJ-02
    public function bd_decrypt($bdLat, $bdLon){

        $x = $bdLon - 0.0065; $y = $bdLat - 0.006;  
        $z = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * $this->x_pi);  
        $theta = atan2($y, $x) - 0.000003 * cos($x * $this->x_pi);  
        $gcjLon = $z * cos($theta);  
        $gcjLat = $z * sin($theta);
        return array('lat' => $gcjLat, 'lon' => $gcjLon);

    }

    //BD-09 to GCJ-02
    public function bd_decrypt_v3($arr){
        $bdLat = $arr[1]; 
        $bdLon = $arr[0];
        $x = $bdLon - 0.0065; $y = $bdLat - 0.006;  
        $z = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * $this->x_pi);  
        $theta = atan2($y, $x) - 0.000003 * cos($x * $this->x_pi);  
        $gcjLon = $z * cos($theta);  
        $gcjLat = $z * sin($theta);
        return array( $gcjLon,$gcjLat);

    }

    //WGS-84 to Web mercator
    //$mercatorLat -> y $mercatorLon -> x
    public function mercator_encrypt($wgsLat, $wgsLon){

        $x = $wgsLon * 20037508.34 / 180.;
        $y = log(tan((90. + $wgsLat) * $this->PI / 360.)) / ($this->PI / 180.);
        $y = $y * 20037508.34 / 180.;
        return array('lat' => $y, 'lon' => $x);
        /*
        if ((abs($wgsLon) > 180 || abs($wgsLat) > 90))
            return NULL;
        $x = 6378137.0 * $wgsLon * 0.017453292519943295;
        $a = $wgsLat * 0.017453292519943295;
        $y = 3189068.5 * log((1.0 + sin($a)) / (1.0 - sin($a)));
        return array('lat' => $y, 'lon' => $x);
        //
		*/
    }

    // Web mercator to WGS-84
    // $mercatorLat -> y $mercatorLon -> x
    public function mercator_decrypt($mercatorLat, $mercatorLon){

        $x = $mercatorLon / 20037508.34 * 180.;
        $y = $mercatorLat / 20037508.34 * 180.;
        $y = 180 / $this->PI * (2 * atan(exp($y * $this->PI / 180.)) - $this->PI / 2);
        return array('lat' => $y, 'lon' => $x);
        /*
        if (abs($mercatorLon) < 180 && abs($mercatorLat) < 90)
            return NULL;
        if ((abs($mercatorLon) > 20037508.3427892) || (abs($mercatorLat) > 20037508.3427892))
            return NULL;
        $a = $mercatorLon / 6378137.0 * 57.295779513082323;
        $x = $a - (floor((($a + 180.0) / 360.0)) * 360.0);
        $y = (1.5707963267948966 - (2.0 * atan(exp((-1.0 * $mercatorLat) / 6378137.0)))) * 57.295779513082323;
        return array('lat' => $y, 'lon' => $x);
        //
		*/
    }

    // two point's distance
    public function distance($latA, $lonA, $latB, $lonB){
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

    // two point's distance
    public function distance_v2($lonA,$latA, $lonB, $latB){
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

    // two point's distance
    public function distance_v3($point1,$point2){

        $lonA = $point1[0];
        $latA = $point1[1];
        $lonB = $point2[0];
        $latB = $point2[1];

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


 
    private function delta($lat, $lon){
        // Krasovsky 1940
        //
        // a = 6378245.0, 1/f = 298.3
        // b = a * (1 - f)
        // ee = (a^2 - b^2) / a^2;
        $a = 6378245.0;//  a: 卫星椭球坐标投影到平面地图坐标系的投影因子。
        $ee = 0.00669342162296594323;//  ee: 椭球的偏心率。
        $dLat = $this->transformLat($lon - 105.0, $lat - 35.0);
        $dLon = $this->transformLon($lon - 105.0, $lat - 35.0);
        $radLat = $lat / 180.0 * $this->PI;
        $magic = sin($radLat);
        $magic = 1 - $ee * $magic * $magic;
        $sqrtMagic = sqrt($magic);
        $dLat = ($dLat * 180.0) / (($a * (1 - $ee)) / ($magic * $sqrtMagic) * $this->PI);
        $dLon = ($dLon * 180.0) / ($a / $sqrtMagic * cos($radLat) * $this->PI);
        return array('lat' => $dLat, 'lon' => $dLon);
    }
 
    private function outOfChina($lat, $lon){
        if ($lon < 72.004 || $lon > 137.8347)
            return TRUE;
        if ($lat < 0.8293 || $lat > 55.8271)
            return TRUE;
        return FALSE;
    }
 
    private function transformLat($x, $y) {
        $ret = -100.0 + 2.0 * $x + 3.0 * $y + 0.2 * $y * $y + 0.1 * $x * $y + 0.2 * sqrt(abs($x));
        $ret += (20.0 * sin(6.0 * $x * $this->PI) + 20.0 * sin(2.0 * $x * $this->PI)) * 2.0 / 3.0;
        $ret += (20.0 * sin($y * $this->PI) + 40.0 * sin($y / 3.0 * $this->PI)) * 2.0 / 3.0;
        $ret += (160.0 * sin($y / 12.0 * $this->PI) + 320 * sin($y * $this->PI / 30.0)) * 2.0 / 3.0;
        return $ret;
    }
 
    private function transformLon($x, $y) {
        $ret = 300.0 + $x + 2.0 * $y + 0.1 * $x * $x + 0.1 * $x * $y + 0.1 * sqrt(abs($x));
        $ret += (20.0 * sin(6.0 * $x * $this->PI) + 20.0 * sin(2.0 * $x * $this->PI)) * 2.0 / 3.0;
        $ret += (20.0 * sin($x * $this->PI) + 40.0 * sin($x / 3.0 * $this->PI)) * 2.0 / 3.0;
        $ret += (150.0 * sin($x / 12.0 * $this->PI) + 300.0 * sin($x / 30.0 * $this->PI)) * 2.0 / 3.0;
        return $ret;
    }

    public function version(){
    	return 2;
    }

    /*
        name 是否在区域中
        desc pt[lng,lat] 经纬度
        desc poly[][lng,lat] 经纬度
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

   /*
      name 是否上下左右4个点有一个点在区域中
   */
   public function isInsidePolygonExt($pt, $poly,$latLngNumber = 0.0009){
        
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


        $pt2a_distance = $this->getDistance($pt[0],$pt[1],$line[0][0],$line[0][1]);
        
        $pt2b_distance = $this->getDistance($pt[0],$pt[1],$line[1][0],$line[1][1]);

        $a2b_distance  = $this->getDistance($line[0][0],$line[0][1],$line[1][0],$line[1][1]);
        

        $result_a = pow($pt2a_distance, 2) + pow($a2b_distance,2) > pow($pt2b_distance,2);
        $result_b = pow($pt2b_distance, 2) + pow($a2b_distance,2) > pow($pt2a_distance,2);
        
        if($result_a && $result_b){

            $height = $this->triangleHeigth([$pt2a_distance,$pt2b_distance,$a2b_distance]);
            return $height;
        }else{
            return min($pt2a_distance,$pt2b_distance);
        }


   }

   public function triangleHeigth($long_arr,$width_index = 2){

        $long = $long_arr[0] + $long_arr[1] + $long_arr[2];
        $area = sqrt(($long/2-$long_arr[0]) * ($long/2-$long_arr[1]) * ($long/2-$long_arr[2]) * $long/2);
        $height = $area * 2 / $long_arr[$width_index];

        return $height;
   }

   //区域数组json原生转高德
   public function polygonJson2gcj($json){
      
      $result = [];

      $arr = json_decode($json,true);
      if(!empty($arr)){
          foreach ($arr as $key => $value) {
              # code...
            $temp = $this->gcj_encrypt_v2($value[0],$value[1]);
            $result[] = [$temp[0],$temp[1]];
          }
      }

      return ($result);
   }

   /**
     * 得到两点间距离
     * @param double $lng1
     * @param double $lat1
     * @param double $lng2
     * @param double $lat2
     * @return float|int
     */
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

    /**
     * 得到两点间距离
     * @param double $lng1
     * @param double $lat1
     * @param double $lng2
     * @param double $lat2
     * @return float|int
     */
    public function getDistanceV3($lnglat1, $lnglat2){    //将角度转为狐度
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
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
        return $s;
    }

    public function GetAmapAddress($lng,$lat,$default=''){
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
    }
}