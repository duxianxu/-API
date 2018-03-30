<?php
/**
 * Created by PhpStorm.
 * User: CHI
 * Date: 2017/7/21
 * Time: 16:04
 */
namespace Api\Controller;

use Think\Controller;

class MaOperationController extends Controller {

    private $url ='http://zykuaiche.com.cn:81/g/service';
    private $url_xiaoma='http://bike.zykuaiche.cn:82/simulate';//小马盒子
    private $url_xm1='http://yd.zykuaiche.com:81/s/service';//xm1盒子
    private $url_xiaoan='http://wg.baojia.com/simulate/service'; //小安盒子
    //http://wg.baojia.com/simulate/service      --小安新网关
    //http://123.57.173.14:8107/simulate/service --小安预上线
    //http://47.95.32.191:8107/simulate/service  --小安线上
    private $key = '987aa22ae48d48908edafda758ae82a8';
    private $box_config = 'mysqli://api-baojia:CSDV4smCSztRcvVb@10.1.11.14:3306/baojia_box';
    private $baojia_config = 'mysqli://api-baojia:CSDV4smCSztRcvVb@10.1.11.2:3306/baojia';
    protected $device_type = 0;//16 XM1盒子 18 小安盒子
    private $PI = 3.14159265358979324;
    private $low_power_min=20;
    private $low_power_max=30;//缺电的定义从35%-20%改为30%-20%
    private $diff_days=4;

    public function index()
    {
        //$result=$this->getXiaomiPosition("0865067026419872");
        //echo "<pre>";
        //print_r($result);die;
        //$gps=D('Gps');
        //$this->assign('distance',round($gps->distance(39.959569,116.441855,39.957521,116.434532),2));
        $this->display('index');
    }

    public function xmtest()
    {
        //禁行区
        /*$Forbid = new \Api\Logic\Forbid();
        $ForbidList = $Forbid->ForbidList("315077");
        echo "<pre>";
        print_r($ForbidList);die;*/
        $this->display('xmtest');
    }
    //获取公司列表
    public function GetCorporations(){
        $strSql = "select DISTINCT cor.id,cor.name 
            from baojia_mebike.repair_member a
            LEFT JOIN corporation cor on cor.id=a.corporation_id
            where cor.car_type=2 and a.status=1 AND ISNULL(cor.name)=FALSE 
            AND cor.id NOT IN(2830,2742,2521,2951)";
        $corporations = M()->query($strSql);
        if($corporations){
            $this->ajaxReturn(["code" => 1, "message" => "查询成功", "corporations" => $corporations], 'json');
        }else{
            $this->ajaxReturn(["code" => 0, "message" => "查询失败"], 'json');
        }
    }
    //发送短信验证码
    public function SendSMSCode()
    {
        $corporation_id = $_POST['corporation_id'];
        $mobile = $_POST['mobile'];
        if ($mobile&&$corporation_id){
            $user = M('baojia_mebike.repair_member')->alias('a')
                ->field('a.id,a.user_name,a.status,a.yx_status,b.mobile')
                ->join('ucenter_member b on a.user_id = b.uid', 'left')
                ->where("b.mobile={$mobile} and a.corporation_id={$corporation_id}")
                ->find();
            if ($user) {
                if ($user['status'] == '1') {
                    $code = $this->CreateSMSCode();
                    $url = 'http://sms.baojia.com/sms/send?version=1.0&token=123456&messages=[{"taskId":2006,"templateId":1,"mobile":' . $mobile . ',"argument":"' . $code . '（小蜜运维手机验证码），如非本人操作，请忽略本短信","useTemplate":false}]';
                    $output = $this->curl_get($url);
                    $outputArr = json_decode($output, true);
                    if ($outputArr['code'] == '2') {
                        $this->ajaxReturn(["code" => 1, "message" => "短信发送成功"], 'json');
                    } else {
                        $this->ajaxReturn(["code" => -2, "message" => "短信发送失败"], 'json');
                    }
                } else {
                    $this->ajaxReturn(["code" => -1, "message" => "权限已关闭"], 'json');
                }
            } else {
                $this->ajaxReturn(["code" => 0, "message" => "账号不存在"], 'json');
            }
        } else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //登录
    public function Login($test=0){
        $corporation_id = $_POST['corporation_id'];
        $mobile = $_POST['mobile'];
        $code = $_POST['code'];
        if($corporation_id&&$mobile&&$code) {
            $nowTimeStr = date('Y-m-d H:i:s');
            $user = M('baojia_mebike.repair_member')->alias('a')
                ->field("a.id,a.user_id,a.user_name,a.job_type,CASE WHEN a.job_type=1 THEN '全职'  ELSE '兼职' END job_type_text,a.status,CASE WHEN a.manager_position_id=0 THEN '员工' ELSE '员工' END manager_position,b.mobile,CASE WHEN a.role_type=1 THEN '运维' WHEN a.role_type=2 THEN '调度' WHEN a.role_type=3 THEN '整备' WHEN a.role_type=4 THEN '库管' ELSE '运维' END role_type,cor.city_id,cor.name corporation_name,a.corporation_id")
                ->join('ucenter_member b on a.user_id = b.uid', 'left')
                ->join('corporation cor on cor.id=a.corporation_id','left')
                ->where("b.mobile={$mobile} AND cor.id={$corporation_id}")
                ->find();
            if ($user) {
                if ($user&&$user['status'] == '1') {
                    $log = M('baojia_oms.sms_log')
                        ->where("mobile={$mobile}")
                        ->field("message,expiration_time")
                        ->order('expiration_time desc')
                        ->limit(1)
                        ->select();
                    if($test){
                        print_r($log);
                        echo $log[0]['message'];
                        echo strpos($log[0]['message']);
                    }
                    if ($log&&$log[0]['message']&&strpos($log[0]['message'],$code) !== false) {
                        $smsCodeTimeStr = $log[0]['expiration_time'];
                        $flag = $this->CheckTime($smsCodeTimeStr, $nowTimeStr);
                        if (!$flag) {
                            $this->ajaxReturn(["code" => -3, "message" => "验证码过期，请刷新后重新获取"], 'json');
                        } else {
                            $user["manual_url"]="http://xiaomi.baojia.com/Public/index.html";
                            \Think\Log::write("登录，参数：" . json_encode($_POST)."，结果：".json_encode($user), "INFO");
                            $this->ajaxReturn(["code" => 1, "message" => "登录成功", "user" => $user], 'json');
                        }
                    }else {
                        $this->ajaxReturn(["code" => -2, "message" => "验证码错误，请重新输入"], 'json');
                    }
                }else {
                    $this->ajaxReturn(["code" => -1, "message" => "权限已关闭"], 'json');
                }
            } else {
                $this->ajaxReturn(["code" => 0, "message" => "账号不存在"], 'json');
            }
        }else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //获取用户信息
    public function GetUserInfo(){
        $corporation_id = $_POST['corporation_id'];
        $user_id = $_POST['user_id'];
        if($user_id&$corporation_id) {
            $user = M('baojia_mebike.repair_member')->alias('a')
                ->field("a.id,a.user_id,a.user_name,a.job_type,CASE WHEN a.job_type=1 THEN '全职'  ELSE '兼职' END job_type_text,a.status,CASE WHEN a.manager_position_id=0 THEN '员工' ELSE '员工' END manager_position,b.mobile,CASE WHEN a.role_type=1 THEN '运维' WHEN a.role_type=2 THEN '调度' WHEN a.role_type=3 THEN '整备' WHEN a.role_type=4 THEN '库管' ELSE '运维' END role_type,cor.city_id,cor.name corporation_name,a.corporation_id")
                ->join('ucenter_member b on a.user_id = b.uid', 'left')
                ->join('corporation cor on cor.id=a.corporation_id','left')
                ->where("b.uid={$user_id} AND cor.id={$corporation_id}")
                ->find();
            if ($user) {
                $user["manual_url"]="http://xiaomi.baojia.com/Public/index.html";
                $this->ajaxReturn(["code" => 1, "message" => "加载数据成功", "user" => $user], 'json');

            } else {
                $this->ajaxReturn(["code" => 0, "message" => "账号不存在"], 'json');
            }
        }else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    public function GetUserInfoByMobile($mobile=""){
        $user = M('baojia_mebike.repair_member')->alias('a')
            ->field("a.id,a.user_id,a.user_name,a.job_type,CASE WHEN a.job_type=1 THEN '全职'  ELSE '兼职' END job_type_text,a.status,CASE WHEN a.manager_position_id=0 THEN '员工' ELSE '员工' END manager_position,b.mobile,CASE WHEN a.role_type=1 THEN '运维' WHEN a.role_type=2 THEN '调度' WHEN a.role_type=3 THEN '整备' WHEN a.role_type=4 THEN '库管' ELSE '运维' END role_type,cor.city_id,cor.name corporation_name,a.corporation_id")
            ->join('ucenter_member b on a.user_id = b.uid', 'left')
            ->join('corporation cor on cor.id=a.corporation_id','left')
            ->where("b.mobile={$mobile} and cor.car_type=2 and a.status=1 AND cor.id<>2878")
            ->find();
        if ($user) {
            $user["manual_url"]="http://xiaomi.baojia.com/Public/index.html";
            $this->ajaxReturn(["code" => 1, "message" => "加载数据成功", "user" => $user], 'json');

        } else {
            $this->ajaxReturn(["code" => 0, "message" => "账号不存在"], 'json');
        }
    }
    //查询车辆品牌
    public function GetBrands($corporation_id=""){
        if (!empty($corporation_id)) {
            $strSql = "select cm.id brand_id,cm.name brand_name,CONCAT('http://pic.baojia.com/s/',cip.url) pic_url,cor.parent_id
                from rent_content rent
                LEFT JOIN car_item_verify civ on civ.car_item_id=rent.car_item_id 
                LEFT JOIN corporation cor on cor.id=rent.corporation_id
                LEFT JOIN car_info cn on cn.id=rent.car_info_id
                LEFT JOIN car_item ci on ci.id = rent.car_item_id
                LEFT JOIN car_model cm ON cm.id = cn.model_id
                LEFT JOIN car_item_color cic on cic.id = ci.color
                left join (select * from car_info_picture where type=0 and status = 2) cip on rent.car_info_id=cip.car_info_id and cip.car_color_id=ci.color 
                where civ.plate_no like 'DD%' and rent.sort_id=112 and rent.status=2 and rent.sell_status=1 and rent.car_info_id<>30150 AND cor.parent_id={$corporation_id}
                and cip.url is not NULL
                GROUP BY cm.id,cm.name";
            $brands = M()->query($strSql);
            $this->ajaxReturn(["code" => 1, "message" => "查询成功","brands" =>$brands], 'json');
        }
        else{
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //查询所有小蜜车辆
    public function LoadAllXiaomi($test=0)
    {
        $time_start = $this->microtime_float();
        $user_id = $_POST['user_id'];
        $lngX = $_POST['lngX'];
        $latY = $_POST['latY'];
        $city = $_POST['city'];
        $brand_id = $_POST['brand_id'];
        $corporation_id= $_POST['corporation_id'];
        $rent_status = $_POST['rent_status'];
        \Think\Log::write("查询所有小蜜车辆，参数：" . json_encode($_POST), "INFO");
        if (empty($user_id)||empty($corporation_id)) {
            $this->ajaxReturn(["code" => -100, "message" => "未登录"], 'json');
        }
        if (empty($lngX) || empty($latY) || empty($city)) {
            $this->ajaxReturn(["code" => -100, "message" => "未获取定位"], 'json');
        }
        if (!empty($user_id)&&!empty($city)) {
            if (strpos($city, '市')) {
                $city = explode('市', $city)[0];
            }
            if (!empty($rent_status)&&$rent_status=="3"&&strlen($rent_status)<3&&($corporation_id=="2118"||$corporation_id=="2523")) {
                //除北京(2118)、天津(2523)，其他城市查询两日无单 20171011
                $this->ajaxReturn(["code" => 0, "message" => "暂无车辆", "data" => null], 'json');
            }
            $user_type=$this->getUserType($user_id,$corporation_id);
            if (!empty($rent_status)&&$rent_status=="11"&&$user_type["job_type"] == 2) {
                //兼职不查无电 20171018
                $this->ajaxReturn(["code" => 0, "message" => "暂无车辆", "data" => null], 'json');
            }
            if ($city== $user_type["city"]) {
                $where = "";
                $subWhere = "";
                $allWhere = " and rn.sell_status=1 and rca.hour_count<1 AND ms.no_mileage_num<>3 and a.residual_battery>$this->low_power_min and a.residual_battery<=$this->low_power_max ";
                if (strpos($rent_status, "3") !== false&&$city!="北京"&&$city!="天津") {
                    $strSqlOrder = "SELECT DISTINCT rent_content_id FROM trade_order 
                        WHERE create_time > (UNIX_TIMESTAMP(NOW()) - 86400 * 2)
                        and rent_type = 3 AND rent_content_id in(SELECT rn.id
                        FROM rent_content rn
                        LEFT JOIN car_item_verify civ on rn.car_item_id=civ.car_item_id
                        LEFT JOIN corporation cor on cor.id=rn.corporation_id and cor.city_id=rn.city_id
                        WHERE civ.plate_no like 'DD%' AND rn.sort_id =112 
                        and rn.status=2 and rn.sell_status=1 AND cor.parent_id={$corporation_id})";
                    $order_content = M('', null, $this->baojia_config)->query($strSqlOrder);
                    $order_content = array_column($order_content, "rent_content_id");
                    $order_rents = implode(",", $order_content);
                }
                //疑失车辆不显示 20171023
                $strSql = "SELECT rn.car_info_id,rn.id rent_content_id,ms.no_mileage_num,rn.sort_id,IFNULL(ms.all_miles,-1) mile,
                    IFNULL(s.latitude,0) gis_lat,IFNULL(s.longitude,0) gis_lng,rca.hour_count,a.datetime,
                    rn.create_time,IFNULL(a.residual_battery,0) residual_battery,rn.car_item_id,civ.plate_no,
                    rn.corporation_id,rn.city_id,rn.update_time,rn.status,rn.sell_status,rsh.operate_status,cm.id model_id
                    FROM rent_content rn  
                    LEFT JOIN car_item_verify civ on rn.car_item_id=civ.car_item_id 
                    LEFT JOIN mileage_statistics ms ON ms.rent_content_id = rn.id 
                    LEFT JOIN rent_sku_hour rsh ON rn.id = rsh.rent_content_id 
                    LEFT JOIN car_info cn on cn.id=rn.car_info_id
                    LEFT JOIN car_model cm ON cm.id = cn.model_id
                    LEFT JOIN fed_gps_usercar c on c.cid=rn.car_item_id
                    left join fed_gps_status s on c.imei=s.imei  and c.devicetype  in(12,14,16,18)
                    left join fed_gps_additional a on c.imei=a.imei 
                    LEFT JOIN rent_content_avaiable rca on rca.rent_content_id=rn.id 
                    LEFT JOIN corporation cor on cor.id=rn.corporation_id and cor.city_id=rn.city_id
                    WHERE civ.plate_no like 'DD%' AND rn.sort_id =112 and rca.hour_count<1
                    and rn.status<>-2 AND rsh.operate_status not in(4,5,6) 
                    AND cor.parent_id={$corporation_id} ";
                if ($user_type["job_type"] == 1) {//全职
                    if (!empty($rent_status)) {
                        if (strpos($rent_status, "2") !== false) {
                            //离线下架 status=2 sell_status=-100 电量高于10%
                            if (empty($subWhere)) {
                                $subWhere .= " rn.sell_status=-100 and a.residual_battery>10 ";
                            } else {
                                $subWhere .= " or rn.sell_status=-100 and a.residual_battery>10 ";
                            }
                        }
                        if (strpos($rent_status, "-1") !== false) {
                            //无电离线 status=2 sell_status=-100 电量低于等于10%且离线
                            if (empty($subWhere)) {
                                $subWhere .= " rn.sell_status=-100 and IFNULL(a.residual_battery,0)<=10 ";
                            } else {
                                $subWhere .= " or rn.sell_status=-100 and IFNULL(a.residual_battery,0)<=10 ";
                            }
                        }
                        if (strpos($rent_status, "3") !== false&&$city!="北京"&&$city!="天津") {
                            //两日无单 status=2 sell_status=1 orde_count=0
                            if (empty($subWhere)) {
                                $subWhere .= " (rn.sell_status=1 and timestampdiff(SECOND,FROM_UNIXTIME(rn.create_time),now())>3600*24*4 and rn.id not in({$order_rents})) ";
                            } else {
                                $subWhere .= " or (rn.sell_status=1 and timestampdiff(SECOND,FROM_UNIXTIME(rn.create_time),now())>3600*24*4 and rn.id not in({$order_rents})) ";
                            }
                        }
                        if (strpos($rent_status, "4") !== false) {
                            //有单无程 status=2 sell_status=1 no_mileage_num=3
                            if (empty($subWhere)) {
                                //$subWhere .= " (rn.sell_status=1 and ms.all_miles BETWEEN 0 and 0.3) ";
                                $subWhere .= " (rn.sell_status=1 and ms.no_mileage_num=3) ";
                            } else {
                                $subWhere .= " or (rn.sell_status=1 and ms.no_mileage_num=3) ";
                            }
                        }
                        if (strpos($rent_status, "5") !== false) {//待小修 status=2 sell_status=1 operate_status=11
                            if (empty($subWhere)) {
                                $subWhere .= " (rn.sell_status=1 and rsh.operate_status=11) ";
                            } else {
                                $subWhere .= " or (rn.sell_status=1 and rsh.operate_status=11) ";
                            }
                        }
                        if (strpos($rent_status, "6") !== false) {//越界下架 sell_status=-7
                            if (empty($subWhere)) {
                                $subWhere .= " rn.sell_status=-7 ";
                            } else {
                                $subWhere .= " or rn.sell_status=-7 ";
                            }
                        }
                        if (strpos($rent_status, "7") !== false) {//待维修 sell_status=-8
                            if (empty($subWhere)) {
                                $subWhere .= " rn.sell_status=-8 ";
                            } else {
                                $subWhere .= " or rn.sell_status=-8 ";
                            }
                        }
                        if (strpos($rent_status, "8") !== false) {//待调动 sell_status=-13
                            if (empty($subWhere)) {
                                $subWhere .= " rn.sell_status=-13 ";
                            } else {
                                $subWhere .= " or rn.sell_status=-13 ";
                            }
                        }
                        if (strpos($rent_status, "10") !== false) {//馈电下架 status=2 sell_status=-1 sell_status=-5
                            if (empty($subWhere)) {
                                $subWhere .= " ((rn.sell_status=-1 or rn.sell_status=-5) and a.residual_battery>0) ";
                            } else {
                                $subWhere .= " or ((rn.sell_status=-1 or rn.sell_status=-5) and a.residual_battery>0) ";
                            }
                        }
                        //全职人员全部开放缺电状态的车辆 20171013，下周一(1016)再改回隐藏状态
                        //if($city!="北京"){//北京以外的城市查缺电
                        if (strpos($rent_status, "9") !== false) {//缺电不包含出租中的车辆 status=2 sell_status=-1 residual_battery<$this->low_power_max
                            if (empty($subWhere)) {
                                //$subWhere .= " (rn.sell_status=1 and rca.hour_count<1 and rn.id in({$order_rents}) AND ms.no_mileage_num<>3 and a.residual_battery>$this->low_power_min and a.residual_battery<=$this->low_power_max) ";
                                $subWhere .= " (rn.sell_status=1 and rca.hour_count<1 AND ms.no_mileage_num<>3 and a.residual_battery>$this->low_power_min and a.residual_battery<=$this->low_power_max) ";
                            } else {
                                //$subWhere .= " or (rn.sell_status=1 and rca.hour_count<1 and rn.id in({$order_rents}) AND ms.no_mileage_num<>3 and a.residual_battery>$this->low_power_min and a.residual_battery<=$this->low_power_max) ";
                                $subWhere .= " or (rn.sell_status=1 and rca.hour_count<1 AND ms.no_mileage_num<>3 and a.residual_battery>$this->low_power_min and a.residual_battery<=$this->low_power_max) ";
                            }
                        }
                        //}
                        if (strpos($rent_status, "11") !== false) {//无电 status=2 sell_status=1 residual_battery=0
                            if (empty($subWhere)) {
                                $subWhere .= " ((rn.sell_status=-1 or rn.sell_status=-5) and IFNULL(a.residual_battery,0)=0) ";
                            } else {
                                $subWhere .= " or ((rn.sell_status=-1 or rn.sell_status=-5) and IFNULL(a.residual_battery,0)=0) ";
                            }
                        }
                        if (!empty($subWhere)) {
                            $where = " and rn.status=2 and(" . $subWhere . ")";
                        }
                    } else {
                        $where = $allWhere;
                    }
                    $strSql .= $where;
                    if (!empty($brand_id)) {
                        $strSql .= " and cm.id in({$brand_id}) ";
                    }
                    if ($test == 1) {
                        echo $strSql;
                    }
                    $rent_content = M('', null, $this->baojia_config)->query($strSql);
                    $count = 0;
                    $gps = D('Gps');
                    $hasOrder = M('', null, $this->baojia_config)->query("SELECT plate_no FROM baojia_mebike.have_order where status=0");
                    if (!empty($hasOrder)) {
                        $hasOrder=array_column($hasOrder,"plate_no");
                    }
                    if (!empty($rent_content)) {
                        foreach ($rent_content as $k => &$v) {
                            if (!empty($hasOrder)) {
                                if(in_array($v['plate_no'],$hasOrder,true)){
                                    unset($rent_content[$k]);
                                    continue;
                                }
                            }
                            $count++;
                            $gd = $gps->gcj_encrypt($v["gis_lat"], $v["gis_lng"]);
                            $v["gis_lat"] = $gd["lat"];
                            $v["gis_lng"] = $gd["lon"];
                            $bd = $gps->bd_encrypt($v['gis_lat'], $v['gis_lng']);
                            $v["bd_latitude"] = strval($bd["lat"]);
                            $v["bd_longitude"] = strval($bd["lon"]);
                            $no_order = false;
                            $not_new = false;
                            if ($order_content&&!in_array($v['rent_content_id'], $order_content)&&$city!="北京"&&$city!="天津") {
                                $no_order = true;
                            }
                            //创建时间大于预定天数
                            if ($this->diffBetweenTwoDays(time(), $v['create_time']) >= $this->diff_days && $v['status'] == 2 && $v['sell_status'] == 1) {
                                $not_new = true;
                            }
                            $v['not_new'] = $not_new;
                            if ($v['status'] == 2 && $v['sell_status'] == -100 && $v['residual_battery']>10) {
                                //离线下架 2  电量大于10%
                                $v["rent_status"] = 2;
                            }
                            if ($v['status'] == 2 && $v['sell_status'] == -100 && $v['residual_battery']<=10) {
                                //无电离线 -1 电量低于等于10%且离线
                                $v["rent_status"] = -1;
                                //} elseif ($v['status'] == 2 && $v['sell_status'] == 1 && ($v['mile'] >= 0 && $v['mile'] < 0.3)) {
                            } elseif ($v['status'] == 2 && $v['sell_status'] == 1 &&$v['no_mileage_num']==3) {
                                //故障 4 有单无程
                                $v["rent_status"] = 4;
                            } elseif ($v['status'] == 2 && $v['sell_status'] == 1 && $no_order && $not_new&&$city!="北京"&&$city!="天津") {
                                //两日无单 3
                                $v["rent_status"] = 3;
                            }
                            elseif ($v['status'] == 2 && $v['sell_status'] == -7) {
                                //越界下架 6
                                $v["rent_status"] = 6;
                            } elseif ($v['status'] == 2 && $v['sell_status'] == -8) {
                                //待维修 7
                                $v["rent_status"] = 7;
                                if($v["update_time"]&&((time()-$v["update_time"])/86400)<=2){
                                    $v["is_two_days"]=1;
                                }else{
                                    $v["is_two_days"]=0;
                                }
                            } elseif ($v['status'] == 2 && $v['sell_status'] == -13) {
                                //待调动 8
                                $v["rent_status"] = 8;
                            } elseif ($v['status'] == 2 && $v['sell_status'] == 1 && $v['operate_status'] == 11) {
                                //待小修 5 operate_status 11=待小修
                                $v["rent_status"] = 5;
                                //} elseif ($v['status'] == 2 && $v['sell_status'] == 1 && $v['hour_count'] < 1 && !$no_order && ($v['mile'] < 0 || $v['mile'] >= 0.3) && $v['residual_battery'] >= $this->low_power_min && $v['residual_battery'] < $this->low_power_max) {
                            }elseif ($v['status'] == 2 && (($v['sell_status'] == -1 || $v['sell_status'] == -5) && $v['residual_battery'] > 0)) {
                                //馈电下架 10
                                $v["rent_status"] = 10;
                            } elseif ($v['status'] == 2 && ($v['sell_status'] == 1 || $v['sell_status'] == -1 || $v['sell_status'] == -5) && $v['residual_battery'] == 0) {
                                //无电 11
                                $v["rent_status"] = 11;
                            }
                            //全职人员全部开放缺电状态的车辆 20171013，下周一(1016)再改回隐藏状态
                            //if($city!="北京"){//北京以外的城市查缺电
                            if ($v['status'] == 2 && $v['sell_status'] == 1 && $v['hour_count'] < 1&& $v['residual_battery'] >$this->low_power_min && $v['residual_battery']<=$this->low_power_max) {
                                //缺电 9
                                $v["rent_status"] = 9;
                            }
                            //}
                        }
                        $time_end = $this->microtime_float();
                        $second = round($time_end - $time_start, 2);
                        $rent_content=array_values($rent_content);
                        \Think\Log::write("全职查询所有小蜜车辆耗时".$second."秒，返回数据".$count."条", "INFO");
                        $this->ajaxReturn(["code" => 1, "message" => "加载数据成功", "count" => $count, "second" => $second, "data" => $rent_content], 'json');
                    } else {
                        $this->ajaxReturn(["code" => 0, "message" => "暂无车辆", "data" => null], 'json');
                    }
                } else {//兼职
                    if (!empty($rent_status)) {
                        if (strpos($rent_status, "10") !== false) {//馈电下架 status=2 sell_status=-1 sell_status=-5
                            if (empty($subWhere)) {
                                $subWhere .= " ((rn.sell_status=-1 or rn.sell_status=-5) and a.residual_battery>0) ";
                            } else {
                                $subWhere .= " or ((rn.sell_status=-1 or rn.sell_status=-5) and a.residual_battery>0) ";
                            }
                        }
                        //if($city!="北京"){//北京兼职查缺电 20171012
                        if (strpos($rent_status, "9") !== false) {//缺电 status=2 sell_status=-1 residual_battery<$this->low_power_max
                            if (empty($subWhere)) {
                                $subWhere .= " (rn.sell_status=1 and rca.hour_count<1 and a.residual_battery>$this->low_power_min and a.residual_battery<=$this->low_power_max) ";
                            } else {
                                $subWhere .= " or (rn.sell_status=1 and rca.hour_count<1 and a.residual_battery>$this->low_power_min and a.residual_battery<=$this->low_power_max) ";
                            }
                        }
                        //}
                        //兼职不查无电 20171018
                        /*if (strpos($rent_status, "11") !== false) {//无电 status=2 sell_status=1 residual_battery=0
                            if (empty($subWhere)) {
                                $subWhere .= " ((rn.sell_status=-1 or rn.sell_status=-5) and IFNULL(a.residual_battery,0)=0) ";
                            } else {
                                $subWhere .= " or ((rn.sell_status=-1 or rn.sell_status=-5) and IFNULL(a.residual_battery,0)=0) ";
                            }
                        }*/
                        //兼职开放待维修 20171020
                        if (strpos($rent_status, "7") !== false) {
                            //待维修 sell_status=-8
                            if (empty($subWhere)) {
                                $subWhere .= " rn.sell_status=-8 ";
                            } else {
                                $subWhere .= " or rn.sell_status=-8 ";
                            }
                        }
                        if (!empty($subWhere)) {
                            $where = " and rn.status=2 and(" . $subWhere . ")";
                        }
                    } else {
                        $where = $allWhere;
                    }
                    $strSql .= $where;
                    $strSql .= " and cm.id<>2054 ";//兼职不包含台铃车
                    if ($test == 1) {
                        echo $strSql;
                    }
                    $rent_content = M('', null, $this->baojia_config)->query($strSql);
                    $count = 0;
                    $gps = D('Gps');
                    $hasOrder = M('', null, $this->baojia_config)->query("SELECT plate_no FROM baojia_mebike.have_order where status=0");
                    if (!empty($hasOrder)) {
                        $hasOrder=array_column($hasOrder,"plate_no");
                    }
                    if (!empty($rent_content)) {
                        foreach ($rent_content as $k => &$v) {
                            if (!empty($hasOrder)) {
                                if(in_array($v['plate_no'],$hasOrder,true)){
                                    unset($rent_content[$k]);
                                    continue;
                                }
                            }
                            $count++;
                            $gd = $gps->gcj_encrypt($v["gis_lat"], $v["gis_lng"]);
                            $v["gis_lat"] = $gd["lat"];
                            $v["gis_lng"] = $gd["lon"];
                            $bd = $gps->bd_encrypt($v['gis_lat'], $v['gis_lng']);
                            $v["bd_latitude"] = strval($bd["lat"]);
                            $v["bd_longitude"] = strval($bd["lon"]);
                            //if($city!="北京"){//北京全职不查缺电   //北京兼职查缺电 20171012
                            if ($v['status'] == 2 && $v['sell_status'] == 1 && $v['hour_count'] < 1&& $v['residual_battery'] >$this->low_power_min && $v['residual_battery']<=$this->low_power_max) {
                                //缺电 9
                                $v["rent_status"] = 9;
                            }
                            //}
                            if ($v['status'] == 2 && (($v['sell_status'] == -1 || $v['sell_status'] == -5) && $v['residual_battery'] > 0)) {
                                //馈电下架 10
                                $v["rent_status"] = 10;
                            }

                            //兼职不查无电 20171018
                            /*
                            if ($v['status'] == 2 && ($v['sell_status'] == 1 || $v['sell_status'] == -1 || $v['sell_status'] == -5) && $v['residual_battery'] == 0) {
                                //无电 11
                                $v["rent_status"] = 11;
                            }*/

                            //兼职开放待维修 20171020
                            if ($v['status'] == 2 && $v['sell_status'] == -8) {
                                //待维修 7
                                $v["rent_status"] = 7;
                                if($v["update_time"]&&((time()-$v["update_time"])/86400)<=2){
                                    $v["is_two_days"]=1;
                                }else{
                                    $v["is_two_days"]=0;
                                }
                            }
                        }
                        $time_end = $this->microtime_float();
                        $second = round($time_end - $time_start, 2);
                        $rent_content=array_values($rent_content);
                        \Think\Log::write("兼职查询所有小蜜车辆耗时".$second."秒，返回数据".$count."条", "INFO");
                        $this->ajaxReturn(["code" => 1, "message" => "加载数据成功", "count" => $count, "second" => $second, "data" => $rent_content], 'json');
                    } else {
                        $this->ajaxReturn(["code" => 0, "message" => "暂无车辆", "data" => null], 'json');
                    }
                }
            } else {
                $this->ajaxReturn(["code" => -1, "message" => "位置不在所属城市", "data" => null], 'json');
            }
        } else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //小蜜运维车辆统计
    public function Statistics($test=0)
    {
        $user_id = $_POST['user_id'];
        $city = $_POST['city'];
        $version = $_POST['version'];
        $lngX = $_POST['lngX'];
        $latY = $_POST['latY'];
        if (empty($lngX) || empty($latY) || empty($city)) {
            \Think\Log::write("小蜜运维车辆统计，未获取定位，参数：" . json_encode($_POST), "INFO");
            //$this->ajaxReturn(["code" => -100, "message" => "未获取定位"], 'json');
        }
        $time_start = $this->microtime_float();
        if (!empty($user_id) && !empty($city)) {
            if (empty($city)) {
                $city = $this->GetAmapCity($lngX, $latY);
            } else {
                if (strpos($city, '市')) {
                    $city = explode('市', $city)[0];
                }
            }
            $cityArray = M('',null,$this->baojia_config)->query("select id,name from area_city where status=1 and name='" . $city . "'");
            $city_id = $cityArray[0]["id"];
            $user_city_id = M('corporation',null,$this->baojia_config)->alias('a')
                ->field("city_id,b.job_type")
                ->join('baojia_mebike.repair_member b on b.corporation_id=a.id', 'left')
                ->where("b.user_id={$user_id} and a.car_type=2 and a.city_id={$city_id}")
                ->find();
            if ($city_id == $user_city_id["city_id"]) {
                $order_content =[];
                if($city!="北京"&&$city!="天津") {
                    //除北京、天津，其他城市查询两日无单 20171011
                    $strSql = "SELECT rn.car_info_id,rn.id rent_content_id,rn.sort_id,ms.no_mileage_num,rn.car_item_id,rn.create_time,rca.hour_count,
                    civ.plate_no,rn.corporation_id,rn.city_id,rn.update_time,rn.status,rn.sell_status,rsh.operate_status,IFNULL(a.residual_battery,0) residual_battery
                    FROM rent_content rn  
                    JOIN car_item_verify civ on rn.car_item_id=civ.car_item_id
                    LEFT JOIN mileage_statistics ms ON ms.rent_content_id = rn.id 
                    JOIN rent_sku_hour rsh ON rn.id = rsh.rent_content_id 
                    LEFT JOIN fed_gps_usercar c on c.cid=rn.car_item_id
                    left join fed_gps_status s on c.imei=s.imei  and c.devicetype  in(12,14,16,18)
                    left join baojia.fed_gps_additional a on c.imei=a.imei
                    LEFT JOIN car_info cn on cn.id=rn.car_info_id
                    LEFT JOIN car_model cm ON cm.id = cn.model_id
                    LEFT JOIN rent_content_avaiable rca on rca.rent_content_id=rn.id 
                    WHERE civ.plate_no like 'DD%' AND rn.sort_id =112 AND rn.city_id={$city_id} 
                    and rn.status=2 
                    and(
                    rn.sell_status=-100 
                    or (rn.sell_status=1 and IFNULL(a.residual_battery,0)<=35)
                    or (rn.sell_status=1 and ms.no_mileage_num=3)  
                    or (rn.sell_status=1 and rsh.operate_status=11)  
                    or rn.sell_status=-7  
                    or rn.sell_status=-8  
                    or rn.sell_status=-13  
                    or (rn.sell_status=-1 or rn.sell_status=-5)
                    or rn.sell_status=1
                    )";
                    if ($user_city_id["job_type"] == 2) {//兼职不包含台铃车
                        $strSql .= " and cm.id<>2054 ";
                    }
                    $rent_content =M('',null,$this->baojia_config)->query($strSql);
                    $strSqlOrder="SELECT DISTINCT rent_content_id FROM trade_order WHERE rent_type = 3 AND create_time>(UNIX_TIMESTAMP(NOW())-86400*2)
                    and rent_content_id in(SELECT rn.id FROM rent_content rn LEFT JOIN car_item_verify civ on rn.car_item_id=civ.car_item_id
                    WHERE civ.plate_no like 'DD%' AND rn.sort_id =112 and rn.status<>-2 AND rn.city_id ={$city_id})";
                    $order_content=M('', null, $this->baojia_config)->query($strSqlOrder);
                    $order_content=array_column($order_content,"rent_content_id");
                }else{
                    $strSql = "SELECT rn.car_info_id,rn.id rent_content_id,rn.sort_id,ms.no_mileage_num,rn.car_item_id,rn.create_time,rca.hour_count,
                    civ.plate_no,rn.corporation_id,rn.city_id,rn.update_time,rn.status,rn.sell_status,rsh.operate_status,IFNULL(a.residual_battery,0) residual_battery
                    FROM rent_content rn  
                    JOIN car_item_verify civ on rn.car_item_id=civ.car_item_id
                    LEFT JOIN mileage_statistics ms ON ms.rent_content_id = rn.id 
                    JOIN rent_sku_hour rsh ON rn.id = rsh.rent_content_id 
                    LEFT JOIN fed_gps_usercar c on c.cid=rn.car_item_id
                    left join fed_gps_status s on c.imei=s.imei  and c.devicetype  in(12,14,16,18)
                    left join baojia.fed_gps_additional a on c.imei=a.imei
                    LEFT JOIN car_info cn on cn.id=rn.car_info_id
                    LEFT JOIN car_model cm ON cm.id = cn.model_id
                    LEFT JOIN rent_content_avaiable rca on rca.rent_content_id=rn.id 
                    WHERE civ.plate_no like 'DD%' AND rn.sort_id =112 AND rn.city_id={$city_id} 
                    and rn.status=2 
                    and(
                    rn.sell_status=-100 
                    or (rn.sell_status=1 and IFNULL(a.residual_battery,0)<=35)
                    or (rn.sell_status=1 and ms.no_mileage_num=3)  
                    or (rn.sell_status=1 and rsh.operate_status=11)  
                    or rn.sell_status=-7  
                    or rn.sell_status=-8  
                    or rn.sell_status=-13  
                    or (rn.sell_status=-1 or rn.sell_status=-5)
                    )";
                    if ($user_city_id["job_type"] == 2) {//兼职不包含台铃车
                        $strSql .= " and cm.id<>2054 ";
                    }
                    $rent_content =M('',null,$this->baojia_config)->query($strSql);
                }
                if ($test == 1) {
                    echo $strSql;
                    echo $strSqlOrder;
                }
                $result["power_shortage"] = 0;  //馈电下架
                $result["offline"] = 0;         //离线下架
                $result["all_offline"] = 0;     //离线下架
                $result["no_order"] = 0;        //两日无单
                $result["no_distance"] = 0;     //有单无程
                $result["to_repair"] = 0;       //待小修
                $result["total_repair"] = 0;    //需运维总数
                $result["out_bounds"] = 0;      //越界下架
                $result["maintain"] = 0;        //待维修
                $result["transfer"] = 0;        //待调动
                $result["total_dispatch"] = 0;  //需调度总数
                $result["offline_no_battery"] = 0;  //无电离线 电量低于等于10%且离线
                if ($rent_content) {
                    $result["battery30"] = 0;       //缺电 待租且电量大于20%小于等于35%
                    $result["battery0"] = 0;        //无电 馈电下架且电量为0
                    foreach ($rent_content as $k => $v) {
                        if($city!="北京"&&$city!="天津") {
                            if ($order_content&&!in_array($v['rent_content_id'], $order_content)) {//两日无单
                                //创建时间大于预定天数
                                if ($this->diffBetweenTwoDays(time(), $v['create_time']) >= $this->diff_days && $v['status'] == 2 && $v['sell_status'] == 1) {
                                    $result["no_order"]++;
                                }
                            }
                        }else{
                            $result["no_order"]=0;
                        }
                        if ($v['status'] == 2 && $v['sell_status'] == 1&&$v['hour_count']<1&&$v["residual_battery"]>$this->low_power_min &&$v["residual_battery"]<=$this->low_power_max) {
                            //缺电 待租且电量大于20%小于等于35%
                            $result["battery30"]++;
                        }
                        if ($v['status'] == 2 && $v['sell_status'] == -100) {
                            $result["all_offline"]++;
                        }
                        if ($v['status'] == 2 && $v['sell_status'] == -100&&$v["residual_battery"]>10) {
                            $result["offline"]++;
                        }
                        if ($v['status'] == 2 && $v['sell_status'] == -100&&$v["residual_battery"]<=10) {
                            //无电离线 电量低于等于10%且离线
                            $result["offline_no_battery"]++;
                        }
                        if ($v['status'] == 2 && $v['sell_status'] == -8) {
                            $result["maintain"]++;
                        }
                        if ($v['status'] == 2 && $v['sell_status'] == -13) {
                            $result["transfer"]++;
                        }
                        if ($v['status'] == 2 && $v['sell_status'] == -7) {
                            $result["out_bounds"]++;
                        }
                        if ($v['status'] == 2&&(($v['sell_status']==-1||$v['sell_status']==-5)&&$v["residual_battery"]>0)) {
                            $result["power_shortage"]++;
                        }
                        if ($v['status'] == 2 && $v['sell_status'] == 1 && $v['operate_status'] == 11) {
                            $result["to_repair"]++;
                        }
                        //if ($v['status'] == 2 && $v['sell_status'] == 1&&($v['mile']>=0&&$v['mile']<0.3)) {
                        if ($v['status'] == 2 && $v['sell_status'] == 1&&$v['no_mileage_num']==3) {
                            $result["no_distance"]++;
                        }
                        if ($v['status'] == 2 &&($v['sell_status']==-1||$v['sell_status']==-5)&&$v["residual_battery"]==0) {
                            //无电 馈电下架且电量为0
                            $result["battery0"]++;
                        }
                    }
                    if ($user_city_id["job_type"] == 2) {
                        //兼职不查无电 20171018
                        $result["battery0"]=0;
                    }
                    //全职人员全部开放缺电状态的车辆 20171013，下周一(1016)再改回隐藏状态
                    /*if($city=="北京"&&$user_city_id["job_type"] == 1){//北京全职不查缺电
                        $result["battery30"] = 0;
                    }*/
                    $result["total_repair"] = $result["power_shortage"]+$result["battery30"]+$result["battery0"] + $result["offline"] + $result["no_order"] + $result["no_distance"] + $result["to_repair"]+$result["offline_no_battery"];
                    $result["total_dispatch"] = $result["out_bounds"] + $result["maintain"] + $result["transfer"];
                    $time_end = $this->microtime_float();
                    $second = round($time_end - $time_start,2);
                    \Think\Log::write("小蜜运维车辆统计，参数：" . json_encode($_POST)."，耗时：".$second."，结果：".json_encode($result), "INFO");
                    $this->ajaxReturn(["code" => 1,"job_type"=>$user_city_id["job_type"],"message" => "加载数据成功","second"=>$second, "data" => $result], 'json');
                } else {
                    $this->ajaxReturn(["code" => 0, "message" => "暂无车辆", "data" => null], 'json');
                }
            }else {
                $this->ajaxReturn(["code" => -1, "message" => "位置不在所属城市", "data" => null], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //查询小蜜车辆编码
    public function QueryCoding(){
        $user_id = $_POST['user_id'];
        $query_type = $_POST['query_type'];
        $code = $_POST['code'];
        if(!empty($user_id)&&!empty($query_type)&&!empty($code)) {
            //1 车牌号 2 imei 3 流量卡号 4 车架号 5 政府牌照
            $sql="select civ.plate_no,civ.vin,cid.imei,cid.mobile from car_item_verify civ LEFT JOIN car_item_device cid on cid.car_item_id=civ.car_item_id";
            if($query_type==5){
                $result[0]["plate_no"]="";
                $result[0]["vin"]="";
                $result[0]["imei"]="";
                $result[0]["mobile"]="";
            }else {
                if ($query_type == 1) {
                    $where = " where civ.plate_no='{$code}' ";
                }
                if ($query_type == 2) {
                    $where = " where cid.imei='{$code}' ";
                }
                if ($query_type == 3) {
                    $where = " where cid.mobile='{$code}' ";
                }
                if ($query_type == 4) {
                    $where = " where civ.vin='{$code}' ";
                }
                $result = M('',null,$this->baojia_config)->query($sql . $where);
                if (!$result) {
                    $result[0]["plate_no"] = "";
                    $result[0]["vin"] = "";
                    $result[0]["imei"] = "";
                    $result[0]["mobile"] = "";
                }
            }
            $result[0]["government_licence"] = "";
            $this->ajaxReturn(["code" => 1, "message" => "查询成功", "data" => $result[0]], 'json');
        }else{
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //操作 4=鸣笛 5=设防 6=撤防 7=启动 30=唤醒 34=开舱锁 35=换电设防
    public function RepairOperation($test=0)
    {
        $time_start = $this->microtime_float();
        $user_id = $_POST['user_id'];
        $rent_id = $_POST['rent_content_id'];
        $operation_type = $_POST['operation_type'];
        if (!empty($user_id) && !empty($rent_id) && !empty($operation_type)) {
            if (!$this->carAuth($user_id, $rent_id)) {
                $this->ajaxReturn(["code" => -2, "message" => "你没有管理这辆车的权限"], 'json');
            }
            $rent_info = M("rent_content",null,$this->baojia_config)->where(["id" => $rent_id])->find();
            if (!$rent_info) {
                $this->ajaxReturn(["code" => -3, "message" => "车辆不存在"], 'json');
            }
            $plate_no = $this->getPlateNo($rent_info["car_item_id"]);
            $imei = $this->getImei($rent_info["car_item_id"]);
            $full_imei = $imei;
            $imei = ltrim($imei, "0");
            $api_result = true;
            $msg="操作失败";
            if($operation_type == 4){//鸣笛
                $api_result_json = $this->accoff($imei);
            }elseif ($operation_type == 5) {//设防
                $api_result_json = $this->lock($imei);
            }elseif ($operation_type == 6) {//撤防
                $api_result_json = $this->unlock($imei);
            }elseif ($operation_type == 7) {//启动
                $api_result_json = $this->accon($imei);
            }elseif($operation_type == 30){//唤醒
                $api_result_json = $this->awaken($imei);
            }elseif ($operation_type == 34) {//开舱锁
                $status_info = $this->gpsStatusInfo($full_imei);
                $lng = $_POST['lng'];
                $lat = $_POST['lat'];
                if(empty($lng)||empty($lat)){
                    $this->ajaxReturn(["code" => -4, "message" => "获取用户位置失败"], 'json');
                }
                if(empty($status_info["longitude"]) || empty($status_info["latitude"])){
                    $this->ajaxReturn(["code" => -5, "message" => "获取车辆位置失败"], 'json');
                }
                $distance = round($this->distance($status_info["gd_latitude"],$status_info["gd_longitude"],$lat,$lng));
                //添加记录
                $log = [
                    "uid" => $user_id,
                    "rent_content_id" => $rent_id,
                    "plate_no" => $plate_no,
                    "imei" =>$full_imei,
                    "user_lng" => $lng,
                    "user_lat" => $lat,
                    "record_time" => time(),
                    "gis_lng" => $status_info["gd_longitude"],
                    "gis_lat" => $status_info["gd_longitude"],
                    "distance"=>$distance
                ];
                M("baojia_mebike.open_cabin_log",null,$this->baojia_config)->add($log);

                $take_radius = 1000;
                if($distance>$take_radius){
                    $this->ajaxReturn(["code" => -6, "message" => "请靠近车辆使用开舱锁功能","distance"=>$distance,"longitude"=>$status_info["gd_longitude"],"latitude"=>$status_info["gd_latitude"]], 'json');
                }
                if($this->device_type == 18) {//小安盒子
                    $api_result_json = $this->open_battery($imei);
                }else{//老盒子
                    $api_result_json["rtCode"]="0";
                }
            }elseif ($operation_type == 35) {//换电设防
                $operationId=$_POST['operationId'];
                if(empty($operationId)){
                    $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
                }
                $api_result_json = $this->lock($imei);
            }
            if($test){
                echo "<pre>";
                print_r($api_result_json);
                echo $imei."--".$this->url;
            }
            if ($api_result_json["rtCode"] != "0") {
                $api_result = false;
                // 设备接收命令并返回成功 0
                // 设备接收命令并返回失败 1
                // 设备断开连接 3   该车盒子离线，操作失败
                // 未收到终端的数据 4 网关访问盒子超时，请重试
                // 命令重复 6
                switch($api_result_json["rtCode"]){
                    case "1":
                        $msg="操作失败";
                        break;
                    case "3":
                        $msg="该车盒子离线，操作失败";
                        break;
                    case "4":
                        $api_result = true;
                        $msg="网关访问盒子超时，请重试";
                        break;
                    case "6":
                        $msg="命令重复";
                        break;
                }
                $data['user_id']            = $user_id;
                $data['rent_content_id']    = $rent_id;
                $data['device_type']        =$this->device_type;
                $data['imei']               = $imei;
                $data['createtime']         = time();
                if($operation_type=="4") {
                    $operation_type_text = "鸣笛";
                }
                if($operation_type=="5"||$operation_type=="35") {
                    $operation_type_text = "设防";
                }
                if($operation_type=="6") {
                    $operation_type_text = "撤防";
                }
                if($operation_type=="7") {
                    $operation_type_text = "启动";
                }
                if($operation_type=="34") {
                    $operation_type_text = "开舱锁";
                }
            }
            $time_end = $this->microtime_float();
            $second = round($time_end - $time_start,2);
            \Think\Log::write("车辆操控，".$operation_type_text."请求网关：" . $this->url, "INFO");
            \Think\Log::write("车辆操控，".$operation_type_text."参数：" . json_encode($_POST)."，耗时：".$second."，结果：".json_encode($api_result_json), "INFO");
            $map['id'] = $operationId;
            if ($api_result == true) {
                //5=设防 6=撤防 7=启动  12 设防  13 撤防 14 启动
                if ($operation_type ==5 ){
                    $operate=12;
                }
                if ($operation_type ==6 ){
                    $operate=13;
                }
                if ($operation_type ==7 ){
                    $operate=14;
                }
                if ($operation_type ==5||$operation_type ==6||$operation_type ==7){
                    D('FedGpsAdditional')->operation_log($user_id,$rent_id,$plate_no,0,0,$operate);
                }
                if ($operation_type == 34) {
                    D('FedGpsAdditional')->exchangeLog($user_id,$rent_id,$plate_no,$lng,$lat,1);
                }
                if ($operation_type == 35){
                    M('operation_logging',null,$this->baojia_config)-> where($map)->setField('operate',1);
                    $reason = D('FedGpsAdditional')->theDayCarNum($user_id,0);
                }
                $r=$this->repairAdd($user_id,$rent_id,$plate_no,$operation_type);
                $this->ajaxReturn(["code" => 1, "message" => "操作成功","result"=>$r,"data"=>$reason['prompt'],"gateway_result"=>$api_result_json], 'json');
            } else {
                if ($operation_type == 35){
                    M('operation_logging',null,$this->baojia_config)-> where($map)->setField('operate',2);
                    $reason = D('FedGpsAdditional')->theDayCarNum($user_id,0);
                }
                $this->ajaxReturn(["code" => 0, "message" => $msg,"data" => $reason['prompt'],"gateway_result"=>$api_result_json], 'json');
            }

        } else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //实时定位
    public function RealTimeLocation($test=0)
    {
        $user_id = $_POST['user_id'];
        $lngX = $_POST['lngX'];
        $latY = $_POST['latY'];
        if($lngX&&$lngX>0) {
            $log = [
                "uid" => $user_id,
                "user_lng" => $lngX,
                "user_lat" => $latY,
                "record_time" => time()
            ];
            $result = M("baojia_mebike.open_cabin_log", null, $this->baojia_config)->add($log);
        }
        \Think\Log::write("实时定位，参数：" . json_encode($_POST)."，结果：".$result, "INFO");
        $this->ajaxReturn(["code" => 1, "message" => "实时定位记录成功","result"=>$result], 'json');
    }
    //删除实时定位记录
    public function DeleteLocation($test=0)
    {
        $result=M("baojia_mebike.open_cabin_log",null,$this->baojia_config)
            ->where('ISNULL(imei)  AND user_lng<user_lat')->delete();
        $result=M("baojia_mebike.open_cabin_log",null,$this->baojia_config)
            ->where('user_lat=0')->delete();
        $this->ajaxReturn(["code" => 1, "message" => "删除实时定位记录成功","result"=>$result], 'json');
    }
    //操作 4=鸣笛 5=设防 6=撤防 7=启动 34=开舱锁 35=换电设防 36=锁舱锁
    public function RepairOperationTest($test=0)
    {
        $user_id = $_POST['user_id'];
        $rent_id = $_POST['rent_content_id'];
        $operation_type = $_POST['operation_type'];
        file_put_contents($_SERVER['DOCUMENT_ROOT']."RepairOperation.txt",json_encode($_POST));
        if (!empty($user_id) && !empty($rent_id) && !empty($operation_type)) {
            if (!$this->carAuth($user_id, $rent_id)) {
                $this->ajaxReturn(["code" => -2, "message" => "你没有管理这辆车的权限"], 'json');
            }
            $rent_info = M("rent_content",null,$this->baojia_config)->where(["id" => $rent_id])->find();
            if (!$rent_info) {
                $this->ajaxReturn(["code" => -3, "message" => "车辆不存在"], 'json');
            }
            $plate_no = $this->getPlateNo($rent_info["car_item_id"]);
            $imei = $this->getImeiTest($rent_info["car_item_id"]);
            $full_imei = $imei;
            $imei = ltrim($imei, "0");
            $api_result = true;
            $msg="操作失败";
            if($operation_type == 4){//鸣笛
                $api_result_json = $this->accoff($imei);
            }elseif ($operation_type == 5) {//设防
                $api_result_json = $this->lock($imei);
            }elseif ($operation_type == 6) {//撤防
                $api_result_json = $this->unlock($imei);
            }elseif ($operation_type == 7) {//启动
                $api_result_json = $this->accon($imei);
            }elseif ($operation_type == 34) {//开舱锁
                //不判断距离直接开舱锁---------------------------------------------------------
                $api_result_json = $this->door($imei);
            }elseif ($operation_type == 35) {//换电设防
                $operationId=$_POST['operationId'];
                if(empty($operationId)){
                    $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
                }
                $api_result_json = $this->lock($imei);
            }elseif($operation_type == 36){
                $api_result_json = $this->lockDoor($imei);
            }
            if($test){
                echo "<pre>";
                print_r($api_result_json);
                echo $imei."--".$this->url;
            }
            if ($api_result_json["rtCode"] != "0") {
                // 设备接收命令并返回成功 0
                // 设备接收命令并返回失败 1
                // 设备断开连接 3
                // 未收到终端的数据 4
                // 命令重复 6
                switch($api_result_json["rtCode"]){
                    case "1":
                        $msg="操作失败";
                        break;
                    case "3":
                        $msg="设备断开连接，无法操作";
                        break;
                    case "4":
                        $msg="请求设备超时，请重试";
                        break;
                    case "6":
                        $msg="命令重复";
                        break;
                }
                $data['user_id']            = $user_id;
                $data['rent_content_id']    = $rent_id;
                $data['device_type']        =$this->device_type;
                $data['imei']               = $imei;
                $data['createtime']         = time();
                if($operation_type=="4") {
                    $operation_type_text = "鸣笛";
                }
                if($operation_type=="5"||$operation_type=="35") {
                    $operation_type_text = "设防";
                }
                if($operation_type=="6") {
                    $operation_type_text = "撤防";
                }
                if($operation_type=="7") {
                    $operation_type_text = "启动";
                }
                if($operation_type=="34") {
                    $operation_type_text = "开舱锁";
                }
                $data['operation_type_text']= $operation_type_text;
                $data['operation_type']= $operation_type;
                $data['request_url'] =$this->url;
                $data['rtcode']=$api_result_json["rtCode"];
                $data['msg']=$msg;
                $data['plate_no']=$plate_no;
                M("baojia_mebike.device_operation_failed_log",null,$this->baojia_config)->add($data);
                $api_result = false;
            }
            $map['id'] = $operationId;
            if ($api_result == true) {
                //5=设防 6=撤防 7=启动  12 设防  13 撤防 14 启动
                if ($operation_type ==5 ){
                    $operate=12;
                }
                if ($operation_type ==6 ){
                    $operate=13;
                }
                if ($operation_type ==7 ){
                    $operate=14;
                }
                if ($operation_type ==5||$operation_type ==6||$operation_type ==7){
                    //file_put_contents($_SERVER['DOCUMENT_ROOT']."canshu7878aa.txt",json_encode($plate_no));
                    //file_put_contents($_SERVER['DOCUMENT_ROOT']."canshu7878aa.txt",$plate_no."--".$operate);
                    D('FedGpsAdditional')->operation_log($user_id,$rent_id,$plate_no,0,0,$operate);
                }
                if ($operation_type == 35){
                    M('operation_logging',null,$this->baojia_config)-> where($map)->setField('operate',1);
                    $reason = D('FedGpsAdditional')->theDayCarNum($user_id,0);
                }
                $r=$this->repairAdd($user_id,$rent_id,$plate_no,$operation_type);
                $this->ajaxReturn(["code" => 1, "message" => "操作成功","result"=>$r,'data'=>$reason['prompt']], 'json');
            } else {
                if ($operation_type == 35){
                    M('operation_logging',null,$this->baojia_config)-> where($map)->setField('operate',2);
                    $reason = D('FedGpsAdditional')->theDayCarNum($user_id,0);
                }
                $this->ajaxReturn(["code" => 0, "message" => $msg, 'data' => $reason['prompt']], 'json');
            }

        } else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //操作 4=鸣笛 5=设防 6=撤防 7=启动 34=开舱锁 37=开轮锁  http://47.95.32.191:8907/simulate/service
    public function OperationByIMEI($test=0)
    {
        $imei = $_POST['imei'];
        $operation_type = $_POST['operation_type'];
        if (!empty($imei)&&!empty($operation_type)) {
            $imei = ltrim($imei, "0");
            /*
             <!--开关后轮锁 type  0: 开 1: 关-->
            {"carId":"865067025957153","type":1,"command":28,"cmd":"carControl","directRt":"false"}
            <!--后座锁  type  0: 开 1: 关-->
            {"carId":"865067025957153","type":1,"command":29,"cmd":"carControl","directRt":"false"}
            <!--控制语音播报 type 表示 语音类别-->
            {"carId":"865067025957153","type":2,"command":14,"cmd":"carControl","directRt":"false"}
            <!--设置防盗开关 type 0 关闭 1 开启-->
            {"carId":"865067025957153","type":0,"command":4,"cmd":"carControl","directRt":"false"}
            <!--远程启动 type 0：熄火 1：打开-->
            {"carId":"865067025957153","type":1,"command":90,"cmd":"carControl","directRt":"false"}
            */
            $data["carId"]=$imei;
            $data["cmd"]="carControl";
            $data["directRt"]="false";
            switch($operation_type){
                case 4:
                    $data["type"]=5;
                    $data["command"]=14;
                    break;
                case 5:
                    $data["type"]=1;
                    $data["command"]=4;
                    break;
                case 6:
                    $data["type"]=0;
                    $data["command"]=4;
                    break;
                case 7:
                    $data["type"]=1;
                    $data["command"]=90;
                    break;
                case 34:
                    $data["type"]=1;
                    $data["command"]=29;
                    break;
                case 37:
                    $data["type"]=0;
                    $data["command"]=28;
                    break;
            }
            $result=$this->json_post($this->url_xiaoan,$data);
            if($test==1){
                echo "<pre>";
                print_r($result);
            }
            if($result["rtCode"]==0){
                $this->ajaxReturn(["code" => 1, "message" => "操作成功","result"=>"success"], 'json');
            }elseif($result["rtCode"]==1){
                $this->ajaxReturn(["code" => 0, "message" => "设备接收命令并返回失败"], 'json');
            }elseif($result["rtCode"]==3){
                $this->ajaxReturn(["code" => 0, "message" => "盒子已断开连接"], 'json');
            }elseif($result["rtCode"]==4){
                $this->ajaxReturn(["code" => 0, "message" => "未收到终端数据"], 'json');
            }elseif($result["rtCode"]==6){
                $this->ajaxReturn(["code" => 0, "message" => "操作重复"], 'json');
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "操作失败，其他错误"], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //操作 4=鸣笛 5=设防 6=撤防 7=启动 30=唤醒 34=开舱锁 37=开轮锁
    public function OperationByIMEIDTZJ($test=0)
    {
        $imei = $_POST['imei'];
        $operation_type = $_POST['operation_type'];
        if (!empty($imei)&&!empty($operation_type)) {
            $imei = ltrim($imei, "0");
            /*
             <!--开关后轮锁 type  0: 开 1: 关-->
            {"carId":"865067025957153","type":1,"command":28,"cmd":"carControl","directRt":"false"}
            <!--后座锁  type  0: 开 1: 关-->
            {"carId":"865067025957153","type":1,"command":29,"cmd":"carControl","directRt":"false"}
            <!--控制语音播报 type 表示 语音类别-->
            {"carId":"865067025957153","type":2,"command":14,"cmd":"carControl","directRt":"false"}
            <!--设置防盗开关 type 0 关闭 1 开启-->
            {"carId":"865067025957153","type":0,"command":4,"cmd":"carControl","directRt":"false"}
            <!--远程启动 type 0：熄火 1：打开-->
            {"carId":"865067025957153","type":1,"command":90,"cmd":"carControl","directRt":"false"}
            */
            $data["carId"]=$imei;
            $data["cmd"]="carControl";
            $data["directRt"]="false";
            switch($operation_type){
                case 4:
                    $data["type"]=5;
                    $data["command"]=14;
                    break;
                case 5:
                    $data["type"]=1;
                    $data["command"]=4;
                    break;
                case 6:
                    $data["type"]=0;
                    $data["command"]=4;
                    break;
                case 7:
                    $data["type"]=1;
                    $data["command"]=90;
                    break;
                /*
                 * {"carId":"865067025367080","command":21,"cmd":"carControl","directRt":"false"}
                 */
                case 34:
                    $data["type"]=1;
                    $data["command"]=29;
                    break;
                case 37:
                    $data["type"]=0;
                    $data["command"]=28;
                    break;
            }
            $result=$this->json_post("http://47.95.32.191:8907/simulate/service",$data);
            if($test==1){
                echo "<pre>";
                print_r($result);
            }
            if($result["rtCode"]==0){
                $this->ajaxReturn(["code" => 1, "message" => "操作成功","result"=>"success","rtCode" =>$result["rtCode"],"sysCode"=>$result["sysCode"]], 'json');
            }elseif($result["rtCode"]==1){
                $this->ajaxReturn(["code" => 0, "message" => "设备接收命令并返回失败","rtCode" =>$result["rtCode"],"sysCode"=>$result["sysCode"]], 'json');
            }elseif($result["rtCode"]==3){
                $this->ajaxReturn(["code" => 0, "message" => "盒子已断开连接","rtCode" =>$result["rtCode"],"sysCode"=>$result["sysCode"]], 'json');
            }elseif($result["rtCode"]==4){
                $this->ajaxReturn(["code" => 0, "message" => "未收到终端数据","rtCode" =>$result["rtCode"],"sysCode"=>$result["sysCode"]], 'json');
            }elseif($result["rtCode"]==6){
                $this->ajaxReturn(["code" => 0, "message" => "操作重复","rtCode" =>$result["rtCode"],"sysCode"=>$result["sysCode"]], 'json');
            }else{
                $this->ajaxReturn(["code" => 0, "message" => "操作失败，其他错误","rtCode" =>$result["rtCode"],"sysCode"=>$result["sysCode"]], 'json');
            }
        }else{
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //查询车辆位置
    public function GetPosition(){
        $user_id = $_POST['user_id'];
        $rent_id = $_POST['rent_content_id'];
        if (!empty($user_id) && !empty($rent_id)) {
            if (!$this->carAuth($user_id, $rent_id)) {
                $this->ajaxReturn(["code" => -2, "message" => "你没有管理这辆车的权限"], 'json');
            }
            $imei_list = $this->getGpsImei($rent_id);
            //echo "<pre>";
            //print_r($imei_list);
            $gps_status = $this->gpsStatusByImeis($imei_list);
            $failed_record = M("rent_failed_record_location",null,$this->baojia_config)
                ->field("lat latitude,lng longitude,FROM_UNIXTIME(create_time) update_time")
                ->where(["rent_id" => $rent_id, "create_time" => ["gt", time() - 86400 * 2]])
                ->order("id desc")
                ->find();
            $gps=D('Gps');
            if(in_array($failed_record["client_id"], [218,1218])){
                $gd = $gps->bd_decrypt($failed_record["latitude"],$failed_record["longitude"]);
                $failed_record["gd_lat"] = floatval($gd["lat"]);
                $failed_record["gd_lng"] = floatval($gd["lon"]);
                $failed_record["bd_lat"] = floatval($failed_record["latitude"]);
                $failed_record["bd_lng"] = floatval($failed_record["longitude"]);

            }elseif(is_array($failed_record)){
                $bd = $gps->bd_encrypt($failed_record["latitude"],$failed_record["longitude"]);
                $failed_record["gd_lat"] = floatval($failed_record["latitude"]);
                $failed_record["gd_lng"] = floatval($failed_record["longitude"]);
                $failed_record["bd_lat"] = $bd["lat"];
                $failed_record["bd_lng"] = $bd["lon"];
            }
            $last_app=$this->getLastAppCoordinate($rent_id);
            if($last_app["longitude"]>0){
                $gd = $gps->gcj_decrypt($last_app["latitude"],$last_app["longitude"]);
                $bd = $gps->bd_encrypt($last_app["latitude"], $last_app["longitude"]);
                $last_app["gd_lat"] = floatval($gd["lat"]);
                $last_app["gd_lng"] = floatval($gd["lon"]);
                $last_app["bd_lat"] = $bd["lat"];
                $last_app["bd_lng"] = $bd["lon"];
            }
            $last_bs=$this->gpsStatusBSByImeis($imei_list);
            $this->ajaxReturn(["code" => 1, "message" => "查询成功","gps_status" => $gps_status,"failed_record"=>$failed_record,"last_app"=>$last_app,"last_bs"=>$last_bs], 'json');
        }
        else{
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }
    //位置矫正 type 16 手动矫正 17 自动矫正
    public function CorrectivePosition($user_id,$rent_content_id,$latitude='',$longitude = '',$type=16){
        if(!$latitude || !$longitude||!$user_id||!$rent_content_id){
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
        if($latitude == 0 ||$longitude==0) {
            $this->ajaxReturn(["code" => -100, "message" => "未获取到定位"], 'json');
        }
        $imei=$this->getImeiByRentID($rent_content_id);
        if(!$imei){
            $this->ajaxReturn(["code" => -1, "message" => "查询数据有误"], 'json');
        }
        /*if (!$this->carAuth($user_id, $rent_content_id)) {
            $this->ajaxReturn(["code" => -2, "message" => "你没有管理这辆车的权限"], 'json');
        }*/
        $rent_info = M("baojia.rent_content",null,$this->baojia_config)->where(["id"=>$rent_content_id])->find();
        $plate_no = $this->getPlateNo($rent_info["car_item_id"]);
        //$operation_type = 25;//位置矫正
        $gps=D('Gps');
        $origin = $gps->gcj_decrypt($latitude,$longitude);
        $new_latitude = $origin["lat"];
        $new_longitude = $origin["lon"];
        $result = $this->gpsStatusData($imei,$new_latitude,$new_longitude);
        \Think\Log::write("位置矫正，参数：" . json_encode($_POST)."，结果：".$result, "INFO");
        if($result > 0){
            //围栏内自动上架车辆，围栏外自动下架车辆  2017.10.11 调整为不自动上架
            /*$is_in_area = 1;
            $pt=[$new_longitude,$new_latitude];
            $in_area_result = $this->isXiaomaInArea($rent_content_id,$pt,$imei);
            if(!$in_area_result){
                $is_in_area = 0;
            }
            $datas['sell_status']=-7;
            $datas['update_time']=time();
            if($is_in_area==1){
                $datas['sell_status']=1;
            }
            M('baojia.rent_content',null,$this->baojia_config)->where("id={$rent_content_id}")->save($datas);
            */
            //$this->repairAdd($user_id,$rent_content_id,$plate_no,$operation_type);
            D('FedGpsAdditional')->operation_log($user_id,$rent_content_id,$plate_no,$longitude,$latitude,$type);
            M("rent_content")->where(["id"=>$rent_content_id])->save(["update_time" => time()]);
            $this->ajaxReturn(["code" => 1, "message" => "更新成功"],json);
        }else{
            $this->ajaxReturn(["code" =>0, "message" => "更新失败"],json);
        }
    }
    //更新坐标
    public function gpsStatusData($imei,$latitude,$longitude){
        $map = [];
        $map["imei"] = $imei;
        $info = M("gps_status",null,$this->box_config)->where($map)->find();
        $data                       = [];
        //$data['imei']               = $imei;// char(16) NOT NULL COMMENT '设备编号',
        //$data['port']               = 0;// smallint(6) NOT NULL COMMENT '端口',
        $data['latitude']           = $latitude;// float(10,6) DEFAULT NULL COMMENT '纬度',
        $data['longitude']          = $longitude;// float(10,6) DEFAULT NULL COMMENT '经度',
        //$data['speed']              = 0;// smallint(6) DEFAULT NULL,
        //$data['basestation']        = '';// char(18) DEFAULT NULL COMMENT '基站',
        //$data['lastonline']         = date("Y-m-d H:i:s");// timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '最后在线时间',
        //$data['alert_code']         = 0;// tinyint(4) DEFAULT NULL COMMENT '最近的设备报警',
        //$data['alert_time']         = 0;// int(11) DEFAULT NULL COMMENT '最近的设备报警时间',
        //$data['alert_receive_time'] = 0;// int(11) DEFAULT NULL COMMENT '最近的报警接收时间',
        //$data['course']             = 0;// smallint(6) DEFAULT NULL COMMENT '航向',
        if($info){
            $result = M("gps_status",null,$this->box_config)->where($map)->save($data);
        }else{
            $data['datetime']           = time();// int(10) NOT NULL,
            $result = M("gps_status",null,$this->box_config)->add($data);
        }
        \Think\Log::write("位置矫正更新坐标，参数：" . json_encode($data)."，结果：".$result, "INFO");
        return $result > 0;
    }
    //检查是否2天以上未更新位置
    public function checkLocation($imei){
        $info = M("fed_gps_additional")
            ->field("datetime")
            ->where("imei='{$imei}' and datetime<= (UNIX_TIMESTAMP(NOW()) - 86400 * 2)")
            ->find();
        echo "<pre>";
        print_r($info);
        if($info){
            return true;
        }else{
            return false;
        }
    }
    public function SetUpdateTime(){
        $rents= M("operation_logging")
            ->field("DISTINCT rent_content_id")
            ->where("time>1508814000")
            ->select();
        //echo "<pre>";
        //print_r($rents);die;
        $count=0;
        foreach ($rents as $k =>$v) {
            $r=M("rent_content")->where(["id"=>$v["rent_content_id"]])->save(["update_time" => time()]);
            $count+=$r;
        }
        echo $count;die;
    }
    public function UpdateMember(){
        $member= M("member")
            ->where("uid=2742951")
            ->find();
        //echo "<pre>";
        //print_r($member);die;
        $member["uid"]=2630751;
        $member["nickname"]="CHI";
        $member["sex"] =0;
        $member["avatar"] =null;
        $member["first_name"] ="曾池";
        $member["last_name"] ="佘";
        $count=M("member")->save($member);
        $r=M("baojia_mebike.repair_member")
            ->where(["user_id"=>2630751])
            ->save(["user_name"=>"佘曾池"]);
        $count+=$r;
        echo $count;die;
    }
    //车辆详情
    public function details($plate_no = '',$test=0){
        //ini_set('memory_limit','512M');
        if(empty($plate_no)){
            $this->ajaxReturn(["code" => 0, "message" => "参数有误"], 'json');
        }
        if( substr($plate_no,0,2) == 'dd' ){
            $plate_no = str_replace('dd', 'DD', $plate_no);
        }elseif( substr($plate_no,0,2) !== 'DD'){
            $plate_no = 'DD'.$plate_no;
        }
        $car_item_id = M("car_item_verify",null,$this->baojia_config)->where(["plate_no" => $plate_no])->getField("car_item_id");
        $rent_content_id = M("rent_content",null,$this->baojia_config)->where(["car_item_id" => $car_item_id])->getField("id");
        if(empty($rent_content_id)){
            $this->ajaxReturn(["code" => -1, "message" => "查询无此车辆" ], 'json');
        }
        $zsdb='mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc';
        $box_config = 'mysqli://api-baojia:CSDV4smCSztRcvVb@10.1.11.14:3306/baojia_box';
        //限行区域
        $groupid = M("car_group_r","",$zsdb)->field("groupId")->where("plate_no='{$plate_no}'")->find();
        $driving_area = M("group","",$zsdb)->field("name")->where(["id"=>$groupid['groupid']])->find(); //查询行驶区域名字
        $userinfo = M("baojia_cloud.group_manager")->field("user_id,user_name")->where(["groupId"=>$groupid['groupid']]) ->find(); //查询区长ID名字
        $mobile = M("ucenter_member",null,$this->baojia_config)->field("mobile")->where(["uid"=>$userinfo['user_id']])->find();  //查询区长手机号
        //定位时间
        $res = M("rent_content")->alias("rc")
            ->join("car_item_verify civ ON civ.car_item_id = rc.car_item_id","left")
            ->join("rent_content_ext rce ON rce.rent_content_id = rc.id","left")
            ->join("corporation cc on cc.id = rc.corporation_id","left")
            ->join("car_item_device cid ON cid.car_item_id = rc.car_item_id","left")
            ->join("rent_content_return_config rcrc ON rcrc.rent_content_id = rc.id","left")
            ->join("car_info cn on cn.id=rc.car_info_id","left")
            ->join("car_item ci on ci.id = rc.car_item_id","left")
            ->join("car_model cm ON cm.id = cn.model_id","left")
            ->join("car_item_color cic on cic.id = ci.color","left")
            ->join("fed_gps_additional fga ON fga.imei = cid.imei","left")
            ->join("fed_gps_status fgs ON fgs.imei = cid.imei","left")
            ->join("car_info_picture cip on cip.car_info_id=rc.car_info_id and cip.car_color_id=ci.color","left")
            ->field("cc.name, rcrc.return_mode, rc.sell_status, civ.plate_no, civ.vin, cip.url picture_url, rc.shop_brand, rc.car_item_id, rc.id, rc.car_info_id, rc.status, cid.imei, fgs.latitude gis_lat, fgs.longitude gis_lng, cn.full_name, ci.`sort_name`, cic.color, cid.device_type, fga.residual_battery, fga.datetime")
            ->where("rc.id = {$rent_content_id}  AND rc.sort_id = 112 AND cip.status=2")
            ->select();
        if($test){
            echo M("rent_content",null,$this->baojia_config)->getLastSql();
        }
        //print_r($res); die;
        //echo $res[0]['imei'];
        if( empty($res[0]['imei']) ){
            $this->ajaxReturn(["code" => -1, "message" => "未获取到盒子号,请重新请求" ], 'json');
        }
        $gps = D('Gps');
        $info = M("baojia_box.gps_status",null,$box_config)->where(["imei"=>$res[0]['imei']])->field("id,imei,latitude,longitude,datetime,lastonline")->find();
        //print_r($info); die;
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_latitude"] = $gd["lat"];
        $info["gd_longitude"] = $gd["lon"];
        $bd = $gps->bd_encrypt($info["gd_latitude"],$info["gd_longitude"]);
        $info["bd_latitude"] = $bd["lat"];
        $info["bd_longitude"] = $bd["lon"];
        $info["datetime_diff"] = $this->timediff($info["datetime"],time())."前";
        $info["datetime"] = date("Y-m-d H:i:s",$info['datetime']);
        $info["is_online"] = time() - strtotime($info['lastonline']) > 1200 ? "离线" : "在线";
        $info["location"] = $this->GetAmapAddress( $info["gd_longitude"], $info["gd_latitude"]);
        $pt=[$info['longitude'],$info['latitude']];
        $in_area_result = $this->isXiaomaInArea($rent_content_id,$pt,$info['imei']);

        $info['is_inarea'] = empty($in_area_result) ? "界外" : "界内";
        $jizhan = M("baojia_box.gps_status_bs",'',$box_config)->where(["imei"=>$res[0]['imei']])->find();
        $jizhan["gd_latitude"] = $gd["lat"];
        $jizhan["gd_longitude"] = $gd["lon"];
        $jizhan['location'] = $this->GetAmapAddress( $gd["lon"], $gd["lat"]);
        //用户最后还车定位轨迹
        $user_return = $this->trade_line($rent_content_id);
        //echo "<pre>";
        //print_r($user_return); die;
        $result["id"] = $rent_content_id;
        $result["picture_url"] = "http://pic.baojia.com/b/".$res[0]['picture_url'];
        $result["plate_no"] = $res[0]['plate_no'];
        $result["sell_status"] = $res[0]['sell_status'];
        $is_rating = "待租中";
        if($res[0]['sell_status'] != 1){
            $is_rating = "不可租";
        }
        $sell_status_item = [];
        $sell_status_item[0]    = "人工停租";
        $sell_status_item[-4]   = "馈电停租";
        $sell_status_item[-1]   = "馈电停租";
        $sell_status_item[-7]   = "越界停租";
        $sell_status_item[101]  = "报修下架";
        $sell_status_item[-8]   = "维修停租";
        $sell_status_item[-10]  = "收回下架";
        $sell_status_item[-100] = "离线停租";
        $sell_status_item[-13] = "待调度";

        isset($sell_status_item[$res[0]['sell_status']]) && $is_rating = $sell_status_item[$res[0]['sell_status']];
        $hasOrder = M("trade_order",null,$this->baojia_config)->where(" rent_content_id={$rent_content_id} and  rent_type=3 and status>=10100 and status<80200 and status<>10301 ")->find();

        if($hasOrder){
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
        $result['return_location'] = $this->GetAmapAddress( $user_return['end']['lon'], $user_return['end']['lat']);
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
        $result["lastonline"] = $info['lastonline'];
        //print_r($result); die;
        \Think\Log::write("查询车辆详情" . json_encode($_POST)."--".$res[0]['imei'], "INFO");
        if(is_array($result)){
            $this->ajaxReturn(["code" => 1, "message" => "请求成功" , "data" => $result], 'json');
        }else{
            $this->ajaxReturn(["code" => -1001, "message" => "失败" ], 'json');
        }
    }
    //加载热力图
    public function LoadHeatmapData(){
        $strSql = "select rcrc.take_lng lng,rcrc.take_lat lat,count(0) count from Trade_order a
            left join rent_content rn on a.rent_content_id=rn.id
            left join rent_content_search rcs on rcs.rent_content_id=rn.id
            right join trade_order_payment t on a.id = t.order_id
            left join trade_payment p on t.payment_id = p.id
            left join trade_order_car_return_info rcrc ON a.id = rcrc.order_id
            where rcs.address_type=99 and rcs.plate_no<>'' and rcs.sort_id=112 and rcs.city_id=1
            and a.status=80200 and a.billing_settlement_status=2 and p.pay_mode in(5,11) and p.pay_status = 1 and p.busi_code != 5
            and a.create_time>1501516800 and  a.create_time<1503676799
            and ((a.rent_type=0 and a.earnest_pay_status=1) or (a.rent_type=3 and a.status<>0)) and IFNULL(rcrc.take_lng,0)>0
            group by rcrc.take_lng,rcrc.take_lat";
        $heatmapData = M('',null,$this->baojia_config)->query($strSql);
        $this->ajaxReturn(["code" => 1, "message" => "查询成功","data" =>$heatmapData], 'json');
    }

    public function lock($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '3';
        if($this->device_type == 18){
            $data["type"] = '1';
            $data["command"] = 4;
            //$data["directRt"] = 'false';
        }
        $r = $this->control_post($data);
        return $r;
    }
    public function unlock($imei)
    {
        if($this->device_type == 14){
            return $this->open($imei);
        }
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '4';
        if($this->device_type == 18){
            $data["type"] = '0';
            $data["command"] = 4;
            //$data["directRt"] = 'false';
        }
        $r = $this->control_post($data);
        return $r;
    }
    public function accon($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '1';
        if($this->device_type == 18){
            $data["command"] = 90;
            $data["internalOp"] = 'operation';
            //$data["directRt"] = "false";
        }
        $r = $this->control_post($data);
        return $r;
    }
    public function open($imei){
        $data['carId'] = $imei;
        $data['cmd'] = 'open';
        //$data['directRt'] = true;
        $r = $this->control_post($data);
        return $r;
    }
    public function accoff($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '2';
        if($this->device_type == 18){
            $data['command'] = 14;
            //$data['directRt'] = false;
            $data['type'] = '5';
        }
        $r = $this->control_post($data);
        return $r;
    }
    public function open_battery($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data["type"] = 1;
        $data["command"] = 29;
        //$data["directRt"] = 'false';
        $r = $this->control_post($data);
        return $r;
    }
    public function awaken($imei)
    {
        //{"carId":"865067025367080","command":21,"cmd":"carControl","directRt":"false"}
        $data['carId'] = $imei;
        $data["command"] = 21;
        $data["cmd"] ="carControl";
        $data["directRt"] = 'false';
        $r = $this->control_post($data);
        return $r;
    }
    //开仓门
    public function door($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '0';
        if($this->device_type == 18){
            $data["command"] = 40;
            $data["internalOp"] = 'operation';
            $data["directRt"] = "false";
        }
        $r = $this->control_post($data);
        return $r;
    }
    //锁仓门
    public function lockDoor($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '1';
        if($this->device_type == 18){
            $data["command"] = 40;
            $data["internalOp"] = 'operation';
            $data["directRt"] = "false";
        }
        $r = $this->control_post($data);
        return $r;
    }
    private function control_post($data, $nosign)
    {
        $postUrl = $this->url;

        /*if($nosign){
            $postUrl = $this->url2;
        }*/
        $sign = $this->getSign3($data, $this->key);
        $data['sign'] = $sign['sign'];
        $json = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json)
            ]
        );
        $output = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($output, true);
        return $data;
    }
    //工单记录
    public function repairAdd($user_id,$rent_id,$plate_no,$repair_type,$sign_type = 0,$remark = ''){
        $data = [];
        $data['user_id']           = $user_id;//维修人员id,
        $data['rent_content_id']   = $rent_id;//车辆ID
        $data['plate_no']          = $plate_no;//车牌号
        $data['repair_begin_time'] = time();//操作时间
        $data['repair_type']       = $repair_type;//4=鸣笛 5=设防 6=撤防 7=启动 34=开舱锁
        if($repair_type!=34) {
            $data["is_success"]= 1;//换电池默认工单完成
            $data["repair_success_time"] = time();//换电池默认工单完成
        }else{
            $data["is_success"]= 0;
        }
        $data["sign_type"]		   = $sign_type;
        $data["remark"]			   = $remark;
        return M("baojia_mebike.repair_order",null,$this->baojia_config)->add($data);
    }

    public function gcj02ToBD($lat,$lon){
        $gps=D('Gps');
        $gd = $gps->gcj_encrypt($lat,$lon);
        //echo "<pre>";
        //print_r($gd);
        $lat = $gd["lat"];
        $lon = $gd["lon"];
        $bd = $gps->bd_encrypt($lat,$lon);
        $info["gd_lat"] = $lat;
        $info["gd_lon"] = $lon;
        $info["bd_latitude"] = $bd["lat"];
        $info["bd_longitude"] = $bd["lon"];
        echo json_encode($info);exit();
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
    public function gpsStatusInfo($imei){
        $info = M("baojia_box.gps_status",null,$this->box_config)->where(["imei"=>$imei])->find();
        $gps=D('Gps');
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_latitude"] = $gd["lat"];
        $info["gd_longitude"] = $gd["lon"];
        $bd = $gps->bd_encrypt($info["gd_latitude"],$info["gd_longitude"]);
        $info["bd_latitude"] = $bd["lat"];
        $info["bd_longitude"] = $bd["lon"];
        $info["datetime_diff"] = $this->timediff($info["datetime"],time());
        //M("baojia.app_config",null,$this->baojia_config)->find();
        return $info;
    }
    public function getPlateNo($car_item_id){
        $plate_no = M("car_item_verify",null,$this->baojia_config)->where(["car_item_id"=>$car_item_id])
            ->getField("plate_no");
        return $plate_no;
    }

    //车辆及管理人权限判断
    public function carAuth($uid,$rent_id){
        $rent_parent_corporation_id = $this->getRentParentCorporation($rent_id);
        $check = $this->authMemberCorporation($uid,$rent_parent_corporation_id);
        return $check;
    }
    //获取车辆的网点id
    public function getRentCorporationId($rent_id){
        return M("rent_content",null,$this->baojia_config)->where(["id"=>$rent_id])->getField("corporation_id");
    }
    //获取企业id
    public function getCorporationParentId($corporation_id){
        return M("corporation",null,$this->baojia_config)->where(["id"=>$corporation_id])->getField("parent_id");
    }
    //获取车辆的企业id
    public function getRentParentCorporation($rent_id){
        return $this->getCorporationParentId($this->getRentCorporationId($rent_id));
    }
    //查看该用户是否有该企业权限
    public function authMemberCorporation($uid,$corporation_id){
        if($uid == 0 || $corporation_id == 0){
            return false;
        }
        return M("baojia_mebike.repair_member",null,$this->baojia_config)
                ->where(["user_id"=>$uid,"corporation_id"=>$corporation_id,"status"=>1])
                ->count() > 0;
    }
    //是否有维修权限
    public function isRepairMember($uid = 0){
        $map = [];
        $map["user_id"] = $uid;
        $info = M("baojia_mebike.repair_member",null,$this->baojia_config)->where($map)->find();
        return is_array($info);
    }
    //查询员工类型
    public function getUserType($user_id,$corporation_id){
        return M("baojia_mebike.repair_member")->alias('rm')
            ->join("corporation cor on cor.id=rm.corporation_id","left")
            ->join("area_city ac on ac.id=cor.city_id","left")
            ->field("rm.user_id,rm.role_type,cor.city_id,ac.name city")
            ->where("rm.user_id={$user_id} and rm.corporation_id={$corporation_id} and rm.status=1")
            ->find();
    }

    private function getImei($car_item_id){
        $map = ["car_item_id"=>$car_item_id];
        $map["device_type"] = ["in",[12,14,16,9,18]];
        $device_info = M("baojia.car_item_device",null,$this->baojia_config)->where($map)->find();
        if($device_info["device_type"] == 16){//XM1盒子 http://yd.zykuaiche.com:81/s/service
            $this->url =$this->url_xm1;
        }
        if($device_info["device_type"] == 14){//小马盒子 http://bike.zykuaiche.cn:82/simulate
            $this->url =$this->url_xiaoma;
        }
        if($device_info["device_type"] == 18){//小安 http://47.95.32.191:8107/simulate/service
            $this->url =$this->url_xiaoan;
        }
        $this->device_type = $device_info["device_type"];
        return $device_info["imei"] ? $device_info["imei"] : "";
    }

    private function getImeiTest($car_item_id){
        $map = ["car_item_id"=>$car_item_id];
        $map["device_type"] = ["in",[12,14,16,9,18]];
        $device_info = M("baojia.car_item_device",null,$this->baojia_config)->where($map)->find();
        if($device_info["device_type"] == 16){//XM1盒子 http://yd.zykuaiche.com:81/s/service
            $this->url =$this->url_xm1;
        }
        if($device_info["device_type"] == 14){//小马盒子 http://bike.zykuaiche.cn:82/simulate
            $this->url =$this->url_xiaoma;
        }
        if($device_info["device_type"] == 18){//小安 http://47.95.32.191:8107/simulate/service
            $this->url ="http://123.57.173.14:8107/simulate/service";
        }
        $this->device_type = $device_info["device_type"];
        return $device_info["imei"] ? $device_info["imei"] : "";
    }
    //获取gps定位
    public function getGpsImei($rent_id = 0){
        $sql = "SELECT cid.imei FROM rent_content rc
				INNER JOIN car_item_device cid ON cid.car_item_id = rc.car_item_id
				INNER JOIN car_device_type cdt ON cdt.device_type = cid.device_type
				WHERE cdt.has_gps = 1 AND cdt.device_type != 6
				AND rc.id = {$rent_id};";
        $list = M('',null,$this->baojia_config)->query($sql);
        $imei_list = [];
        if($list){
            foreach ($list as $key => $value) {
                $imei_list[] = $value["imei"];
            }
        }
        return $imei_list;
    }

    public function getImeiByRentID($rent_id = 0){
        $imei=M('rent_content',null,$this->baojia_config)->alias('rc')
            ->field("cid.imei")
            ->join('car_item_device cid ON cid.car_item_id = rc.car_item_id', 'left')
            ->join('car_device_type cdt ON cdt.device_type = cid.device_type', 'left')
            ->where("cdt.has_gps = 1 AND cdt.device_type != 6 AND rc.id = {$rent_id}")
            ->find();
        return $imei["imei"];
    }

    public function getLastAppCoordinate($rent_id){
        $co=M('baojia_mebike.trade_order_return_log',null,$this->baojia_config)
            ->field("curpoint,FROM_UNIXTIME(time) update_time")
            ->where("rent_content_id={$rent_id}")
            ->order("time desc")
            ->find();
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
        //echo "<pre>";
        //print_r($coordinate);
        return $coordinate;
    }

    public function gpsStatusByImeis($imei_list){
        if(count($imei_list) == 0){
            return "";
        }
        $map = [];
        $map["imei"] = ["in",$imei_list];
        $order = " lastonline desc ";
        $info = M("baojia_box.gps_status",null,$this->box_config)
            ->field("longitude,latitude,lastonline update_time")
            ->where($map)->order($order)->find();
        if(!$info){
            return null;
        }
        $gps=D('Gps');
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_lat"] = floatval($gd["lat"]);
        $info["gd_lng"] = floatval($gd["lon"]);
        $bd = $gps->bd_encrypt($info["gd_lat"],$info["gd_lng"]);
        $info["bd_lat"] = $bd["lat"];
        $info["bd_lng"] = $bd["lon"];
        return $info;
    }

    public function gpsStatusBSByImeis($imei_list){
        if(count($imei_list) == 0){
            return "";
        }
        $map = [];
        $map["imei"] = ["in",$imei_list];
        $order = " lastonline desc ";
        $info = M("baojia_box.gps_status_bs",null,$this->box_config)
            ->field("longitude,latitude,lastonline update_time")
            ->where($map)->order($order)->find();
        if(!$info){
            return null;
        }
        $gps=D('Gps');
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_lat"] = floatval($gd["lat"]);
        $info["gd_lng"] = floatval($gd["lon"]);
        $bd = $gps->bd_encrypt($info["gd_latitude"],$info["gd_longitude"]);
        $info["bd_lat"] = $bd["lat"];
        $info["bd_lng"] = $bd["lon"];
        return $info;
    }

    public function GetUserCityID($user_id){
        $city_id = M('corporation',null,$this->baojia_config)->alias('a')
            ->field("city_id")
            ->join('baojia_mebike.repair_member b on b.corporation_id=a.id', 'left')
            ->where("b.id={$user_id}")
            ->find();
        return $city_id;
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

    public function isXiaomaInArea($rent_id,$pt,$imei,$has_poly = 0){
        $rent_info = M("baojia.rent_content",null,$this->baojia_config)->where(["id"=>$rent_id])->find();
        //非小马单车不判断，默认在区域内
        /*if(!in_array($rent_info["sort_id"],[112,113])){
            return true;
        }*/
        $map = ["car_item_id"=>$rent_info["car_item_id"]];
        $map["device_type"] = ["in",[12,14,16,18,9]];
        //$car_item_device = M("baojia.car_item_device",null)->where($map)->find();
        //$imei = $car_item_device["imei"];
        $no_zero_imei = ltrim($imei,"0");
        $aliyun_conn = "mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc";

        $ga_list_sql = "SELECT ga.lat, ga.lng FROM car_group_r cgr
                        INNER JOIN `group` g ON g.id = cgr.groupId
                        INNER JOIN group_area ga ON ga.groupId = g.id
                        WHERE cgr.carId = '{$no_zero_imei}'
                        AND cgr.`status` = 1 AND g.`status` = 1 ORDER BY ga. NO";
        $list = M("",null,$aliyun_conn)->query($ga_list_sql);
        M("baojia.action",null,$this->baojia_config)->find();
        if($list){
            $poly = [];
            foreach ($list as $key => $value) {
                # code...
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

    public function trade_line($rent_content_id){
        $return = [];
        $gps = D('Gps');
        $orderid =M('baojia_mebike.trade_order_return_log',null,$this->baojia_config)->where(["rent_content_id"=>$rent_content_id])->order("id desc")->limit(1)->field("order_id")->find();
        if(empty($orderid)){
            return $return;
        }
        $order=M('trade_order',null,$this->baojia_config)->where("id=".$orderid['order_id'])->find();
        $beginTime=$order['begin_time'];
        $endtime=$order['end_time'];
        $device=M('car_item_device',null,$this->baojia_config)->where('car_item_id='.$order['car_item_id'])->field('imei')->find();
        // echo "<pre>";
        // print_r($order);
        $car_return_info=M('trade_order_car_return_info',null,$this->baojia_config)->where('order_id='.$orderid['order_id'])->find();
        if($car_return_info && $car_return_info['take_lng']>0 && $car_return_info['take_lat']>0){
            $liststart['lat']=(float)$car_return_info['take_lat'];
            $liststart['lon']=(float)$car_return_info['take_lng'];
        }
        if($car_return_info && $car_return_info['return_lng']>0 && $car_return_info['return_lat']>0){
            $listend['lat']=(float)$car_return_info['return_lat'];
            $listend['lon']=(float)$car_return_info['return_lng'];
        }
        $imei = $device['imei'];
        $condition="imei='$imei'";
        if($beginTime && $endtime)$condition.=" AND datetime between $beginTime AND $endtime";
        $PointArr1 = M('fed_gps_location',null,$this->baojia_config)->where("$condition")->order('datetime asc')->select();
        // $res = M('fed_gps_location')->getLastsql();
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
            $PointArr2=M("",null,$this->box_config)->query("select latitude,longitude from {$tbs1[0]} where  imei='{$imei}' and longitude>0 and latitude>0 and datetime>={$beginTime} and datetime<={$endtime} order by datetime asc");
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
        $return['guiji'] = $PointArr2;
        return $return;
        // var_dump($PointArr2);
    }

    //电压转换电量
    public function getDumpEle($voltage){
        $v = ($voltage - 43) / (0.12);
        $battery=0;
        if ($voltage > 53) { // 53 ~
            $battery=1;
        } else if ($voltage >= 43 && $voltage <= 55) { //43~55
            $battery= $v / 100;
        } else if ($voltage < 43 && $voltage > 10) { //43~10
            $battery= -1;
        }
        return sprintf('%.2f',$battery);
    }

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

    public function GetAmapCity($lng,$lat,$default='北京'){
        $res = file_get_contents('http://restapi.amap.com/v3/geocode/regeo?output=JSON&location='.$lng.','.$lat.'&key=7461a90fa3e005dda5195fae18ce521b&s=rsv3&radius=1000&extensions=base');
        $res = json_decode($res);
        if($res->info == 'OK'){
            $default=explode('市',$res->regeocode->addressComponent->city)[0];
            if(empty($default)){
                $default=explode('市',$res->regeocode->addressComponent->province)[0];
            }
        }
        echo $default;
        return $default;
    }

    public function GetAmapAddress($lng,$lat,$default=''){
        $res = file_get_contents('http://restapi.amap.com/v3/geocode/regeo?output=JSON&location='.$lng.','.$lat.'&key=7461a90fa3e005dda5195fae18ce521b&s=rsv3&radius=1000&extensions=base');
        $res = json_decode($res);
        if($res->info == 'OK'){
            $default=$res->regeocode->formatted_address;
        }
        return $default;
    }
    // 验证验证码时间是否过期
    public function CheckTime($nowTimeStr,$smsCodeTimeStr){
        $nowTime = strtotime($nowTimeStr);
        $smsCodeTime = strtotime($smsCodeTimeStr);
        $period = floor(($nowTime-$smsCodeTime)/60); //60s
        if($period>=0 && $period<=20){
            return true;
        }else{
            return false;
        }
    }
    // 生成短信验证码
    public function CreateSMSCode($length = 4){
        $min = pow(10 , ($length - 1));
        $max = pow(10, $length) - 1;
        return rand($min, $max);
    }

    private function timediff($ent_time, $start_time) {
        $time = abs($ent_time - $start_time);
        $start = 0;
        $string="";
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

    function diffBetweenTwoDays ($day1, $day2)
    {
        if ($day1 < $day2) {
            $tmp = $day2;
            $day2 = $day1;
            $day1 = $tmp;
        }
        return ($day1-$day2)/86400;
    }

    private function json_post($url,$data)
    {
        $json = json_encode($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json)
            ]
        );
        $output = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($output, true);
        return $data;
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

    function curl_get($url, $data='', $method='GET')
    {
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
            if ($data != '') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
            }
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); // 执行操作
        curl_close($curl); // 关闭CURL会话
        return $tmpInfo; // 返回数据
    }

    private function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
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
    //获取小安设备redis 定位数据
    public function GetXiaomiPosition($imei){
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
}