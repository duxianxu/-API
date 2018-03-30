<?php
namespace Api\Logic;
use Think\Model;
class UploadImage{

       //上传图片通用类
    public function upload( $pic1, $pic2){

        if(isset($pic1)){
            $_FILES["uploadfile"]=$pic1;
            $root   =  "/hd2/web/upfiles/pic/Public/img/pic";
            $root1="/" . date("Y") . "/" . date("md") . "/";

            $filename = md5(time().rand(300,999)) . ".jpg";
            $filepath = $root.$root1.$filename;
            $rr = $this->createdir($root.$root1, 0777);
            $pic1=move_uploaded_file($_FILES["uploadfile"]["tmp_name"], $filepath);
            if($pic1){
                $pic1=['path'=>'xiaomi.baojia.com/Public/img/pic'.$root1.$filename,'short_path'=>'Public/img/pic'.$root1.$filename];
            }
        }
        if(isset($pic2)){
            $_FILES["uploadfile"]=$pic2;
            $root   =  "/hd2/web/upfiles/pic/Public/img/pic";
            $root1="/" . date("Y") . "/" . date("md") . "/";

            $filename2 = md5(time().rand(200,200)) . ".jpg";
            $filepath2 = $root.$root1.$filename2;
            $rr = $this->createdir($root.$root1, 0777);
            $pic2=move_uploaded_file($_FILES["uploadfile"]["tmp_name"], $filepath2);
            if($pic2){
                $pic2=['path'=>'xiaomi.baojia.com/Public/img/pic'.$root1.$filename2,'short_path'=>'Public/img/pic'.$root1.$filename2];
            }
        }
        $pic = [];
        $pic=["pic1"=>$pic1,"pic2"=>$pic2];
        return $pic;
    }
    protected function createdir($path, $mode) {
        if (is_dir($path)) {  //判断目录存在否，存在不创建
            //echo "目录'" . $path . "'已经存在";
            return true;
        } else { //不存在创建
            $re = mkdir($path, $mode, true); //第三个参数为true即可以创建多极目录
            if ($re) {
                //echo "目录创建成功";
                return true;
            } else {
                //echo "目录创建失败";
                return false;
            }
        }
    }






























}

?>