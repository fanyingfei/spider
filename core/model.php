<?php

class model extends basis{
    private $config_table;
    private $resume_table;
    private $account_table;
    private $condition_table;
    private $education_table;
    private $project_table;
    private $workexps_table;

    public  function __construct($config)
    {
        $this->config_table = $config['config_table'];
        $this->resume_table = $config['resume_table'];
        $this->account_table = $config['account_table'];
        $this->condition_table = $config['condition_table'];
        $this->education_table = $config['education_table'];
        $this->project_table = $config['project_table'];
        $this->workexps_table = $config['workexps_table'];
    }

    function init_start(){
        $GLOBALS['db']->update($this->account_table, array('status'=>self::ACCOUNT_INIT,'used_time'=>0,'error_num'=>0),'status = '.self::ACCOUNT_RUN);
        $GLOBALS['db']->update($this->condition_table, array('status'=>self::CONDITION_INIT) ,"status != ".self::CONDITION_SUC);
        $GLOBALS['db']->update($this->resume_table, array('status'=>self::RESUME_INIT) ,"status !=".self::RESUME_SUC);
    }

    function delete_conditions(){
        $GLOBALS['db']->delete($this->condition_table);
    }

    function update_conditions_page($total_page,$rec_id){
        return $GLOBALS['db']->update($this->condition_table, array('total_page'=>$total_page) ,"rec_id=".$rec_id);
    }

    function update_conditions_suc($rec_id){
        return $GLOBALS['db']->update($this->condition_table, array('status'=>self::CONDITION_SUC) ,"rec_id=".$rec_id);
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

    function grab_resume_suc($resume_id,$path){
        $data = array('status'=>self::RESUME_SUC ,'path'=>$path, 'crawler_time' => time());
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function grab_resume_parse_suc($resume_id){
        $data = array('status'=>self::RESUME_SUC ,'parse_time' => time());
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function grab_resume_fail($resume_id,$path){
        $data = array('status'=>self::RESUME_ERROR ,'path'=>$path);
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function update_resume_fail($resume_id){
        $data = array('status'=>self::RESUME_ERROR);
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function update_resume_param($resume_id){
        $data = array('status'=>self::RESUME_PARAM);
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function update_resume_body($resume_id){
        $data = array('status'=>self::RESUME_BODY);
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
            $sql = "select * from ".$this->account_table." where status = ".self::ACCOUNT_INIT ." and error_num < 50 and used_time < $time";
            $account = $GLOBALS['db']->getRow($sql);
            if(empty($account)){
                echo "have not accout\n\r";
                save_log('暂无空闲账号可用');
                sleep(10);
                continue;
            }

            $low_time = ACCOUNT_SLEEP_TIME - 20;
            $high_time = ACCESS_COUNT_LIMIT + 20;
            $used_time = time() + rand($low_time, $high_time);
            $res = $GLOBALS['db']->update($this->account_table, array('status' =>self::ACCOUNT_INIT , 'used_time' => $used_time), "account_id=" . $account['account_id'].' and status = '.self::ACCOUNT_INIT);

            if($res){
                echo "get one account and update account status and used_time\n\r";
                save_log('获得可用账号，休息'.$used_time.'秒 ,当前账号ID : '.$account['account_id']);
                return $account;
            }
        }
    }

    function get_search_info(){
        while(true){
            $sql = "select * from ".$this->condition_table." where status = ".self::CONDITION_INIT ." order by sort desc";
            $row = $GLOBALS['db']->getRow($sql);

            if(empty($row)) return '';
            echo "get conditions\n\r";
            $res = $GLOBALS['db']->update($this->condition_table,array('status'=>self::CONDITION_RUN),"rec_id=".$row['rec_id'].' and status = '.self::CONDITION_INIT);
            if($res) return $row;
        }
    }

    function get_resume_info($account_name){
        while(true){
            $time = strtotime(date('Y-m-d H:i:s').'-7 days');
            //该账号下七天前没抓过的简历，七天之内不重复抓
            $sql = "select * from ".$this->resume_table." where status = ".self::RESUME_INIT ." and account = '".$account_name."' and crawlertime < ".$time;
            $row = $GLOBALS['db']->getRow($sql);

            if(empty($row)) return '';
            echo "get one resumt\n\r";
            $res = $GLOBALS['db']->update($this->resume_table,array('status'=>self::RESUME_RUN),"resume_id=".$row['resume_id'].' and status = '.self::RESUME_INIT);
            if($res) return $row;
        }
    }

    function get_parse_info(){
        while(true){
            $sql = "select * from ".$this->resume_table." where parse_status = ".self::RESUME_INIT ;
            $row = $GLOBALS['db']->getRow($sql);

            if(empty($row)) return '';
            echo "get one resumt\n\r";
            $res = $GLOBALS['db']->update($this->resume_table,array('parse_status'=>self::RESUME_RUN),"resume_id=".$row['resume_id'].' and parse_status = '.self::RESUME_INIT);
            if($res) return $row;
        }
    }

    function get_config(){
        $sql = "select * from ".$this->config_table;
        return $GLOBALS['db']->getAll($sql);
    }

    function set_config_condition(){
        $GLOBALS['db']->update($this->config_table , array('status'=>self::CONFIG_SUC , 'finish_time'=>date('Y-m-d H:i:s')),"code='".CONDDTION_STATUS . "'");
    }

    function set_config_resume(){
        $GLOBALS['db']->update($this->config_table , array('status'=>self::CONFIG_SUC , 'finish_time'=>date('Y-m-d H:i:s')),"code='".RESUME_STATSU . "'");
    }

    function save_workepx($resume_id,$new_data){
        $table_name = $this->workexps_table;
        $key_name = 'work_id';
        $sign1 = 'corporation_name';
        $sign2 = 'corporation_type';
        $sign3 = 'position_name';
        $this->parse_common_save($resume_id,$new_data,$table_name,$key_name,$sign1,$sign2,$sign3);
    }

    function save_project($resume_id,$new_data){
        $table_name = $this->project_table;
        $key_name = 'pro_id';
        $sign1 = 'project_name';
        $sign2 = 'corporation_name';
        $sign3 = 'position_name';
        $this->parse_common_save($resume_id,$new_data,$table_name,$key_name,$sign1,$sign2,$sign3);
    }

    function save_education($resume_id,$new_data){
        $table_name = $this->education_table;
        $key_name = 'edu_id';
        $sign1 = 'school_name';
        $sign2 = 'discipline_name';
        $sign3 = 'degree';
        $this->parse_common_save($resume_id,$new_data,$table_name,$key_name,$sign1,$sign2,$sign3);
    }

    private function parse_common_save($resume_id ,$new_data ,$table_name , $key_name ,$sign1,$sign2,$sign3){
        if(empty($new_data)) return false;

        $sql = "select * from $table_name where resume_id = $resume_id";
        $old_data = $GLOBALS['db']->getAll($sql);

        if(empty($old_data)){
            foreach($new_data as $new){
                $new['create_time'] = $new['update_time'] = date('Y-m-d H:i:s');
                $GLOBALS['db']->insert($table_name,$new);
            }
            return true;
        }

        $old_list = $new_list = array();
        foreach($new_data as $new){
            $new_list[$new[$sign1]][$new[$sign2]][$new[$sign3]] = $new;
        }

        foreach($old_data as $old){
            if(empty($new_list[$old[$sign1]][$old[$sign2]][$old[$sign3]])){
                $GLOBALS['db']->delete($table_name , array($key_name=>$old[$key_name]));
            }
            $old_list[$old[$sign1]][$old[$sign2]][$old[$sign3]] = $old;
        }

        foreach($new_data as $new){
            if(empty($old_list[$new[$sign1]][$new[$sign2]][$new[$sign3]])){
                $new['create_time'] = $new['update_time'] = date('Y-m-d H:i:s');
                $GLOBALS['db']->insert($table_name,$new);
            }else{
                $old_one = $old_list[$new[$sign1]][$new[$sign2]][$new[$sign3]];
                $key_value = $old_one[$key_name];
                unset($old_one[$key_name]);
                unset($old_one['update_time']);
                unset($old_one['create_time']);
                if($old_one != $new){
                    $new['update_time'] = date('Y-m-d H:i:s');
                    $GLOBALS['db']->update($table_name , $new ,"$key_name=$key_value");
                }
            }
        }
    }

}