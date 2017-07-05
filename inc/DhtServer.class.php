<?php
class DhtServer{
    public static function join_dht($table,$bootstrap_nodes){
        if(count($table) == 0){
            foreach($bootstrap_nodes as $node){
                //echo '路由表为空 将自身伪造的ID 加入预定义的DHT网络 '.$node[0].PHP_EOL;
                self::find_node(array(gethostbyname($node[0]), $node[1])); //将自身伪造的ID 加入预定义的DHT网络
            }
        }
    }

    public static function auto_find_node($table,$bootstrap_nodes){
        self::join_dht($table,$bootstrap_nodes);
        $wait = 1.0 / MAX_NODE_SIZE;
        while(count($table) >0 ){
            // 从路由表中删除第一个node并返回被删除的node
            $node = array_shift($table);
            // 发送查找find_node到node中
            self::find_node(array($node->ip, $node->port), $node->nid);
            usleep($wait);
        }
    }

    public static function find_node($address, $id = null){
        global $nid;
        if(is_null($id)){
            $mid = Base::get_node_id();
        }else{
            $mid = Base::get_neighbor($id, $nid); // 否则伪造一个相邻id
        }
        //echo '查找朋友'.$address[0].'是否在线'.PHP_EOL;
        // 定义发送数据 认识新朋友的。
        $msg = array(
            't' => Base::entropy(2),
            'y' => 'q',
            'q' => 'find_node',
            'a' => array(
                'id' => $nid,
                'target' => $mid
            )
        );
        // 发送请求数据到对端
        self::send_response($msg, $address);
    }

    public static function send_response($msg, $address){
        global $serv;
        if(!filter_var($address[0], FILTER_VALIDATE_IP))
        {
            return false;
        }
        $serv->sendto($address[0], $address[1], Base::encode($msg));
    }
}