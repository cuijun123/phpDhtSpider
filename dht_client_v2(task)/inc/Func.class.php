<?php

class Func
{

    /*
     * 日志记录
     * @param $msg
     * @param int $type
     */
    public static function Logs($msg, $type = 1)
    {
        if ($type == 1) { //启动信息
            $path = BASEPATH . '/logs/start_' . date('Ymd') . '.log';
        } elseif ($type == 2) { //hash信息
            $path = BASEPATH . '/logs/hashInfo_' . date('Ymd') . '.log';
        } else {
            $path = BASEPATH . '/logs/otherInfo_' . date('Ymd') . '.log';
        }

        $fp = fopen($path, 'ab');
        fwrite($fp, $msg);
        fclose($fp);
    }

    public static function sizecount($filesize)
    {
        if ($filesize == null || $filesize == '' || $filesize == 0) return '0';
        if ($filesize >= 1073741824) {
            $filesize = round($filesize / 1073741824 * 100) / 100 . ' gb';
        } elseif ($filesize >= 1048576) {
            $filesize = round($filesize / 1048576 * 100) / 100 . ' mb';
        } elseif ($filesize >= 1024) {
            $filesize = round($filesize / 1024 * 100) / 100 . ' kb';
        } else {
            $filesize = $filesize . ' bytes';
        }
        return $filesize;
    }

    public static function characet($data)
    {
        if (!empty($data)) {
            $fileType = mb_detect_encoding($data, array('UTF-8', 'GBK', 'LATIN1', 'BIG5'));
            if ($fileType != 'UTF-8') {
                $data = mb_convert_encoding($data, 'utf-8', $fileType);
            }
        }
        return $data;
    }

    public static function getKeyWords($title){
        if($title == ''){
            return '';
        }
        $title = explode(' ',$title);
        if(!is_array($title)){
            return '';
        }
        $title = str_replace(',','',$title);
        foreach ($title as $key=> $value){
            if(strlen($value) < 5){
                unset($title[$key]);
            }
            if(strpos($value,'.') !== false){
                unset($title[$key]);
            }
        }
        return implode(',',$title);
    }
}
