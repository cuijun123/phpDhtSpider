<?php
//swoole version 1.9.5
error_reporting(E_ERROR );
ini_set('date.timezone','Asia/Shanghai');
//ini_set("memory_limit","1024M");
define('BASEPATH', dirname(__FILE__));
define('WORKER_NUM', 1);// 主进程数, 一般为CPU的1至4倍 同时执行任务数量
define('MAX_REQUEST', 0);// 允许最大连接数, 不可大于系统ulimit -n的值
define('AUTO_FIND_TIME', 3000);//定时寻找节点时间间隔 /毫秒
define('MAX_NODE_SIZE', 300);//保存node_id最大数量
define('MAX_UDP_CONNENT_SEC', 0.002);//多少秒允许一次udp链接 防止cpu占用过高
define('BIG_ENDIAN', pack('L', 1) === pack('N', 1));

require_once BASEPATH . '/inc/Node.class.php'; //node_id类
require_once BASEPATH . '/inc/Bencode.class.php';//bencode编码解码类
require_once BASEPATH .'/inc/Base.class.php';//基础操作类
require_once BASEPATH .'/inc/Func.class.php';
require_once BASEPATH . '/inc/DhtClient.class.php';
require_once BASEPATH . '/inc/DhtServer.class.php';
require_once BASEPATH . '/inc/Metadata.class.php';

$nid = Base::get_node_id();// 伪造设置自身node id
$table = array();// 初始化路由表
$queue = new SplQueue;
$time = microtime(true);
$workers = array();
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
    'worker_num' => WORKER_NUM,//设置启动的worker进程数
    'daemonize' => true,
    'max_request' => MAX_REQUEST, //防止 PHP 内存溢出, 一个工作进程处理 X 次任务后自动重启 (注: 0,不自动重启)
    'dispatch_mode' => 2,//保证同一个连接发来的数据只会被同一个worker处理
    'log_file' => BASEPATH . '/logs/error.log',
    'max_conn'=>1000,//最大连接数
    'heartbeat_check_interval' => 5, //启用心跳检测，此选项表示每隔多久轮循一次，单位为秒
    'heartbeat_idle_time' => 10, //与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间
));

$serv->on('WorkerStart', function($serv, $worker_id){
    global $table,$bootstrap_nodes,$queue,$workers;
    DhtServer::join_dht($table,$bootstrap_nodes);
    swoole_timer_tick(AUTO_FIND_TIME, function ($timer_id)use($table,$bootstrap_nodes) {
        if(count($table) == 0){
            DhtServer::join_dht($table,$bootstrap_nodes);
        }
        DhtServer::auto_find_node($table,$bootstrap_nodes);
    });

    swoole_timer_tick(1, function () use($queue,$workers) {
        swoole_process::wait(false);
            if(count($queue) > 1){
                    $data = $queue->shift();
                    $process = new swoole_process(function (swoole_process $worker) use($data){
                        $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
                        if (!@$client->connect($data[0], $data[1], 10))
                        {
                           // echo ("connect failed. Error: {$client->errCode}".PHP_EOL);
                        }else{
                            //echo 'connent success! '.$data[0].':'.$data[1].PHP_EOL;
                            Metadata::download_metadata($client,$data[2]);
                            $client->close(true);
                        }
                        $worker->exit(0);
                    }, false);
                    $pid = $process->start();
                    //swoole_process::wait();
                    //$workers[$pid] = $process;
            }
    });
});

/*
$server，swoole_server对象
$fd，TCP客户端连接的文件描述符
$from_id，TCP连接所在的Reactor线程ID
$data，收到的数据内容，可能是文本或者二进制内容
 */
$serv->on('Receive', function($serv, $fd, $from_id, $data){
    global $time,$queue;
    if(microtime(true) - $time < MAX_UDP_CONNENT_SEC){
        return false;
    }
    //echo (microtime(true)).PHP_EOL;

    $time = microtime(true);

    if(count($queue) >= 300){
        return false;
    }

    if(strlen($data) == 0){
        $serv->close($fd,true);
        return false;
    }
    $msg = Base::decode($data);
    if(!isset($msg['y'])){
        $serv->close($fd,true);
        return false;
    };
    $fdinfo = $serv->connection_info($fd, $from_id);
    if($msg['y'] == 'r'){
        // 如果是回复, 且包含nodes信息 添加到路由表
        if(array_key_exists('nodes', $msg['r'])){
            DhtClient::response_action($msg, array($fdinfo['remote_ip'], $fdinfo['remote_port']));
        }else{
            $serv->close($fd,true);
            return false;
        }
    }elseif($msg['y'] == 'q'){
        // 如果是请求, 则执行请求判断
        DhtClient::request_action($msg, array($fdinfo['remote_ip'], $fdinfo['remote_port']));
    }else{
        $serv->close($fd,true);
        return false;
    }
});



$serv->start();

