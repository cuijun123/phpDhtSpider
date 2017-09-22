<?php
//swoole version 1.9.5
/*
 * 安装swoole pecl install swoole
 * 设置服务器 ulimit -n 100000
 * 关闭防火墙和后台规则 防止端口不通
 */
error_reporting(E_ERROR );
ini_set('date.timezone','Asia/Shanghai');
ini_set("memory_limit","-1");
define('BASEPATH', dirname(__FILE__));
$config = require_once BASEPATH.'/config.php';
define('WORKER_NUM', 2);// 主进程数, 一般为CPU的1至4倍 同时执行任务数量
define('MAX_REQUEST', 0);// 允许最大连接数, 不可大于系统ulimit -n的值
require_once BASEPATH .'/inc/Func.class.php';
require_once BASEPATH . '/inc/Bencode.class.php';//bencode编码解码类
require_once BASEPATH .'/inc/Base.class.php';//基础操作类
require_once BASEPATH . '/inc/Db.class.php';
Func::Logs(date('Y-m-d H:i:s', time()) . " - 服务启动...".PHP_EOL,1);//记录启动日志


swoole_set_process_name("php_dht_server:[master] worker");

//SWOOLE_PROCESS 使用进程模式，业务代码在Worker进程中执行
//SWOOLE_SOCK_UDP 创建udp socket
$serv = new swoole_server('0.0.0.0', 2345, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
$serv->set(array(
    'worker_num' => WORKER_NUM,//设置启动的worker进程数
    'daemonize' => $config['daemonize'],//是否后台守护进程
    'max_request' => MAX_REQUEST, //防止 PHP 内存溢出, 一个工作进程处理 X 次任务后自动重启 (注: 0,不自动重启)
    'dispatch_mode' => 2,//保证同一个连接发来的数据只会被同一个worker处理
    'log_file' => BASEPATH . '/logs/error.log',
    'max_conn'=>65535,//最大连接数
    'heartbeat_check_interval' => 5, //启用心跳检测，此选项表示每隔多久轮循一次，单位为秒
    'heartbeat_idle_time' => 10, //与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间
));

$serv->on('WorkerStart', function ($serv, $worker_id) use ($config){
    Db::$config = array(
        'host'=>$config['db']['host'],
        'user'=>$config['db']['user'],
        'pass'=>$config['db']['pass'],
        'name'=>$config['db']['name'],
    );

    swoole_set_process_name("php_dht_server:[".$worker_id."] worker");
});

$serv->on('Receive', function($serv, $fd, $from_id, $data){
    if(strlen($data) == 0){
		$serv->close($fd,true);
        return false;
    }
    //$fdinfo = $serv->connection_info($fd, $from_id);
    $rs = Base::decode($data);
    if(is_array($rs) && isset($rs['infohash'])){
        $data = Db::get_one("select 1 from history where infohash = '$rs[infohash]' limit 1");
        if(!$data){
            Db::insert('history',array('infohash'=>$rs['infohash']));
            $files = '';
            $length = 0;
            if($rs['files'] !=''){
                $files = json_encode($rs['files']);
                foreach ($rs['files'] as $value){
                    $length += $value['length'];
                }
            }else{
                $length = $rs['length'];
            }
            Db::insert('bt',array(
                    'name'=>$rs['name'],
                    'keywords'=>Func::getKeyWords($rs['name']),
                    'infohash'=>$rs['infohash'],
                    'files'=>$files,
                    'length'=>$length,
                    'piece_length'=>$rs['piece_length'],
                    'hits'=>0,
                    'time'=>date('Y-m-d H:i:s'),
                    'lasttime'=>date('Y-m-d H:i:s'),
                )
            );
        }else{
            Db::query("update bt set `hot` = `hot` + 1 where infohash = '$rs[infohash]'");
        }
    }
    $serv->close($fd,true);
});

$serv->start();

