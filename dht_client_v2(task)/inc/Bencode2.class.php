<?php
/**
 * bencode编码解码类
 */
class Bencode{
    /**
     * bencode解码
     * @param  string $str 要解码的数据
     * @return object      解码后的数据
     */
    static public function decode($str){
        if(!is_string($str))
            return '';
        Decode::$source = $str;
        Decode::$length = strlen($str);
        return Decode::decodes();
    }

    /**
     * bencode编码
     * @param  object $value 要编码的数据
     * @return string        编码后的数据
     */
    static public function encode($value){
        if(is_object($value)){
            if(method_exists($value, 'toArray'))
                $value = $value->toArray();
            else
                $value = (array) $value;
        }
        Encode::$data = $value;
        return Encode::encodes();
    }
}

/**
 * bencode解码类
 */
class Decode{
    /**
     * 解码数据
     * @var string
     */
    public static $source;
    /**
     * 数据长度
     * @var integer
     */
    public static $length;
    /**
     * 当前索引
     * @var integer
     */
    private static $offset = 0;


    /**
     * 解码bencode数据
     * @param  string $source 要解码的数据
     * @return mixed         解码后的数据
     */
    static public function decodes(){
        self::$offset = 0;
        // 调用类本身完成解码
        $decoded = self::do_decode();
        //echo self::$offset .'---'. self::$length.PHP_EOL;
        // 验证数据
        if(self::$offset != self::$length)
            return '';

        return $decoded;
    }

    /**
     * 选择操作类型
     * @return mixed 解码后的数据
     */
    static private function do_decode(){
        // 截取数据字符判断操作类型
        switch(self::get_char()){
            case 'i':
                ++self::$offset;
                return self::decode_integer();
            case 'l':
                ++self::$offset;
                return self::decode_list();
            case 'd':
                ++self::$offset;
                return self::decode_dict();
            default:
                if(ctype_digit(self::get_char()))
                    return self::decode_string();
        }

        return '';
    }

    /**
     * 解码数字类型数据
     * @return integer 解码后的数据
     */
    static private function decode_integer(){
        $offset_e = strpos(self::$source, 'e', self::$offset);

        if($offset_e === false)
            return '';

        $current_off = self::$offset;

        if(self::get_char($current_off) == '-')
            ++$current_off;

        if($offset_e === $current_off)
            return '';

        while($current_off < $offset_e){
            if(!ctype_digit(self::get_char($current_off)))
                return '';

            ++$current_off;
        }

        $value = substr(self::$source, self::$offset, $offset_e - self::$offset);
        $absolute_value = (string) abs($value);

        if(1 < strlen($absolute_value) && '0' == $value[0])
            return '';

        self::$offset = $offset_e + 1;

        return $value + 0;
    }

    /**
     * 解码字符串类型数据
     * @return string 解码后的数据
     */
    static private function decode_string(){
        if('0' === self::get_char() && ':' != self::get_char(self::$offset + 1))
            return '';

        $offset_o = strpos(self::$source, ':', self::$offset);

        if($offset_o === false)
            return '';

        $content_length = (int) substr(self::$source, self::$offset, $offset_o);

        if(($content_length + $offset_o + 1) > self::$length)
            return '';

        $value = substr(self::$source, $offset_o + 1, $content_length);
        self::$offset = $offset_o + $content_length + 1;

        return $value;
    }

    /**
     * 解码数组类型数据
     * @return array 解码后的数据
     */
    static private function decode_list(){
        $list = array();
        $terminated = false;
        $list_offset = self::$offset;

        while(self::get_char() !== false){
            if(self::get_char() == 'e'){
                $terminated = true;
                break;
            }

            $list[] = self::do_decode();
        }

        if(!$terminated && self::get_char() === false)
            return '';

        self::$offset++;

        return $list;
    }

    /**
     * 解码词典类型数据
     * @return array 解码后的数据
     */
    static private function decode_dict(){
        $dict = array();
        $terminated = false;
        $dict_offset = self::$offset;

        while(self::get_char() !== false){
            if(self::get_char() == 'e'){
                $terminated = true;
                break;
            }

            $key_offset = self::$offset;

            if(!ctype_digit(self::get_char()))
                return '';

            $key = self::decode_string();

            if(isset($dict[$key]))
                return '';

            $dict[$key] = self::do_decode();
        }

        if(!$terminated && self::get_char() === false)
            return '';

        self::$offset++;

        return $dict;
    }

    /**
     * 截取数据
     * @param  integer $offset 截取索引
     * @return string|false         截取到的数据
     */
    static private function get_char($offset = null){
        if($offset === null)
            $offset = self::$offset;

        if(empty(self::$source) || self::$offset >= self::$length)
            return false;

        return self::$source[$offset];
    }
}

class Encode{
    /**
     * 保存编码数据
     * @var mixed
     */
    public static $data;


    /**
     * bencode编码
     * @param  mixed $data 要编码的数据
     * @return string       编码后的数据
     */
    static public function encodes(){
        $encoded = self::do_encode();
        return $encoded;
    }

    /**
     * 选择操作类型
     * @param  mixed $data 要编码的数据
     * @return string       编码后的数据
     */
    static private function do_encode($data = null){
        $data = is_null($data) ? self::$data : $data;

        if(is_array($data) && (isset($data[0]) || empty($data))){
            return self::encode_list($data);
        }elseif(is_array($data)){
            return self::encode_dict($data);
        }elseif(is_integer($data) || is_float($data)){
            $data = sprintf("%.0f", round($data, 0));
            return self::encode_integer($data);
        }else{
            return self::encode_string($data);
        }
    }

    /**
     * 编码数字类型数据
     * @param  integer $data 要编码的数据
     * @return string       编码后的数据
     */
    static private function encode_integer($data = null){
        $data = is_null($data) ? self::$data : $data;

        return sprintf("i%.0fe", $data);
    }

    /**
     * 编码字符串类型数据
     * @param  string $data 要编码的数据
     * @return string       编码后的数据
     */
    static private function encode_string($data = null){
        $data = is_null($data) ? self::$data : $data;

        return sprintf("%d:%s", strlen($data), $data);
    }

    /**
     * 编码数组数据
     * @param  array $data 要编码的数据
     * @return string           编码后的数据
     */
    static private function encode_list(array $data = null){
        $data = is_null($data) ? self::$data : $data;
        $list = '';

        foreach($data as $value)
            $list .= self::do_encode($value);

        return "l{$list}e";
    }

    /**
     * 编码词典类型数据
     * @param  array $data 要编码的数据
     * @return string           编码后的数据
     */
    static private function encode_dict(array $data = null){
        $data = is_null($data) ? self::$data : $data;
        ksort($data);
        $dict = '';

        foreach($data as $key => $value)
            $dict .= self::encode_string($key) . self::do_encode($value);

        return "d{$dict}e";
    }
}