<?php

class model extends basis{
    private $resume_table;
    private $account_table;
    private $condition_table;
    private $education_table;
    private $project_table;
    private $workexps_table;

    public  function __construct($config)
    {
        $this->resume_table = $config['resume_table'];
        $this->account_table = $config['account_table'];
        $this->condition_table = $config['condition_table'];
        $this->education_table = $config['education_table'];
        $this->project_table = $config['project_table'];
        $this->workexps_table = $config['workexps_table'];
    }

    function init_start(){
        save_log('抓取初始化');
        $GLOBALS['db']->update($this->account_table, array('status'=>self::ACCOUNT_INIT,'used_time'=>0,'error_num'=>0),'status = '.self::ACCOUNT_RUN);
        $GLOBALS['db']->update($this->condition_table, array('status'=>self::CONDITION_INIT) ,"status != ".self::CONDITION_SUC);
        $GLOBALS['db']->update($this->resume_table, array('status'=>self::RESUME_INIT) ,"status =".self::RESUME_RUN);

        //得到所有账号并初始化开始时间，避免账号每天都同一时间开始
        $account_list = $GLOBALS['db']->getAll("select account_id from ".$this->account_table);
        foreach($account_list as $account_one){
			$sleep_time = rand( 0 , 60*20);
            $used_time = time() + $sleep_time;
            save_log('账号ID : '.$account_one['account_id'].' 开始抓取时间推后'.$sleep_time.'秒');
            $GLOBALS['db']->update($this->account_table, array('used_time'=>$used_time),'account_id = '.$account_one['account_id']);
        }
    }

    function parse_start(){
        $GLOBALS['db']->update($this->resume_table, array('parse_status'=>self::RESUME_INIT) ,"parse_status !=".self::RESUME_SUC);
    }

    function delete_conditions(){
        $GLOBALS['db']->delete($this->condition_table);
    }

    function get_conditions_count(){
        $sql = "select count(*) as total_num from ".$this->condition_table;
        return $GLOBALS['db']->getOne($sql);
    }

    function update_account_qiandao($account_id){
        $GLOBALS['db']->update($this->account_table, array('qiandao'=>date('Y-m-d')),'account_id = '.$account_id);
    }

    function get_account_count(){
        $sql = "select count(*) as total_num from ".$this->account_table.' where error_num < 50';
        return $GLOBALS['db']->getOne($sql);
    }

    function update_conditions_total_page($total_page,$rec_id){
        return $GLOBALS['db']->update($this->condition_table, array('total_page'=>$total_page) ,"rec_id=".$rec_id);
    }

    function grab_conditions_cur_page($cur_page,$rec_id){
        $data = array('status'=>self::CONDITION_INIT,'cur_page'=>$cur_page + 1,'update_time'=>date('Y-m-d H:i:s'));
        return $GLOBALS['db']->update($this->condition_table, $data ,"rec_id=".$rec_id);
    }

    function update_conditions_status($status,$rec_id){
        return $GLOBALS['db']->update($this->condition_table, array('status'=>$status,'update_time'=>date('Y-m-d H:i:s')) ,"rec_id=".$rec_id);
    }

    function grab_conditions_suc($total_page,$rec_id){
        $data = array('status'=>self::CONDITION_SUC,'cur_page'=>$total_page,'is_full'=>self::CONDITION_IS_FULL,'update_time'=>date('Y-m-d H:i:s'));
        return $GLOBALS['db']->update($this->condition_table,$data ,"rec_id=".$rec_id);
    }

    function insert_conditions($data){
        return $GLOBALS['db']->insert($this->condition_table,$data);
    }

    function get_resume_by_user_sn($user_sn){
        $sql = "select resume_id,login_time from resume where user_sn = '".$user_sn."'";
        $resume_info = $GLOBALS['db']->getRow($sql);
        return $resume_info;
    }

    function save_resume_detail($row,$html){
        $data['status'] = self::RESUME_SUC ;
        $data['crawler_time'] = time();
        $content = base64_encode($html);
        if($content != $row['content'] && md5($content) != $row['content']){
            $data['content'] = $content;
            $data['parse_status'] = self::RESUME_INIT ;
            $msg = '内容改变，改状态并需要重新解析';
        }else{
            $msg = '内容没有改变，只更改状态，无需重新解析';
        }
        save_log($msg);

        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$row['resume_id']);
    }

    function resume_parse_suc($row){
        $resume_id = $row['resume_id'];
        $content = md5($row['content']);
        $data = array('parse_status'=>self::RESUME_SUC ,'parse_time' => time(),'content'=>$content);
        return $GLOBALS['db']->update($this->resume_table,$data,'resume_id = '.$resume_id);
    }

    function update_resume_status($status,$resume_id){
        return $GLOBALS['db']->update($this->resume_table,array('status'=>$status),'resume_id = '.$resume_id);
    }

    function update_resume_one($one,$resume_id){
        $one['status'] = self::RESUME_INIT ;
        return $GLOBALS['db']->update($this->resume_table,$one,'resume_id = '.$resume_id);
    }

    function update_resume_parse($resume_id , $one){
        return $GLOBALS['db']->update($this->resume_table,$one,'resume_id = '.$resume_id);
    }

    function save_resume_one($one){
        $one['status'] = self::RESUME_INIT ;
        return $GLOBALS['db']->insert($this->resume_table,$one);
    }

    function update_account_status($status,$account_id){
        return $GLOBALS['db']->update($this->account_table, array('status' =>$status ), "account_id=" . $account_id);
    }

    function add_account_error($account_id,$error_num){
        return $GLOBALS['db']->update($this->account_table, array('error_num' =>$error_num + 1 ), "account_id=" . $account_id);
    }

    function get_account(){
        while(true){
            $time = time();
            $sql = "select * from ".$this->account_table." where status = ".self::ACCOUNT_INIT ." and error_num < 50 and used_time < $time";
            $account = $GLOBALS['db']->getRow($sql);
            if(empty($account)){
                save_log('暂无空闲账号可用，休息3秒');
                sleep(3);
                return false;
            }

            $sleep_time = rand(5, ACCOUNT_SLEEP_TIME);
            $used_time = time() +$sleep_time;
            $res = $GLOBALS['db']->update($this->account_table, array('status' =>self::ACCOUNT_INIT , 'used_time' => $used_time), "account_id=" . $account['account_id'].' and status = '.self::ACCOUNT_INIT);

            if($res){
                save_log('获得可用账号，休息'.$sleep_time.'秒 ,当前账号ID : '.$account['account_id']);
                return $account;
            }
        }
    }

    function get_search_info(){
        while(true){
            $sql = "select * from ".$this->condition_table." where status = ".self::CONDITION_INIT ." order by is_full asc, sort desc";
            $row = $GLOBALS['db']->getRow($sql);

            if(empty($row)){
                $GLOBALS['db']->update($this->condition_table,array('status'=>self::CONDITION_INIT , 'cur_page'=>0) , 'status = '.self::CONDITION_SUC);
                save_log('搜索条件生成的简历列表抓取完毕');
                continue;
            }
            $res = $GLOBALS['db']->update($this->condition_table,array('status'=>self::CONDITION_RUN),"rec_id=".$row['rec_id'].' and status = '.self::CONDITION_INIT);
            if($res) return $row;
        }
    }

    function get_resume_info($account_name){
        while(true){
            $time = strtotime(date('Y-m-d H:i:s').'-7 days');
            //该账号下七天前没抓过的简历，七天之内不重复抓
            $sql = "select * from ".$this->resume_table." where status = ".self::RESUME_INIT ." and account = '".$account_name."' and crawler_time < ".$time;
            $row = $GLOBALS['db']->getRow($sql);

            if(empty($row)) return '';
            $res = $GLOBALS['db']->update($this->resume_table,array('status'=>self::RESUME_RUN),"resume_id=".$row['resume_id'].' and status = '.self::RESUME_INIT);
            if($res) return $row;
        }
    }

    function get_parse_info(){
        while(true){
            $sql = "select resume_id , content from ".$this->resume_table." where status = ".self::RESUME_SUC." and parse_status = ".self::RESUME_INIT ;
            $row = $GLOBALS['db']->getRow($sql);

            if(empty($row)) return '';
            $res = $GLOBALS['db']->update($this->resume_table,array('parse_status'=>self::RESUME_RUN),"resume_id=".$row['resume_id'].' and parse_status = '.self::RESUME_INIT);
            if($res && strlen($row['content']) > 100){
                $row['content'] = base64_decode($row['content']);
                return $row;
            }
        }
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