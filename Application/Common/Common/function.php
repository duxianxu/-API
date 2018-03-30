<?php

header("Content-type:text/html;charset=utf-8");

/*
 * 分页
 * Created by 班布拉
 */

function bj_page($num, $count, $limit, $parameter, $current = 'current') {
    $Page = new \Think\Page($count, $limit, $parameter);
    $Page->rollPage = $num;
    $Page->setConfig(next, "下一页");
    $Page->setConfig(prev, "上一页");
    return $Page->bj_show($current); // 分页显示输出
}

/**
 * 生成二维码
 * @param  string  $url  url连接
 * @param  integer $size 尺寸 纯数字
 */
function qrcode($url,$img_url=false,$size=4){
    Vendor('Phpqrcode.phpqrcode');
    // 如果没有http 则添加
    /*if (strpos($url, 'http')===false) {
        $url='http://'.$url;
    }*/
    QRcode::png($url,$img_url,QR_ECLEVEL_L,$size,2,false,0xFFFFFF,0x000000);
}

/****
  *验证电池编号格式
  *@s    string   电池编号
 **/

function batteryLuhm($s) {
    $n = 0;
    for ($i = strlen($s); $i >= 1; $i--) {
        $index=$i-1;
        //偶数位
        if ($i % 2==0) {
            $t = $s{$index} * 2;
            if ($t > 9) {
                $t = (int)($t/10)+ $t%10;
            }
            $n += $t;
        } else {//奇数位
            $n += $s{$index};
        }
    }
    return ($n % 10) == 0;
}