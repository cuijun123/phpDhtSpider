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
            if ($packet === false) {
                return false;
            }

            $check_handshake = self::check_handshake($packet, $infohash);

            if ($check_handshake === false) {
                return false;
            }


            $packet = self::send_ext_handshake($client);

            if ($packet === false) {
                return false;
            }

            $ut_metadata = self::get_ut_metadata($packet);
            $metadata_size = self::get_metadata_size($packet);
			  if($metadata_size > self::$PIECE_LENGTH * 1000){
                return false;
            }

            //var_dump($ut_metadata);
            //var_dump($metadata_size);
            $metadata = array();
            $piecesNum = ceil($metadata_size / (self::$PIECE_LENGTH));//2 ^ 14
            for ($i = 0; $i < $piecesNum; $i++) {
                $request_metadata = self::request_metadata($client, $ut_metadata, $i);
                if($request_metadata === false){
                    return false;
                }

                $packet = self::recvall($client);
                if($packet === false){
                    return false;
                }

                $ee = substr($packet,0,strpos($packet,"ee")+2);
                $dict = Base::decode(substr($ee,strpos($packet,"d")));

                if(isset($dict['msg_type']) && $dict['msg_type'] != 1){
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
            //Func::Logs(var_export($metadata,1),3);
            $_infohash = strtoupper(bin2hex($infohash));
            if(isset($metadata['name']) && $metadata['name'] !=''){
                $_data['name'] = Func::characet($metadata['name']);
                $_data['infohash'] = $_infohash;
                $_data['files'] = isset($metadata['files']) ? $metadata['files'] : '';
                $_data['length'] = isset($metadata['length']) ? $metadata['length'] : 0;
                $_data['piece_length'] = isset($metadata['piece length']) ? $metadata['piece length'] : 0;
                //$_data['length_format'] = Func::sizecount($_data['length']);
                //$_data['magnetUrl'] = 'magnet:?xt=urn:btih:'.$_infohash;
                unset($metadata);
            }else{
                return false;
            }
            return $_data;
            //Func::Logs(date('Y-m-d H:i:s').': '.$_data['name'].'['.$_data['magnetUrl'].']'.PHP_EOL.PHP_EOL,2);
            //echo $_data['name'].PHP_EOL;
            //var_dump(var_export($_data,1));
            // Func::Logs(var_export($_data,1),3);

        } catch (Exception $e) {
            $client->close(true);
            var_dump($e->getMessage());
        }
}

//bep_0009
public static function request_metadata($client, $ut_metadata, $piece)
{
    $msg = chr(self::$BT_MSG_ID) . chr($ut_metadata) . Base::encode(array("msg_type" => 0, "piece" => $piece));
    $msg_len = pack("I", strlen($msg));
    if (!BIG_ENDIAN) {
        $msg_len = strrev($msg_len);
    }
    $_msg = $msg_len . $msg;

    $rs = $client->send($_msg);
    if($rs === false){
        return false;
    }
}

    public static function recvall($client)
    {
        $data_length = $client->recv(4, true);
        if($data_length === false){
            return false;
        }

        if(strlen($data_length) != 4){
            return false;
        }

        $data_length = intval(unpack('N',$data_length)[1]);

        if($data_length == 0){
            return false;
        }

		if($data_length > self::$PIECE_LENGTH * 1000){
            return false;
        }

        $data = '';
        while (true){
            if($data_length > 8192){
                if(($_data = $client->recv(8192, true)) == false){
                    //echo $data_length.PHP_EOL;
                    return false;
                }else{
                    $data .= $_data;
                    $data_length = $data_length - 8192;
                }
            }else{
                if(($_data = $client->recv($data_length, true)) == false){
                    return false;
                }else{
                    $data .= $_data;
                    break;
                }
            }
        }
        return $data;
    }

public static function send_handshake($client, $infohash)
{
    $bt_protocol = self::$_bt_protocol;
    $bt_header = chr(strlen($bt_protocol)) . $bt_protocol;
    $ext_bytes = "\x00\x00\x00\x00\x00\x10\x00\x00";
    $peer_id = Base::get_node_id();
    $packet = $bt_header . $ext_bytes . $infohash . $peer_id;
    $rs = $client->send($packet);
    if($rs === false){
        return false;
    }
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
    $msg_len = pack("I", strlen($msg));
    if (!BIG_ENDIAN) {
        $msg_len = strrev($msg_len);
    }
    $msg = $msg_len . $msg;

    $rs = $client->send($msg);
    if ($rs === false) {
        return false;
    }

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