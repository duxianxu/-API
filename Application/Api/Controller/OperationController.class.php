<?php


/**
 * Created by PhpStorm.
 * User: CHI
 * Date: 2017/7/21
 * Time: 16:04
 */
namespace Api\Controller;

use Think\Controller;
use Think\Exception;

set_time_limit(31);

class OperationController extends BController {



    private $box_config ='';
    protected $device_type = 0;//16 XM1盒子 18 小安盒子
    private $diff_days=2;
    private $manual_url ="http://xmtest.baojia.com/api/index/tasklist";

    /**
     * 接口调试页面
     */
    public function index()
    {
        //phpinfo();die;
        $details = new \Api\Model\DetailsModel();
        $ip=$this->getIp();
        $statistics_url=$details->getAuth();
        $this->assign("current_time",time());
        $this->assign('statistics_url', $statistics_url);
        $this->assign('ip',$ip);
        $this->display('index');
    }

    /**
     * websocket 调试页面
     */
    public function socket()
    {
        $this->display('socket');
    }

    /**
     * 测试方法
     */
    public function test(){
M()->query("DELETE FROM baojia_mebike.repair_member WHERE id=603");

           }

    /**
     * 测试超时方法
     * @param int $user_id
     */
    public function TestGetUserInfo($user_id=2630751)
    {
        try {
            $time_start = $this->microtimeFloat();

            sleep(6);

            if ($user_id) {
                $user = M('baojia_mebike.repair_member')->alias('a')
                    ->field("a.id,a.user_id,a.user_name,a.job_type,CASE WHEN a.job_type=1 THEN '全职'  ELSE '兼职' END job_type_text,a.status,CASE WHEN a.manager_position_id=0 THEN '员工' ELSE '员工' END manager_position,b.mobile,CASE WHEN a.role_type=1 THEN '运维' WHEN a.role_type=2 THEN '调度' WHEN a.role_type=3 THEN '整备' WHEN a.role_type=4 THEN '库管' ELSE '运维' END role_type_text,a.role_type")
                    ->join('ucenter_member b on a.user_id = b.uid', 'left')
                    ->join('corporation cor on cor.id=a.corporation_id', 'left')
                    ->where("b.uid={$user_id}")
                    ->find();
                $time_end = $this->microtimeFloat();
                $second = round($time_end - $time_start, 2);
                if ($user) {
                    $user["manual_url"] = $this->manual_url;
                    $this->ajaxReturn(["code" => 1, "second" => $second, "message" => "加载数据成功", "user" => $user], 'json');
                } else {
                    $this->ajaxReturn(["code" => 0, "second" => $second, "message" => "账号不存在"], 'json');
                }
            } else {
                $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            //$this->ajaxReturn(["code" => -100, "message" => "参数不完整","exception"=>$exception], 'json');
        }
    }

    /**
     * 小蜜接口测试页面
     */
    public function xmtest()
    {
        $this->display('xmtest');
    }

    /**
     * 查询任务分类
     * @param int $corporation_id
     * @param int $city_id
     * @param int $user_id
     * @param string $brand_id
     * @param int $test
     */
    public function GetTaskType($corporation_id=7159,$city_id=1,$user_id=2630751,$brand_id="",$uuid="aaa",$test=0)
    {
        if (empty($city_id)) {
            $this->ajaxReturn(["code" => -100, "message" => "请重新选择运营公司或重新登录"], 'json');
        }
        if (empty($city_id) || empty($user_id) || empty($corporation_id)) {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
        $this->check_terminal($user_id, $uuid);
        $time_start = $this->microtimeFloat();
        $search = new \Api\Logic\SearchInfo();
        $user_type = $search->getUserType($user_id, $corporation_id);
        $taskSearch = new \Api\Logic\TaskSearch();
        $rent_content = $taskSearch->getXiaomi($city_id, $corporation_id);
        $sql_time_end = $this->microtimeFloat();
        $order_content = $taskSearch->getTwoDayNoOrder($city_id, $corporation_id);
        $order_content5 = $taskSearch->getFiveDayNoOrder($city_id, $corporation_id);
        $unstable=$taskSearch->getUnstable($corporation_id,$city_id);
        $invalid=$taskSearch->getInvalid($corporation_id,$city_id);
        $in_renting=$taskSearch->getInRenting($corporation_id);
        $changed=$taskSearch->getChanged($corporation_id);
        $inspectioned=$taskSearch->getInspectioned($corporation_id);
        $search_end = $this->microtimeFloat();
        //$redis = new \Redis();
        //$redis->pconnect(C("COMMON_REDIS_HOST"), C("COMMON_REDIS_PORT"),0.5);

        //查询已派给他人和他人已抢单的车辆
        $dispatched =$taskSearch->getDispatched($corporation_id,$user_id);
        $result["code1"] = 0;             //1  低电
        $result["code2"] = 0;             //2  缺电
        $result["code3"] = 0;             //3  馈电
        $result["code4"] = 0;             //4  ⽆电在线
        $result["code5"] = 0;             //5  无电离线
        $result["code7"] = 0;             //7  两日无单
        $result["code8"] = 0;             //8  有单无程
        $result["code10"] = 0;            //10 越界
        $result["code15"] = 0;            //15 存在故障
        $result["code16"] = 0;            //16 已寻回
        $result["code17"] = 0;            //17 无效盒子
        $result["code18"] = 0;            //18 非稳盒子
        $result["code19"] = 0;            //19 还车区域外
        $result["code20"] = 0;            //20 已检修
        $result["code21"] = 0;            //21 五日无单
        $result["code22"] = 0;            //22 4级寻车
        $result["code23"] = 0;            //23 3级寻车
        $result["code24"] = 0;            //24 2级寻车
        $result["code25"] = 0;            //25 1级寻车
        $result["code26"] = 0;            //26 用户上报
        $result["code27"] = 0;            //27 有电离线
        $r = [];
        if ($user_type) {
            if ($rent_content) {
                foreach ($rent_content as $k => $v) {
                    //排除出租中的车辆
                    if (in_array($v["rent_content_id"], $in_renting)) {
                        unset($rent_content[$k]);
                        continue;
                    }
                    //根据车品牌查询
                    if (!empty($brand_id) && ($brand_id != $v["model_id"])) {
                        unset($rent_content[$k]);
                        continue;
                    }
                    //非整备
                    if ($user_type["role_type"] != 3) {
                        //去除派给他人和被他人抢单的
                        if (in_array($v["rent_content_id"], $dispatched)) {
                            unset($rent_content[$k]);
                            continue;
                        }
                    }
                    //寻车
                    if ($v['search_status'] > 0) {
                        switch ($v['search_status']) {
                            case 100:
                                $result["code25"]++;
                                break;
                            case 200:
                                $result["code24"]++;
                                break;
                            case 300:
                                $result["code23"]++;
                                break;
                            case 400:
                                $result["code22"]++;
                                break;
                        }
                    }
                    else {
                        //查询车辆是否已经换电，Redis有效时间换电后1小时
                        //$Key =C("KEY_PREFIX")."changed_rent_content_id:".$v['rent_content_id'];
                        //$changed =$redis->get($Key);

                        //$inspectioned_key  = C("KEY_PREFIX"). "inspectioned_rent_content_id:".$v['rent_content_id'];
                        //$inspectioned =$redis->get($inspectioned_key);

                        //回库(存在故障、已寻回、无效盒子、非稳盒子)
                        if($invalid&&in_array($v['rent_content_id'], $invalid)){
                            $result["code17"]++;            //17 无效盒子
                        }
                        elseif($unstable&&in_array($v['rent_content_id'], $unstable)){
                            $result["code18"]++;            //18 非稳盒子
                        }
                        //已寻回 repaire_status 维修状态 0-不需要修 100-小修 200-大修 300-寻回需维修
                        elseif($v['repaire_status'] == 300) {
                            //已寻回
                            $result["code16"]++;
                        }
                        //存在故障 repaire_status 维修状态 0-不需要修 100-小修 200-大修 300-寻回需维修
                        elseif ($v['repaire_status'] == 200) {
                            //存在故障需回库
                            $result["code15"]++;
                        }

                        //换电(低电、缺电、馈电、无电离线) lack_power_status
                        //馈电状态 0-正常 100-低电 200-缺电 300-馈电 400-无电
                        elseif($v["lack_power_status"] == 100&&!in_array($v["rent_content_id"],$changed)) {//&&empty($changed)
                            //低电 待租且电量大于40%小于等于50%
                            $result["code1"]++;
                        }
                        elseif($v["lack_power_status"] == 200&&!in_array($v["rent_content_id"],$changed)) {//&&empty($changed)
                            //缺电 待租且电量大于20%小于等于40%
                            $result["code2"]++;
                        }
                        elseif($v['offline_status'] ==0 && $v["lack_power_status"] == 400&&!in_array($v["rent_content_id"],$changed)) {//&&empty($changed)
                            //无电在线 4 在线且电量为0
                            $result["code4"]++;
                        }
                        //馈电
                        elseif($v["lack_power_status"] == 300&&!in_array($v["rent_content_id"],$changed)) {//&&empty($changed)
                            //馈电
                            $result["code3"]++;
                        }
                        //无电离线 offline_status 离线状态 0-在线 100-离线
                        elseif($v['offline_status'] == 100 && $v["lack_power_status"] == 400&&!in_array($v["rent_content_id"],$changed)) {//&&empty($changed)
                            //echo $v['rent_content_id'];
                            //无电离线 -1 电量低于等于10%且离线
                            $result["code5"]++;
                        }

                        //调度(越界、还车区域外、已检修、2日无单、5日无单)
                        //五日无单
                        elseif($order_content5 && in_array($v['rent_content_id'], $order_content5)) {
                            $result["code21"]++;
                        }
                        //两日无单
                        elseif($order_content && in_array($v['rent_content_id'], $order_content)) {
                            $result["code7"]++;
                        }
                        //越界 out_status 越界状态 0-界内 100-网点外 200-界外
                        elseif($v['out_status'] == 200) {
                            //越界
                            $result["code10"]++;
                        }
                        //还车区域外 out_status 越界状态 0-界内 100-网点外 200-界外
                        elseif($v['out_status'] == 100) {
                            //还车区域外
                            $result["code19"]++;
                        }

                        //检修(用户上报、有电离线、有单无程) 寻车>回库>调度>换电>检修
                        //用户上报 repaire_status 维修状态 0-不需要修 100-小修 200-大修
                        elseif($v["repaire_status"] == 100) {
                            $result["code26"]++;
                        }
                        //有电离线 offline_status 离线状态 0-在线 100-离线
                        elseif($v['offline_status'] == 100 && $v["lack_power_status"] != 400) {
                            //echo $v['rent_content_id'];
                            $result["code27"]++;
                        }
                        //有单无程
                        elseif(($v['sell_status'] == 1||$v["reserve_status"]==100) && $v['no_mileage_num'] == 3&&!in_array($v["rent_content_id"],$inspectioned)) {//&&empty($inspectioned)
                            $result["code8"]++;
                        }
                    }
                }
                $status_time_end = $this->microtimeFloat();
                //寻⻋任务1>回库任务2>调度任务3>换电任务4>检修任务5
                //1	寻车 2	回库 3	调度 4	换电 5	检修
                $task_type = $taskSearch->getTaskType($corporation_id);
                if ($task_type) {
                    $r["find"]=$r["storage"]=$r["dispatch"]=$r["change"]=$r["overhaul"]=[];
                    foreach ($task_type as $k => $v) {
                        $price=intval($v["price"]);
                        //整备
                        if ($user_type["job_type"] == 3) {
                            //寻⻋任务
                            if($v["task_id"]==1) {
                                switch ($v['code_id']) {
                                    case 25:
                                        array_push($r["find"], ["rent_status" => 25, "count" => $result["code25"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 24:
                                        array_push($r["find"], ["rent_status" => 24, "count" => $result["code24"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 23:
                                        array_push($r["find"], ["rent_status" => 23, "count" => $result["code23"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 22:
                                        array_push($r["find"], ["rent_status" => 22, "count" => $result["code22"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                }
                            }
                            //回库任务
                            if($v["task_id"]==2) {
                                switch ($v['code_id']) {
                                    case 15:
                                        array_push($r["storage"], ["rent_status" => 15, "count" => $result["code15"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 16:
                                        array_push($r["storage"], ["rent_status" => 16, "count" => $result["code16"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 17:
                                        array_push($r["storage"], ["rent_status" => 17, "count" => $result["code17"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 18:
                                        array_push($r["storage"], ["rent_status" => 18, "count" => $result["code18"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 4:
                                        array_push($r["storage"], ["rent_status" => 4, "count" => $result["code4"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 5:
                                        array_push($r["storage"], ["rent_status" => 5, "count" => $result["code5"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                }
                            }
                            //换电任务
                            if($v["task_id"]==4) {
                                switch ($v['code_id']) {
                                    case 1:
                                        array_push($r["change"], ["rent_status" => 1, "count" => $result["code1"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 2:
                                        array_push($r["change"], ["rent_status" => 2, "count" => $result["code2"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 3:
                                        array_push($r["change"], ["rent_status" => 3, "count" => $result["code3"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 4:
                                        array_push($r["change"], ["rent_status" => 4, "count" => $result["code4"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 5:
                                        array_push($r["change"], ["rent_status" => 5, "count" => $result["code5"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                }
                            }
                            //调度任务
                            if($v["task_id"]==3) {
                                switch ($v['code_id']) {
                                    case 7:
                                        array_push($r["dispatch"], ["rent_status" => 7, "count" => $result["code7"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 21:
                                        array_push($r["dispatch"], ["rent_status" => 21, "count" => $result["code21"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 10:
                                        array_push($r["dispatch"], ["rent_status" => 10, "count" => $result["code10"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 19:
                                        array_push($r["dispatch"], ["rent_status" => 19, "count" => $result["code19"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 20:
                                        array_push($r["dispatch"], ["rent_status" => 20, "count" => $result["code20"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                }
                            }
                            //检修任务
                            if($v["task_id"]==5) {
                                switch ($v['code_id']) {
                                    case 8:
                                        array_push($r["overhaul"], ["rent_status" => 8, "count" => $result["code8"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 26:
                                        array_push($r["overhaul"], ["rent_status" => 26, "count" => $result["code26"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 27:
                                        array_push($r["overhaul"], ["rent_status" => 27, "count" => $result["code27"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                }
                            }
                        }else{
                            //寻⻋任务
                            if($v["task_id"]==1&&$v["show"]==1) {
                                //22 4级寻车   23 3级寻车    24 2级寻车    25 1级寻车
                                switch ($v['code_id']) {
                                    case 22:
                                        array_push($r["find"], ["rent_status" => 22, "count" => $result["code22"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 23:
                                        array_push($r["find"], ["rent_status" => 23, "count" => $result["code23"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 24:
                                        array_push($r["find"], ["rent_status" => 24, "count" => $result["code24"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 25:
                                        array_push($r["find"], ["rent_status" => 25, "count" => $result["code25"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                }
                            }
                            //回库任务
                            if($v["task_id"]==2&&$v["show"]==1) {
                                switch ($v['code_id']) {
                                    case 15:
                                        array_push($r["storage"], ["rent_status" => 15, "count" => $result["code15"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 16:
                                        array_push($r["storage"], ["rent_status" => 16, "count" => $result["code16"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 17:
                                        array_push($r["storage"], ["rent_status" => 17, "count" => $result["code17"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 18:
                                        array_push($r["storage"], ["rent_status" => 18, "count" => $result["code18"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 4:
                                        array_push($r["storage"], ["rent_status" => 4, "count" => $result["code4"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 5:
                                        array_push($r["storage"], ["rent_status" => 5, "count" => $result["code5"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                }
                            }
                            //换电任务
                            if($v["task_id"]==4&&$v["show"]==1) {
                                switch ($v['code_id']) {
                                    case 1:
                                        array_push($r["change"], ["rent_status" => 1, "count" => $result["code1"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 2:
                                        array_push($r["change"], ["rent_status" => 2, "count" => $result["code2"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 3:
                                        array_push($r["change"], ["rent_status" => 3, "count" => $result["code3"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 4:
                                        array_push($r["change"], ["rent_status" => 4, "count" => $result["code4"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 5:
                                        array_push($r["change"], ["rent_status" => 5, "count" => $result["code5"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                }
                            }
                            //调度任务
                            if($v["task_id"]==3&&$v["show"]==1) {
                                switch ($v['code_id']) {
                                    case 7:
                                        array_push($r["dispatch"], ["rent_status" => 7, "count" => $result["code7"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 21:
                                        array_push($r["dispatch"], ["rent_status" => 21, "count" => $result["code21"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 10:
                                        array_push($r["dispatch"], ["rent_status" => 10, "count" => $result["code10"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 19:
                                        array_push($r["dispatch"], ["rent_status" => 19, "count" => $result["code19"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 20:
                                        array_push($r["dispatch"], ["rent_status" => 20, "count" => $result["code20"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                }
                            }
                            //检修任务
                            if($v["task_id"]==5&&$v["show"]==1) {
                                switch ($v['code_id']) {
                                    case 8:
                                        array_push($r["overhaul"], ["rent_status" => 8, "count" => $result["code8"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 26:
                                        array_push($r["overhaul"], ["rent_status" => 26, "count" => $result["code26"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                    case 27:
                                        array_push($r["overhaul"], ["rent_status" => 27, "count" => $result["code27"], "title" => $v["status_title"], "money" =>$price]);
                                        break;
                                }
                            }
                        }
                    }
                }
                $r["storage"]=array_values($taskSearch->arraySort($r["storage"], 'rent_status', 'asc'));
                $r["overhaul"]=array_values($taskSearch->arraySort($r["overhaul"], 'rent_status', 'asc'));
                $r["dispatch"]=array_values($taskSearch->arraySort($r["dispatch"], 'rent_status', 'asc'));
                $r["find"] =array_values($taskSearch->arraySort($r["find"], 'rent_status', 'asc'));
                $r["change"]=array_values($taskSearch->arraySort($r["change"], 'rent_status', 'asc'));
                $time_end = $this->microtimeFloat();
                $sql_second = round($sql_time_end - $time_start,2);
                $status_second = round($status_time_end - $time_start,2);
                $search_second = round($search_end - $time_start,2);
                $second = round($time_end - $time_start, 2);
                \Think\Log::write("小蜜运维车辆统计,耗时" . $second . "秒,sql查询时间".$sql_second."秒,总查询时间".$search_second."秒,参数：" . json_encode($_POST) . "，结果：" . json_encode($result), "INFO");
                $this->ajaxReturn(["code" => 1, "job_type" => $user_type["job_type"], "message" => "加载数据成功", "second" => $second,"search_second"=>$search_second,"status_second"=>$status_second,"result" => $r], 'json');
            } else {
                $this->ajaxReturn(["code" => 0, "message" => "暂无车辆"], 'json');
            }
        } else {
            $this->ajaxReturn(["code" => -1, "message" => "位置不在所属城市"], 'json');
        }
    }

    /**
     * 查询所有任务
     * @param int $corporation_id
     * @param int $city_id
     * @param int $user_id
     * @param string $rent_status
     * @param string $task_type  查询调度或者换电 需加task_type=7
     * @param string $brand_id
     * @param int $show_all
     */
    public function GetAllTask($corporation_id=10258,$city_id=1,$user_id=2630751,$rent_status="",$task_type="",$brand_id="",$show_all=1,$uuid="aaa")
    {
        if (empty($user_id) || empty($corporation_id)) {
            $this->ajaxReturn(["code" => -100, "message" => "未登录"], 'json');
        }
        if (empty($city_id)) {
            $this->ajaxReturn(["code" => -100, "message" => "请重新选择运营公司或重新登录"], 'json');
        }
        if (empty($uuid)) {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }

        $this->check_terminal($user_id, $uuid);
        if (empty($rent_status)) {
            $rent_status = "1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33";
        }
        $array = explode(',', $rent_status);
        if (empty($task_type)) {
            $task_type = "1,2,3,4,5,6,7";
        }
        $time_start = $this->microtimeFloat();
        $task_array = explode(',', $task_type);
        $search = new \Api\Logic\SearchInfo();
        $taskSearch = new \Api\Logic\TaskSearch();
        $user_type = $search->getUserType($user_id, $corporation_id);
        $in_renting=$taskSearch->getInRenting($corporation_id);
        $changed=$taskSearch->getChanged($corporation_id);
        $inspectioned=$taskSearch->getInspectioned($corporation_id);
        $details = new \Api\Model\DetailsModel();
        $gps = new \Api\Model\GpsModel();
        if ($user_type) {
            //专属任务数量
            $task_count = 0;
            //进行中的专属任务数量
            $task_start_count = 0;
            //查询专属任务的车辆id和状态  verify_status 1=任务中(已派单) 2=开始任务 type 2=抢单
            $personalRent=$taskSearch->getPersonalTask($corporation_id,$user_id);
            $personalRentId = [];
            $data = [];
            if ($personalRent && is_array($personalRent)) {
                foreach ($personalRent as $k => $v) {
                    if ($v["type"] != 2) {
                        //$xiaoan=$details->getPositionAndElectricity($v["imei"]);
                        //$residual_battery= $xiaoan["residual_battery"]?$xiaoan["residual_battery"]:0;
                        $gis_lng=$v["gis_lng"];
                        $gis_lat=$v["gis_lat"];
                        array_push($personalRentId, $v["rent_content_id"]);
                        $task_count++;
                        $gd = $gps->gcj_encrypt($gis_lat, $gis_lng);
                        $d["gis_lat"] = $gd["lat"];
                        $d["gis_lng"] = $gd["lon"];
                        $bd = $gps->bd_encrypt($gd["lat"],$gd["lon"]);
                        $d["bd_latitude"] = strval($bd["lat"]);
                        $d["bd_longitude"] = strval($bd["lon"]);
                        $d["car_info_id"] = $v["car_info_id"];
                        $d["car_item_id"] = $v["car_item_id"];
                        $d["imei"] = $v["imei"];
                        $d["is_personal_task"] = 1;
                        $d["plate_no"] = $v["plate_no"];
                        $d["total_money"] = $d["task_money"] = intval($v["task_money"]);
                        $d["rent_content_id"] = $v["rent_content_id"];
                        $d["task_type"] = $v["task_type"];
                        $d["task_type_title"] = $v["task_type_title"];
                        $all_task = $taskSearch->getTask($v["rent_content_id"]);
//                        if ($show_all == 0){
                            //先判断车辆是否有检修和寻车的状态
                            $equal = array_intersect($all_task["all_rent_status"],[15,16,17,18,22,23,24,25]);
                            //调度和换电同时存在的时候 task_type=7
                            $m=false;
                            $n=false;
                            foreach ($all_task["all_rent_status"] as $ka=>$va){
                                if(!empty($equal)){
                                    continue;
                                }
                                if($va<6&& in_array($va,[1,2,3,4,5]) ){
                                    $m=true;
                                }
                                if($va>6&& in_array($va,[7,10,19,20,21]) ){
                                    $n=true;
                                }

                            }
                            if($m&&$n){
                                $d["task_type"] = 7;
                                $d["task_type_title"] = "调度换电";
                            }
//                        }
                        array_push($data, $d);
                    }
                    if ($v["verify_status"] == 2) {
                        $task_start_count++;
                    }
                }
                if ($show_all == 0) {
                    $this->ajaxReturn(["code" => 1, "message" => "查询专属任务成功", "task_count" => $task_count, "task_start_count" => $task_start_count, "data" => $data], 'json');
                }
            } else {
                if ($show_all == 0) {
                    $this->ajaxReturn(["code" => 1, "message" => "暂无专属任务", "task_count" => $task_count, "task_start_count" => $task_start_count], 'json');
                }
            }
            $sql_time_start = $this->microtimeFloat();
            $rent_content = $taskSearch->getXiaomi($city_id, $corporation_id);
            $sql_time_end= $this->microtimeFloat();
            $order_content = $taskSearch->getTwoDayNoOrder($city_id, $corporation_id);
            $order_content5 = $taskSearch->getFiveDayNoOrder($city_id, $corporation_id);
            $unstable = $taskSearch->getUnstable($corporation_id, $city_id);
            $array_task_type = $taskSearch->getTaskType($corporation_id);
            //$redis = new \Redis();
            //$redis->pconnect(C("COMMON_REDIS_HOST"), C("COMMON_REDIS_PORT"),0.5);
            $count = 0;
            //查询预约
            /*$hasOrder = M()->query("SELECT plate_no FROM baojia_mebike.have_order where status=0");
            if (!empty($hasOrder)) {
                $hasOrder = array_column($hasOrder, "plate_no");
            }*/
            //查询已派给他人和他人已抢单的车辆
            $dispatched =$taskSearch->getDispatched($corporation_id,$user_id);

            $search_end = $this->microtimeFloat();
            if (is_array($rent_content)) {
                //$user_type["job_type"] 1全职 2兼职
                foreach ($rent_content as $k => &$v) {
                    //排除出租中的车辆
                    if (in_array($v["rent_content_id"], $in_renting)) {
                        unset($rent_content[$k]);
                        continue;
                    }
                    //$xiaoan=$details->getPositionAndElectricity($v["imei"]);
                    //$residual_battery= $xiaoan["residual_battery"]?$xiaoan["residual_battery"]:0;
                    $gis_lng=$v["gis_lng"];
                    $gis_lat=$v["gis_lat"];
                    //如果车辆品牌不为空 过滤掉非此品牌的车辆
                    if (!empty($brand_id) && ($brand_id != $v["model_id"])) {
                        unset($rent_content[$k]);
                        continue;
                    }
                    //过滤掉已预约的车辆
                    /*if (!empty($hasOrder)) {
                        if ($hasOrder && in_array($v['plate_no'], $hasOrder) && !in_array($v["rent_content_id"], $personalRentId)) {
                            unset($rent_content[$k]);
                            continue;
                        }
                    }*/
                    //专属任务已查
                    if (in_array($v["rent_content_id"], $personalRentId)) {
                        unset($rent_content[$k]);
                        continue;
                    }
                    $v["rent_status"] = -10000;
                    $v["all_rent_status"] = [];
                    $v["all_rent_status_title"] = [];
                    //是否是专属任务 0否 1是
                    $v["is_personal_task"] = 0;

                    //检修(有单无程、用户上报、有电离线) 寻车>回库>调度>换电>检修
                    //$inspectioned_key  = C("KEY_PREFIX"). "inspectioned_rent_content_id:".$v['rent_content_id'];
                    //$inspectioned =$redis->get($inspectioned_key);
                    //8 有单无程
                    if (($v['sell_status'] == 1||$v["reserve_status"]==100)&& $v['no_mileage_num'] == 3&&!in_array($v["rent_content_id"],$inspectioned)) {//&&empty($inspectioned)
                        $v["rent_status"] = 8;
                        $v["rent_status_title"] = "有单无程";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    //26 用户上报 repaire_status 维修状态 0-不需要修 100-小修 200-大修
                    if ($v["repaire_status"] == 100) {
                        $v["rent_status"] = 26;
                        $v["rent_status_title"] = "用户上报";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    //27 有电离线 offline_status 离线状态 0-在线 100-离线
                    if ($v['offline_status'] == 100) {
                        if($v["rent_content_id"]=="5890518"){
                            //echo "5890518--".$v["lack_power_status"];
                        }
                        if ($v["lack_power_status"] != 400) {
                            //离线 2 电量大于10%且离线
                            $v["rent_status"] = 27;
                            $v["rent_status_title"] = "离线";
                            array_push($v["all_rent_status"], $v["rent_status"]);
                            array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                        }
                    }

                    //调度(越界、还车区域外、已检修20、2日无单、5日无单)
                    //21 五日无单
                    $five_noorder = false;
                    //if ($order_content5 && !in_array($v['rent_content_id'], $order_content5)) {
                    if ($order_content5 && in_array($v['rent_content_id'], $order_content5)) {
                        if ($this->diffBetweenTwoDays(time(), $v['sell_time']) >= 5&&($v['sell_status'] == 1||$v["reserve_status"]==100)) {//&& $v['sell_status'] == 1
                            $v["rent_status"] = 21;
                            $v["rent_status_title"] = "五日无单";
                            array_push($v["all_rent_status"], $v["rent_status"]);
                            array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                            $five_noorder = true;
                            $dispatch_flag=true;
                        }
                    }
                    //两日无单
                    //if (!$five_noorder && $order_content && !in_array($v['rent_content_id'], $order_content)) {
                    if (!$five_noorder && $order_content && in_array($v['rent_content_id'], $order_content)) {
                        //创建时间大于预定天数 改为上架时间大于预定天数
                        if ($this->diffBetweenTwoDays(time(), $v['sell_time']) >= $this->diff_days&&($v['sell_status'] == 1||$v["reserve_status"]==100)) {//&& $v['sell_status'] == 1
                            //两日无单 7
                            $v["rent_status"] = 7;
                            $v["rent_status_title"] = "2日无单";
                            array_push($v["all_rent_status"], $v["rent_status"]);
                            array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                        }
                    }
                    //越界 out_status 越界状态 0-界内 100-网点外 200-界外
                    if ($v['out_status'] == 200 && $v['repaire_status'] == 0) {
                        //10 越界
                        $v["rent_status"] = 10;
                        $v["rent_status_title"] = "越界";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    //还车区域外 out_status 越界状态 0-界内 100-网点外 200-界外
                    if ($v['out_status'] == 100 && $v['repaire_status'] == 0) {
                        //19 还车区域外
                        $v["rent_status"] = 19;
                        $v["rent_status_title"] = "还车区域外";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }

                    //换电
                    //查询车辆是否已经换电，Redis有效时间换电后1小时
                    //$Key =C("KEY_PREFIX")."changed_rent_content_id:".$v['rent_content_id'];
                    //$changed =$redis->get($Key);
                    //lack_power_status	馈电状态 0-正常 100-低电 200-缺电 300-馈电 400-无电
                    if ($v["lack_power_status"] == 100&&!in_array($v["rent_content_id"],$changed)&&!in_array($v["rent_content_id"],$changed)) {//&&empty($changed)
                        //1 低电 待租且电量大于40%小于等于50%
                        $v["rent_status"] = 1;
                        $v["rent_status_title"] = "低电";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    if ($v["lack_power_status"] == 200&&!in_array($v["rent_content_id"],$changed)) {//&&empty($changed)
                        //2 缺电 待租且电量大于20%小于等于40%
                        $v["rent_status"] = 2;
                        $v["rent_status_title"] = "缺电";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    if ($v['offline_status'] == 0 && $v["lack_power_status"] == 400&&!in_array($v["rent_content_id"],$changed)) {//&&empty($changed)
                        //4 无电在线 在线且电量为0
                        $v["rent_status"] = 4;
                        $v["rent_status_title"] = "无电在线";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    if ($v["lack_power_status"] == 300&&!in_array($v["rent_content_id"],$changed)) {//&&empty($changed)
                        //3 馈电
                        $v["rent_status"] = 3;
                        $v["rent_status_title"] = "馈电";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    //离线 offline_status 离线状态 0-在线 100-离线
                    if ($v['offline_status'] == 100&&!in_array($v["rent_content_id"],$changed)) {//&&empty($changed)
                        if ($v["lack_power_status"] == 400) {
                            //5 无电离线 电量低于等于10%且离线
                            $v["rent_status"] = 5;
                            $v["rent_status_title"] = "无电离线";
                            array_push($v["all_rent_status"], $v["rent_status"]);
                            array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                        }
                    }

                    //回库(存在故障、已寻回、无效盒子、非稳盒子)
                    //已寻回 repaire_status 维修状态 0-不需要修 100-小修 200-大修 300-寻回需维修
                    if ($v['repaire_status'] == 300) {
                        //16 已寻回
                        $v["rent_status"] = 16;
                        $v["rent_status_title"] = "已寻回";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    //存在故障 repaire_status 维修状态 0-不需要修 100-小修 200-大修 300-寻回需维修
                    if ($v['repaire_status'] == 200) {
                        //15 存在故障
                        $v["rent_status"] = 15;
                        $v["rent_status_title"] = "存在故障";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    if ($unstable && in_array($v['rent_content_id'], $unstable)) {
                        //18 非稳盒子
                        $v["rent_status"] = 18;
                        $v["rent_status_title"] = "非稳盒子";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    if ($gis_lat<= 0 || $gis_lng<= 0) {
                        //17 无效盒子
                        $v["rent_status"] = 17;
                        $v["rent_status_title"] = "无效盒子";
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    //寻车 search_status	寻车状态 0-无寻车 100-一级寻车 200-二级寻车 300-三级寻车 400-四级寻车
                    if ($v['search_status'] > 0) {
                        switch ($v['search_status']) {
                            case 100:
                                //25 1级寻车
                                $v["rent_status"] = 25;
                                $v["rent_status_title"] = "1级寻车";
                                break;
                            case 200:
                                //24 2级寻车
                                $v["rent_status"] = 24;
                                $v["rent_status_title"] = "2级寻车";
                                break;
                            case 300:
                                //23 3级寻车
                                $v["rent_status"] = 23;
                                $v["rent_status_title"] = "3级寻车";
                                break;
                            case 400:
                                //22 4级寻车
                                $v["rent_status"] = 22;
                                $v["rent_status_title"] = "4级寻车";
                                break;
                        }
                        array_push($v["all_rent_status"], $v["rent_status"]);
                        array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                    }
                    //33 特殊下架 special_status 特殊下架状态 0-在线 100-特殊下架
                    if( $user_type["role_type"] == 3 ){
                        if ($v["special_status"] == 100) {
                            $v["rent_status"] = 33;
                            $v["rent_status_title"] = "特殊下架";
                            array_push($v["all_rent_status"], $v["rent_status"]);
                            array_push($v["all_rent_status_title"], $v["rent_status_title"]);
                        }
                    }



                    //排除掉不是当前分类的车辆
                    $arr = array_intersect($v["all_rent_status"], $array);
                    if (!in_array($v["rent_status"], $array) && count($arr) == 0) {
                        $v["rent_status"] = -10000;
                    }
                    if ($v["rent_status"] != -10000) {
                        $gd = $gps->gcj_encrypt($gis_lat, $gis_lng);
                        $d["gis_lat"] = $gd["lat"];
                        $d["gis_lng"] = $gd["lon"];
                        $bd = $gps->bd_encrypt($gd["lat"],$gd["lon"]);
                        $d["bd_latitude"] = strval($bd["lat"]);
                        $d["bd_longitude"] = strval($bd["lon"]);
                        $d["car_info_id"] = $v["car_info_id"];
                        $d["car_item_id"] = $v["car_item_id"];
                        $d["imei"] = $v["imei"];
                        $d["is_personal_task"] = $v["is_personal_task"];
                        $d["plate_no"] = $v["plate_no"];
                        $d["rent_content_id"] = $v["rent_content_id"];
                        $d["rent_status"] = $v["rent_status"];
                        $d["rent_status_title"] = $v["rent_status_title"];
                        $d["sell_status"] = $v["sell_status"];
                        $d["task_type"] = $v["task_type"];
                        $d["task_type_title"] = $v["task_type_title"];
                        $d["all_rent_status"] = $v["all_rent_status"];
                        $d["all_rent_status_title"] = $v["all_rent_status_title"];
                        foreach ($array_task_type as $k1 => $v1) {
                            if ($v1["code_id"] == $v["rent_status"]) {
                                $v["show"] = $v1["show"];
                                $d["task_money"] = intval($v1["price"]);
                                $d["task_type"] = $v1["task_id"];
                                $d["task_type_title"] = $v1["task_title"];
                            }
                        }
                        if($v["rent_status"] == 33){
                            $d["task_type"] = 6;
                            $d["task_type_title"] = "特殊下架";
                        }
                        if($user_type["role_type"] <> 3){
                            if ($v["special_status"] == 100) {
                                unset($d);
                                continue;
                            }
                        }

                        //先判断车辆是否有检修和寻车的状态
                        $equal = array_intersect($v["all_rent_status"],[15,16,17,18,22,23,24,25]);
                        //调度和换电同时存在的时候 task_type=7
                        $m=false;
                        $n=false;
                        foreach ($v["all_rent_status"] as $ka=>$va){
                            if(!empty($equal)){
                                continue;
                            }
                            if($va<6&& in_array($va,[1,2,3,4,5]) ){
                                $m=true;
                            }
                            if($va>6&& in_array($va,[7,10,19,20,21]) ){
                                $n=true;
                            }

                        }
                        if($m&&$n){
                            $d["task_type"] = 7;
                            $d["task_type_title"] = "调度换电";
                        }

                        //排除不是所选任务的车
                        if (!in_array($d["task_type"], $task_array)) {
                            unset($rent_content[$k]);
                            continue;
                        }
                        $d["total_money"] = $d["task_money"];
                        if ($show_all == 0) {
                            if ($v["is_personal_task"] == 1) {
                                $count++;
                                array_push($data, $d);
                            }
                        } else {
                            //role_type 1运维 2调度 3整备 4库管
                            //整备         显示所有(派给其他人+未配置+已配置)
                            //运维(兼职) 运维(全职) 库管  已配置(去除派给他人和被他人抢单的)

                            //非整备
                            if ($user_type["role_type"] != 3) {
                                if ($v["show"] == 1) {
                                    //去除派给他人和被他人抢单的----------------------------------
                                    if (in_array($v["rent_content_id"], $dispatched)) {
                                        unset($rent_content[$k]);
                                        continue;
                                    }
                                    $count++;
                                    array_push($data, $d);
                                }
                            } else {
                                $count++;
                                array_push($data, $d);
                            }
                        }
                    } else {
                        unset($rent_content[$k]);
                    }
                }
                $time_end = $this->microtimeFloat();
                $sql_second = round($sql_time_end - $sql_time_start,2);
                $search_second = round($search_end - $time_start,2);
                $second = round($time_end - $time_start, 2);
                $rent_content = array_values($data);
                \Think\Log::write("所有任务耗时" . $second . "秒,sql查询".$sql_second."秒,总查询".$search_second."秒,返回数据" . $count . "条,corporation_id=" . $corporation_id . ",city_id=".$city_id.",user_id=" . $user_id, "INFO");
                $this->ajaxReturn(["code" => 1, "message" => "加载数据成功", "task_count" => $task_count, "task_start_count" => $task_start_count, "count" => $count, "second" => $second, "data" => $rent_content], 'json');
            } else {
                $this->ajaxReturn(["code" => 0, "message" => "暂无车辆", "task_count" => 0, "task_start_count" => 0], 'json');
            }
        } else {
            $this->ajaxReturn(["code" => -1, "message" => "位置不在所属城市", "task_count" => 0, "task_start_count" => 0], 'json');
        }
    }

    /**
     * 查询车辆任务
     * @param int $rent_content_id
     * @param int $user_id
     * @param int $test
     */
    public function GetTaskByID($rent_content_id=321344,$user_id=2630751,$uuid="aaa",$test=0)
    {
        $time_start = $this->microtimeFloat();
        if (empty($rent_content_id)||empty($user_id)||empty($uuid)) {
            $this->ajaxReturn(["code" => -100, "message" => "参数错误"], 'json');
        }
        $this->check_terminal($user_id, $uuid);
        $taskSearch = new \Api\Logic\TaskSearch();
        $area = new \Api\Logic\Area();
        $result = $taskSearch->getTask($rent_content_id);
        $data_status = $area->get_status($rent_content_id);
        if($data_status["special_status"] ==100){
            $result["task"] = [];
        }
        if($result&&is_array($result)) {
            $result["have_reservation"] =0;
            //查询预约
            $hasOrder = M()->query("SELECT id,plate_no,uid,status FROM baojia_mebike.have_order WHERE status=0 AND uid={$user_id} AND plate_no='{$result["plate_no"]}'");
            if($hasOrder){
                $result["have_reservation"] =1;
            }
            if($result["gis_lat"]&&$result["gis_lat"]>0&&$result["gis_lng"]&&$result["gis_lng"]>0) {
                $gps = D('Gps');
                $gd = $gps->gcj_encrypt($result["gis_lat"], $result["gis_lng"]);
                $result["gis_lng"]=$gd["lon"];
                $result["gis_lat"]=$gd["lat"];
                $result["location"] = $area->GetAmapAddress($gd["lon"],$gd["lat"]);
            }
        }
        $time_end = $this->microtimeFloat();
        $second = round($time_end - $time_start, 2);
        $result["second"]=$second;
        if($test==1){
            echo "<pre>";print_r($result);die;
        }
        $this->ajaxReturn($result, 'json');
    }

    /**
     * 查询是否为助力车
     * @param int $rent_content_id
     */
    public function GetIsMoped($rent_content_id=5889398){
        $is_moped=0;
        $text="请不要忘记关闭电池仓";
        $car_info_id=M("rent_content")->where("id={$rent_content_id}")->getField("car_info_id");
        $car_info_name = M("car_info")->where("name LIKE '%助力%' AND id={$car_info_id}")->getField("name");
        if($car_info_name){
            $is_moped=1;
            $text="";
        };
        $this->ajaxReturn(["code" => 1, "message" => "查询是否为助力车成功","value"=>$is_moped,"text"=>$text], 'json');
    }

    /**
     * 获取公司列表
     */
    public function GetCorporations($user_id=2630751,$uuid="aaa")
    {
        if (empty($user_id)) {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
        $this->check_terminal($user_id, $uuid);
        $strSql = "SELECT cor.id,cor.name,cor.city_id,ac.name city_name,cb.name corporation_brand_name,
                ac.gis_lat,ac.gis_lng,CONCAT(ac.name,cb.name) brand_name,IFNULL(cec.order_type,0) order_type 
                FROM baojia_mebike.repair_member a
                LEFT JOIN ucenter_member b ON a.user_id = b.uid
                LEFT JOIN corporation cor ON cor.id=a.corporation_id
                LEFT JOIN corporation_brand cb on cb.id=cor.corporation_brand_id
                LEFT JOIN baojia_mebike.corporation_ext_config cec on cec.corporation_id=a.corporation_id
                LEFT JOIN area_city ac on ac.id=cor.city_id
                WHERE cor.car_type=2 AND a.status=1 AND ISNULL(cor.name)=FALSE 
                AND cor.id NOT IN(2830,2742,2521,2951) AND a.user_id={$user_id}";
        $corporations = M()->query($strSql);
        if ($corporations) {
            foreach ($corporations as $k => &$v) {
                //baojia_mebike.repair_member role_type 1=运维 2=调度 3=整备 4=库管
                $v["show_dispatch"] = "0";
                $role = M("baojia_mebike.repair_member")->field("role_type")->where("user_id={$user_id} AND corporation_id={$v["id"]}")->find();
                $v["role_type"] = $role["role_type"];
                if ($role && ($role["role_type"] == 1 || $role["role_type"] == 2) && $v["order_type"] == 1) {
                    $v["show_dispatch"] = "1";
                }
            }
            $this->ajaxReturn(["code" => 1, "message" => "查询成功", "corporations" => $corporations], 'json');
        } else {
            $this->ajaxReturn(["code" => 0, "message" => "查询失败"], 'json');
        }
    }

    /**
     * 发送短信验证码
     */
    public function SendSMSCode()
    {
        try {
            $mobile = $_POST['mobile'];
            if ($mobile) {
                $uid=M("ucenter_member")->where("mobile='{$mobile}'")->getField("uid");
                if ($uid) {
                    $user_status = M('baojia_mebike.repair_member')->field('id,status')->where("status=1 and user_id={$uid}")->find();
                    if ($user_status) {
                        $code = $this->CreateSMSCode();
                        setcookie("SMSCode", $code, time() + 3600, '/');
                        $url =C("SMS_LINK").'&messages=[{"taskId":2036,"templateId":1,"mobile":' . $mobile . ',"argument":"短信验证码' . $code . '，如非本人操作，请忽略本短信（宝驾掌柜）","useTemplate":false}]';
                        $output = $this->curlGet($url);
                        \Think\Log::write("发送短信验证码，参数：" . json_encode($_POST) . "结果：" . $output, "INFO");
                        $outputArr = json_decode($output, true);
                        if ($outputArr['code'] == '2') {
                            /*$Redis = new \Redis();
                            $Redis->pconnect(C("REDIS_HOST"), C("REDIS_PORT"), 0.5);
                            $Redis->AUTH(C("REDIS_AUTH"));
                            $Redis->set("Code" . $mobile, $code, 1200);*/
                            //\Think\Log::write("发送短信验证码，Redis：" . $Redis->get("Code".$mobile)."，code：".$code, "INFO");
                            $this->ajaxReturn(["code" => 1, "message" => "短信发送成功"], 'json');
                        } else {
                            $this->ajaxReturn(["code" => -2, "message" => "短信发送失败"], 'json');
                        }
                    } else {
                        $this->ajaxReturn(["code" => -1, "message" => "您的权限已关闭"], 'json');
                    }
                } else {
                    $this->ajaxReturn(["code" => 0, "message" => "账号不存在"], 'json');
                }
            } else {
                $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
            }
        } catch (Exception $exception) {
            $this->ajaxReturn(["code" => -101, "message" => "短信发送失败", "exception" => $exception], 'json');
        }
    }

    /**
     * 登录
     * @param string $mobile
     * @param string $code
     * @param string $uuid
     */
    public function Login($mobile="",$code ="",$uuid="aaa"){
        if($mobile&&$code) {
            /*$Redis = new \Redis();
            $Redis->pconnect(C("REDIS_HOST"), C("REDIS_PORT"), 0.5);
            $Redis->AUTH(C("REDIS_AUTH"));
            $redis_code = $Redis->get("Code".$mobile);*/
            $uid=M("ucenter_member")->where("mobile='{$mobile}'")->getField("uid");
            if ($uid) {
                $user= M("baojia_mebike.repair_member")
                    ->field("id,user_id,user_name,job_type,CASE WHEN job_type=1 THEN '全职'  ELSE '兼职' END job_type_text,status,CASE WHEN manager_position_id=0 THEN '员工' ELSE '员工' END manager_position,CASE WHEN role_type=1 THEN '运维' WHEN role_type=2 THEN '调度' WHEN role_type=3 THEN '整备' WHEN role_type=4 THEN '库管' ELSE '运维' END role_type_text,role_type,yx_status")
                    ->where("status=1 and user_id={$uid}")
                    ->find();
                if ($user && $user['status'] == '1') {
                    /*if (empty($redis_code)) {
                        $this->ajaxReturn(["code" => -3, "message" => "验证码过期，请刷新后重新获取"], 'json');
                    }
                    if($code!=$redis_code) {
                        $this->ajaxReturn(["code" => -2, "message" => "验证码错误，请重新输入"], 'json');
                    }*/
                    $log = M('baojia_oms.sms_log')->where("mobile='{$mobile}'")
                        ->field("message,send_time")
                        ->order('send_time desc')->find();
                    if ($log&&$log['message']&&$log['send_time']&&strpos($log['message'],$code) !== false) {
                        $send_time = strtotime($log['send_time']);
                        $now = time();
                        $period = floor(($now-$send_time)/60);
                        if($period>=20){
                            $this->ajaxReturn(["code" => -3, "message" => "验证码过期，请刷新后重新获取"], 'json');
                        } else {
                            $user["manual_url"]=$this->manual_url;
                            $user["mobile"]=$mobile;
                            $result=0;
                            if ($this->getIp() != "::1") {
                                $result = M("baojia_mebike.repair_member")->where("user_id = %d", $user["user_id"])->save(["authority" => $uuid]);
                            }
                            $this->ajaxReturn(["code" => 1, "message" => "登录成功", "user" => $user,"result"=>$result], 'json');
                        }
                    }else {
                        $this->ajaxReturn(["code" => -2, "message" => "验证码错误，请重新输入"], 'json');
                    }
                }else {
                    $this->ajaxReturn(["code" => -1, "message" => "权限已关闭"], 'json');
                }
            }else {
                $this->ajaxReturn(["code" => 0, "message" => "账号不存在"], 'json');
            }
        }else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }

    /**
     * 获取用户信息
     */
    public function GetUserInfo($user_id="",$uuid="aaa"){
        if($user_id) {
            $this->check_terminal($user_id, $uuid);
            $mobile=M("ucenter_member")->where("uid={$user_id}")->getField("mobile");
            $user= M("baojia_mebike.repair_member")
                ->field("id,user_id,user_name,job_type,CASE WHEN job_type=1 THEN '全职'  ELSE '兼职' END job_type_text,status,CASE WHEN manager_position_id=0 THEN '员工' ELSE '员工' END manager_position,CASE WHEN role_type=1 THEN '运维' WHEN role_type=2 THEN '调度' WHEN role_type=3 THEN '整备' WHEN role_type=4 THEN '库管' ELSE '运维' END role_type_text,role_type,yx_status")
                ->where("status=1 and user_id={$user_id}")
                ->find();
            if ($user) {
                $user["manual_url"]=$this->manual_url;
                $user["mobile"]=$mobile;
                $this->ajaxReturn(["code" => 1, "message" => "加载数据成功", "user" => $user], 'json');

            } else {
                $this->ajaxReturn(["code" => 0, "message" => "账号不存在"], 'json');
            }
        }else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }

    /**
     * 根据手机号查询员工信息
     * @param string $mobile
     */
    public function GetUserInfoByMobile($mobile="15010302632"){
        if ($mobile) {
            $user_id=M("ucenter_member")->where("mobile='{$mobile}'")->getField("uid");
            $user= M("baojia_mebike.repair_member")
                ->field("id,authority,user_id,user_name,job_type,CASE WHEN job_type=1 THEN '全职'  ELSE '兼职' END job_type_text,status,CASE WHEN manager_position_id=0 THEN '员工' ELSE '员工' END manager_position,CASE WHEN role_type=1 THEN '运维' WHEN role_type=2 THEN '调度' WHEN role_type=3 THEN '整备' WHEN role_type=4 THEN '库管' ELSE '运维' END role_type_text,role_type,yx_status")
                ->where("status=1 and user_id={$user_id}")
                ->find();
            if ($user) {
                $user["manual_url"] = "http://xiaomi.baojia.com/Public/index.html";
                $user["mobile"]=$mobile;
                $this->ajaxReturn(["code" => 1, "message" => "加载数据成功", "user" => $user], 'json');
            } else {
                $this->ajaxReturn(["code" => 0, "message" => "账号不存在"], 'json');
            }
        } else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }

    /**
     * 查询员工所属运营公司
     * @param string $user_id
     */
    public function GetUserCorporations($user_id="",$uuid="aaa"){
        if ($user_id&&$uuid) {
            //$this->check_terminal($user_id, $uuid);
            $strSql = "SELECT cor.id,cor.name,cor.city_id,ac.name city_name,cb.name corporation_brand_name,
                ac.gis_lat,ac.gis_lng,CONCAT(ac.name,cb.name) brand_name FROM baojia_mebike.repair_member a
                LEFT JOIN ucenter_member b ON a.user_id = b.uid
                LEFT JOIN corporation cor ON cor.id=a.corporation_id
                LEFT JOIN corporation_brand cb on cb.id=cor.corporation_brand_id
                LEFT JOIN area_city ac on ac.id=cor.city_id
                WHERE cor.car_type=2 AND a.status=1 AND ISNULL(cor.name)=FALSE 
                AND cor.id NOT IN(2830,2742,2521,2951) AND a.user_id={$user_id}";
            $corporations = M()->query($strSql);
            $this->ajaxReturn(["code" => 1, "message" => "加载数据成功", "corporations" => $corporations], 'json');
        }else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }

    /**
     * 查询车辆品牌
     * @param string $corporation_id
     */
    public function GetBrands($corporation_id="2118"){
        if (!empty($corporation_id)) {
            $strSql = "select cm.id brand_id,cm.name brand_name,CONCAT('http://pic.baojia.com/s/',cip.url) pic_url,cor.parent_id
                from rent_content rent
                LEFT JOIN car_item_verify civ on civ.car_item_id=rent.car_item_id 
                LEFT JOIN corporation cor on cor.id=rent.corporation_id
                LEFT JOIN car_info cn on cn.id=rent.car_info_id
                LEFT JOIN car_item ci on ci.id = rent.car_item_id
                LEFT JOIN car_model cm ON cm.id = cn.model_id
                LEFT JOIN car_item_color cic on cic.id = ci.color
                left join (select url,car_info_id,car_color_id from car_info_picture where type=0 and status = 2) cip on rent.car_info_id=cip.car_info_id and cip.car_color_id=ci.color 
                where rent.sort_id=112 and rent.status=2 
                and rent.sell_status=1 and rent.car_info_id<>30150 
                AND cor.parent_id={$corporation_id}
                and cip.url is not NULL
                GROUP BY cm.id,cm.name";
            $brands = M()->query($strSql);
            $this->ajaxReturn(["code" => 1, "message" => "查询成功","brands" =>$brands], 'json');
        }
        else{
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }


    /**
     * 查询小蜜车辆编码
     */
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
                $result = M()->query($sql . $where);
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

    /**
     * 车辆控制操作 4=鸣笛 5=设防 6=撤防 7=启动 30=唤醒 34=开舱锁 36=关仓
     */
    public function RepairOperation($uuid="aaa")
    {
        $user_id = $_POST['user_id'];
        $rent_content_id = $_POST['rent_content_id'];
        $operation_type = $_POST['operation_type'];
        $gis_lng = $_POST['gis_lng'];
        $gis_lat = $_POST['gis_lat'];
        $pid = $_POST['pid'];
        $corporation_id = $_POST['corporation_id'];
        if (!empty($user_id) && !empty($rent_content_id) && !empty($operation_type) && !empty($corporation_id)) {
            $this->check_terminal($user_id, $uuid);
            $search = new \Api\Logic\SearchInfo();
            $user_type = $search->getUserType($user_id, $corporation_id);
            //role_type 角色类型：1=运维 2=调度 3=整备 4=库管
            //job_type 1=全职 2=兼职
            //运维(兼职) 非自己任务的车不允许开仓
            if ($operation_type == 34 && $user_type["role_type"] == 1 && $user_type["job_type"] == 2) {
                $is_own = M("baojia_mebike.dispatch_order")->where("verify_status IN(1,2) AND rent_content_id={$rent_content_id} AND uid={$user_id}")->getField("id");
                if (empty($is_own)) {
                    $this->ajaxReturn(["code" => -100, "message" => "你没有权限处理这辆车"], 'json');
                }
            }
            $operation_type_text = "鸣笛";
            if ($operation_type == 4) {//鸣笛
                $operation_type_text = "鸣笛";
            } elseif ($operation_type == 5) {//设防
                $operation_type_text = "设防";
            } elseif ($operation_type == 6) {//撤防
                $operation_type_text = "撤防";
            } elseif ($operation_type == 7) {//启动
                $operation_type_text = "启动";
            } elseif ($operation_type == 30) {//唤醒
                $operation_type_text = "唤醒";
            } elseif ($operation_type == 34) {//开舱锁
                $operation_type_text = "开舱锁";
            } elseif ($operation_type == 36) {//关仓
                $operation_type_text = "关仓";
            }
            $control = D("Control");
            $areaLogic = new \Api\Logic\Area();
            $is_rating = $areaLogic->is_rating($rent_content_id);
            //出租中的车辆不允许设防
            if ($operation_type == 5 && $is_rating) {
                \Think\Log::write("车辆" . $rent_content_id . $operation_type_text . ",正在出租中", "INFO");
                $this->ajaxReturn(["code" => -1, "message" => "车辆正在出租中"], 'json');
            }
            $result = $control->control($corporation_id, $user_id, $rent_content_id, $operation_type, $gis_lng, $gis_lat);
            //echo "<pre>";print_r($result);die;
            if ($result["code"] == 1) {
                //5=设防 6=撤防 7=启动 30=唤醒 34=开舱锁 36=关仓
                //12 设防  13 撤防 14 启动 34 开仓 45 关仓 46 重启盒子
                if ($operation_type == 5) {
                    $operate = 12;
                }
                if ($operation_type == 6) {
                    $operate = 13;
                }
                if ($operation_type == 7) {
                    $operate = 14;
                }
                if ($operation_type == 30) {
                    $operate = 46;
                }
                if ($operation_type == 34) {
                    $operate = 34;
                }
                if ($operation_type == 36) {
                    $operate = 45;
                }
                //36 关仓记录
                if ($this->getIp() != "::1") {
                    if ($operation_type == 5 || $operation_type == 6 || $operation_type == 7 || $operation_type == 30 || $operation_type == 34 || $operation_type == 36) {
                        D('FedGpsAdditional')->operation_log($user_id, $rent_content_id, $result["plate_no"], $gis_lng, $gis_lat, $operate, $pid);
                    }
                    if ($operation_type == 34) {
                        D('FedGpsAdditional')->exchangeLog($user_id, $rent_content_id, $result["plate_no"], $gis_lng, $gis_lat, 1);
                    }
                    //$this->repairAdd($user_id, $rent_content_id, $result["plate_no"], $operation_type);
                }
                \Think\Log::write("车辆" . $rent_content_id . $operation_type_text . "成功", "INFO");
                $this->ajaxReturn($result, 'json');
            } else {
                $this->ajaxReturn($result, 'json');
            }
        } else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }

    /**
     * 实时定位
     * @param int $test
     */
    public function RealTimeLocation($test=0)
    {
        if (!empty($_POST['user_id'])) {
            $user_id = $_POST['user_id'];
            $lng = $_POST['gis_lng'];
            $lat = $_POST['gis_lat'];
            if(!empty($user_id) && !empty($lng) && !empty($lng)>0&&!empty($lng) && !empty($lng) > 0) {
                $Redis = new \Redis();
                $Redis->pconnect(C("REDIS_HOST"), C("REDIS_PORT"),0.5);
                $Redis->AUTH(C("REDIS_AUTH"));
                if( $Redis->get("RealTimeLocation".$user_id) ){
                    //\Think\Log::write("2分钟实时定位，参数：" . json_encode($_POST), "INFO");
                    $this->ajaxReturn(["code" => 1, "message" => "实时定位记录成功"], 'json');
                }
                $log = [
                    "uid" => $user_id,
                    "user_lng" => $lng,
                    "user_lat" => $lat,
                    "record_time" => time()
                ];
                $result = M("baojia_mebike.open_cabin_log")->add($log);
                $Redis->set("RealTimeLocation".$user_id,1,120);
                \Think\Log::write("实时定位，参数：" . json_encode($_POST) . "，结果：" . $result, "INFO");
                $this->ajaxReturn(["code" => 1, "message" => "实时定位记录成功", "result" => $result], 'json');
            } else {
                $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
            }
        } else {
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }

    /**
     * 操作 4=鸣笛 5=设防 6=撤防 7=启动 34=开舱锁 37=开轮锁  http://47.95.32.191:8907/simulate/service
     * @param int $test
     */
    public function OperationByIMEI($test=0)
    {
        $imei = $_POST['imei'];
        $operation_type = $_POST['operation_type'];
        if (!empty($imei)&&!empty($operation_type)) {
            $control = D("Control");
            $result=$control->controlByImei($imei,$operation_type);
            $this->ajaxReturn($result, 'json');
        }else{
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }

    /**
     * 查询车辆位置
     */
    public function GetPosition(){
        $user_id = $_POST['user_id'];
        $rent_id = $_POST['rent_content_id'];
        $corporation_id= $_POST['corporation_id'];
        if (!empty($user_id) && !empty($rent_id)&& !empty($corporation_id)) {
            $search = new \Api\Logic\SearchInfo();
            if ($search->carAuth($corporation_id,$rent_id)<1) {
                $this->ajaxReturn(["code" => -2, "message" => "你没有管理这辆车的权限"], 'json');
            }
            $imei= $search->getGpsImei($rent_id);
            $gps_status = $search->gpsStatusByImeis($imei);
            $gps_status=$gps_status?$gps_status:array("gd_lat"=>0,"gd_lng"=>0,"bd_lat"=>0,"bd_lng"=>0,"latitude"=>"","longitude"=>"","update_time"=>"");
            $failed_record = M("rent_failed_record_location")
                ->field("lat latitude,lng longitude,FROM_UNIXTIME(create_time) update_time,client_id")
                ->where(["rent_id" => $rent_id, "create_time" => ["gt", time() - 86400 * 2]])
                ->order("id desc")
                ->find();
            $gps = new \Api\Model\GpsModel();
            //'218,1218时存储的是百度地图坐标，其他存储的是高德坐标
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
            $failed_record=$failed_record?$failed_record:array("gd_lat"=>0,"gd_lng"=>0,"bd_lat"=>0,"bd_lng"=>0,"latitude"=>"","longitude"=>"","update_time"=>"");
            $last_app=$search->getLastAppCoordinate($rent_id);
            if($last_app["longitude"]>0){
                $gd = $gps->gcj_decrypt($last_app["latitude"],$last_app["longitude"]);
                $bd = $gps->bd_encrypt($last_app["latitude"], $last_app["longitude"]);
                $last_app["gd_lat"] = floatval($gd["lat"]);
                $last_app["gd_lng"] = floatval($gd["lon"]);
                $last_app["bd_lat"] = $bd["lat"];
                $last_app["bd_lng"] = $bd["lon"];
            }
            $last_app=$last_app?$last_app:array("gd_lat"=>0,"gd_lng"=>0,"bd_lat"=>0,"bd_lng"=>0,"latitude"=>"","longitude"=>"","update_time"=>"");
            $last_bs=$search->gpsStatusBSByImeis($imei);
            $last_bs=$last_bs?$last_bs:array("gd_lat"=>0,"gd_lng"=>0,"bd_lat"=>0,"bd_lng"=>0,"latitude"=>"","longitude"=>"","update_time"=>"");
            $this->ajaxReturn(["code" => 1, "message" => "查询成功","gps_status" => $gps_status,"failed_record"=>$failed_record,"last_app"=>$last_app,"last_bs"=>$last_bs], 'json');
        }
        else{
            $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
        }
    }

    /**
     * 位置校正 type 16 手动校正 17 自动校正 18校正位置不添加记录
     * @param $user_id
     * @param $rent_content_id
     * @param string $latY
     * @param string $lngX
     * @param int $type
     */
    public function CorrectivePosition($user_id,$rent_content_id,$latY='',$lngX = '',$type=16,$uuid="aaa"){
        if($type==16) {
            if (!$latY || !$lngX || !$user_id || !$rent_content_id||empty($uuid)) {
                $this->ajaxReturn(["code" => -100, "message" => "参数不完整"], 'json');
            }
            $this->check_terminal($user_id, $uuid);
            $areaLogic = new \Api\Logic\Area();
            $is_rating = $areaLogic->is_rating($rent_content_id);
            if ($is_rating) {
                $this->ajaxReturn(["code" => -1, "message" => "车辆正在出租中"], 'json');
            }
            if ($latY == 0 || $lngX == 0) {
                $this->ajaxReturn(["code" => -100, "message" => "未获取到定位"], 'json');
            }
            $search = new \Api\Logic\SearchInfo();
            $imei = $search->getGpsImei($rent_content_id);
            if (empty($imei)) {
                $this->ajaxReturn(["code" => -1, "message" => "查询数据有误"], 'json');
            }
            $rent_info = M("rent_content")->field("car_item_id")->where(["id" => $rent_content_id])->find();
            $plate_no = $search->getPlateNo($rent_info["car_item_id"]);
            //$operation_type = 25;//位置校正
            $gps = new \Api\Model\GpsModel();
            $origin = $gps->gcj_decrypt($latY, $lngX);
            $new_latitude = $origin["lat"];
            $new_longitude = $origin["lon"];
            $result = $gps->gpsStatusData($imei, $new_latitude, $new_longitude);
            \Think\Log::write("位置校正，参数：" . json_encode($_POST) . "，结果：" . $result, "INFO");
            if ($result > 0) {
                if ($type <> 18) {
                    D('FedGpsAdditional')->operation_log($user_id, $rent_content_id, $plate_no, $lngX, $latY, $type);
                }
                //M("rent_content")->where(["id" => $rent_content_id])->save(["update_time" => time()]);
                $this->ajaxReturn(["code" => 1, "message" => "更新成功"], json);
            } elseif ($result == -100) {
                $this->ajaxReturn(["code" => 0, "message" => "校正的位置与盒子位置一致"], json);
            } else {
                $this->ajaxReturn(["code" => 0, "message" => "更新失败"], json);
            }
        }else{
            $this->ajaxReturn(["code" => 1, "message" => "更新成功"], json);
        }
    }

    // 生成短信验证码
    public function CreateSMSCode($length = 4){
        $min = pow(10 , ($length - 1));
        $max = pow(10, $length) - 1;
        return rand($min, $max);
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

    function curlGet($url, $data='', $method='GET')
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

    public function getIp(){
        if ($_SERVER['REMOTE_ADDR']) {
            $cip = $_SERVER['REMOTE_ADDR'];
        } elseif (getenv("REMOTE_ADDR")) {
            $cip = getenv("REMOTE_ADDR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $cip = getenv("HTTP_CLIENT_IP");
        } else {
            $cip = "unknown";
        }
        return $cip;
    }

    private function microtimeFloat()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}