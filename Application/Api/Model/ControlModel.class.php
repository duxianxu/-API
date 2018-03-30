<?php
namespace Api\Model;
use Think\Model;

class ControlModel extends Model{

    private $box_config = 'mysqli://api-baojia:CSDV4smCSztRcvVb@10.1.11.14:3306/baojia_box';
    private $url ='http://zykuaiche.com.cn:81/g/service';
    private $url_xiaoma='http://bike.zykuaiche.cn:82/simulate';//小马盒子
    private $url_xm1='http://yd.zykuaiche.com:81/s/service';//xm1盒子
    private $url_xiaoan='http://wg.baojia.com/simulate/service'; //小安盒子
    private $key = '987aa22ae48d48908edafda758ae82a8';
    private $device_type = 0;//16 XM1盒子 18 小安盒子
    private $distance = 1000;//允许员工开舱锁距离车的米数
    private $PI = 3.14159265358979324;

    public function __construct()
    {

    }

    /**车辆操控
     * @param $user_id  员工id
     * @param $rent_content_id 车辆id
     * @param $operation_type 操作类型 4=鸣笛 5=设防 6=撤防 7=启动 30=唤醒 33=断电 34=开舱锁 36=关仓 去掉换电设防
     * @param $gis_lng 手机定位，高德坐标经度
     * @param $gis_lat 手机定位，高德坐标纬度
     * @return array 返回结果
     */
    public function control($corporation_id,$user_id,$rent_content_id,$operation_type,$gis_lng,$gis_lat)
    {
        $time_start = $this->microtimeFloat();
        if (empty($user_id)||empty($rent_content_id)||empty($operation_type)||empty($corporation_id)) {
            return ["code" => -100, "message" => "参数不完整"];
        }
        //return $this->userAuth($corporation_id,$rent_content_id);
        if ($this->userAuth($corporation_id,$rent_content_id)<1) {
            return ["code" => -2, "message" => "你没有管理这辆车的权限"];
        }
        $parameters["user_id"]=$user_id;
        $parameters["rent_content_id"]=$rent_content_id;
        $parameters["operation_type"]=$operation_type;
        $parameters["gis_lng"]=$gis_lng;
        $parameters["gis_lat"]=$gis_lat;
        $rent_info = M("rent_content")->where(["id" => $rent_content_id])->find();
        if (!$rent_info) {
            return ["code" => -3, "message" => "车辆不存在"];
        }
        $plate_no = $this->getPlateNo($rent_info["car_item_id"]);
        $imei = $this->getImei($rent_info["car_item_id"]);
        if(empty($imei)){
            return ["code" => -4, "message" => "未绑定盒子imei或盒子类型有误"];
        }
        $full_imei = $imei;

        $Redis = new \Redis();
        $Redis->pconnect('10.1.11.83', 36379, 0.5);
        $Redis->AUTH('oXjS2RCA1odGxsv4');
        $Redis->SELECT(2);
        if( $Redis->get("Operation".$full_imei) ){
            return ['code' =>-7, 'message' => '别急，正在处理，请勿重复操作'];
        }

        $imei = ltrim($imei, "0");
        $api_result = true;
        $msg="操作成功";
        $result_confirm=[];
        if($operation_type == 4){//鸣笛
            $operation_type_text = "鸣笛";
            $api_result_json = $this->whistle($imei);
        }elseif ($operation_type == 5) {//设防
            $operation_type_text = "设防";
            $api_result_json = $this->lock($imei);
        }elseif ($operation_type == 6) {//撤防
            $operation_type_text = "撤防";
            $api_result_json = $this->unlock($imei);
        }elseif ($operation_type == 7) {//启动
            $operation_type_text = "启动";
            $api_result_json = $this->accon($imei);
        }elseif($operation_type == 30){//唤醒
            $operation_type_text = "唤醒";
            $api_result_json = $this->awaken($imei);
        }elseif ($operation_type == 33) {//断电
            $operation_type_text = "断电";
            $api_result_json = $this->accoff($imei);
        }elseif ($operation_type == 36) {//关仓
            $operation_type_text = "关仓";
            $api_result_json = $this->close_battery($imei);
        }elseif ($operation_type == 34) {//开舱锁
            $operation_type_text = "开舱锁";
            $status_info = $this->gpsStatusInfo($full_imei);
            if(empty($gis_lng)||empty($gis_lat)){
                return ["code" => -5, "message" => "获取用户位置失败"];
            }
            if(empty($status_info["gd_latitude"]) || empty($status_info["gd_latitude"])){
                return ["code" => -6, "message" => "获取车辆位置失败"];
            }
            $distance = round($this->distance($status_info["gd_latitude"],$status_info["gd_longitude"],$gis_lat,$gis_lng));
            if($distance>$this->distance){
                \Think\Log::write("车辆操控，请靠近车辆使用开舱锁功能，距离".$distance."，允许距离：" . $this->distance, "INFO");
                return ["code" => -7, "message" => "请靠近车辆使用开舱锁功能","distance"=>$distance,"longitude"=>$status_info["gd_longitude"],"latitude"=>$status_info["gd_latitude"]];
            }
            if($this->device_type == 18) {//小安盒子
                $api_result_json = $this->open_battery($imei);
            }else{//老盒子
                $api_result_json["rtCode"]="0";
            }
        }
        if(empty($api_result_json)){
            $time_end = $this->microtimeFloat();
            $second = round($time_end - $time_start,2);
            \Think\Log::write($operation_type_text.$imei.",接口请求网关超时：".$second . $this->url, "INFO");
            return ["code" => 0, "message" => "接口请求网关超时", "result" => $api_result_json];
        }
        if ($api_result_json["rtCode"] != "0") {
            $api_result = false;
            switch($api_result_json["rtCode"]){
                case "1":
                    $msg="盒子返回失败";
                    break;
                case "3":
                    $msg="该车盒子离线，操作失败";
                    break;
                case "4":
                    $msg="网关访问盒子超时，请重试";
                    break;
                case "5":
                    $msg="盒子没返回或已经超时";
                    break;
                case "6":
                    $msg="命令重复";
                    break;
            }
        }else{
            if($this->device_type == 18) {
                if($api_result_json["msgkey"]) {
                    $cData['carId'] = $imei;
                    $cData['key'] = $api_result_json["msgkey"];
                    $cData['cmd'] = 'resultQuery';
                    for ($i = 0; $i < 10; $i++) {
                        $result_confirm = $this->post($cData);
                        \Think\Log::write($operation_type_text.",盒子IMEI:" . $imei . ",第" . $i . "次,网关确认返回结果:" . json_encode($result_confirm) . ",参数:" . json_encode($cData), "INFO");
                        if ($result_confirm['rtCode'] == '0') {
                            $api_result = true;
                            break;
                        } else {
                            sleep(1);
                        }
                    }
                    if ($result_confirm['rtCode'] != '0') {
                        \Think\Log::write($operation_type_text.",".$imei.",操作不成功，请再试一次，结果：".json_encode($api_result_json), "INFO");
                        return ["code" => 0, "message" => "操作不成功，请再试一次", "result" => $api_result_json, "result_confirm" => $result_confirm];
                    }
                }
            }
        }
        $Redis->set("Operation".$full_imei,1,3);
        $time_end = $this->microtimeFloat();
        $second = round($time_end - $time_start,2);
        \Think\Log::write("车辆操控，".$operation_type_text."请求网关：" . $this->url, "INFO");
        \Think\Log::write("车辆操控，".$operation_type_text."参数：" . json_encode($parameters)."，耗时：".$second."，结果：".json_encode($api_result_json), "INFO");
        \Think\Log::write("盒子IMEI:".$imei.",网关返回结果:".$msg, "INFO");
        if ($api_result == true) {
            //此处不再添加其他任何业务的处理
            return ["code" => 1, "message" =>$msg,"result"=>$api_result_json,"result_confirm"=>$result_confirm,"second"=>$second,"plate_no"=>$plate_no];
        } else {
            return ["code" => 0, "message" => $msg,"result"=>$api_result_json,"result_confirm"=>$result_confirm,"second"=>$second,"plate_no"=>$plate_no];
        }
    }

    /**车辆操控
     * @param $user_id  员工id
     * @param $rent_content_id 车辆id
     * @param $operation_type 操作类型 4=鸣笛 5=设防 6=撤防 7=启动 30=唤醒 33=断电 34=开舱锁 36=锁舱锁
     * @param $gis_lng 手机定位，高德坐标经度
     * @param $gis_lat 手机定位，高德坐标纬度
     * @return array 返回结果
     */
    public function controlTest($corporation_id,$user_id,$rent_content_id,$operation_type,$gis_lng,$gis_lat)
    {
        $time_start = $this->microtimeFloat();
        if (empty($user_id)||empty($rent_content_id)||empty($operation_type)||empty($corporation_id)) {
            return ["code" => -100, "message" => "参数不完整"];
        }
        if ($this->userAuth($corporation_id,$corporation_id)<1) {
            return ["code" => -2, "message" => "你没有管理这辆车的权限"];
        }
        $parameters["user_id"]=$user_id;
        $parameters["rent_content_id"]=$rent_content_id;
        $parameters["operation_type"]=$operation_type;
        $parameters["gis_lng"]=$gis_lng;
        $parameters["gis_lat"]=$gis_lat;
        $rent_info = M("rent_content")->where(["id" => $rent_content_id])->find();
        if (!$rent_info) {
            return ["code" => -3, "message" => "车辆不存在"];
        }
        $plate_no = $this->getPlateNo($rent_info["car_item_id"]);
        $imei = $this->getImeiTest($rent_info["car_item_id"]);
        if(empty($imei)){
            return ["code" => -4, "message" => "未绑定盒子imei"];
        }
        $full_imei = $imei;
        $imei = ltrim($imei, "0");
        $api_result = true;
        $msg="操作成功";
        if($operation_type == 4){//鸣笛
            $operation_type_text = "鸣笛";
            $api_result_json = $this->whistle($imei);
        }elseif ($operation_type == 5) {//设防
            $operation_type_text = "设防";
            $api_result_json = $this->lock($imei);
        }elseif ($operation_type == 6) {//撤防
            $operation_type_text = "撤防";
            $api_result_json = $this->unlock($imei);
        }elseif ($operation_type == 7) {//启动
            $operation_type_text = "启动";
            $api_result_json = $this->accon($imei);
        }elseif($operation_type == 30){//唤醒
            $operation_type_text = "唤醒";
            $api_result_json = $this->awaken($imei);
        }elseif ($operation_type == 33) {//断电
            $operation_type_text = "断电";
            $api_result_json = $this->accoff($imei);
        }elseif ($operation_type == 34) {//开舱锁
            $operation_type_text = "开舱锁";
            //不判断距离直接开舱锁---------------------------------------------------------
            $api_result_json = $this->door($imei);
        }elseif($operation_type == 36){
            $api_result_json = $this->lockDoor($imei);
        }

        if(empty($api_result_json)){
            $time_end = $this->microtimeFloat();
            $second = round($time_end - $time_start,2);
            \Think\Log::write($operation_type_text.$imei.",接口请求网关超时：".$second . $this->url, "INFO");
            return ["code" => 0, "message" => "接口请求网关超时", "result" => $api_result_json];
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
                    $msg="盒子返回失败";
                    break;
                case "3":
                    $msg="该车盒子离线，操作失败";
                    break;
                case "4":
                    $msg="网关访问盒子超时，请重试";
                    break;
                case "5":
                    $msg="盒子没返回或已经超时";
                    break;
                case "6":
                    $msg="命令重复";
                    break;
            }
        }
        $time_end = $this->microtimeFloat();
        $second = round($time_end - $time_start,2);
        \Think\Log::write("车辆操控，".$operation_type_text.$imei."请求网关：" . $this->url, "INFO");
        \Think\Log::write("车辆操控，".$operation_type_text.$imei.",参数：" . json_encode($parameters)."，耗时：".$second."，结果：".json_encode($api_result_json), "INFO");
        if ($api_result == true) {
            //此处不再添加其他任何业务的处理
            return ["code" => 1, "message" =>$msg,"result"=>$api_result_json,"second"=>$second,"plate_no"=>$plate_no];
        } else {
            return ["code" => 0, "message" => $msg,"result"=>$api_result_json,"second"=>$second,"plate_no"=>$plate_no];
        }
    }

    //操作类型 4=鸣笛 5=设防 6=撤防 7=启动 30=唤醒 33=断电 34=开舱锁 36=锁舱锁
    public function controlByImei($imei,$operation_type)
    {
        $time_start = $this->microtimeFloat();
        if (empty($imei)) {
            return ["code" => -100, "message" => "参数不完整"];
        }
        $parameters["imei"]=$imei;
        $parameters["operation_type"]=$operation_type;
        $this->getDeviceType($imei);
        $no_zero_imei = ltrim($imei, "0");
        $Redis = new \Redis();
        $Redis->pconnect('10.1.11.83', 36379, 0.5);
        $Redis->AUTH('oXjS2RCA1odGxsv4');
        $Redis->SELECT(2);
        if( $Redis->get("Operation".$imei) ){
            return ['code' =>-7, 'message' => '别急，正在处理，请勿重复操作'];
        }
        if($operation_type == 4){//鸣笛
            $operation_type_text = "鸣笛";
            $api_result_json = $this->whistle($no_zero_imei);
        }elseif ($operation_type == 5) {//设防
            $operation_type_text = "设防";
            $api_result_json = $this->lock($no_zero_imei);
        }elseif ($operation_type == 6) {//撤防
            $operation_type_text = "撤防";
            $api_result_json = $this->unlock($no_zero_imei);
        }elseif ($operation_type == 7) {//启动
            $operation_type_text = "启动";
            $api_result_json = $this->accon($no_zero_imei);
        }elseif($operation_type == 30){//唤醒
            $operation_type_text = "唤醒";
            $api_result_json = $this->awaken($no_zero_imei);
        }elseif ($operation_type == 34) {//开舱锁
            $operation_type_text = "开舱锁";
            if($this->device_type == 18) {//小安盒子
                $api_result_json = $this->open_battery($no_zero_imei);
            }else{//老盒子
                $api_result_json["rtCode"]="0";
            }
        }

        if(empty($api_result_json)){
            $time_end = $this->microtimeFloat();
            $second = round($time_end - $time_start,2);
            \Think\Log::write($operation_type_text.$imei.",接口请求网关超时：".$second . $this->url, "INFO");
            return ["code" => 0, "message" => "接口请求网关超时", "result" => $api_result_json];
        }

        \Think\Log::write("车辆操控，".$operation_type_text.$imei.",参数：" . json_encode($parameters)."，结果：".json_encode($api_result_json), "INFO");
        $msg="操作成功";
        if ($api_result_json["rtCode"] != "0") {
            $api_result = false;
            switch($api_result_json["rtCode"]){
                case "1":
                    $msg="盒子返回失败";
                    break;
                case "3":
                    $msg="该车盒子离线，操作失败";
                    break;
                case "4":
                    $msg="网关访问盒子超时，请重试";
                    break;
                case "5":
                    $msg="盒子没返回或已经超时";
                    break;
                case "6":
                    $msg="命令重复";
                    break;
            }
        }else{
            if($this->device_type == 18) {
                if($api_result_json["msgkey"]) {
                    $cData['carId'] = $no_zero_imei;
                    $cData['key'] = $api_result_json["msgkey"];
                    $cData['cmd'] = 'resultQuery';
                    for ($i = 0; $i < 10; $i++) {
                        $result_confirm = $this->post($cData);
                        \Think\Log::write("盒子IMEI:" . $imei . ",第" . $i . "次,网关确认返回结果:" . json_encode($result_confirm) . ",参数:" . json_encode($cData), "INFO");
                        if ($result_confirm['rtCode'] == '0') {
                            $api_result = true;
                            break;
                        } else {
                            sleep(1);
                        }
                    }
                    if ($result_confirm['rtCode'] != '0') {
                        return ["code" => 0, "message" => "操作不成功，请再试一次", "result" => $api_result_json, "result_confirm" => $result_confirm];
                    }
                }
            }
        }
        $Redis->set("Operation".$imei,1,3);
        $time_end = $this->microtimeFloat();
        $second = round($time_end - $time_start,2);
        \Think\Log::write("车辆操控，".$operation_type_text."请求网关：" . $this->url, "INFO");
        \Think\Log::write("车辆操控，".$operation_type_text."参数：" .$imei.",".$operation_type."，耗时：".$second."，结果：".json_encode($api_result_json), "INFO");
        if ($api_result == true) {
            return ["code" => 1, "message" =>$msg,"result"=>$api_result_json,"second"=>$second];
        } else {
            return ["code" => 0, "message" => $msg,"result"=>$api_result_json,"second"=>$second];
        }
    }

    //操作类型 4=鸣笛 5=设防 6=撤防 7=启动 30=唤醒 33=断电 34=开舱锁 36=锁舱锁
    public function controlByPlate($plate_no,$operation_type)
    {
        $time_start = $this->microtimeFloat();
        if (empty($plate_no)||empty($operation_type)) {
            return ["code" => -100, "message" => "参数不完整"];
        }
        $imei=$this->getImeiByPlate($plate_no);
        $parameters["plate_no"]=$plate_no;
        $parameters["imei"]=$imei;
        $parameters["operation_type"]=$operation_type;
        $this->getDeviceType($imei);
        $no_zero_imei = ltrim($imei, "0");
        $Redis = new \Redis();
        $Redis->pconnect('10.1.11.83', 36379, 0.5);
        $Redis->AUTH('oXjS2RCA1odGxsv4');
        $Redis->SELECT(2);
        if( $Redis->get("Operation".$imei) ){
            return ['code' =>-7, 'message' => '别急，正在处理，请勿重复操作'];
        }
        if($operation_type == 4){//鸣笛
            $operation_type_text = "鸣笛";
            $api_result_json = $this->whistle($no_zero_imei);
        }elseif ($operation_type == 5) {//设防
            $operation_type_text = "设防";
            $api_result_json = $this->lock($no_zero_imei);
        }elseif ($operation_type == 6) {//撤防
            $operation_type_text = "撤防";
            $api_result_json = $this->unlock($no_zero_imei);
        }elseif ($operation_type == 7) {//启动
            $operation_type_text = "启动";
            $api_result_json = $this->accon($no_zero_imei);
        }elseif($operation_type == 30){//唤醒
            $operation_type_text = "唤醒";
            $api_result_json = $this->awaken($no_zero_imei);
        }elseif ($operation_type == 33) {//断电
            $operation_type_text = "断电";
            $api_result_json = $this->accoff($no_zero_imei);
        }elseif ($operation_type == 34) {//开舱锁
            $operation_type_text = "开舱锁";
            if($this->device_type == 18) {//小安盒子
                $api_result_json = $this->open_battery($no_zero_imei);
            }else{//老盒子
                $api_result_json["rtCode"]="0";
            }
        }

        if(empty($api_result_json)){
            $time_end = $this->microtimeFloat();
            $second = round($time_end - $time_start,2);
            \Think\Log::write($operation_type_text.$imei.",接口请求网关超时：".$second . $this->url, "INFO");
            return ["code" => 0, "message" => "接口请求网关超时", "result" => $api_result_json];
        }

        \Think\Log::write("车辆操控，".$operation_type_text."参数：" . json_encode($parameters)."，结果：".json_encode($api_result_json), "INFO");
        $msg="操作成功";
        if ($api_result_json["rtCode"] != "0") {
            $api_result = false;
            switch($api_result_json["rtCode"]){
                case "1":
                    $msg="盒子返回失败";
                    break;
                case "3":
                    $msg="该车盒子离线，操作失败";
                    break;
                case "4":
                    $msg="网关访问盒子超时，请重试";
                    break;
                case "5":
                    $msg="盒子没返回或已经超时";
                    break;
                case "6":
                    $msg="命令重复";
                    break;
            }
        }else{
            if($this->device_type == 18) {
                if($api_result_json["msgkey"]) {
                    $cData['carId'] = $no_zero_imei;
                    $cData['key'] = $api_result_json["msgkey"];
                    $cData['cmd'] = 'resultQuery';
                    for ($i = 0; $i < 10; $i++) {
                        $result_confirm = $this->post($cData);
                        \Think\Log::write("盒子IMEI:" . $imei . ",第" . $i . "次,网关确认返回结果:" . json_encode($result_confirm) . ",参数:" . json_encode($cData), "INFO");
                        if ($result_confirm['rtCode'] == '0') {
                            $api_result = true;
                            break;
                        } else {
                            sleep(1);
                        }
                    }
                    if ($result_confirm['rtCode'] != '0') {
                        return ["code" => 0, "message" => "操作不成功，请再试一次", "result" => $api_result_json, "result_confirm" => $result_confirm];
                    }
                }
            }
        }
        $Redis->set("Operation".$imei,1,3);
        $time_end = $this->microtimeFloat();
        $second = round($time_end - $time_start,2);
        \Think\Log::write("车辆操控，".$operation_type_text."请求网关：" . $this->url, "INFO");
        \Think\Log::write("车辆操控，".$operation_type_text."参数：" .$imei.",".$operation_type."，耗时：".$second."，结果：".json_encode($api_result_json), "INFO");
        if ($api_result == true) {
            return ["code" => 1, "message" =>$msg,"result"=>$api_result_json,"second"=>$second];
        } else {
            return ["code" => 0, "message" => $msg,"result"=>$api_result_json,"second"=>$second];
        }
    }

    //设防 完整参数{"carId":"865067026xxxxxx","type":1,"command":14,"cmd":"carControl","internalOp":"operation"}
    public function lock($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '3';
        if($this->device_type == 18){
            $data["type"] = '1';
            $data["command"] = 4;
        }
        $r = $this->post($data);
        return $r;
    }

    //撤防
    public function unlock($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '4';
        if($this->device_type == 18){
            //小安盒子
            $data["type"] = '0';
            $data["command"] = 4;
        }
        $r = $this->post($data);
        return $r;
    }

    //启动
    public function accon($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '1';
        if($this->device_type == 18){
            $data["command"] = 90;
        }
        $r = $this->post($data);
        return $r;
    }

    //断电
    public function accoff($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '0';
        if($this->device_type == 18){
            $data["command"] = 90;
        }
        $r = $this->post($data);
        return $r;
    }

    //鸣笛
    public function whistle($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data['type'] = '2';
        if($this->device_type == 18){
            $data['command'] = 14;
            $data['type'] = '5';
        }
        $r = $this->post($data);
        return $r;
    }

    //开仓
    public function open_battery($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data["type"] = 0;
        $data["command"] = 29;
        $r = $this->post($data);
        return $r;
    }

    //关仓
    public function close_battery($imei)
    {
        $data['carId'] = $imei;
        $data['cmd'] = 'carControl';
        $data["type"] = 1;
        $data["command"] = 29;
        $r = $this->post($data);
        return $r;
    }

    //唤醒
    public function awaken($imei)
    {
        $data['carId'] = $imei;
        $data["command"] = 21;
        $data["type"] = 1;
        $data["cmd"] ="carControl";
        $r = $this->post($data);
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
        }
        $r = $this->post($data);
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
        }
        $r = $this->post($data);
        return $r;
    }

    //网关post请求
    private function post($data)
    {
        $data["internalOp"] = 'operation';
        $postUrl = $this->url;
        $sign = $this->getSign3($data, $this->key);
        $data['sign'] = $sign['sign'];
        $json = json_encode($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,20);
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

    //车辆及管理人权限判断
    public function userAuth($corporation_id,$rent_content_id){
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

    //根据car_item_id查询车牌号
    public function getPlateNo($car_item_id){
        $plate_no = M("car_item_verify")
            ->where(["car_item_id"=>$car_item_id])
            ->getField("plate_no");
        return $plate_no;
    }

    //根据car_item_id查询imei
    private function getImei($car_item_id){
        $map = ["car_item_id"=>$car_item_id];
        $map["device_type"] = ["in",[12,14,16,9,18]];
        $device_info = M("car_item_device")->where($map)->Field("device_type,imei")->find();
        if($device_info["device_type"] == 16){//XM1盒子 http://yd.zykuaiche.com:81/s/service
            $this->url =$this->url_xm1;
        }
        if($device_info["device_type"] == 14){//小马盒子 http://bike.zykuaiche.cn:82/simulate
            $this->url =$this->url_xiaoma;
        }
        if($device_info["device_type"] == 18){//小安 http://wg.baojia.com/simulate/service
            //$this->url =$this->url_xiaoan;
            $this->url =$this->url_xiaoan=C("GATEWAY_LINK");
        }
        $this->device_type = $device_info["device_type"];
        return $device_info["imei"] ? $device_info["imei"] : "";
    }

    private function getDeviceType($imei){
        $map = ["imei"=>$imei];
        $device_info = M("car_item_device")->where($map)->Field("device_type,imei")->find();
        if($device_info&&$device_info["device_type"]) {
            if ($device_info["device_type"] == 16) {//XM1盒子 http://yd.zykuaiche.com:81/s/service
                $this->url = $this->url_xm1;
            }
            if ($device_info["device_type"] == 18) {//小安 http://wg.baojia.com/simulate/service
                $this->url = $this->url_xiaoan;
            }
            $this->device_type = $device_info["device_type"];
        }else{
            $this->device_type =18;
            $this->url = $this->url_xiaoan;
        }
        return $device_info["imei"] ? $device_info["imei"] : "";
    }

    private function getImeiTest($car_item_id){
        $map = ["car_item_id"=>$car_item_id];
        $map["device_type"] = ["in",[12,14,16,9,18]];
        $device_info = M("baojia.car_item_device")->where($map)->find();
        if($device_info["device_type"] == 16){//XM1盒子 http://yd.zykuaiche.com:81/s/service
            $this->url =$this->url_xm1;
        }
        if($device_info["device_type"] == 14){//小马盒子 http://bike.zykuaiche.cn:82/simulate
            $this->url =$this->url_xiaoma;
        }
        if($device_info["device_type"] == 18){//小安 http://123.57.173.14:8107/simulate/servic
            $this->url ="http://123.57.173.14:8107/simulate/service";
        }
        $this->device_type = $device_info["device_type"];
        return $device_info["imei"] ? $device_info["imei"] : "";
    }

    private function getImeiByPlate($plate_no){
        $map = ["plate_no"=>$plate_no];
        $map["device_type"] = ["in",[12,14,16,9,18]];
        $device_info = M('car_item_verify')->alias('civ')
            ->field('cid.imei,cid.device_type')
            ->join('car_item_device cid ON civ.car_item_id=cid.car_item_id', 'left')
            ->where($map)
            ->find();
        if($device_info["device_type"] == 16){//XM1盒子 http://yd.zykuaiche.com:81/s/service
            $this->url =$this->url_xm1;
        }
        if($device_info["device_type"] == 14){//小马盒子 http://bike.zykuaiche.cn:82/simulate
            $this->url =$this->url_xiaoma;
        }
        if($device_info["device_type"] == 18){//小安 http://123.57.173.14:8107/simulate/servic
            $this->url ="http://123.57.173.14:8107/simulate/service";
        }
        $this->device_type = $device_info["device_type"];
        return $device_info["imei"] ? $device_info["imei"] : "";
    }

    //根据imei查询车辆定位
    public function gpsStatusInfo($imei){
        $info = M("baojia_box.gps_status",null,$this->box_config)->where(["imei"=>$imei])->find();
        $gps=D('Gps');
        $gd = $gps->gcj_encrypt($info["latitude"],$info["longitude"]);
        $info["gd_latitude"] = $gd["lat"];
        $info["gd_longitude"] = $gd["lon"];
        $bd = $gps->bd_encrypt($info["gd_latitude"],$info["gd_longitude"]);
        $info["bd_latitude"] = $bd["lat"];
        $info["bd_longitude"] = $bd["lon"];
        return $info;
    }

    public function isXiaomaInArea($rent_content_id){
        $car_item_id=M("rent_content")->where(["id"=>$rent_content_id])->getField("car_item_id");
        $map = ["car_item_id"=>$car_item_id];
        $car_item_device = M("car_item_device")->where($map)->find();
        $imei = $car_item_device["imei"];
        $info = M("baojia_box.gps_status",null,$this->box_config)->where(["imei"=>$imei])->field("id,imei,latitude,longitude,datetime,lastonline")->find();
        $pt=[$info['longitude'],$info['latitude']];
        //echo "<pre>";print_r($pt);
        $no_zero_imei = ltrim($imei,"0");
        $aliyun_config = "mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc";
        $ga_list_sql = "SELECT ga.lat,ga.lng FROM car_group_r cgr
                        INNER JOIN `group` g ON g.id = cgr.groupId INNER JOIN group_area ga ON ga.groupId = g.id
                        WHERE cgr.carId = '{$no_zero_imei}' AND cgr.`status` = 1 AND g.`status` = 1 ORDER BY ga. NO;";
        $list = M("",null,$aliyun_config)->query($ga_list_sql);
        //echo "<pre>";print_r($list);
        if($list){
            $poly = [];
            foreach ($list as $key => $value) {
                $poly[] = [$value["lng"],$value["lat"]];
            }
            //echo "<pre>";print_r($poly);
            if(count($poly) > 3 && $pt[0] > 0 && $pt[1] > 0){
                $result = $this->isInsidePolygon($pt,$poly);
                return $result;
            }
        }
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

    //计算距离
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

    //当前时间毫秒数
    public function microtimeFloat()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
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
}