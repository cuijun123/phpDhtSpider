<?php
class Func{

    /*
     * 日志记录
     * @param $msg
     * @param int $type
     */
    public static function Logs($msg,$type=1){
        if($type == 1){ //启动信息
            $path = BASEPATH.'/logs/start_'.date('Ymd').'.log';
        }elseif($type ==2){ //hash信息
            $path = BASEPATH.'/logs/hashInfo_'.date('Ymd').'.log';
        }else{
            $path = BASEPATH.'/logs/otherInfo_'.date('Ymd').'.log';
        }

        $fp = fopen($path, 'ab');
        fwrite($fp, $msg);
        fclose($fp);
    }

    public static function sizecount($filesize) {
        if($filesize >= 1073741824) {
            $filesize = round($filesize / 1073741824 * 100) / 100 . ' gb';
        } elseif($filesize >= 1048576) {
            $filesize = round($filesize / 1048576 * 100) / 100 . ' mb';
        } elseif($filesize >= 1024) {
            $filesize = round($filesize / 1024 * 100) / 100 . ' kb';
        } else {
            $filesize = $filesize . ' bytes';
        }
        return $filesize;
    }
}
