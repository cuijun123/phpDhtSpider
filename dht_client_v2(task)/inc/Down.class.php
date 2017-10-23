<?php
class Down{
    public $mpid=0;
    public $works=[];
    public $max_process= 10;
    public $table;
    public function __construct(){
        try {
            $this->mpid = posix_getpid();
            $this->run();
            $this->processWait();
        }catch (\Exception $e){
            die('ALL ERROR: '.$e->getMessage());
        }
    }

    public function run(){
        for ($i=0; $i < $this->max_process; $i++) {
            $this->CreateProcess(null);
        }
    }

    public function CreateProcess($index=null){
        $process = new swoole_process(function(swoole_process $worker)use($index){
            if(is_null($index)){
                $index = $worker->pid;
            }
            $this->checkMpid($worker);
            global $Channel;
            $data = $Channel->pop();
            if($data === false){
                return false;
            }
            var_dump($data);

        }, false, false);
        $pid=$process->start();
        $this->works[$pid]=$pid;
        return $pid;
    }
    public function checkMpid(&$worker){
        if(!swoole_process::kill($this->mpid,0)){
            $worker->exit();
            file_put_contents(BASEPATH.'/logs/runtime.log', "Master process exited, I [{$worker['pid']}] also quit\n", FILE_APPEND);
        }
    }

    public function rebootProcess($ret){
        $pid=$ret['pid'];
        $index=array_search($pid, $this->works);
        if($index!==false){
            $index=intval($index);
            $new_pid=$this->CreateProcess($index);
            //echo "rebootProcess: {$index}={$new_pid} Done\n";
            unset($this->works[$pid]);
            return;
        }
        throw new \Exception('rebootProcess Error: no pid');
    }

    public function processWait()
    {
        while (1) {
            if (count($this->works)) {
                $ret = swoole_process::wait();
                if ($ret) {
                    $this->rebootProcess($ret);
                }
            } else {
                break;
            }
        }
    }
};