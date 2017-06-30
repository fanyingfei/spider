<?php

class model{
    private $resume_table;
    private $account_table;
    private $condition_table;

    const RESUME_INIT = 0;
    const RESUME_RUN = 1;
    const RESUME_SUC = 2;
    const RESUME_NOT = 3; //非法参数
    const RESUME_ERROR = 3;//参数异常
    const RESUME_WORK = 4;//工作经验空
    const ACCOUNT_INIT = 0; //初始
    const ACCOUNT_RUN = 1; //正在使用
    const ACCOUNT_LOGIN = 2; //重新登录
    const ACCOUNT_ERROR = 3; //账号错误
    const ACCOUNT_PHONE = 4; //异地短信验证
    const CONDITION_INIT = 0;
    const CONDITION_RUN = 1;
    const CONDITION_SUC = 2;
    const CONDITION_ERROR = 3;//抓取异常
    const CONDITION_NULL = 4;//无搜索结果

    public  function __construct($config)
    {
        $this->resume_table = $config['resume_table'];
        $this->account_table = $config['account_table'];
        $this->condition_table = $config['condition_table'];
    }

    function init_start(){
        $GLOBALS['db']->update($this->account_table, array('status'=>self::ACCOUNT_INIT,'used_time'=>0,'error_num'=>0),'status = '.self::ACCOUNT_RUN);
        $GLOBALS['db']->update($this->condition_table, array('status'=>self::CONDITION_INIT) ,"status != ".self::CONDITION_SUC);
        $GLOBALS['db']->update($this->resume_table, array('status'=>self::RESUME_INIT) ,"status !=".self::RESUME_SUC);
    }

    function get_conditions_num(){
        $sql = "select count(rec_id) as num from conditions";
        $num = $GLOBALS['db']->getOne($sql);
        return $num;
    }

    function update_conditions_page($total_page,$rec_id){
        return $GLOBALS['db']->update($this->condition_table, array('total_page'=>$total_page) ,"rec_id=".$rec_id);
    }

    function update_conditions_init($rec_id){
        return $GLOBALS['db']->update($this->condition_table, array('status'=>self::CONDITION_INIT) ,"rec_id=".$rec_id);
    }

    function grab_conditions_fail($rec_id){
        return $GLOBALS['db']->update($this->condition_table, array('status'=>self::CONDITION_ERROR) ,"rec_id=".$rec_id);
    }

    function grab_conditions_curr($cur_page,$rec_id){
        $data = array('status'=>self::CONDITION_INIT,'cur_page'=>$cur_page + 1);
        return $GLOBALS['db']->update($this->condition_table, $data ,"rec_id=".$rec_id);
    }

    function grab_conditions_suc($total_page,$rec_id){
        $data = array('status'=>self::CONDITION_SUC,'cur_page'=>$total_page);
        return $GLOBALS['db']->update($this->condition_table,$data ,"rec_id=".$rec_id);
    }

    function grab_conditions_null($rec_id){
        $data = array('status'=>self::CONDITION_NULL );
        return $GLOBALS['db']->update($this->condition_table,$data ,"rec_id=".$rec_id);
    }

    function insert_conditions($data){
        return $GLOBALS['db']->insert($this->condition_table,$data);
    }

    function get_resume_by_sign($sign){
        $sql = "select resume_id,url,logintime from resume where sign = '".$sign."'";
        $resume_info = $GLOBALS['db']->getRow($sql);
        return $resume_info;
    }

    function update_resume_suc($resume_id){
        $data = array('status'=>self::RESUME_SUC );
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function update_resume_not($resume_id){
        $data = array('status'=>self::RESUME_NOT );
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function grab_resume_suc($resume_id,$path){
        $data = array('status'=>self::RESUME_SUC ,'path'=>$path, 'crawlertime' => date('Y-m-d H:i:s'));
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function grab_resume_fail($resume_id,$path){
        $data = array('status'=>self::RESUME_ERROR ,'path'=>$path, 'crawlertime' => date('Y-m-d H:i:s'));
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function update_resume_fail($resume_id){
        $data = array('status'=>self::RESUME_ERROR);
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function update_resume_one($one,$resume_id){
        $one['status'] = self::RESUME_INIT ;
        return $GLOBALS['db']->update($this->resume_table,$one,'resume_id = '.$resume_id);
    }

    function update_resume_init($resume_id){
        $data = array('status'=>self::RESUME_INIT);
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function save_resume_one($one){
        $one['status'] = self::RESUME_INIT ;
        return $GLOBALS['db']->insert($this->resume_table,$one);
    }

    function set_account_error($account_id){
        return $GLOBALS['db']->update($this->account_table, array('status' =>self::ACCOUNT_ERROR ), "account_id=" . $account_id);
    }

    function add_account_error($account_id,$error_num){
        return $GLOBALS['db']->update($this->account_table, array('error_num' =>$error_num + 1 ), "account_id=" . $account_id);
    }

    function set_account_login($account_id){
        return $GLOBALS['db']->update($this->account_table, array('status' =>self::ACCOUNT_LOGIN ), "account_id=" . $account_id);
    }

    function set_account_phone($account_id){
        return $GLOBALS['db']->update($this->account_table, array('status' =>self::ACCOUNT_PHONE ), "account_id=" . $account_id);
    }

    function get_account(){
        while(true){
            $time = time();
            $sql = "select * from account where status = ".self::ACCOUNT_INIT ." and error_num < 50 and used_time < $time";
            $account = $GLOBALS['db']->getRow($sql);
            if(empty($account)){
                echo "have not accout\n\r";
                save_log('暂无空闲账号可用');
                sleep(10);
                continue;
            }

            $res = $GLOBALS['db']->update($this->account_table,array('status'=>self::ACCOUNT_RUN),"account_id=".$account['account_id'].' and status = '.self::ACCOUNT_INIT);
            echo "get one account\n\r";
            save_log('获得可用账号，当前账号ID : '.$account['account_id']);
            if($res) return $account;
        }
    }

    function get_search_info(){
        while(true){
            $sql = "select * from conditions where status = ".self::CONDITION_INIT ." and cur_page <= total_page";
            $row = $GLOBALS['db']->getRow($sql);

            if(empty($row)) return '';

            $res = $GLOBALS['db']->update($this->condition_table,array('status'=>self::CONDITION_RUN),"rec_id=".$row['rec_id'].' and status = '.self::CONDITION_INIT);
            if($res) return $row;
        }
    }

    function get_resume_info($account_name){
        while(true){
            $sql = "select * from resume where status = ".self::RESUME_INIT ." and account = '".$account_name."'";
            $row = $GLOBALS['db']->getRow($sql);

            if(empty($row)) return '';
            echo "get one resumt\n\r";
            $res = $GLOBALS['db']->update($this->resume_table,array('status'=>self::RESUME_RUN),"resume_id=".$row['resume_id'].' and status = '.self::RESUME_INIT);
            if($res) return $row;
        }
    }

    function update_account($account){
        $low_time = ACCOUNT_SLEEP_TIME - 20;
        $high_time = ACCESS_COUNT_LIMIT + 20;
        $used_time = time() + rand($low_time, $high_time);
        echo "update account status and used_time\n\r";
        save_log('账号使用完毕，休息'.$used_time.'秒 , 当前账号 : '.$account['account_id']);
        return $GLOBALS['db']->update($this->account_table, array('status' =>self::ACCOUNT_INIT , 'used_time' => $used_time), "account_id=" . $account['account_id']);
    }

}