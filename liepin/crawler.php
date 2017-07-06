<?php

class crawler extends basis
{
    private $config_resume_status = 0;
    private $config_condition_status = 0;

    public function __construct(){
        parent::__construct();
        $config_list = $GLOBALS['model']->get_config();
        foreach($config_list as $one){
            if($one['code'] == CONDDTION_STATUS){
                $this->config_condition_status = $one['status'];
            }elseif($one['code'] == RESUME_STATSU){
                $this->config_resume_status = $one['status'];
            }
        }
    }

    public function start(){
        if(!$this->config_condition_status){
            $this->get_conditions();
        }else{
            echo "search conditions already run \n\r";
            save_log('搜索条件已经生成，无需重复生成');
        }

        $GLOBALS['model']->init_start();

        while(true){
            $this->crawler_stop();

            $account = $GLOBALS['model']->get_account();

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
                foreach ($degrees as $degree) {
                    foreach ($sex as $se) {
                        $str = 'cs_id=&csc_id=&form_submit=1&sortflag=12&expendflag=1&pageSize=50&curPage=%%s&userHope=&resReward=&res_ids=&so_flag=&keys=&titleKeys=&company=&company_type=0&industrys=&jobtitles=&dqs=%s&workyearslow=&workyearshigh=&edulevellow=%s&edulevelhigh=%s&agelow=&agehigh=&sex=%s';
                        $generator_url = sprintf($str, $city, $degree, $degree, $se);
                        $this->_save_search_url($generator_url , $city , '' , $degree);
                    }
                }
            }else{
                foreach ($industries as $indus) {
                    foreach ($degrees as $degree) {
                        foreach ($sex as $se) {
                            $str = 'cs_id=&csc_id=&form_submit=1&sortflag=12&expendflag=1&pageSize=50&curPage=%%s&userHope=&resReward=&res_ids=&so_flag=&keys=&titleKeys=&company=&company_type=0&industrys=%s&jobtitles=&dqs=%s&workyearslow=&workyearshigh=&edulevellow=%s&edulevelhigh=%s&agelow=&agehigh=&sex=%s';
                            $generator_url = sprintf($str, $indus, $city, $degree, $degree, $se);
                            $this->_save_search_url($generator_url , $city , $indus , $degree);
                        }
                    }
                }
            }
        }
        $GLOBALS['model']->set_config_condition();
    }

    /*
    * 抓取简历列表
    */
    public function get_resume_list($account){
        $row = $GLOBALS['model']->get_search_info();
        if(empty($row)){
            echo "resume list already crawler over , start crawler resume\n\r";
            save_log('搜索条件生成的简历列表抓取完毕');
            return false;
        }

        //当前页面大于等于总页数时，已经是抓完了
        if($row['total_page'] > 0 && $row['cur_page'] >= $row['total_page']){
            $GLOBALS['model']->update_conditions_suc($row['rec_id']);
            return true;
        }

        $cur_page = $row['total_page'] > 0 ? $row['cur_page'] : 0 ;

        $search_url = sprintf($row['search_url'],$cur_page);
        echo $row['rec_id']."---crawler resume list , current page : $cur_page \n\r";
        save_log('抓取搜索列表，当前列表ID : '.$row['rec_id'].' , 当前页数 : '.$cur_page);


        $url = SEARCH_RESUME_LIST .'?'. $search_url;
        $res = grab_curl($account,$url);
        if(empty($res)){
            $GLOBALS['model']->grab_conditions_fail($row['rec_id']);
            return true;
        }

        if(!$this->deal_conditions($row['rec_id'],$res)){
            return true;
        }

        phpQuery::newDocumentHTML($res);

        $total_page = $row['total_page'];
        $num_str = pq('.pagerbar .addition')->text();
        if(preg_match('/共(\d+)页.*?/uis',$num_str,$match)){
            $total_page = $match[1];
            if($total_page > $row['total_page']) $GLOBALS['model']->update_conditions_page($total_page,$row['rec_id']);
        }else{
            save_log('搜索列表抓取失败，列表ID : '.$row['rec_id'] , 'error');
            $GLOBALS['model']->grab_conditions_fail($row['rec_id']);
            return true;
        }

        try{
            //返回解析此页登录时间不变简历的数量
            $exist_count = $this->save_resume_list($account['account']);
        }catch(Exception $e){
            $GLOBALS['model']->grab_conditions_fail($row['rec_id']);
        }
        if($this->config_resume_status && $exist_count > 25){
            //简历拆取完，当前页有一半登录时间没变过，说明后面的已经抓过了
            $GLOBALS['model']->update_conditions_suc($row['rec_id']);
            return true;
        }

        //当前页数加1大于等于总页数时，就是已经抓完了
        if($row['cur_page']+1 >= $total_page){
            $GLOBALS['model']->grab_conditions_suc($total_page,$row['rec_id']);
        }else{
            $GLOBALS['model']->grab_conditions_curr($cur_page,$row['rec_id']);
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
            $one['sign'] = trim(pq($tr)->find('td.td-name a')->attr('data-id'));
            $one['url'] = RESUME_DETAIL.'?res_id_encode='.$one['sign'];
            $one['sex'] = trim(pq($tr)->find('td')->eq(2)->text());
            $age = trim(pq($tr)->find('td')->eq(3)->text());
            $one['birth'] = date('Y') - intval($age);
            $one['agree'] = trim(pq($tr)->find('td')->eq(4)->attr('title'));
            $one['work_year'] = trim(pq($tr)->find('td')->eq(5)->text());
            $one['work_place'] = trim(pq($tr)->find('td')->eq(6)->text());
            $one['cur_position'] = htmlspecialchars(trim(pq($tr)->find('td')->eq(7)->attr('title')));
            $one['cur_company'] = htmlspecialchars(trim(pq($tr)->find('td')->eq(8)->attr('title')));
            $one['login_time'] = trim(pq($tr)->find('td')->eq(9)->text());
            $one['account'] = $account_name;

            echo "crawler sign : ".$one['sign']." \n\r";

            $resume_info = $GLOBALS['model']->get_resume_by_sign($one['sign']);
            if(empty($resume_info)){
                $one['create_time'] = $one['updatetime'] = date('Y-m-d H:i:s');
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
            save_log('这个猎头账号没有简历可以抓取');
            return false; //返回false时去执行抓取搜索条件
        }

        if(!empty($row['crawlertime']) && strtotime($row['crawlertime'] . '7 days') > strtotime(date('Y-m-d H:i:s'))){
            //七天之内抓过的简历不抓了
            $GLOBALS['model']->update_resume_suc($row['resume_id']);
            echo 'this resume already crawler';
            save_log('当前简历七天之内抓取过，无需再抓, 简历ID : '.$row['resume_id']);
            return true;
        }

        $html = grab_curl($account, $row['url']);

        if(empty($html)){
            $GLOBALS['model']->update_resume_fail($row['resume_id']);
            return true;
        }

        echo "get this resume html";
        save_log('得到简历详情，当前简历ID : '.$row['resume_id']);

        $workexps = grab_curl($account,WORKEXPS_DETAIL ,'res_id_encode='.$row['sign']);
        if(empty($workexps)){
            $GLOBALS['model']->update_resume_fail($row['resume_id']);
            return true;
        }

        echo "get this resume workexps";
        save_log('得到简历工作经验，当前简历ID : '.$row['resume_id']);

        $html = str_replace('<div class="resume-work"  id="workexp_anchor" >', '<div class="resume-work"  id="workexp_anchor" >' . $workexps, $html);
        $html = preg_replace('/\/\/(.*?)\.lietou-static\.com/','https://$1.lietou-static.com',$html);
        $html = preg_replace('/\/\/(.*?)\.liepin\.com/','https://$1.liepin.com',$html);

        $dir = 'down/' . date('Y') . '/' . date('m') . '/' . date('d') . '/' . date('H') . '/';
        if (!empty($row['path'])) {
            $path = ROOT_PATH . $row['path'];
        } else {
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $path = ROOT_PATH . $dir . $row['resume_id'] . '.html';
        }

        if(!$this->deal_resume($row['resume_id'],$html)) return true;

        $strlen = file_put_contents($path, trim($html));//返回定写入字节数
        echo 'resumt save html,bytes length : '.$strlen;

        $file_path = str_replace(ROOT_PATH , '' , $path);

        if ($strlen < 1000){
            $GLOBALS['model']->grab_resume_fail($row['resume_id'],$file_path);
            save_log('保存简历失败，当前简历ID : '.$row['resume_id'],'error');
        }else{
            $GLOBALS['model']->grab_resume_suc($row['resume_id'],$file_path);
            save_log('保存简历，当前简历ID : '.$row['resume_id']);
        }
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
        $data['degree'] = intval($degree);
        $data['search_url'] = $search_url;
        $data['create_time'] = date('Y-m-d H:i:s');

        if($data['city'] == 20) $sort += 3;
        elseif ($data['city'] == 10) $sort += 2;
        elseif (in_array($data['city'],array(50,60,70))) $sort++;

        if(in_array($data['industry'],array(10,40,420,130,140,150,430))) $sort++;

        if($data['degree'] == 40) $sort += 3;
        elseif($data['degree'] < 40) $sort += 2;
        elseif($data['degree'] > 40) $sort++;

        $data['sort'] = $sort;

        $GLOBALS['model']->insert_conditions($data);
    }

    public function crawler_stop(){
        if(date('H') >= 12 && date('H') < 13){
            $time = mt_rand(3600,5400);
            echo "noon sleep\n\r";
            save_log('中午休息，停止抓取');
            sleep($time);
        }
    //    if(date('H') > 19) exit;
    }

    function deal_conditions($rec_id,$res){
        if(strpos($res,'没有找到符合条件的简历，建议您重新搜索') !== false){
            $GLOBALS['model']->grab_conditions_null($rec_id);
            return false;
        }
        return true;
    }

    function deal_resume($resume_id,$res){
        if(strpos($res,'参数非法，请稍后再试') !== false){
            $GLOBALS['model']->update_resume_param($resume_id);
            return false;
        }

        if(strpos($res,'找简历_猎聘猎头网') === false && strpos($res,'个人信息') === false ){
            $GLOBALS['model']->update_resume_body($resume_id);
            return false;
        }
        return true;
    }

}