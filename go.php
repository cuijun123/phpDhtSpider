<?php
//swoole version 1.9.5
error_reporting(E_ERROR );
ini_set("memory_limit","1024M");
define('BASEPATH', dirname(__FILE__));
define('WORKER_NUM', 1);// 主进程数, 一般为CPU的1至4倍 同时执行任务数量
define('MAX_REQUEST', 65535);// 允许最大连接数, 不可大于系统ulimit -n的值
define('AUTO_FIND_TIME', 3000);//定时寻找节点时间间隔 /毫秒
define('MAX_NODE_SIZE', 300);//保存node_id最大数量

require_once BASEPATH . '/inc/Node.class.php'; //node_id类
require_once BASEPATH . '/inc/Bencode.class.php';//bencode编码解码类
require_once BASEPATH .'/inc/Base.class.php';//基础操作类
require_once BASEPATH .'/inc/Func.class.php';
require_once BASEPATH . '/inc/DhtClient.class.php';
require_once BASEPATH . '/inc/DhtServer.class.php';
require_once BASEPATH . '/inc/Metadata.class.php';

$splq = new SplQueue;

$nid = Base::get_node_id();// 伪造设置自身node id
$table = array();// 初始化路由表
// 长期在线node
$bootstrap_nodes = array(
    array('router.bittorrent.com', 6881),
    array('dht.transmissionbt.com', 6881),
    array('router.utorrent.com', 6881)
);
Func::Logs(date('Y-m-d H:i:s', time()) . " - 服务启动...".PHP_EOL,1);//记录启动日志

//SWOOLE_PROCESS 使用进程模式，业务代码在Worker进程中执行
//SWOOLE_SOCK_UDP 创建udp socket
$serv = new swoole_server('0.0.0.0', 6882, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
$serv->set(array(
    'worker_num' => WORKER_NUM,
    'daemonize' => false,
    'max_request' => MAX_REQUEST,
    'dispatch_mode' => 3,//保证同一个连接发来的数据只会被同一个worker处理
    'log_file' => BASEPATH . '/logs/error.log',
));

$serv->on('WorkerStart', function($serv, $worker_id){
    global $table,$bootstrap_nodes,$splq;
    DhtClient::join_dht($table,$bootstrap_nodes);
    swoole_timer_tick(AUTO_FIND_TIME, function ($timer_id)use($table,$bootstrap_nodes) {
        if(count($table) == 0){
            DhtClient::join_dht($table,$bootstrap_nodes);
        }
        DhtClient::auto_find_node($table,$bootstrap_nodes);
    });

});

/*
$server，swoole_server对象
$fd，TCP客户端连接的文件描述符
$from_id，TCP连接所在的Reactor线程ID
$data，收到的数据内容，可能是文本或者二进制内容
 */
$serv->on('Receive', function($serv, $fd, $from_id, $data){
    if(strlen($data) == 0) return false;
    $msg = Base::decode($data);
    if(!isset($msg['y'])) return false;
    $fdinfo = $serv->connection_info($fd, $from_id);
    if($msg['y'] == 'r'){
        // 如果是回复, 且包含nodes信息
        if(array_key_exists('nodes', $msg['r'])){
            DhtServer::response_action($msg, array($fdinfo['remote_ip'], $fdinfo['remote_port']));
        }
    }elseif($msg['y'] == 'q'){
        // 如果是请求, 则执行请求判断
        DhtServer::request_action($msg, array($fdinfo['remote_ip'], $fdinfo['remote_port']));
    }else{

        return false;
    }
});



$serv->start();