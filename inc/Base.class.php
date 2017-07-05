<?php
/**
 * 基础操作类
 */
class Base{
    /**
     * 把字符串转换为数字
     * @param  string $str    要转换的字符串
     * @return   string 转换后的字符串
     */
    static public function hash2int($str){
        return hexdec(bin2hex($str));
    }

    /**
     * 生成随机字符串
     * @param  integer $length 要生成的长度
     * @return   string 生成的字符串
     */
    static public function entropy($length=20){
        $str = '';

        for($i=0; $i<$length; $i++)
            $str .= chr(mt_rand(0, 255));

        return $str;
    }

    /**
     * 生成一个node id
     * @return   string 生成的node id
     */
    static public function get_node_id(){
        return sha1(self::entropy(), true);
    }

    static public function get_neighbor($target, $nid){
        return substr($target, 0, 10) . substr($nid, 10, 10);
    }

    /**
     * bencode编码
     * @param  mixed $msg 要编码的数据
     * @return   string 编码后的数据
     */
    static public function encode($msg){
        return Bencode::encode($msg);
    }

    /**
     * bencode解码
     * @param  string $msg 要解码的数据
     * @return   mixed      解码后的数据
     */
    static public function decode($msg){
        return Bencode::decode($msg);
    }

    /**
     * 对nodes列表编码
     * @param  mixed $nodes 要编码的列表
     * @return string        编码后的数据
     */
    static public function encode_nodes($nodes){
        // 判断当前nodes列表是否为空
        if(count($nodes) == 0)
            return $nodes;

        $n = '';

        // 循环对node进行编码
        foreach($nodes as $node)
            $n .= pack('a20Nn', $node->nid, ip2long($node->ip), $node->port);

        return $n;
    }

    /**
     * 对nodes列表解码
     * @param  string $msg 要解码的数据
     * @return mixed      解码后的数据
     */
    static public function decode_nodes($msg){
        // 先判断数据长度是否正确
        if((strlen($msg) % 26) != 0)
            return array();

        $n = array();

        // 每次截取26字节进行解码
        foreach(str_split($msg, 26) as $s){
            // 将截取到的字节进行字节序解码
            $r = unpack('a20nid/Nip/np', $s);
            $n[] = new Node($r['nid'], long2ip($r['ip']), $r['p']);
        }

        return $n;
    }
}