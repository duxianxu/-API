<?php
return array(
    /* 模块相关配置 */
    'DEFAULT_MODULE'     => 'Statistics',
    'MODULE_ALLOW_LIST'    =>    array('Home','Admin','Statistics','Api'),
    'MODULE_DENY_LIST'      =>  array('Common','Runtime'),
    'URL_MODEL'              => 1,  //启用rewrite

    'SHOW_PAGE_TRACE'        => false,                           // 是否显示调试面板
    'URL_CASE_INSENSITIVE'   => false,                           // url区分大小写

    /* 数据库配置 */
    'DB_TYPE' => 'mysqli', // 数据库类型
    'DB_PORT' => '3306', // 端口
    'DB_HOST' => '10.1.11.2', // 服务器地址
    'DB_NAME' => 'baojia', // 数据库名
    'DB_USER' => 'api-baojia',
    'DB_PWD' => 'CSDV4smCSztRcvVb', // 密码


     /*盒子上报Redis配置*/
    'REDIS_HOST' => '10.1.11.83', // 服务器地址
    'REDIS_PORT' => '36379', // 服务器端口号
    'REDIS_AUTH' => 'oXjS2RCA1odGxsv4',

    /*普通Redis配置*/
    'COMMON_REDIS_HOST' =>'10.1.11.82',    // 正式服务器地址
    'COMMON_REDIS_PORT' => '6379',         // 正式服务器端口号
    // 生产环境Redis key前缀-----------------------------------------------
    'KEY_PREFIX' => 'production_',

    /*两日无单、五日无单、非稳盒子、无效盒子请求链接*/
    // 正式服务器地址------------------------------------------------------
    'STATISTICS_LINK' =>'http://btv.baojia.com',

    //小安盒子正式网关-----------------------------------------------------
    'GATEWAY_LINK'=>'http://wg.baojia.com/simulate/service',

    //短信正式链接---------------------------------------------------------
    'SMS_LINK'=>'http://sms.baojia.com/sms/send?version=1.0&token=123456',

    'LOG_RECORD' => true, // 开启日志记录
    'LOG_LEVEL' => 'EMERG,ALERT,CRIT,ERR,INFO', // 只记录EMERG ALERT CRIT ERR 错误
    'LOG_TYPE'  =>  'File',

    'TMPL_PARSE_STRING' => array(
    '__PUBLIC__' => '/Public', // 更改默认的/Public 替换规
    '__JS__' => '/Public/js', // 增加新的JS类库路径替换规则
    '__CSS__' => '/Public/css', // 增加新的CSS路径替换规则
    //'__IMG__' => '/images', // 增加新的图片径替换规则
	//'__PLG__'=>'/plugins',
    ),
    /*链接参数*/
    'BAOJIA_LINK' =>'mysql://yfread:yfread@10.1.11.14:3306/baojia_box#utf8',
    'BAOJIA_CS_LINK' =>'mysql://apitest-baojia:TKQqB5Gwachds8dv@10.1.11.110:3306/baojia_mebike#utf8',
    'BAOJIA_LINK_DC'=>'mysqli://baojia_dc:Ba0j1a-Da0!@#*@rent2015.mysql.rds.aliyuncs.com:3306/dc',
    'DB_CONFIG_BOX' => 'mysqli://api-baojia:CSDV4smCSztRcvVb@10.1.11.14:3306/baojia_box',

);