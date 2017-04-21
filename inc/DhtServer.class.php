<?php

class DhtServer
{

    public static $_bt_protocol = 'BitTorrent protocol';
    public static $BT_MSG_ID = 20;
    public static $EXT_HANDSHAKE_ID = 0;
    public static $PIECE_LENGTH = 16384;

    /**
     * 处理接收到的find_node回复
     * @param  array $msg 接收到的数据
     * @param  array $address 对端链接信息
     * @return void
     */
    public static function response_action($msg, $address)
    {
        global $table;
        // 先检查接收到的信息是否正确
        if (!isset($msg['r']['nodes']) || !isset($msg['r']['nodes'][1])) return;
        // 对nodes数据进行解码

        //echo '朋友'.$address[0].'在线'.PHP_EOL;
        $nodes = Base::decode_nodes($msg['r']['nodes']);
        // 对nodes循环处理
        foreach ($nodes as $node) {
            // 将node加入到路由表中
            self::append($node);
        }
        //echo '路由表nodes数量 '.count($table).PHP_EOL;
    }

    /**
     * 处理对端发来的请求
     * @param  array $msg 接收到的请求数据
     * @param  array $address 对端链接信息
     * @return void
     */
    public static function request_action($msg, $address)
    {
        switch ($msg['q']) {
            case 'ping'://确认你是否在线
                //echo '朋友'.$address[0].'正在确认你是否在线'.PHP_EOL;
                self::on_ping($msg, $address);
                break;
            case 'find_node': //向服务器发出寻找节点的请求
                //echo '朋友'.$address[0].'向你发出寻找节点的请求'.PHP_EOL;
                self::on_find_node($msg, $address);
                break;
            case 'get_peers':
                //echo '朋友'.$address[0].'向你发出查找资源的请求'.PHP_EOL;
                // 处理get_peers请求
                self::on_get_peers($msg, $address);
                break;
            case 'announce_peer':
                //echo '朋友' . $address[0] . '找到资源了 通知你一声' . PHP_EOL;
                // 处理announce_peer请求
                self::on_announce_peer($msg, $address);
                break;
            default:
                break;
        }
    }

    /**
     * 添加node到路由表
     * @param  Node $node node模型
     * @return boolean       是否添加成功
     */
    public static function append($node)
    {
        global $nid, $table;
        // 检查node id是否正确
        if (!isset($node->nid[19]))
            return false;

        // 检查是否为自身node id
        if ($node->nid == $nid)
            return false;

        // 检查node是否已存在
        if (in_array($node, $table))
            return false;

        if ($node->port < 1 or $node->port > 65535)
            return false;

        // 如果路由表中的项达到200时, 删除第一项
        if (count($table) >= MAX_NODE_SIZE)
            array_shift($table);

        return array_push($table, $node);
    }

    public static function on_ping($msg, $address)
    {
        global $nid;
        // 获取对端node id
        $id = $msg['a']['id'];
        // 生成回复数据
        $msg = array(
            't' => $msg['t'],
            'y' => 'r',
            'r' => array(
                'id' => Base::get_neighbor($id, $nid)
            )
        );

        // 将node加入路由表
        self::append(new Node($id, $address[0], $address[1]));
        // 发送回复数据
        DhtClient::send_response($msg, $address);
    }

    public static function on_find_node($msg, $address)
    {
        global $nid;

        // 获取对端node id
        $id = $msg['a']['id'];
        // 生成回复数据
        $msg = array(
            't' => $msg['t'],
            'y' => 'r',
            'r' => array(
                'id' => Base::get_neighbor($id, $nid),
                'nodes' => Base::encode_nodes(self::get_nodes(16))
            )
        );

        // 将node加入路由表
        self::append(new Node($id, $address[0], $address[1]));
        // 发送回复数据
        DhtClient::send_response($msg, $address);
    }

    /**
     * 处理get_peers请求
     * @param  array $msg 接收到的get_peers请求数据
     * @param  array $address 对端链接信息
     * @return void
     */
    public static function on_get_peers($msg, $address)
    {
        global $nid;

        // 获取info_hash信息
        $infohash = $msg['a']['info_hash'];
        // 获取node id
        $id = $msg['a']['id'];

        // 生成回复数据
        $msg = array(
            't' => $msg['t'],
            'y' => 'r',
            'r' => array(
                'id' => Base::get_neighbor($id, $nid),
                'nodes' => Base::encode_nodes(self::get_nodes()),
                'token' => substr($infohash, 0, 2)
            )
        );

        // 将node加入路由表
        self::append(new Node($id, $address[0], $address[1]));
        // 向对端发送回复数据
        DhtClient::send_response($msg, $address);
    }

    /**
     * 处理announce_peer请求
     * @param  array $msg 接收到的announce_peer请求数据
     * @param  array $address 对端链接信息
     * @return void
     */
    public static function on_announce_peer($msg, $address)
    {
        global $nid, $db, $db_server, $splq, $serv, $queue;
        $infohash = $msg['a']['info_hash'];
        $port = $msg['a']['port'];
        $token = $msg['a']['token'];
        $id = $msg['a']['id'];
        $tid = $msg['t'];

        //echo 'Ip:' . $address[0] . ' Port:' . $port .' test connent!'. PHP_EOL;
        //return;

        // 验证token是否正确
        if (substr($infohash, 0, 2) != $token) return;

        if (isset($msg['a']['implied_port']) && $msg['a']['implied_port'] != 0) {
            $port = $address[1];
        }

        if ($port >= 65536 || $port <= 0) {
            return;
        }

        if ($tid == '') {
            //return;
        }

        // 生成回复数据
        $msg = array(
            't' => $msg['t'],
            'y' => 'r',
            'r' => array(
                'id' => $nid
            )
        );

        // 发送请求回复
        DhtClient::send_response($msg, $address);

        $ip = $address[0];
        $metadata = '';

        $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $client->set(array(
            'heartbeat_check_interval' => 10, //启用心跳检测，此选项表示每隔多久轮循一次，单位为秒
            'heartbeat_idle_time' => 20, //与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间
        ));

        $client->on("connect", function (swoole_client $cli) use ($ip, $port, $infohash) {
            self::send_handshake($cli, $infohash);
        });

        $client->on("receive", function (swoole_client $cli, $data = '') use ($ip, $port, $infohash) { //客户端收到来自于服务器端的数据时会回调此函数
            global $piecesNum, $metadata, $metadata_size;
            if (!empty($data) && $data !== false) {

                if (strpos($data, 'BitTorrent protocol') !== false && strpos($data, 'complete_ago' !== false)) { //send_handshake
                    if (self::check_handshake($cli, $data, $infohash) == false) {
                        $cli->close();
                        return;
                    } else {
                        self::send_ext_handshake($cli);
                    }
                } elseif (strpos($data, 'metadata_size') !== false && strpos($data, 'ut_comment' !== false)) {
                    $ut_metadata = self::get_ut_metadata($data);
                    $metadata_size = self::get_metadata_size($data);
                    $piecesNum = ceil($metadata_size / (self::$PIECE_LENGTH));//2 ^ 14
                    for ($i = 0; $i < $piecesNum; $i++) {
                        self::request_metadata($cli, $ut_metadata, $i);
                    }
                } elseif (strpos($data, 'msg_type') !== false && strpos($data, 'total_size') !== false) {
                    $_data = substr($data, strpos($data, "ee") + 2);
                    $metadata .= $_data;
                    $metadata = Base::decode($metadata);
                    if (is_array($metadata)) {
                        $cli->close();
                        $_data = [];
                        if (isset($metadata['name']) && $metadata['name'] != '') {
                            $_data['name'] = $metadata['name'];
                            $_data['files'] = isset($metadata['files']) ? $metadata['files'] : '';
                            $_data['length'] = $metadata['length'];
                            $_data['length_format'] = Func::sizecount($metadata['length']);
                            $_data['magnetUrl'] = 'magnet:?xt=urn:btih:' . strtoupper(bin2hex($infohash));
                            $metadata = '';
                        } else {
                            return false;
                        }
                        Func::Logs(date('Y-m-d H:i:s').' '.var_export($_data, 1).PHP_EOL);
                        var_dump(var_export($_data, 1));
                    }
                }
            } else {
                $cli->close();
            }
        });

        $client->on("error", function (swoole_client $cli) use ($ip, $port) { //连接服务器失败时会回调此函数
            if ($cli->errCode != 111) { //111链接不上 链接被拒绝
                $str = 'Ip:' . $ip . ' Port:' . $port . ' ' . socket_strerror($cli->errCode) . PHP_EOL;
                echo $str;
            }
        });

        $client->on("close", function (swoole_client $cli) { //连接被关闭时回调此函数。 Server端关闭或Client端主动关闭，都会触发onClose事件
            //echo "Connection close\n";
        });
        $client->connect($ip, $port, 1);


    }

    public static function get_nodes($len = 8)
    {
        global $table;

        if (count($table) <= $len)
            return $table;

        $nodes = array();

        for ($i = 0; $i < $len; $i++) {
            $nodes[] = $table[mt_rand(0, count($table) - 1)];
        }

        return $nodes;
    }

    public static function send_handshake($cli, $infohash)
    {
        $bt_protocol = self::$_bt_protocol;
        $bt_header = chr(strlen($bt_protocol)) . $bt_protocol;
        $ext_bytes = "\x00\x00\x00\x00\x00\x10\x00\x00";
        $peer_id = Base::get_node_id();
        $packet = $bt_header . $ext_bytes . $infohash . $peer_id;
        if ($cli->send($packet) == false) {
            $cli->close();
        }
        return;
    }

    public static function check_handshake($cli, $packet, $self_infohash)
    {
        $bt_header_len = ord(substr($packet, 0, 1));
        $packet = substr($packet, 1);
        if ($bt_header_len != strlen(self::$_bt_protocol)) {
            return false;
        }

        $bt_header = substr($packet, 0, $bt_header_len);
        $packet = substr($packet, $bt_header_len);
        if ($bt_header != self::$_bt_protocol) {
            return false;
        }

        $packet = substr($packet, 8);
        $infohash = substr($packet, 0, 20);

        if ($infohash != $self_infohash) {
            return false;
        }
        return true;
    }


    public static function send_ext_handshake($cli)
    {
        $msg = chr(self::$BT_MSG_ID) . chr(self::$EXT_HANDSHAKE_ID) . Base::encode(array("m" => array("ut_metadata" => 1)));//{"m":{"ut_metadata": 1}
        $msg_len = pack("I", strlen($msg));
        if (!BIG_ENDIAN) {
            $msg_len = strrev($msg_len);
        }
        $packet = $msg_len . $msg;
        if ($cli->send($packet) == false) {
            $cli->close();
        }
        return;
    }

    public static function get_ut_metadata($data)
    {
        $ut_metadata = '_metadata';
        $index = strpos($data, $ut_metadata) + strlen($ut_metadata) + 1;
        return intval($data[$index]);
    }


    public static function get_metadata_size($data)
    {
        $metadata_size = 'metadata_size';
        $start = strpos($data, $metadata_size) + strlen($metadata_size) + 1;
        $data = substr($data, $start);
        $e_index = strpos($data, "e");
        return intval(substr($data, 0, $e_index));
    }

    //bep_0009
    public static function request_metadata($cli, $ut_metadata, $piece)
    {
        $msg = chr(self::$BT_MSG_ID) . chr($ut_metadata) . Base::encode(array("msg_type" => 0, "piece" => $piece));
        $msg_len = pack("I", strlen($msg));
        if (!BIG_ENDIAN) {
            $msg_len = strrev($msg_len);
        }
        $_msg = $msg_len . $msg;

        if ($cli->send($_msg) == false) {
            $cli->close();
        }
        return;
    }
}