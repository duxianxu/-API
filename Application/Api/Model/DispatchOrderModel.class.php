<?php
namespace Api\Model;
use Think\Model;
class DispatchOrderModel extends Model{
    protected $dbName = 'baojia_mebike';
//    protected $trueTableName = 'dispatch_order_1';


    public function get_one($where,$field='',$page='',$order='')
    {
        return $this->field($field)->where($where)->order($order)->find();
    }
    public function get_all($where,$field='',$page='',$order='')
    {
        return $this->field($field)->where($where)->page($page)->order($order)->select();
    }
	public function get_group($where,$field='',$group='')
    {
        return $this->field($field)->where($where)->group($group)->select();
    }
    public function update($where,$data)
    {
        return $this->where($where)->save($data);
    }
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








}
?>