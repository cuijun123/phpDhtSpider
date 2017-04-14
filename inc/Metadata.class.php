<?php

/**
 * Created by PhpStorm.
 * User: Cui Jun
 * Date: 2017-4-11
 * Time: 13:34
 * To change this template use File | Settings | File Templates.
 */
class Metadata
{
    public static $_bt_protocol = 'BitTorrent protocol';
    public static $BT_MSG_ID = 20;
    public static $EXT_HANDSHAKE_ID = 0;
    public static $PIECE_LENGTH = 16384;

    public static function download_metadata($client, $infohash)
    {
        try {
            $packet = self::send_handshake($client, $infohash);
            //var_dump($packet);

            if ($packet == false) {
                return false;
            }

            $check_handshake = self::check_handshake($packet, $infohash);

            if ($check_handshake == false) {
                return false;
            }

            $packet = self::send_ext_handshake($client);

            if ($packet == false) {
                return false;
            }


            $ut_metadata = self::get_ut_metadata($packet);
            $metadata_size = self::get_metadata_size($packet);

            //var_dump($ut_metadata);
            //var_dump($metadata_size);
            $metadata = array();
            $piecesNum = ceil($metadata_size / (self::$PIECE_LENGTH));//2 ^ 14
            for ($i = 0; $i < $piecesNum; $i++) {
                self::request_metadata($client, $ut_metadata, $i);
                $packet = self::recvall($client);
                $ee = substr($packet,0,strpos($packet,"ee")+2);
                $dict = Base::decode(substr($ee,strpos($packet,"d")));

                if($dict['msg_type'] != 1){
                    return false;
                }

                $_metadata = substr($packet,strpos($packet,"ee")+2);

                if(strlen($_metadata) > self::$PIECE_LENGTH){
                    return false;
                }

                $metadata[] = $_metadata;
            }
            $metadata = implode('',$metadata);

            $_data = [];
            $metadata = Base::decode($metadata);
            if($metadata['name'] !=''){
                $_data['name'] = $metadata['name'];
                $_data['files'] = isset($metadata['files']) ? $metadata['files'] : '';
                $_data['length'] = $metadata['length'];
                $_data['length_format'] = Func::sizecount($metadata['length']);
                $_data['magnetUrl'] = 'magnet:?xt=urn:btih:'.strtoupper(bin2hex($infohash));
                unset($metadata);
            }else{
                return false;
            }

            var_dump(var_export($_data,1));
           // Func::Logs(var_export($_data,1),3);

        } catch (Exception $e) {
            $client->close();
            var_dump($e->getMessage());
        }
    }

    //bep_0009
    public static function request_metadata($client, $ut_metadata, $piece)
    {
        $msg = chr(self::$BT_MSG_ID) . chr($ut_metadata) . Base::encode(array("msg_type" => 0, "piece" => $piece));
        define('BIG_ENDIAN', pack('L', 1) === pack('N', 1));
        $msg_len = pack("I", strlen($msg));
        if (!BIG_ENDIAN) {
            $msg_len = strrev($msg_len);
        }
        $_msg = $msg_len . $msg;

        $client->send($_msg);
    }

    public static function recvall($client)
    {

/*        $data = '';
        try {
            $data = $client->recv($size, swoole_client::MSG_PEEK | swoole_client::MSG_WAITALL);
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }

        return $data;*/

        $total_data = array();
        $data = '';
        $begin = time();
        while (true) {
            usleep(5);
            if ($total_data && ((time() - $begin) > 5)) {
                break;
            } elseif ((time() - $begin) > 5 * 2) {
                break;
            }
            try {
                $data = $client->recv(1024, 0);
                if ($data) {
                    $total_data[] = $data;
                    $begin = time();
                }
            } catch (Exception $e) {

            }
        }
        return implode('', $total_data);
    }

    public static function send_handshake($client, $infohash)
    {
        $bt_protocol = self::$_bt_protocol;
        $bt_header = chr(strlen($bt_protocol)) . $bt_protocol;
        $ext_bytes = "\x00\x00\x00\x00\x00\x10\x00\x00";
        $peer_id = Base::get_node_id();
        $packet = $bt_header . $ext_bytes . $infohash . $peer_id;
        $client->send($packet);
        $data = $client->recv(4096, 0);
        if ($data === false) {
            return false;
        }
        return $data;
    }

    public static function check_handshake($packet, $self_infohash)
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

    public static function send_ext_handshake($client)
    {
        $msg = chr(self::$BT_MSG_ID) . chr(self::$EXT_HANDSHAKE_ID) . Base::encode(array("m" => array("ut_metadata" => 1)));//{"m":{"ut_metadata": 1}

        define('BIG_ENDIAN', pack('L', 1) === pack('N', 1));
        $msg_len = pack("I", strlen($msg));
        if (!BIG_ENDIAN) {
            $msg_len = strrev($msg_len);
        }
        $msg = $msg_len . $msg;

        $client->send($msg);
        $data = $client->recv(4096, 0);
        if ($data === false) {
            return false;
        }
        return $data;
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


}