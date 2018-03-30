<?php
namespace Home\Controller;
use Think\Controller;
use Think\Controller\RestController;
class IndexController extends RestController {
    public function index(){
      $this->response(["code" => 1, "message" => "短信发送成功"], 'json');
     if(IS_POST)
    	{
    		$stime = strtotime($_POST['startTime']);
    		$etime = strtotime($_POST['endTime']);
    		if(empty($stime)||empty($etime))
    		{
    			$this->error('请选择日期','',1);
    		}
    		elseif($stime>$etime)
    		{
    			$this->error('开始日期大于结束日期，请重新选择','',1);
    		}
    		else{
           echo $_POST['startTime'].'('.$stime.')',$_POST['endTime'].'('.$etime.')';
          $where = "";
          if($_POST['city'])
          {
             $city_id_array = I("post.city");  //选择的城市
             foreach ($city_id_array as $v) {
              $city .= $v.',';
             } 
             $city = rtrim($city,',');
             $where.=" and rn.city_id in($city)";
          }
          if($_POST['return_car'])
          {
             $return_car_array = I("post.return_car");  //选择的运营模式
             foreach ($return_car_array as $v) {
              $return_car .= $v.',';
             } 
             $return_car = rtrim($return_car,',');
             $where.=" and rnc.return_mode in($return_car)";
          }     
         
            $user_action = $this->user_action($stime,$etime,$where);
             
            $car_status = $this->car_status($where);
            
            $order  = $this->order_count($stime,$etime,$where);
           
            $travel = $this->travel_count($stime,$etime,$where);
            
            $pay_count = $this->pay_type_count($stime,$etime,$where);
                   $citys = $this->xmi_city();
                 // echo "<pre/>";
                 // print_r($car_status);die;
                 $this->assign('city_id',$city_id_array);
                 $this->assign('return_car',$return_car_array);
                 $this->assign('list',$citys);
                 $this->assign('leibie',1);
        		     $this->assign('zhuce',$user_action['zhuce']);
        		     $this->assign('shiming',$user_action['shiming']);
        		     $this->assign('pay',$user_action['pay']);
        		     $this->assign('pay_return',$user_action['pay_return']);
        		     $this->assign('nlock',$user_action['nlock']);
        		     $this->assign('manlock',$user_action['manlock']); 

        		     $this->assign('torent',$car_status['torent']);
        		     $this->assign('rentout',$car_status['rentout']);
        		     $this->assign('crossxiajia',$car_status['crossxiajia']);
        		     $this->assign('offline',$car_status['offline']);
        		     $this->assign('nopower',$car_status['nopower']);
        		     $this->assign('return',$car_status['return']);
        		     $this->assign('stophire',$car_status['stophire']);
        		     $this->assign('maintain',$car_status['maintain']);
                 $this->assign('yunying',$car_status['yunying']);
                 $this->assign('noyunying',$car_status['noyunying']);

    		         $this->assign('reservation_num',$order['reservation_num']);  //预约次数
                 $this->assign('use_num',$order['use_num']);                  //使用次数
                 $this->assign('return_car_num',$order['return_car_num']);    //还车次数
                 $this->assign('pay_num',$order['pay_num']);                  //支付次数
                 $this->assign('cancel_num',$order['cancel_num']);            //取消预约次数

                 $this->assign('all_mile',$travel['all_mile']);               //总里程
                 $this->assign('all_time',$travel['all_time']);               //总时间
                 $this->assign('average_mileage',$travel['average_mileage']); //平均里程
                 $this->assign('average_time',$travel['average_time']);       //平均时间

                 $this->assign('wx_num',$pay_count['wx_num']);                   //微信支付次数
                 $this->assign('wx_amount',$pay_count['wx_amount']);             //微信支付金额
                 $this->assign('bb_num',$pay_count['bb_num']);                   //宝币支付次数
                 $this->assign('bb_amount',$pay_count['bb_amount']);             //宝币支付金额
                 $this->assign('column_num',$pay_count['column_num']);           //栏内还车次数
                 $this->assign('column_outside_num',$pay_count['column_outside_num']); //栏外还车次数
                 $this->assign('stime',$_POST['startTime']); $this->assign('etime',$_POST['endTime']); 
                 $this->display('index');
    		}
    	}
    	else{

            $citys = $this->xmi_city();
            $this->assign('list',$citys);
    		    $this->display('index');
    	}
    	
   }
   /**
   *用户行为
   *start  开始时间
   *end    结束时间
   **/
    public function user_action($stime=0, $etime=0,$where=""){
               //注册人数
                $zhuce = M('ucenter_member')->where("`from` = 710130 and `regdate`>{$stime} and `regdate`<{$etime}")->count();
                //身份实名认证
                $shiming = M('ucenter_member')->alias('u')  
                          ->join("member_identity_card m ON m.user_id = u.uid",'left')
                          ->where("u.`from` = 710130 AND m.`verify_status` = 2 AND m.`verify_idcard_result` = 1 AND m.`validate_idcard` = 1 AND u.`regdate` > {$stime} AND u.`regdate`<{$etime}")
                          ->count();
                //押金缴纳人数
                $pay = M('ucenter_member')->alias('u')   
                       ->join('trade_payment t ON t.user_id = u.uid','LEFT')
                       ->where("u.`from`= 710130 AND t.busi_code = 20 AND t.refilled = 1 AND t.paid_time > {$stime} AND t.paid_time<{$etime}")
                       ->Distinct(true)
                       ->field('t.user_id')
                       ->select();
                $pay = count($pay);
                //押金退款人数 
                $pay_return = M('ucenter_member')->alias('u')   
                       ->join('trade_payment t ON t.user_id = u.uid','LEFT')
                       ->where("u.`from`= 710130 AND t.busi_code = 99 AND t.paid_time > {$stime} AND t.paid_time<{$etime}")
                       ->Distinct(true)
                       ->field('t.user_id')
                       ->select();
                $pay_return = count($pay_return); 
                //临时锁车次数
                   $nlock = M('')->query("SELECT SUM(a) a, COUNT(b) b FROM ( SELECT COUNT(1) a, COUNT(cl.user_id) b, cl.order_id, cl.op_command FROM car_item_device_op_log cl LEFT JOIN rent_content rn 
                    ON rn.car_item_id = cl.car_item_id LEFT JOIN rent_content_return_config rnc ON rnc.rent_content_id = rn.id WHERE cl.order_id <> 0 AND rn.sort_id = 112 AND cl.op_command LIKE '%B' $where 
                    AND cl.start_time BETWEEN {$stime} AND {$etime} GROUP BY cl.order_id HAVING a > 1 ) AS table1");
                   $manlock = $nlock[0]['b'];  //临时锁车人数
                   $nlock = $nlock[0]['a'] - $nlock[0]['b'];

                   $result['zhuce'] = $zhuce;             //注册人数
                   $result['shiming'] = $shiming;         //身份实名认证
                   $result['pay'] = $pay;                 //押金缴纳人数
                   $result['pay_return'] = $pay_return;   //押金退款人数
                   $result['nlock'] =  $nlock;            //临时锁车次数
                   $result['manlock'] = $manlock;         //临时锁车人数
                   return $result;
    }
     /**
   *车辆现状
   *start  开始时间
   *end    结束时间
   **/
    public function car_status($where=""){
              //运营中车辆 
                $res = M('rent_content');
                $yunying = $res->alias('rn')
                          ->join('rent_content_search rnt ON rn.id = rnt.rent_content_id','left')
                          ->join('rent_content_return_config rnc ON rn.id = rnc.rent_content_id','left')
                          ->where("rnt.address_type = 99 AND rnt.plate_no <> '' AND rnt.sort_id = 112 AND rn. STATUS = 2 AND rn.sell_status = 1 AND rn.car_info_id <> 30150 ".$where)
                          ->count();
                          
        // var_dump($torent);die;
                //出租中车辆
                   $res1 = M('rent_content_search');
                    $rentout = $res1->alias('rnt')
                           ->join('rent_content rn ON rn.id = rnt.rent_content_id','left')
                           ->join('trade_order t ON t.rent_content_id = rn.id','left')
                           ->join('rent_content_return_config rnc ON rn.id = rnc.rent_content_id','left')
                           ->where("rnt.address_type = 99 AND rnt.plate_no <> '' AND rnt.sort_id = 112 AND rn.sell_status=1 and t.rent_type = 3 AND t. STATUS >= 10100 AND t. STATUS < 80200 AND t. STATUS <> 10301 AND rn.car_info_id <> 30150".$where)
                           ->count();
                           // echo $res1->getLastSql();die;
                          //当前时间待租车辆
                   $torent = $yunying - $rentout;
               
                //越界下架车辆
                  $crossxiajia = $res1->alias('rnt')
                           ->join('rent_content rn ON rn.id = rnt.rent_content_id','left')
                           ->join('rent_content_return_config rnc ON rn.id = rnc.rent_content_id','left')
                           ->where("rnt.address_type = 99 AND rnt.plate_no <> '' AND rnt.sort_id = 112  AND rn.sell_status = -7 AND rn.car_info_id <> 30150".$where)
                           ->count();
                 //离线下驾车辆
                    $offline = $res1->alias('rnt')
                           ->join('rent_content rn ON rn.id = rnt.rent_content_id','left')
                           ->join('rent_content_return_config rnc ON rn.id = rnc.rent_content_id','left')
                           ->where("rnt.address_type = 99 AND rnt.plate_no <> '' AND rnt.sort_id = 112  AND rn.sell_status = -100 AND rn.car_info_id <> 30150".$where)
                           ->count(); 
                //馈电下架车辆
                    $nopower = $res1->alias('rnt')
                           ->join('rent_content rn ON rn.id = rnt.rent_content_id','left')
                           ->join('rent_content_return_config rnc ON rn.id = rnc.rent_content_id','left')
                           ->where("rnt.address_type = 99 AND rnt.plate_no <> '' AND rnt.sort_id = 112  AND rn.sell_status = -1 AND rn.car_info_id <> 30150".$where)
                           ->count();  
                //回收下架车辆
                    $return = $res1->alias('rnt')
                           ->join('rent_content rn ON rn.id = rnt.rent_content_id','left')
                           ->join('rent_content_return_config rnc ON rn.id = rnc.rent_content_id','left')
                           ->where("rnt.address_type = 99 AND rnt.plate_no <> '' AND rnt.sort_id = 112  AND rn.sell_status = -10 AND rn.car_info_id <> 30150".$where)
                           ->count();
                //人工停租车辆  
                    $stophire = $res1->alias('rnt')
                           ->join('rent_content rn ON rn.id = rnt.rent_content_id','left')
                           ->join('rent_content_return_config rnc ON rn.id = rnc.rent_content_id','left')
                           ->where("rnt.address_type = 99 AND rnt.plate_no <> '' AND rnt.sort_id = 112  AND rn.sell_status = 0 AND rn. STATUS = 2 AND rn.car_info_id <> 30150".$where)
                           ->count(); 
                //维修车辆
                 $maintain = $res1->alias('rnt')
                           ->join('rent_content rn ON rn.id = rnt.rent_content_id','left')
                           ->join('rent_sku_hour rs ON rs.rent_content_id=rnt.rent_content_id','left')
                           ->join('rent_content_return_config rnc ON rn.id = rnc.rent_content_id','left')
                           ->where("rnt.address_type = 99 AND rnt.plate_no <> '' AND rnt.sort_id = 112  AND rn.business_flags = 1 AND rs.operate_status = 3 AND rn.car_info_id <> 30150".$where)
                           ->count();
                //未运营车辆
                   $noyunying = $crossxiajia + $offline + $nopower + $return + $stophire + $maintain;
                   $result['torent'] = $torent;             //当前时间待租车辆
                   $result['rentout'] = $rentout;           //出租中车辆
                   $result['yunying'] = $yunying;           //运营中车辆
                   $result['crossxiajia'] = $crossxiajia;   //越界下架车辆
                   $result['offline'] =  $offline;          //离线下驾车辆
                   $result['nopower'] = $nopower;           //馈电下架车辆
                   $result['return'] = $return;             //回收下架车辆 
                   $result['stophire'] = $stophire;         //人工停租车辆
                   $result['maintain'] = $maintain;         //维修车辆
                   $result['noyunying'] = $noyunying;       //未运营车辆
                   return $result;
    }
   /**
      *订单统计
     * start  开始时间
     * end    结束时间
     **/
    public function order_count($start=0, $end=0, $where1){
        //预约次数
        $order_model = M('Trade_order');
        $where = 'a.create_time>'.$start.' and  a.create_time<'.$end.' and ((a.rent_type=0 and a.earnest_pay_status=1) or (a.rent_type=3 and a.status<>0))';
        $y_where = $where.' and  a.status in(40200,50200) and a.owner_consented=2 and a.hand_over_state<10200  and rn.sort_id = 112';
        $reservation_num = $order_model->alias("a")
                       ->join('rent_content rn on a.rent_content_id=rn.id','left')
                       ->join('rent_content_return_config rnc on rn.id=rnc.rent_content_id','left')
                       ->where($y_where.$where1)->count();
        //使用次数
        $s_where = $where.'  and (a.status=80200 and (a.billing_settlement_status=2 or (a.hand_over_state in(20200,20100) and a.billing_settlement_status<>2)) or ( a.status=50200 and a.hand_over_state=10200))  and rn.sort_id = 112';
        $use_num = $order_model->alias("a")
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rnc on rn.id=rnc.rent_content_id','left')
            ->where($s_where.$where1)->count();
        //还车次数
        $h_where = $where.' and a.status=80200 and (a.billing_settlement_status=2 or (a.hand_over_state in(20200,20100) and a.billing_settlement_status<>2))  and rn.sort_id = 112';
        $return_car_num = $order_model->alias("a")
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rnc on rn.id=rnc.rent_content_id','left')
            ->where($h_where.$where1)->count();
        //支付次数
        $z_where = $where.' and a.status=80200 and a.billing_settlement_status=2 and rn.sort_id = 112';
        $pay_num = $order_model->alias("a")
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rnc on rn.id=rnc.rent_content_id','left')
            ->where($z_where.$where1)->count();
        // echo $order_model->getLastSql();
        //取消预约次数
        $q_where = $where.' and a.status in(-1,10301) and rn.sort_id = 112';
        $cancel_num = $order_model->alias("a")
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('rent_content_return_config rnc on rn.id=rnc.rent_content_id','left')
            ->where($q_where.$where1)->count();
        $result['reservation_num'] = $reservation_num;  //预约次数
        $result['use_num'] = $use_num;                  //使用次数
        $result['return_car_num'] = $return_car_num;    //还车次数
        $result['pay_num'] = $pay_num;                  //支付次数
        $result['cancel_num'] = $cancel_num;            //取消次数
        return $result;
//        echo "<pre>";
//        var_dump($result);
    }

    /**
     *行驶统计
     * start  开始时间
     * end    结束时间
     **/
    public  function  travel_count($start=0, $end=0, $where1){
        $order_model = M('Trade_order');
        $where = 'a.create_time>'.$start.' and  a.create_time<'.$end.' and ((a.rent_type=0 and a.earnest_pay_status=1) or (a.rent_type=3 and a.status<>0))';
        $h_where = $where.' and a.status=80200 and (a.billing_settlement_status=2 or (a.hand_over_state in(20200,20100) and a.billing_settlement_status<>2))  and rn.sort_id = 112 and a.end_time > 0';
        $travel_arr = $order_model->alias("a")
            ->field('count(a.id) num,sum(oe.all_mile) all_mile,sum(a.end_time - a.begin_time) all_time')
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('trade_order_ext oe on a.id=oe.order_id','left')
            ->join('rent_content_return_config rnc on rn.id=rnc.rent_content_id','left')
            ->where($h_where.$where1)->find();
        if($travel_arr){
            $result['all_mile'] = $travel_arr['all_mile'];
            $result['all_time'] = $this->timecha($travel_arr['all_time']);
            $result['average_mileage'] = sprintf("%.2f", ($travel_arr['all_mile']/$travel_arr['num']));
            $result['average_time'] = $this->timecha(sprintf("%.2f", ($travel_arr['all_time']/$travel_arr['num'])));
        }else{
            $result['all_mile'] = 0;            //总里程
            $result['all_time'] = 0;            //总时间
            $result['average_mileage'] = 0;    //平均里程
            $result['average_time'] = 0;       //平均时间
        }
        return $result;
    }

    /**
     *支付类型统计
     * start  开始时间
     * end    结束时间
     **/
    public  function  pay_type_count($start=0, $end=0, $where1){
        $order_model = M('Trade_order');
        $where = 'a.create_time>'.$start.' and  a.create_time<'.$end.' and ((a.rent_type=0 and a.earnest_pay_status=1) or (a.rent_type=3 and a.status<>0))';
        $h_where = $where.' and a.status=80200 and (a.billing_settlement_status=2 or (a.hand_over_state in(20200,20100) and a.billing_settlement_status<>2))  and rn.sort_id = 112';
        $wx_where = $h_where.' and p.pay_mode=5 and p.pay_status = 1';
        $wxpay_arr = $order_model->alias("a")
            ->field('count(a.id) wx_num,sum(p.amount) wx_amount')
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('trade_order_payment t on a.id = t.order_id','right')
            ->join('trade_payment p on t.payment_id = p.id','left')
            ->join('rent_content_return_config rnc on rn.id=rnc.rent_content_id','left')
            ->where($wx_where.$where1)->find();
        $bb_where = $h_where.' and p.pay_mode=11 and p.pay_status = 1';
        $bbpay_arr = $order_model->alias("a")
            ->field('count(a.id) bb_num,sum(p.amount) bb_amount')
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('trade_order_payment t on a.id = t.order_id','right')
            ->join('trade_payment p on t.payment_id = p.id','left')
            ->join('rent_content_return_config rnc on rn.id=rnc.rent_content_id','left')
            ->where($bb_where.$where1)->find();
        $result['wx_num'] = $wxpay_arr['wx_num'];          //微信支付次数
        $result['wx_amount'] = $wxpay_arr['wx_amount']?$wxpay_arr['wx_amount']:0;  //微信支付金额
        $result['bb_num'] = $bbpay_arr['bb_num'];                               //宝币支付次数
        $result['bb_amount'] = $bbpay_arr['bb_amount']?$bbpay_arr['bb_amount']:0;  //宝币支付金额
        //栏内栏外还车次数
        // $h_where .= ' and tor.version = 1';
        $return_car = $order_model->alias("a")
            ->field('tor.resault,count(DISTINCT a.id) return_num')
            ->join('rent_content rn on a.rent_content_id=rn.id','left')
            ->join('baojia_mebike.trade_order_return_log tor on tor.order_id=a.id','left')
            ->join('rent_content_return_config rnc on rn.id=rnc.rent_content_id','left')
            ->where($h_where.$where1)->group('tor.resault=1')->select();
        if($return_car){
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
        return $result;
    }

    /**
      *小蜜单车城市表
     */
    public  function  xmi_city(){
        $Model = new \Think\Model();
        $city = $Model->query('select id,name,gis_lat,gis_lng from baojia.area_city where status=1 and id in(select DISTINCT city_id from  baojia.rent_content where sort_id=112) and id !=3');
        return $city;
    }
	//统计小蜜每天运营中的车辆
	public  function  xmi_city_count(){

        header('Content-Type:text/html; charset=utf-8');
        $city = $this->xmi_city();

        $return_mode = array(0,1,2,4,32);
        foreach ($city as &$val){
			if($val['name'] == '等待'){
				$val['id'] = 0;
			}
			foreach ($return_mode as $vv){
				//echo $val['name']."---".$val['id']."---".$vv."<br/>";
				$this->xmi_count(0,0,$val['id'],$vv);
			}
             /*if($val['name'] == '等待'){
                 $this->xmi_count(0,0,0,0);
             }else{
                 foreach ($return_mode as $vv){
                     $this->xmi_count(0,0,$val['id'],$vv);
                 }
             }*/
        }

    }
	//统计天津每天运营中的车辆
	public  function   xmi_tianjin_count(){
		$city = M('')->query('select id,name,gis_lat,gis_lng from baojia.area_city where status=1 and id =3');
        $return_mode = array(0,1,2,4,32);
        foreach ($city as $val){
			foreach ($return_mode as $vv){
				// echo $val['name']."---".$val['id']."---".$vv."<br/>";
				$this->xmi_count(0,0,$val['id'],$vv);
			}
        }
	}

    public  function  xmi_count($start=0,$end=0,$city_id=0,$return_mode=0){
        $dq_time = time();
        $dq_hour = date('H',time());
        if($dq_hour == '0' || $dq_hour == '00'){
            $dq_time = strtotime("-1 day");
        }

        $start = $start?strtotime($start):strtotime(date('Y-m-d',$dq_time).' 00:00:00');
        $end   = $end?strtotime($end):strtotime(date('Y-m-d',$dq_time).' 23:59:59');
        $order_model = M('Trade_order');
        $where = 'a.create_time>'.$start.' and  a.create_time<'.$end.' and ((a.rent_type=0 and a.earnest_pay_status=1) or (a.rent_type=3 and a.status<>0))';
        if(!empty($city_id)){
            $where .= ' and r.city_id ='.$city_id.'';
            $where3 = ' and rn.city_id ='.$city_id.'';
        }
        if(!empty($return_mode)){
            $where .= ' and rnc.return_mode ='.$return_mode.'';
            $where3 .= ' and rnc.return_mode ='.$return_mode.'';
        }
		//订单总数
        $o_where = $where." and r.sort_id = 112  and rnt.address_type = 99 and rnt.plate_no <> ''";
        $total_order =  $order_model->alias("a")
            ->join('rent_content r on a.rent_content_id=r.id','left')
            ->join('rent_content_search rnt on a.rent_content_id = rnt.rent_content_id','left')
            ->join('rent_content_return_config rnc on r.id=rnc.rent_content_id','left')
            ->where($o_where)->count();
        //每日有出租记录的车数
        $s_where = $where."  and (a.status=80200 and (a.billing_settlement_status=2 or (a.hand_over_state in(20200,20100) and a.billing_settlement_status<>2)) or ( a.status=50200 and a.hand_over_state=10200))  and r.sort_id = 112  and rnt.address_type = 99 AND rnt.plate_no <> ''";
        $day_rent = $order_model->alias("a")
            // ->field('count(distinct rnt.plate_no) day_rent,count(1) use_num')
            ->join('rent_content r on a.rent_content_id=r.id','left')
            ->join('rent_content_search rnt on a.rent_content_id = rnt.rent_content_id','left')
            ->join('rent_content_return_config rnc on r.id=rnc.rent_content_id','left')
            ->where($s_where)->count('distinct rnt.plate_no');

        
		//运营中车辆
        $res = M('rent_content');
        $yunying = $res->alias('rn')
            ->join('car_item_verify civ ON rn.car_item_id = civ.car_item_id','left')
            ->join('rent_content_return_config rnc ON rn.id = rnc.rent_content_id','left')
            ->where(" civ.plate_no like 'DD%' AND rn.sort_id = 112 AND rn. STATUS = 2 AND rn.sell_status = 1 AND rn.car_info_id <> 30150 ".$where3)
            ->count();
        // echo $yunying;
        //每日支付的金额
        $h_where = $where." and a.status=80200 and (a.billing_settlement_status=2 or (a.hand_over_state in(20200,20100) and a.billing_settlement_status<>2))  and r.sort_id = 112  and rnt.address_type = 99 AND rnt.plate_no <> ''";
        $wx_where = $h_where.' and p.pay_mode in(5,11) and p.pay_status = 1  and p.busi_code != 5';
        $pay_amount = $order_model->alias("a")
            ->join('rent_content r on a.rent_content_id=r.id','left')
            ->join('rent_content_search rnt on r.id = rnt.rent_content_id','right')
            ->join('rent_content_return_config rnc on r.id=rnc.rent_content_id','left')
            ->join('trade_order_payment t on a.id = t.order_id','right')
            ->join('trade_payment p on t.payment_id = p.id','left')
            ->where($wx_where)->sum('p.amount');
        //日车均订单数
        $day_average_order = sprintf("%.2f", $total_order/$yunying);
        //贡献值（出租）
        $day_rent_amount  =  sprintf("%.2f", $pay_amount/$day_rent);
        //贡献值（运营）
        $day_operation_amount  =  sprintf("%.2f", $pay_amount/$yunying);
        //出租率（运营中）
        $day_chuzulv  =  sprintf("%.2f", $day_rent/$yunying);
        // echo "<br/>".$day_average_order;
        // echo "<br/>".$day_rent_amount;
        // echo "<br/>".$day_operation_amount;
        // echo "<br/>".$day_chuzulv;
        // $aa = 'mysql://bjtest_dev1:yU23VFFFwfZ9y5YG@10.1.11.110:3306/baojia_mebike#utf8';
        // $aa = 'mysql://apitest-baojia:TKQqB5Gwachds8dv@10.1.11.110:3306/baojia_mebike#utf8';
        $data['order_total'] = $total_order;
        $data['day_operation_car'] = $yunying;
        $data['day_average_order'] = $day_average_order;
        $data['pay_amount']        = $pay_amount?$pay_amount:0;
        $data['day_rent']          = $day_rent;
        $data['day_rent_amount']   = $day_rent_amount;
        $data['day_operation_amount']  = $day_operation_amount;
        $data['day_chuzulv']       = $day_chuzulv;
        $data['city_id']           = $city_id;
        $data['return_mode']       = $return_mode;
        $data['count_time']        = $start;
        $data['create_time']       = time();
        $map["city_id"] = $city_id;
        $map["return_mode"] = $return_mode;
        $map["count_time"] = array('between',array($start,$end));
        $result = M('baojia_mebike.xmi_day_count')->where($map)->find();
        if(!$result){
            M('baojia_mebike.xmi_day_count')->add($data);
        }
    }

    private function timecha($time) {
        $time = abs($time);
        $start = 0;
        $y = floor($time / 315360000);
        if ($start || $y) {
            $start = 1;
            $time -= $y * 315360000;
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
        $h = floor($time / 36000);
        if ($start || $h) {
            $start = 1;
            $time -= $h * 36000;
            if ($h)
                $string .= $h . "时";
        }
        $s = floor($time / (600));
        if ($start || $s) {
            $start = 1;
            $time -= $s * 600;
            if ($s)
                $string .= $s . "分";
        }
        if (empty($string)) {
            return abs($time) . '秒';
        }
        return $string;
    }
	
	
}