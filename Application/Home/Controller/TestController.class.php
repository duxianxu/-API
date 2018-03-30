<?php
namespace Home\Controller;
use Think\Controller\RestController;

class TestController extends RestController {
    public function index(){
        $today = strtotime(date('Y-m-d', time()));
        $this->assign('today',$today);
        $end = ($today + 24 * 60 * 60)-1;
        $this->assign('end',$end);
        $cityArray = $this->getCitiyList();
		// echo "<pre>";
		// print_r($cityArray);
        $this->assign('cityArray',$cityArray);
        $this->display('index');
    }

    public  function  getCitiyList(){
        $Model = new \Think\Model();
        $cityArray = $Model->query('select id,name,gis_lat,gis_lng from baojia.area_city where status=1 and id in(select DISTINCT city_id from  baojia.rent_content where sort_id=112) and gis_lat is not null');
        return $cityArray;
    }

    public function loadData()
    {
        if ($this->_method == 'post') {
            $startDate =$_POST['startDate'];
            $endDate = $_POST['endDate'];
            $city = $_POST['city'];
            $pattern = $_POST['pattern'];
            if(empty($startDate))
            {
                $result = array('status' => 0, 'info' => '开始日期不能为空');
                $this->response($result, 'json');
            }
            if (empty($endDate)) {
                $result = array('status' => 0, 'info' => '截止日期不能为空');
                $this->response($result, 'json');
            }
            $start= strtotime($startDate);
            $end = (strtotime($endDate) + 24 * 60 * 60)-1;
            if($start>$end)
            {
                $result = array('status' => 0, 'info' => '开始日期大于截止日期');
                $this->response($result, 'json');
            }
            $cityAndPattern="";
            $orderCityAndPattern="";
            if($city!=0) {
                $cityAndPattern=" AND rn.city_id in({$city}) ";
                $orderCityAndPattern=" AND a.city_id in({$city}) ";
            }
            if($pattern!=0){
                $cityAndPattern.=" and rcrc.return_mode in ({$pattern}) ";
                $orderCityAndPattern.=" and rcrc.return_mode in ({$pattern}) ";
            }
            $user  = $this->user_behavior($start,$end,$cityAndPattern);
            $order  = $this->order_statistics($start,$end,$orderCityAndPattern);
            $order_pay=$this->order_pay($start,$end,$orderCityAndPattern);
            $travel=$this->travel($start,$end,$orderCityAndPattern);
            $result = array('status' => 1, 'info' =>'获取数据成功','start'=>$start,'end'=>$end,'user'=>$user,'order'=>$order,'order_pay'=>$order_pay,'travel'=>$travel);
            $this->response($result, 'json');
        } else {
            $result = array('status' => 0, 'info' => "只支持POST请求");
            $this->response($result, 'json');
        }
    }

    public function loadRentCarCount()
    {
        if ($this->_method == 'post') {
            $startDate =$_POST['startDate'];
            $endDate = $_POST['endDate'];
            $city = $_POST['city'];
            $pattern = $_POST['pattern'];
            if(empty($startDate))
            {
                $result = array('status' => 0, 'info' => '开始日期不能为空');
                $this->response($result, 'json');
            }
            if (empty($endDate)) {
                $result = array('status' => 0, 'info' => '截止日期不能为空');
                $this->response($result, 'json');
            }
            $start= strtotime($startDate);
            $end = (strtotime($endDate) + 24 * 60 * 60)-1;
            if($start>$end)
            {
                $result = array('status' => 0, 'info' => '开始日期大于截止日期');
                $this->response($result, 'json');
            }
            //$cityAndPattern="";
            $orderCityAndPattern="";
            if($city!=0) {
                //$cityAndPattern=" AND rn.city_id in({$city}) ";
                $orderCityAndPattern=" AND a.city_id in({$city}) ";
            }
            if($pattern!=0){
                //$cityAndPattern.=" and rcrc.return_mode in ({$pattern}) ";
                $orderCityAndPattern.=" and rcrc.return_mode in ({$pattern}) ";
            }
            $rent_car_count  = $this->rent_car_count($start,$end,$orderCityAndPattern);
            $result = array('status' => 1, 'info' =>'获取数据成功','start'=>$start,'end'=>$end,'rent_car_count'=>$rent_car_count);
            $this->response($result, 'json');
        } else {
            $result = array('status' => 0, 'info' => "只支持POST请求");
            $this->response($result, 'json');
        }
    }

    public function loadCarData()
    {
        if ($this->_method == 'post') {
            $startDate =$_POST['startDate'];
            $endDate = $_POST['endDate'];
            $city = $_POST['city'];
            $pattern = $_POST['pattern'];
            if(empty($startDate))
            {
                $result = array('status' => 0, 'info' => '开始日期不能为空');
                $this->response($result, 'json');
            }
            if (empty($endDate)) {
                $result = array('status' => 0, 'info' => '截止日期不能为空');
                $this->response($result, 'json');
            }
            $start= strtotime($startDate);
            $end = (strtotime($endDate) + 24 * 60 * 60)-1;
            if($start>$end)
            {
                $result = array('status' => 0, 'info' => '开始日期大于截止日期');
                $this->response($result, 'json');
            }
            $today = date('Y-m-d', time());
            if($today==$startDate&&$today==$endDate) {
                $cityAndPattern="";
                if($city!=0) {
                    $cityAndPattern=" AND rn.city_id in({$city}) ";
                }
                if($pattern!=0){
                    $cityAndPattern.=" and rcrc.return_mode in ({$pattern}) ";
                }
                $maintain = $this->car_maintain($cityAndPattern);
                $car_status = $this->car_status($cityAndPattern);
                $result = array('status' => 1, 'info' => '获取数据成功', 'start' => $start, 'end' => $end, 'car_status' => $car_status, 'maintain' => $maintain);
            }else{
                $result = array('status' => 0, 'info' => "不是今天的日期");
            }
            $this->response($result, 'json');
        } else {
            $result = array('status' => 0, 'info' => "只支持POST请求");
            $this->response($result, 'json');
        }
    }

    public function loadCarOrderData()
    {
        if ($this->_method == 'post') {
            $startDate =$_POST['startDate'];
            $endDate = $_POST['endDate'];
            $city = $_POST['city'];
            $pattern = $_POST['pattern'];
            if(empty($startDate))
            {
                $result = array('status' => 0, 'info' => '开始日期不能为空');
                $this->response($result, 'json');
            }
            if (empty($endDate)) {
                $result = array('status' => 0, 'info' => '截止日期不能为空');
                $this->response($result, 'json');
            }
            $start= strtotime($startDate);
            $end = (strtotime($endDate) + 24 * 60 * 60)-1;
            if($start>$end)
            {
                $result = array('status' => 0, 'info' => '开始日期大于截止日期');
                $this->response($result, 'json');
            }
            $today = date('Y-m-d', time());
            if($today==$startDate&&$today==$endDate) {
                $cityAndPattern="";
                if($city!=0) {
                    $cityAndPattern=" AND rn.city_id in({$city}) ";
                }
                if($pattern!=0){
                    $cityAndPattern.=" and rcrc.return_mode in ({$pattern}) ";
                }
                $car_order = $this->car_order($cityAndPattern);
                $result = array('status' => 1, 'info' => '获取数据成功', 'start' => $start, 'end' => $end, 'car_order' => $car_order);
            }else{
                $result = array('status' => 0, 'info' => "不是今天的日期");
            }
            $this->response($result, 'json');
        } else {
            $result = array('status' => 0, 'info' => "只支持POST请求");
            $this->response($result, 'json');
        }
    }

    public function loadOperationCarData()
    {
        if ($this->_method == 'post') {
            $startDate =$_POST['startDate'];
            $endDate = $_POST['endDate'];
            $city = $_POST['city'];
            $pattern = $_POST['pattern'];
            if(empty($startDate))
            {
                $result = array('status' => 0, 'info' => '开始日期不能为空');
                $this->response($result, 'json');
            }
            if (empty($endDate)) {
                $result = array('status' => 0, 'info' => '截止日期不能为空');
                $this->response($result, 'json');
            }
            $start= strtotime($startDate);
            $end = (strtotime($endDate) + 24 * 60 * 60)-1;
            if($start>$end)
            {
                $result = array('status' => 0, 'info' => '开始日期大于截止日期');
                $this->response($result, 'json');
            }
            $cityAndPattern="";
            if($city!=0) {
                $cityAndPattern=" and city_id in({$city}) ";
            }
            if($pattern!=0){
                $cityAndPattern.=" and return_mode in ({$pattern}) ";
            }
            $aa = 'mysql://bjtest_dev1:yU23VFFFwfZ9y5YG@10.1.11.110:3306/testbaojia#utf8';
            $strSql="select sum(day_operation_car) day_operation_car from xmi_day_count where count_time between {$start} and {$end} ";
            $day_operation_car = M('xmi_day_count','',$aa)->query($strSql.$cityAndPattern);
            //echo $strSql.$cityAndPattern;
            $result = array('status' => 1, 'info' => '获取数据成功', 'start' => $start, 'end' => $end, 'day_operation_car' => $day_operation_car[0]['day_operation_car']);
            $this->response($result, 'json');
        } else {
            $result = array('status' => 0, 'info' => "只支持POST请求");
            $this->response($result, 'json');
        }
    }

    public function loadCarOnlineData()
    {
        if ($this->_method == 'post') {
            $startDate =$_POST['startDate'];
            $endDate = $_POST['endDate'];
            $city = $_POST['city'];
            $pattern = $_POST['pattern'];
            $cars = $_POST['cars'];
            if(empty($startDate))
            {
                $result = array('status' => 0, 'info' => '开始日期不能为空');
                $this->response($result, 'json');
            }
            if (empty($endDate)) {
                $result = array('status' => 0, 'info' => '截止日期不能为空');
                $this->response($result, 'json');
            }
            $start= strtotime($startDate);
            $end = (strtotime($endDate) + 24 * 60 * 60)-1;
            if($start>$end)
            {
                $result = array('status' => 0, 'info' => '开始日期大于截止日期');
                $this->response($result, 'json');
            }
            $today = date('Y-m-d', time());
            if($today==$startDate&&$today==$endDate) {
                $car_online = $this->car_online($cars);
                $result = array('status' => 1, 'info' => '获取数据成功', 'start' => $start, 'end' => $end, 'car_online' => $car_online);
            }else{
                $result = array('status' => 0, 'info' => "不是今天的日期");
            }
            $this->response($result, 'json');
        } else {
            $result = array('status' => 0, 'info' => "只支持POST请求");
            $this->response($result, 'json');
        }
    }

    /**
     *用户行为
     **/
    public function user_behavior($start=0, $end=0,$cityAndPattern){
        $strWhere="u.`from` = 710130 and u.`regdate` between {$start} and {$end} ";
        //注册人数
        $register_count = M('ucenter_member')->alias('u')
            ->join('member m on m.uid=u.uid','LEFT')
            ->where($strWhere)
            ->count();
        $strWhere="u.`from` = 710130 AND mic.`verify_status` = 2 AND mic.`verify_idcard_result` = 1 AND mic.`validate_idcard` = 1 AND u.`regdate` between {$start} AND {$end} ";
        //实名认证
        $validate_count = M('ucenter_member')->alias('u')
            ->join("member_identity_card mic ON mic.user_id = u.uid",'left')
            ->join('member m on m.uid=u.uid','LEFT')
            ->where("u.`from` = 710130 AND mic.`verify_status` = 2 AND mic.verify_idcard_result = 1 and mic.update_time between {$start} and {$end}")
            ->count();
        //押金缴纳
        $pay_deposit = M('ucenter_member')->alias('u')
            ->join('trade_payment t ON t.user_id = u.uid','LEFT')
            ->where("u.`from`= 710130 AND t.busi_code = 20 AND t.refilled = 1 AND t.paid_time > {$start} AND t.paid_time<{$end}")
            ->Distinct(true)
            ->field('t.user_id')
            ->select();
        $pay_deposit_count = count($pay_deposit);
        //押金退款人数
        $deposit_return = M('ucenter_member')->alias('u')
            ->join('payable_return t ON t.user_id = u.uid','LEFT')
            ->where("u.`from`= 710130 AND t.create_time between {$start} and {$end}")
            ->Distinct(true)
            ->field('t.user_id')
            ->select();
        $deposit_return_count = count($deposit_return);
        //活跃用户
        $week_active = M('')->query("SELECT count(*) z FROM ( SELECT user_id, count(f) f1 FROM ( SELECT count(*), user_id, FROM_UNIXTIME(t.begin_time, 
            '%Y-%m-%d') f FROM trade_order t LEFT JOIN ucenter_member u ON u.uid = t.user_id WHERE u.`from` = 710130 AND t.begin_time BETWEEN 
        UNIX_TIMESTAMP(date(sysdate())) - 6 * 86400 AND UNIX_TIMESTAMP(date(sysdate()))+1*86400 GROUP BY user_id, FROM_UNIXTIME(t.begin_time, '%Y-%m-%d')) 
        AS table1 GROUP BY user_id HAVING f1 > 1 ) AS table2");
        $month_active = M('')->query("SELECT count(*) z FROM ( SELECT user_id, count(f) f1 FROM ( SELECT count(*), user_id, FROM_UNIXTIME(t.begin_time, 
            '%Y-%m-%d') f FROM trade_order t LEFT JOIN ucenter_member u ON u.uid = t.user_id WHERE u.`from` = 710130 AND t.begin_time BETWEEN 
        UNIX_TIMESTAMP(date(sysdate())) - 29 * 86400 AND UNIX_TIMESTAMP(date(sysdate()))+1*86400 GROUP BY user_id, FROM_UNIXTIME(t.begin_time, '%Y-%m-%d')) 
        AS table1 GROUP BY user_id HAVING f1 > 1 ) AS table2");
        //临时锁车次数
        $lock = M('')->query("SELECT SUM(lock_count) lock_count, COUNT(user_count) user_count FROM ( 
                    SELECT COUNT(1) lock_count, COUNT(log.user_id) user_count, log.order_id, log.op_command 
                    FROM car_item_device_op_log log 
                    LEFT JOIN rent_content rn ON rn.car_item_id = log.car_item_id 
                    LEFT JOIN rent_content_return_config rcrc ON rcrc.rent_content_id = rn.id 
                    WHERE log.order_id <> 0 AND rn.sort_id = 112 AND log.op_command='B'{$cityAndPattern}
                    AND log.start_time BETWEEN {$start} AND {$end} GROUP BY log.order_id HAVING lock_count > 1 ) AS table1");
        $lock_user_count = $lock[0]['user_count'];  //临时锁车人数
        $lock_count = $lock[0]['lock_count'];       //临时锁车次数
        $result['register_count'] = $register_count;                  //注册人数
        $result['validate_count'] = $validate_count;                  //实名认证人数
        $result['pay_deposit_count'] = $pay_deposit_count;            //押金缴纳人数
        $result['deposit_return_count'] = $deposit_return_count;      //押金退款人数
        $result['lock_user_count'] = $lock_user_count;                //临时锁车人数
        $result['lock_count'] = $lock_count;                          //临时锁车次数
        $result['week_active'] = $week_active[0]['z'];      //周活跃用户
        $result['month_active'] = $month_active[0]['z'];    //月活跃用户
        return $result;
    }

    /**
     *订单统计
     **/
    public function order_statistics($start=0, $end=0,$cityAndPattern){
        $order_model = M('Trade_order');
        $where = 'a.create_time>'.$start.' and  a.create_time<'.$end.' and ((a.rent_type=0 and a.earnest_pay_status=1) or (a.rent_type=3 and a.status<>0))';
        //预约次数 所有订单数 order_num
        $order_num =M('')->query("SELECT COUNT(*) AS order_num FROM trade_order a 
        left JOIN rent_content rn on a.rent_content_id=rn.id 
        left JOIN rent_content_search rnt on a.rent_content_id = rnt.rent_content_id 
        left JOIN rent_content_return_config rcrc on rn.id=rcrc.rent_content_id 
        WHERE ( a.create_time BETWEEN {$start} 
        AND {$end}
        and ((a.rent_type=0 and a.earnest_pay_status=1) 
        or (a.rent_type=3 and a.status<>0)) and rn.sort_id = 112 
        and rnt.address_type = 99 
        and rnt.plate_no <> '' 
        ) {$cityAndPattern} ");
        //echo "<pre>";
        //print_r($order_num);

        //使用次数
        $s_where = $where.'  and (a.status=80200 and (a.billing_settlement_status=2 or (a.hand_over_state in(20200,20100) and a.billing_settlement_status<>2)) or ( a.status=50200 and a.hand_over_state=10200))  and rn.sort_id = 112';
        $use_num = $order_model->alias("a")
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rcrc on rn.id=rcrc.rent_content_id','left')
            ->where($s_where.$cityAndPattern)->count();

        //还车次数
        $h_where = $where.' and a.status=80200 and (a.billing_settlement_status=2 or (a.hand_over_state in(20200,20100) and a.billing_settlement_status<>2))  and rn.sort_id = 112';
        $return_car_num = $order_model->alias("a")
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rcrc on rcrc.rent_content_id=rn.id', 'left')
            ->where($h_where.$cityAndPattern)->count();
        //支付次数
        $z_where = $where.' and a.status=80200 and a.billing_settlement_status=2 and rn.sort_id = 112';
        $pay_num = $order_model->alias("a")
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rcrc on rcrc.rent_content_id=rn.id', 'left')
            ->where($z_where.$cityAndPattern)->count();
        //取消预约次数
        $q_where = $where.' and a.status in(-1,10301) and rn.sort_id = 112';
        $cancel_num = $order_model->alias("a")
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rcrc on rcrc.rent_content_id=rn.id', 'left')
            ->where($q_where.$cityAndPattern)->count();
        $result['order_num'] =$order_num[0]['order_num'];                  //预约次数 所有订单数 order_num
        $result['use_num'] =$use_num;                                      //使用次数
        $result['return_car_num'] = $return_car_num;                       //还车次数
        $result['pay_num'] = $pay_num;                                     //支付次数
        $result['cancel_num'] = $cancel_num;                               //取消次数
        return $result;
    }

    /**
     *统计有出租记录的车辆数
     **/
    public function rent_car_count($start=0, $end=0,$cityAndPattern){
        //出租车辆数
        $strSql="select sum(rent_car_count) rent_car_count from(
                select count(distinct car_item_id) rent_car_count,create_time from (
                select rn.car_item_id,from_unixtime(a.create_time, '%Y-%m-%d') create_time 
                from Trade_order a
                left join rent_content rn on a.rent_content_id=rn.id
                left join rent_content_return_config rcrc on rcrc.rent_content_id=rn.id
                where a.create_time between {$start} and {$end} 
                and((a.rent_type=0 and a.earnest_pay_status=1) or (a.rent_type=3 and a.status<>0))
                and (
                a.status=80200 and (
                a.billing_settlement_status=2 
                or (a.hand_over_state in(20200,20100) 
                and a.billing_settlement_status<>2)
                ) 
                or ( a.status=50200 and a.hand_over_state=10200)
                ) 
                and rn.sort_id = 112 {$cityAndPattern}
                ) b group by create_time
                ) c ";
        //echo $strSql;
        $rent_car_count = M('')->query($strSql);
        //echo "<pre>";
        //print_r($rent_car_count);
        $result['rent_car_count'] = $rent_car_count[0]['rent_car_count']?$rent_car_count[0]['rent_car_count']:0;  //出租车辆数
        return $result['rent_car_count'];
    }

    /**
     *订单支付统计
     **/
    public  function  order_pay($start=0,$end=0,$cityAndPattern){
        $order_model = M('Trade_order');
        $where = 'a.create_time>'.$start.' and  a.create_time<'.$end.' and ((a.rent_type=0 and a.earnest_pay_status=1) or (a.rent_type=3 and a.status<>0))';
        $h_where = $where.' and a.status=80200 and (a.billing_settlement_status=2 or (a.hand_over_state in(20200,20100) and a.billing_settlement_status<>2))  and rn.sort_id = 112';
		$h_where2 = $where.' and a.status=80200 and a.billing_settlement_status=2 and rn.sort_id = 112 ';
        $wx_where = $h_where2.' and p.pay_mode=5 and p.pay_status = 1 and p.busi_code !=5';
        $wxpay_arr = $order_model->alias("a")
            ->field('count(a.id) wx_num,sum(p.amount) wx_amount')
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('trade_order_payment t on a.id = t.order_id','right')
            ->join('trade_payment p on t.payment_id = p.id','left')
            ->join('rent_content_return_config rcrc on rcrc.rent_content_id=rn.id', 'left')
            ->where($wx_where.$cityAndPattern)->find();
        $bb_where = $h_where2.' and p.pay_mode=11 and p.pay_status = 1 and p.busi_code !=5';
        $bbpay_arr = $order_model->alias("a")
            ->field('count(a.id) bb_num,sum(p.amount) bb_amount')
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('trade_order_payment t on a.id = t.order_id','right')
            ->join('trade_payment p on t.payment_id = p.id','left')
            ->join('rent_content_return_config rcrc on rcrc.rent_content_id=rn.id', 'left')
            ->where($bb_where.$cityAndPattern)->find();
        $result['wx_num'] = $wxpay_arr['wx_num'];          //微信支付次数
        $result['wx_amount'] = $wxpay_arr['wx_amount']?$wxpay_arr['wx_amount']:0;  //微信支付金额
        $result['bb_num'] = $bbpay_arr['bb_num'];                                  //宝币支付次数
        $result['bb_amount'] = $bbpay_arr['bb_amount']?$bbpay_arr['bb_amount']:0;  //宝币支付金额
        //栏内栏外还车次数
        $hc_where=$h_where.' and tor.version=1 ';
        $return_car = $order_model->alias("a")
            ->field('tor.resault,count(DISTINCT a.id) return_num')
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rcrc on rcrc.rent_content_id=rn.id', 'left')
            ->join('baojia_mebike.trade_order_return_log tor on tor.order_id=a.id','left')
            ->where($hc_where.$cityAndPattern)->group('tor.resault=1')->select();
        if($return_car){
            $result['column_num'] =0;
            $result['column_outside_num']=0;
            foreach ($return_car as $key=>$val){
                if($val['resault'] == 1){
                    $result['column_num'] = $val['return_num'];
                }
                if($val['resault'] == 0){
                    $result['column_outside_num'] = $val['return_num'];
                }
            }
        }else{
            $result['column_num'] = 0;          //栏内还车次数
            $result['column_outside_num'] = 0;  //栏外还车次数
        }
        //网点内/外还车次数
        $phc_where=$h_where.' and tor.corporation_id>=0 and tor.resault=1 and tor.version=1 ';
        $return_car = $order_model->alias("a")
            ->field('tor.corporation_id,count(DISTINCT a.id) return_num')
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rcrc on rcrc.rent_content_id=rn.id', 'left')
            ->join('baojia_mebike.trade_order_return_log tor on tor.order_id=a.id','left')
            ->where($phc_where.$cityAndPattern)->group('tor.corporation_id>0')->select();
        if($return_car){
            $result['point_num'] =0;
            $result['point_outside_num'] =0;
            foreach ($return_car as $key=>$val){
                if($val['corporation_id']>0){
                    $result['point_num'] = $val['return_num'];
                }
                if($val['corporation_id'] == 0){
                    $result['point_outside_num'] = $val['return_num'];
                }
            }
        }else{
            $result['point_num'] = 0;          //网点内还车次数
            $result['point_outside_num'] = 0;  //网点外还车次数
        }
        return $result;
    }

    /**
     *行驶统计
     **/
    public  function  travel($start=0, $end=0,$cityAndPattern){
        $order_model = M('Trade_order');
        $where = 'a.create_time>'.$start.' and  a.create_time<'.$end.' and ((a.rent_type=0 and a.earnest_pay_status=1) or (a.rent_type=3 and a.status<>0))';
        $h_where = $where.' and a.status=80200 and (a.billing_settlement_status=2 or (a.hand_over_state in(20200,20100) and a.billing_settlement_status<>2))  and rn.sort_id = 112 and a.end_time > 0';
        $travel_arr = $order_model->alias("a")
            ->field('count(a.id) num,sum(oe.all_mile) all_mile,sum(a.end_time - a.begin_time) all_time')
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rcrc on rcrc.rent_content_id=rn.id', 'left')
            ->join('trade_order_ext oe on a.id=oe.order_id','left')
            ->where($h_where.$cityAndPattern)->find();
        if($travel_arr){
            $result['all_mile'] = $travel_arr['all_mile'];
            $result['all_time'] = $this->time_conversion($travel_arr['all_time']);
            $result['average_mileage'] = sprintf("%.2f", ($travel_arr['all_mile']/$travel_arr['num']));
            $result['average_time'] = $this->time_conversion(sprintf("%.2f", ($travel_arr['all_time']/$travel_arr['num'])));
        }else{
            $result['all_mile'] = 0;            //总里程
            $result['all_time'] = 0;            //总时间
            $result['average_mileage'] = 0;     //平均里程
            $result['average_time'] = 0;        //平均时间
        }
		$result['sql'] = $order_model->alias("a")->getlastsql();
        return $result;
    }

    /**
     * 车辆现状
     **/
    public function car_status($cityAndPattern){
        $strSql="select rnt.car_item_id,rn.status,rn.sell_status,t.is_sell,rcrc.return_mode,rn.car_info_id 
            from rent_content_search rnt 
            join rent_content rn on rn.id=rnt.rent_content_id
            LEFT JOIN rent_content_return_config rcrc on rcrc.rent_content_id=rnt.rent_content_id
            LEFT JOIN (select rent_content_id,1 as is_sell from baojia.trade_order where rent_type=3 and status>=10100 and status<80200 and status<>10301) t on rn.id=t.rent_content_id 
            where rnt.address_type= 99 AND rnt.plate_no <> '' AND rnt.sort_id = 112 ";
        $car_list = M('')->query($strSql.$cityAndPattern);
        return $car_list;
    }

    /**
     * 车辆在线
     **/
    public function car_online($cars){
        $strSql="select fgu.cid,rn.status,rn.sell_status,rn.car_info_id,fgu.imei,fgs.latitude,fgs.longitude,fga.residual_battery,UNIX_TIMESTAMP(fgs.lastonline) lastonline,fgu.devicetype
        from fed_gps_usercar fgu 
        LEFT JOIN rent_content rn on fgu.cid = rn.car_item_id
        left join fed_gps_status fgs on fgu.imei=fgs.imei
        left join fed_gps_additional fga on fgu.imei=fga.imei 
        WHERE fgu.cid>0 and fgu.devicetype=16
        and fgu.cid in({$cars})";
        $car_online = M('')->query($strSql);
        return $car_online;
    }

    /**
     * 车辆订单
     **/
    public function car_order($cityAndPattern){
        $strSql="select rn.car_info_id,rnt.rent_content_id,rnt.sort_id, IFNULL(t2.ordercount,0) ordercount,
                ifnull(t2.mile,0) mile,rnt.car_item_id,rnt.plate_no,rnt.gis_lat,rnt.gis_lng,rnt.corporation_id,
                rnt.city_id,rnt.province_id,rnt.update_time,rn.status,rn.sell_status,
                t.is_sell,rsh.operate_status from rent_content_search rnt 
                join rent_content rn on rn.id=rnt.rent_content_id 
                LEFT JOIN rent_content_return_config rcrc on rcrc.rent_content_id=rnt.rent_content_id
                left join (select rent_content_id,1 as is_sell from trade_order where rent_type=3 and status>=10100 and status<80200 and status<>10301
                ) t on rn.id=t.rent_content_id        
                LEFT JOIN(SELECT bto.rent_content_id,count(1) AS ordercount,MAX(e.all_mile)  mile 
                FROM trade_order bto LEFT JOIN trade_order_ext e on bto.id=e.order_id 
                WHERE bto.rent_type = 3 AND bto.end_time >(UNIX_TIMESTAMP(NOW()) - 86400 * 2 ) GROUP BY bto.rent_content_id
                ) t2 ON rn.id = t2.rent_content_id 
                join rent_sku_hour rsh on rn.id=rsh.rent_content_id where rnt.address_type=99
                and rnt.plate_no<>'' and rnt.sort_id=112 ";
        $car_order = M('')->query($strSql.$cityAndPattern);
        return $car_order;
    }

    /**
     * 车辆维修数量
     **/
    public function car_maintain($cityAndPattern){
        $strWhere="rnt.address_type = 99 AND rnt.plate_no <> '' AND rnt.sort_id = 112  AND rn.business_flags = 1 AND rs.operate_status = 3 AND rn.car_info_id <> 30150 ";
        //维修车辆
        $maintain = M('rent_content_search')->alias('rnt')
            ->join('rent_content rn ON rn.id = rnt.rent_content_id', 'left')
            ->join('rent_sku_hour rs ON rs.rent_content_id=rnt.rent_content_id', 'left')
            ->join('rent_content_return_config rcrc on rcrc.rent_content_id=rnt.rent_content_id', 'left')
            ->where($strWhere.$cityAndPattern)
            ->count();
        return $maintain;
    }

    private function time_conversion($time) {
        $time = abs($time);
        $start = 0;
        $string='';
        /*$y = floor($time / 31536000);
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
                $string .= $h . "小时";
        }*/
        $s = floor($time / (60));
        if ($start || $s) {
            $start = 1;
            $time -= $s * 60;
            if ($s)
                $string .= $s . "分钟";
        }
        if (empty($string)) {
            return abs($time) . '秒';
        }
        return $string;
    }
}