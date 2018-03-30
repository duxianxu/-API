<?php
namespace Api\Logic;

class FreeCar{
    /*
	 *检测是否是红包车
	 *cid car_item_id
     20171016新规则:
     * 1.非有单无程的车辆
     * 2.创建时间超过15天
     * 3.72小时内无单且电量低于90%
     * 4.最后一次上架操作后有过订单的车辆
	*/
    public function checkfreecar($rent_content_id,$corporation_parent_id,$test=0){
        $is_free=0;
        if(!empty($corporation_parent_id)) {
            //红包车配置 baojia_mebike.corporation_favour_config  字段is_favour=0 为不参与红包车 没有记录的默认参与
            $is_favour = M("baojia_mebike.corporation_favour_config")
                ->where("corporation_id={$corporation_parent_id}")
                ->getField("is_favour");
            //echo "aaa".$is_favour;die;
            if ($is_favour && $is_favour == 0) {
                return $is_free;
            }
        }
        //创建时间超过15天
        $create_time= M("rent_content")
            ->where("create_time<= (UNIX_TIMESTAMP(NOW()) - 86400 * 15) and id={$rent_content_id}")
            ->getField("create_time");
        if (empty($create_time)) {
            return $is_free;
        }
        //非有单无程的车辆 排除疑似故障车(有单无程车辆)
        $fault_car=M('mileage_statistics')
            ->where("no_mileage_num<>3 and rent_content_id={$rent_content_id}")
            ->getField("rent_content_id");
        if($fault_car) {
            //查询电量
            $battery = M("rent_content_ext")
                ->where("rent_content_id={$rent_content_id}")
                ->getField("battery_capacity");
            if($battery&&$battery< 90) {
                //72小时内无单且电量低于90%
                $strSql72 = "and rent_content_id={$rent_content_id} and create_time>(UNIX_TIMESTAMP(NOW()) - 86400 * 3 )";
                $num72 = M('trade_order')
                    ->where("rent_type = 3 {$strSql72}")
                    ->group('rent_content_id')
                    ->count(1);
                if (!$num72 || $num72 == 0) {
                    //最后一次上架操作后有过订单的车辆
                    $log =M("operation_logging")
                        ->where(["operate" => 8,"rent_content_id"=>$rent_content_id])
                        ->field("time")
                        ->order("time desc")
                        ->limit(1)
                        ->select();
                    if($log&&$log[0]["time"]) {
                        $order_count = M("trade_order")->where("rent_type=3 AND create_time>{$log[0]["time"]} AND rent_content_id={$rent_content_id}")->count(1);
                        if ($order_count > 0) {
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
}
