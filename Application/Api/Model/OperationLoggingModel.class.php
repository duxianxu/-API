<?php
namespace Api\Model;
use Think\Model;

class OperationLoggingModel extends Model{

//    protected $trueTableName = 'operation_logging_1';
    /**
     * 全部信息
     * @param 添加数据
     */
    public function add_record($param){

//        return $param;
        if(!is_array($param))
        {
            return -1;
        }else{
            $id = $this->add($param);
            return $id;
        }

    }
    /**
     * 查询三条记录信息
     * @param
     */
    public function  car_operation_log($rent_content_id = '',$uid=0,$corporation_id=0){
		$user_arr = A('Battery')->beforeUserInfo($uid,$corporation_id);
        $map['rent_content_id'] =  $rent_content_id;
		if($user_arr['role_type'] == '运维'){
            $map['_string'] = " (uid = {$user_arr['user_id']} and operate IN (7, 9, 10)) or operate not IN (7, 9, 10)";
		}else{
			$map['operate'] = array('neq',0);
		}
        $res = $this->field('id,uid,operate,car_rule,time,source,desc')->where($map)->order('time desc')->limit(3)->select();
        // var_dump(strpos($val['operate'],'5') == 0 );die;
        foreach ($res as &$val){
			$val['desc'] = $val['desc']?$val['desc']:"";
            //查询运维姓名
            if( $val['operate'] == 12 || $val['operate'] == 13 || $val['operate'] == 14 )
            {
                $val['is_circle'] = 0;
            }else{
                $val['is_circle'] = 1;
            }
            $umap['user_id'] = $val['uid'];
            $repair = M('baojia_mebike.repair_member')->where($umap)->getField('user_name');
            $val['user_name'] = $repair?$repair:"";
            if($val['operate'] == 1){
                if($val['car_rule'] == -1){
                    $val['operate'] = '无电离线换电';
                }else{
                    $val['operate'] = '换电设防';
                }
            }else if($val['operate'] == -1){
                if($val['car_rule'] == -1){
                    $val['operate'] = '无电离线换电';
                }else {
                    $val['operate'] = '换电未设防';
                }
            }else if($val['operate'] == 2){
                if($val['car_rule'] == -1){
                    $val['operate'] = '无电离线换电';
                }else {
                    $val['operate'] = '换电设防失败';
                }
            }else if($val['operate'] == 3){
                $val['operate'] = ($val['source'] == 2)?'QY确认回收':'确认回收';
            }else if($val['operate'] == 4){
                $val['operate'] = '完成小修';
            }else if($val['operate'] == 5){
                $val['operate'] = '下架回收';
            }else if($val['operate'] == 6){
                $val['operate'] = '待小修';
            }else if($val['operate'] == 7){
                $val['operate'] = '车辆丢失';
            }else if($val['operate'] == 8){
                if($val['source'] == 1){
                    $val['operate'] = 'H5上架待租';
                }else if($val['source'] == 2){
                    $val['operate'] = 'QY上架待租';
                }else if($val['source'] == 3){
                    $val['operate'] = 'ICSS上架待租';
                }else{
                    $val['operate'] = '上架待租';
                }
            }else if($val['operate'] == 9){
                $val['operate'] = '疑失';
            }else if($val['operate'] == 10){
                $val['operate'] = '疑难';
            }else if($val['operate'] == 11){
                $val['operate'] = '电池丢失';
            }else if($val['operate'] == 12){
                $val['operate'] = '设防';
            }else if($val['operate'] == 13){
                $val['operate'] = '撤防';
            }else if($val['operate'] == 14){
                $val['operate'] = '启动';
            }else if($val['operate'] == 15){
                if($val['source'] == 1){
                    $val['operate'] = 'H5人工停租';
                }else if($val['source'] == 2){
                    $val['operate'] = 'QY人工停租';
                }else{
                    $val['operate'] = 'ICSS人工停租';
                }
            }else if($val['operate'] == 100){
                $val['operate'] = '备注信息';
            }else if($val['operate'] == 16){
                $val['operate'] = '手动校正';
            }else if($val['operate'] == 17){
                $val['operate'] = '自动校正';
            }else if($val['operate'] == 18){
                $val['operate'] = '找回车辆，已上报';
            }else if($val['operate'] == 19){
                $val['operate'] = $val['desc'];
            }else if($val['operate'] == 19){
				$val['operate'] = $val['desc'];
			}else if($val['operate'] == 23){
				$val['operate'] = "无此故障";
			}else if($val['operate'] == 24){
				$val['operate'] = "有此故障";
			}else if($val['operate'] == 30){
				$val['operate'] = "检修任务";
			}else if($val['operate'] == 31){
				$val['operate'] = "寻车任务";
			}else if($val['operate'] == 32){
				$val['operate'] = "验收入库";
			}else if($val['operate'] == 33){
				$val['operate'] = "开始换电";
			}else if($val['operate'] == 34){
				$val['operate'] = "开仓锁";
			}else if($val['operate'] == 35){
				$val['operate'] = "故障上报";
			}else if($val['operate'] == 36){
				$val['operate'] = "备注车辆";
			}else if($val['operate'] == 37){
				$val['operate'] = "被扣押上报";
			}else if($val['operate'] == 42){
				$val['operate'] = "回库任务";
			}else if($val['operate'] == 43){
				$val['operate'] = "调度任务";
			}else if($val['operate'] == 44){
                $val['operate'] = "换电任务";
            }else if($val['operate'] == 45){
                $val['operate'] = "关仓";
            }else if($val['operate'] == 46){
                $val['operate'] = "重启盒子";
            }else if($val['operate'] == 25){
                $val['operate'] = "特殊下架";
            }else{
				$val['operate'] = '';
			}
            $val['time'] = date("Y-m-d H:i:s",$val['time']);
			
            unset($val['uid']);
        }
        //  echo "<pre>";
        // print_r($res);
        return  $res;
    }

    /* 所有MODEL公共方法 */
    public function get_one($where,$field='',$order='')
    {
        return $this->field($field)->where($where)->order($order)->find();
    }
    public function get_all($where,$field='',$order='',$p="",$limit="")
    {
        return $this->field($field)->where($where)->order($order)->page($p,$limit)->select();
    }
    public function update($where,$data)
    {
        return $this->where($where)->save($data);
    }
    public function get_count($where)
    {
        return $this->where($where)->count();
    }













}
?>


