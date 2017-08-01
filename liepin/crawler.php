<?php

class crawler extends basis
{
    public function __construct(){

    }

    public function start(){
        $conditions_count = $GLOBALS['model']->get_conditions_count();
        if($conditions_count > 0){
            save_log('搜索条件已经生成，无需重复生成');
        }else{
            save_log('开始生成搜索条件');
            $this->get_conditions();
            save_log('搜索条件生成完毕');
        }

        $GLOBALS['model']->init_start();

        $os_name = PHP_OS ;
        if(strpos(PHP_OS ,"Linux") !== false){
            if (!function_exists("pcntl_fork")) {
                save_log('不支持fork子进程' , 'error');
                $this->loop_grab();
                return false;
            }
            $account_num = $GLOBALS['model']->get_account_count();
            $account_time = ceil(ACCOUNT_SLEEP_TIME / 5); //一个账号按5s来算处理完一次请求，休息时间除以5代表一个进程能处理多少账号
            $process_count = ceil($account_num/$account_time);
            pcntl_signal(SIGCHLD, SIG_IGN); //如果父进程不关心子进程什么时候结束,子进程结束后，内核会回收
            for($i = 0; $i<$process_count; $i++){
                $pid = pcntl_fork();    //创建子进程 父进程和子进程都会执行下面代码
                if ($pid == -1) {
                    save_log('进程创建失败','error');//错误处理：创建子进程失败时返回-1.
                } elseif ($pid) {
                    save_log('子进程fork成功 , pid : '.$pid,'info'); //父进程会得到子进程号，所以这里是父进程执行的逻辑
                } elseif($pid == 0){
                    save_log('子进程开始工作 ' ,'info');
                    global $db;
                    $db = DBHelper::getIntance(get_db_config());
                    $this->loop_grab(); //子进程得到的$pid为0, 所以这里是子进程执行的逻辑。
                }
            }
        }else if(strpos(PHP_OS ,"WIN") !==false){
            $this->loop_grab();
        }else{
            save_log('这是什么系统：'.PHP_OS , 'error');
            exit;
        }

    }

    function loop_grab(){
        while(true){
            $this->crawler_stop();

            $account = $GLOBALS['model']->get_account();

            if(empty($account)) continue;

            $flag = $this->save_resume($account);

            //为真时，还有简历可抓，继续抓简历，为假时，去抓搜索条件，
            //因为Ａ账号通过搜索条件抓到的简历只有Ａ账号能进行抓取，所以先抓简历避免账号浪费
            if($flag) continue;

            $this->get_resume_list($account);
        }
    }

    /*
     * 细分搜索条件;
     * 按照行业进行细分
     * 细分的条件是：城市，性别，年龄
     */
    public function get_conditions()
    {
        $GLOBALS['model']->delete_conditions();
        $input_url = CONDITIONS_JS_URL;
        $response = str_replace('"', "'", file_get_contents($input_url));
        $industries = $this->_industry($response); //行业　010','040','420'互联网
        $cities = $this->_region($response); //城市
        $ages = $this->_age(); //年龄
        $degrees = $this->_degree(); //学历
        $sex = array(0, 1); //性别


        foreach($cities as $city){
            if(in_array($city,array('100','230','240','300'))){
                $str = 'cs_id=&csc_id=&form_submit=1&sortflag=12&expendflag=1&pageSize=50&curPage=%%s&userHope=&resReward=&res_ids=&so_flag=&keys=&titleKeys=&company=&company_type=0&industrys=&jobtitles=&dqs=%s&workyearslow=&workyearshigh=&edulevellow=&edulevelhigh=&agelow=&agehigh=&sex=';
                $generator_url = sprintf($str, $city);
                $this->_save_search_url($generator_url , $city);
            }elseif(in_array($city,array('220','310'))){
                foreach ($sex as $se) {
                    $str = 'cs_id=&csc_id=&form_submit=1&sortflag=12&expendflag=1&pageSize=50&curPage=%%s&userHope=&resReward=&res_ids=&so_flag=&keys=&titleKeys=&company=&company_type=0&industrys=&jobtitles=&dqs=%s&workyearslow=&workyearshigh=&edulevellow=&edulevelhigh=&agelow=&agehigh=&sex=%s';
                    $generator_url = sprintf($str, $city, $se);
                    $this->_save_search_url($generator_url,$city);
                }
            }elseif(in_array($city,array('110','120','130','160','190','200','260'))){
                foreach ($degrees as $degree_low=>$degree_high) {
                    foreach ($sex as $se) {
                        $str = 'cs_id=&csc_id=&form_submit=1&sortflag=12&expendflag=1&pageSize=50&curPage=%%s&userHope=&resReward=&res_ids=&so_flag=&keys=&titleKeys=&company=&company_type=0&industrys=&jobtitles=&dqs=%s&workyearslow=&workyearshigh=&edulevellow=%s&edulevelhigh=%s&agelow=&agehigh=&sex=%s';
                        $generator_url = sprintf($str, $city, $degree_low, $degree_high, $se);
                        $this->_save_search_url($generator_url , $city , '' , $degree_low.'-'.$degree_high);
                    }
                }
            }else{
                foreach ($industries as $indus) {
                    foreach ($degrees as $degree_low=>$degree_high) {
                        foreach ($sex as $se) {
                            $str = 'cs_id=&csc_id=&form_submit=1&sortflag=12&expendflag=1&pageSize=50&curPage=%%s&userHope=&resReward=&res_ids=&so_flag=&keys=&titleKeys=&company=&company_type=0&industrys=%s&jobtitles=&dqs=%s&workyearslow=&workyearshigh=&edulevellow=%s&edulevelhigh=%s&agelow=&agehigh=&sex=%s';
                            $generator_url = sprintf($str, $indus, $city, $degree_low, $degree_high, $se);
                            $this->_save_search_url($generator_url , $city , $indus , $degree_low.'-'.$degree_high);
                        }
                    }
                }
            }
        }
    }

    /*
    * 抓取简历列表
    */
    public function get_resume_list($account){
        $row = $GLOBALS['model']->get_search_info();
        if(empty($row)){
            return false;
        }

        //当前页面大于等于总页数时，已经是抓完了
        if($row['total_page'] > 0 && $row['cur_page'] >= $row['total_page']){
            $GLOBALS['model']->update_conditions_status(self::CONDITION_SUC,$row['rec_id']);
            return true;
        }

        $cur_page = $row['total_page'] > 0 ? $row['cur_page'] : 0 ;

        $search_url = sprintf($row['search_url'],$cur_page);
        save_log('抓取搜索列表，当前列表ID : '.$row['rec_id'].' , 当前页数 : '.$cur_page);


        $url = SEARCH_RESUME_LIST .'?'. $search_url;
        $res = grab_curl($account,$url);

        if(!$this->deal_conditions($account,$row['rec_id'],$res)){
            return true;
        }

        phpQuery::newDocumentHTML($res);

        $total_page = $row['total_page'];
        $num_str = pq('.pagerbar .addition')->text();
        if(preg_match('/共(\d+)页.*?/uis',$num_str,$match)){
            $total_page = $match[1];
            if($total_page > $row['total_page']) $GLOBALS['model']->update_conditions_total_page($total_page,$row['rec_id']);
        }else{
            save_log('搜索列表抓取失败，列表ID : '.$row['rec_id'] , 'error');
            $GLOBALS['model']->update_conditions_status(self::CONDITION_ERROR,$row['rec_id']);

            $dir = ROOT_PATH .'down/conditions_'.$row['rec_id'].'.html';
            file_put_contents($dir,$res);

            return true;
        }

        try{
            //返回解析此页登录时间不变简历的数量
            $exist_count = $this->save_resume_list($account['account']);
        }catch(Exception $e){
            save_log('搜索列表保存失败，列表ID : '.$row['rec_id'] , 'error');
            $GLOBALS['model']->update_conditions_status(self::CONDITION_ERROR,$row['rec_id']);
        }
        if($row['is_full'] && $exist_count > 25){
            //简历拆取完，当前页有一半登录时间没变过，说明后面的已经抓过了
            $GLOBALS['model']->update_conditions_status(self::CONDITION_SUC,$row['rec_id']);
            save_log('当前页有一半登录时间没变过，后面的已经抓过了，当前搜索id : '.$row['rec_id']);
            return true;
        }

        //当前页数加1大于等于总页数时，就是已经抓完了
        if($row['cur_page']+1 >= $total_page){
            $GLOBALS['model']->grab_conditions_suc($total_page,$row['rec_id']);
        }else{
            $GLOBALS['model']->grab_conditions_cur_page($cur_page,$row['rec_id']);
        }
        save_log('搜索列表抓取成功，列表ID : '.$row['rec_id']);
        return true;
    }


    /*
     * 保存简历列表
     */
    public function save_resume_list($account_name){
        $exist_count = 0;
        $resume_list = pq(".result-list .table-list tr.table-list-peo");
        foreach($resume_list as $tr){
            $one = array();
            $one['user_name'] = trim(pq($tr)->find('td.td-name a strong')->text());
            $one['user_sn'] = trim(pq($tr)->find('td.text-right .checkbox')->attr('data-userid'));
            $one['sign'] = trim(pq($tr)->find('td.td-name a')->attr('data-id'));
            $one['sex'] = trim(pq($tr)->find('td')->eq(2)->text());
            $age = trim(pq($tr)->find('td')->eq(3)->text());
            $one['birth'] = date('Y') - intval($age);
            $one['agree'] = trim(pq($tr)->find('td')->eq(4)->attr('title'));
            $one['work_year'] = trim(pq($tr)->find('td')->eq(5)->text());
            $one['work_place'] = trim(pq($tr)->find('td')->eq(6)->attr('title'));
            $one['cur_position'] = htmlspecialchars(trim(pq($tr)->find('td')->eq(7)->attr('title')),ENT_QUOTES);
            $one['cur_company'] = htmlspecialchars(trim(pq($tr)->find('td')->eq(8)->attr('title')),ENT_QUOTES);
            $one['login_time'] = trim(pq($tr)->find('td')->eq(9)->text());
            $one['account'] = $account_name;

            $resume_info = $GLOBALS['model']->get_resume_by_user_sn($one['user_sn']);
            if(empty($resume_info)){
                $one['create_time'] = $one['update_time'] = date('Y-m-d H:i:s');
                $resume_id = $GLOBALS['model']->save_resume_one($one);
                save_log('从搜索列表抓取简历信息 , 当前简历ID : '.$resume_id);
            }else{
                if($resume_info['login_time'] == $one['login_time']){
                    $exist_count++;
                    save_log('从搜索列表抓取的简历登录时间没有变化 ,不做更改, 当前简历ID : '.$resume_info['resume_id']);
                }else{
                    $one['update_time'] = date('Y-m-d H:i:s');
                    $GLOBALS['model']->update_resume_one($one,$resume_info['resume_id']);
                    save_log('从搜索列表抓取的简历更新, 当前简历ID : '.$resume_info['resume_id']);
                }
            }
        }
        return $exist_count;
    }

    /*
     * 保存简历
     */
    public function save_resume($account){
        $row = $GLOBALS['model']->get_resume_info($account['account']);

        if (empty($row)) {
            save_log($account['account'].'账号没有简历可以抓取');
            return false; //返回false时去执行抓取搜索条件
        }

        if(!empty($row['crawlertime']) && strtotime($row['crawlertime'] . '7 days') > strtotime(date('Y-m-d H:i:s'))){
            //七天之内抓过的简历不抓了
            $GLOBALS['model']->update_resume_status(self::RESUME_SUC , $row['resume_id']);
            save_log('当前简历七天之内抓取过，无需再抓, 简历ID : '.$row['resume_id']);
            return true;
        }

        $detail_url = RESUME_DETAIL.'?res_id_encode='.$row['sign'];
        $html = grab_curl($account, $detail_url);

        if(!$this->deal_resume($account,$row['resume_id'],$html)) return true;

        $workexps = grab_curl($account,WORKEXPS_DETAIL ,'res_id_encode='.$row['sign']);
        if(empty($workexps)){
            $GLOBALS['model']->update_resume_status(self::RESUME_NOT_WORKEPX , $row['resume_id']);
            save_log('简历工作经验为空，当前简历ID : '.$row['resume_id']);
            return true;
        }

        $html = str_replace('<div class="resume-work"  id="workexp_anchor" >', '<div class="resume-work"  id="workexp_anchor" >' . $workexps, $html);
        $html = preg_replace('/\/\/(.*?)\.lietou-static\.com/','https://$1.lietou-static.com',$html);
        $html = preg_replace('/\/\/(.*?)\.liepin\.com/','https://$1.liepin.com',$html);

        $GLOBALS['model']->save_resume_detail($row,trim($html));
        save_log('保存简历，当前简历ID : '.$row['resume_id']);
        return true;
    }


    /**
     * 获取行业
     */
    function _industry($response)
    {
        $rs = array();
        //暂时只取互联网、软件、网络游戏
        //return array('01', '32', '40');
        if (preg_match_all('/industry:\[\[\[(.*?)\]\]\],hJob/is', $response, $mat)) {
            if (preg_match_all('/\[\'(\d+)\',\'(.*?)\',\'(.*?)\'\]/is', $mat[0][0], $mats)) {
                foreach ($mats[1] as $one) {
                    $rs[] = $one;
                }
            }
        } else {
            throw new Exception('解析猎聘行业失败！');
        }

        return $rs;
    }

    /**
     * 获取区域
     */
    function _region($response)
    {
        $city_list = array(
            '010'=>'北京',
            '020'=>'上海',
            '030'=>'天津',
            '040'=>'重庆',
            '050'=>'广东',
            '060'=>'江苏省',
            '070'=>'浙江省',
            '080'=>'安徽省',
            '090'=>'福建省',
            '100'=>'甘肃省',
            '110'=>'广西',
            '120'=>'贵州省',
            '130'=>'海南省',
            '140'=>'河北省',
            '150'=>'河南省',
            '160'=>'黑龙江省',
            '170'=>'湖北省',
            '180'=>'湖南省',
            '190'=>'吉林省',
            '200'=>'江西省',
            '210'=>'辽宁省',
            '220'=>'内蒙古',
            '230'=>'宁夏',
            '240'=>'青海省',
            '250'=>'山东省',
            '260'=>'山西省',
            '270'=>'陕西省',
            '280'=>'四川省',
            '290'=>'西藏',
            '300'=>'新疆',
            '310'=>'云南省'
        );
        $rs = array();
        //暂时只取省
        if (preg_match_all('/.*?\[\d+,\[\'(\d+)\',\'(.*?)\',\'(.*?)\'\]/is', $response, $mats)) {
            foreach ($mats[1] as $one) {
                $rs[] = $one;
            }
        } else {
            throw new Exception('解析猎聘城市失败！');
        }
        $rs = array_slice($rs, 0, 31);
        return $rs;
    }

    /**
     * 获取学位
     */
    function _degree()
    {
        $degree_init = array(
            '博士后' => '005',
            '博士' => '010',
            'MBA/EMBA' => '020',
            '硕士' => '030',
            '本科' => '040',
            '大专' => '050',
            '中专' => '060',
            '中技' => '070',
            '高中' => '080',
            '初中' => '090',
        );
        $degree = array(
            '090'=>'050',
            '040'=>'040',
            '030'=>'005'
        );
        return $degree;
    }

    /**
     * 年龄
     */
    function _age()
    {
        $age = array(20,  30 , 40 , 50 , 60);

        return $age;
    }

    private function _save_search_url ($search_url,$city='', $industry='', $degree='')
    {
        $sort = 0;
        $data = array();
        $data['industry'] = intval($industry);
        $data['city'] = intval($city);
        $data['degree'] = $degree;
        $data['search_url'] = $search_url;
        $data['create_time'] = date('Y-m-d H:i:s');

        if($data['city'] == 20) $sort += 3;
        elseif ($data['city'] == 10) $sort += 2;
        elseif (in_array($data['city'],array(50,60,70))) $sort++;

        if(in_array($data['industry'],array(10,40,420,130,140,150,430))) $sort++;

        if($data['degree'] == '040-040') $sort += 3;
        elseif($data['degree'] == '030-005') $sort += 2;
        elseif($data['degree'] == '090-050') $sort++;

        $data['sort'] = $sort;

        $GLOBALS['model']->insert_conditions($data);
    }

    public function crawler_stop(){
        $w = date("w");
        if($w == 0 || $w == 6){
			if(SATURDAY_AND_SUNDAY && date('H') >= END_TIME - 2) {
			   save_log('一天抓取结束');
			   exit;
			}
       }
        if(date('H') >= 12 && date('H') < 13){
            $time = mt_rand(3600,5400);
            save_log('中午休息，停止抓取');
            sleep($time);
        }
       if(date('H') >= END_TIME) {
           save_log('一天抓取结束');
           exit;
       }
    }

    function deal_conditions($account,$rec_id,$res){
        $res = $this->deal_account($account,$res);
        if(empty($res)){
            $GLOBALS['model']->update_conditions_status(self::CONDITION_INIT ,$rec_id);
            return false;
        }
        if(strpos($res,'没有找到符合条件的简历，建议您重新搜索') !== false){
            $GLOBALS['model']->update_conditions_status(self::CONDITION_NULL ,$rec_id);
            return false;
        }

        if(strpos($res,'当前搜索条件') === false){
            $GLOBALS['model']->update_conditions_status(self::CONDITION_NULL ,$rec_id);
            return false;
        }
        return true;
    }

    function deal_resume($account,$resume_id,$res){
        $res = $this->deal_account($account,$res);
        if(empty($res)){
            $GLOBALS['model']->update_resume_status(self::RESUME_INIT,$resume_id);
            return false;
        }

        if(strpos($res,'参数非法，请稍后再试') !== false){
            $GLOBALS['model']->update_resume_status(self::RESUME_PARAM,$resume_id);
            return false;
        }

        if(strpos($res,'个人信息') === false ){
            $GLOBALS['model']->update_resume_status(self::RESUME_BODY,$resume_id);
            return false;
        }
        return true;
    }


    function deal_account($account,$res){
        $account_id = $account['account_id'];
        if(empty($res)){
            //这个错可能不是账号的错，所以只是错误次数加1
            $GLOBALS['model']->add_account_error($account_id,$account['error_num']);
            return '';
        }

        if(strpos($res,'请输入认证的手机号') !== false && strpos($res,'请输入登录密码') !== false){
            $GLOBALS['model']->update_account_status(self::ACCOUNT_LOGIN,$account_id);
            send_email($account['account'].'需要重新登录' , '账号重新登录');
            return '';
        }

        if(strpos($res,'检测到您账号的操作行为过于频繁') !== false){
            $GLOBALS['model']->update_account_status(self::ACCOUNT_LOGIN,$account_id);
            send_email($account['account'].'需要重新登录' , '账号重新登录');
            return '';
        }

        if(strpos($res,'当前查看简历的速度过快') !== false){
            $GLOBALS['model']->update_account_status(self::ACCOUNT_LOGIN,$account_id);
            send_email($account['account'].'查看简历速度过快' , '账号需要休息');
            return '';
        }

        if(strpos($res,'账号登录异常') !== false){
            $GLOBALS['model']->update_account_status(self::ACCOUNT_PHONE,$account_id);
            send_email($account['account'].'账号登录异常，需要发送短信' , '账号需发短信');
            return '';
        }

        if(strpos($res,'id="page-error"') !== false && strpos($res,'class="resError"') !== false){
            $GLOBALS['model']->update_account_status(self::ACCOUNT_ERROR,$account_id);
            send_email($account['account'].'发生未知错误' , '账号发生未知错误');
            return '';
        }

        return $res;
    }

}