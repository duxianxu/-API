<?php
namespace Api\Controller;

use Think\Controller\RestController;

class BController extends RestController
{
    /**
     * 检验登录的终端
     * @param $uid
     * @param $uuid
     * @param bool $isCheckUid
     */
    protected function check_terminal($user_id, $uuid)
    {
        $version=I('request.version');
        $device_os=I('request.device_os');
        if((strtoupper($device_os) == 'IOS' && $version=="2.0.1") || ($device_os == 'Android' && $version=="2.0.3")){
            return true;
        }

        if (is_numeric($user_id) && $user_id > 0) {
            $member=M("baojia_mebike.repair_member")->field("authority")
                ->where("status=1 and user_id={$user_id}")->select();
            $authority = array_column($member, "authority");
            if($authority){
                if(!in_array($uuid, $authority)){
                    $result = array("code" => "4004", "message" => "该账户已在其他终端登录，需重新登录才能正常使用");
                    $this->response($result, 'json');
                }
            }else{
                $result = array("code" => "4004", "message" => "该账户已在其他终端登录，需重新登录才能正常使用");
                $this->response($result, 'json');
            }
        }
    }

    protected function promossion($uid, $iflogin,$isCheckUid=true)
    {
        if ($isCheckUid) {
            $this->CheckInt($uid);
        }
        if (is_numeric($uid) && $uid > 0) {
            if (check_iflogin($uid, $iflogin) == false) {
                $result = array('status' => 0, 'info' => '用户验证失败,请重新登录');
                if(C("ISTEST") != 1){
                    $this->response($result, 'json');    
                }
            }
        }
    }
	

    protected function CheckInt($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            $result = array('status' => 0, 'info' => '参数错误');
            $this->response($result, 'json');
        } else {
            return intval($id);
        }
    }
}

?>