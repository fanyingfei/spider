<?php

class crawler
{

    public function __construct(){

    }

    public function start(){
        $GLOBALS['model']->init_start();
        $this->get_resume_list();
    }

    /*
     * 细分搜索条件;
     * 按照行业进行细分
     * 细分的条件是：城市，性别，年龄
     */
    public function get_conditions()
    {
        $conditions_num = $GLOBALS['model']->get_conditions_num();
        //已经跑过搜索条件
        if($conditions_num > 100){
            echo "search conditions already run \n\r";
            save_log('搜索条件已经生成，无需重复生成');
            return true;
        }

        $input_url = CONDITIONS_JS_URL;
        $response = str_replace('"', "'", file_get_contents($input_url));
        $industries = $this->_industry($response); //行业　010','040','420'互联网
        $cities = $this->_region($response); //城市
        $ages = $this->_age(); //年龄
        $degrees = $this->_degree(); //学历
        $sex = array(0, 1); //性别


        //生成条件，插入数据库
        foreach ($industries as $indus) {
            foreach ($cities as $city) {
                //互联网时北京，上海，广州，江苏，浙江
                if(in_array($city,array('010','020','050','060','070'))){
                    foreach ($degrees as $degree) {
                        foreach ($sex as $se) {
                            $str = 'cs_id=&csc_id=&form_submit=1&sortflag=12&expendflag=1&pageSize=50&curPage=%%s&userHope=&resReward=&res_ids=&so_flag=&keys=&titleKeys=&company=&company_type=0&industrys=%s&jobtitles=&dqs=%s&workyearslow=&workyearshigh=&edulevellow=%s&edulevelhigh=%s&agelow=&agehigh=&sex=%s';
                            $generator_url = sprintf($str, $indus, $city, $degree, $degree, $se);
                            $this->_save_search_url($generator_url);
                        }
                    }
                }else{
                    $str = 'cs_id=&csc_id=&form_submit=1&sortflag=12&expendflag=1&pageSize=50&curPage=%%s&userHope=&resReward=&res_ids=&so_flag=&keys=&titleKeys=&company=&company_type=0&industrys=%s&jobtitles=&dqs=%s&workyearslow=&workyearshigh=&edulevellow=&edulevelhigh=&agelow=&agehigh=&sex=';
                    $generator_url = sprintf($str, $indus, $city);
                    $this->_save_search_url($generator_url);
                }

            }
        }
    }

    /*
    * 抓取简历列表
    */
    public function get_resume_list(){
        while(true){
            $this->crawler_stop();

            $row = $GLOBALS['model']->get_search_info();
            if(empty($row)){
                echo "resume list already crawler over , start crawler resume\n\r";
                save_log('搜索条件生成的简历列表抓取完毕，开始抓取简历');
                $this->save_resume();
                break;
            }

			//当前页面大于等于总页数时，已经是抓完了
            if($row['cur_page'] >= $row['total_page']) continue;

            $cur_page = $row['total_page'] > 0 ? $row['cur_page'] : 0 ;

            $search_url = sprintf($row['search_url'],$cur_page);
            echo $row['rec_id']."---crawler resume list , current page : $cur_page \n\r";
            save_log('抓取搜索列表，当前列表ID : '.$row['rec_id'].' , 当前页数 : '.$cur_page);


            $url = SEARCH_RESUME_LIST .'?'. $search_url;
            //拿到账号抓取，把账号置为不可用
            $account = $GLOBALS['model']->get_account();
            $res = grab_curl($account,$url);
            if(empty($res)){
                $GLOBALS['model']->update_conditions_init($row['rec_id']);
                continue;
            }

            if(!deal_conditions($row['rec_id'],$res)){
                continue;
            }

            phpQuery::newDocumentHTML($res);

            $total_page = $row['total_page'];
            $num_str = pq('.pagerbar .addition')->text();
            if(preg_match('/共(\d+)页.*?/uis',$num_str,$match)){
                $total_page = $match[1];
                $GLOBALS['model']->update_conditions_page($total_page,$row['rec_id']);
            }else{
                save_log('搜索列表抓取失败，列表ID : '.$row['rec_id'] , 'error');
                $GLOBALS['model']->grab_conditions_fail($row['rec_id']);
                continue;
            }

            try{
                $this->save_resume_list($account['account']);
            }catch(Exception $e){

            }

			//当前页数加1大于等于总页数时，就是已经抓完了
            if($row['cur_page']+1 >= $total_page){
                $GLOBALS['model']->grab_conditions_suc($total_page,$row['rec_id']);
            }else{
                $GLOBALS['model']->grab_conditions_curr($cur_page,$row['rec_id']);
            }
            save_log('搜索列表抓取成功，列表ID : '.$row['rec_id']);
        }
    }


    /*
     * 保存简历列表
     */
    public function save_resume_list($account_name){
        $resume_list = pq(".result-list .table-list tr.table-list-peo");
        foreach($resume_list as $tr){
            $one = array();
            $one['username'] = trim(pq($tr)->find('td.td-name a strong')->text());
            $one['sign'] = trim(pq($tr)->find('td.td-name a')->attr('data-id'));
            $one['url'] = RESUME_DETAIL.'?res_id_encode='.$one['sign'];
            $one['sex'] = trim(pq($tr)->find('td')->eq(2)->text());
            $one['age'] = trim(pq($tr)->find('td')->eq(3)->text());
            $one['agree'] = trim(pq($tr)->find('td')->eq(4)->text());
            $one['workyear'] = trim(pq($tr)->find('td')->eq(5)->text());
            $one['workplace'] = trim(pq($tr)->find('td')->eq(6)->text());
            $one['curjob'] = htmlspecialchars(trim(pq($tr)->find('td')->eq(7)->text()));
            $one['company'] = htmlspecialchars(trim(pq($tr)->find('td')->eq(8)->text()));
            $one['logintime'] = trim(pq($tr)->find('td')->eq(9)->text());
            $one['createtime'] = date('Y-m-d H:i:s');
            $one['account'] = $account_name;

            if($one['agree'] == '博士…') $one['agree'] = '博士后';
            if($one['agree'] == 'MBA/E…') $one['agree'] = 'MBA/EMBA';

            echo "crawler sign : ".$one['sign']." \n\r";

            $resume_info = $GLOBALS['model']->get_resume_by_sign($one['sign']);
            if(empty($resume_info)){
                $resume_id = $GLOBALS['model']->save_resume_one($one);
                save_log('从搜索列表抓取简历信息 , 当前简历ID : '.$resume_id);
            }else{
                if($resume_info['logintime'] == $one['logintime'] && $resume_info['url'] == $one['url']){
                    save_log('从搜索列表抓取的简历登录时间没有变化 ,不做更改, 当前简历ID : '.$resume_info['resume_id']);
                    continue;
                }else{
                    $GLOBALS['model']->update_resume_one($one,$resume_info['resume_id']);
                    save_log('从搜索列表抓取的简历更新, 当前简历ID : '.$resume_info['resume_id']);
                }
            }
        }
    }

    /*
     * 保存简历
     */
    public function save_resume(){
        while(true) {
            $this->crawler_stop();

            $account = $GLOBALS['model']->get_account();

            $row = $GLOBALS['model']->get_resume_info($account['account']);

            if (empty($row)) {
                save_log('这个猎头账号没有简历可以抓取');
                $GLOBALS['model']->update_account($account);
                continue;
            }

            if(!empty($row['crawlertime']) && strtotime($row['crawlertime'] . '7 days') > strtotime(date('Y-m-d H:i:s'))){
                //七天之内抓过的简历不抓了
                echo 'this resume already crawler';
                $GLOBALS['model']->update_resume_suc($row['resume_id']);
                save_log('当前简历七天之内抓取过，无需再抓, 简历ID : '.$row['resume_id']);
            }

            $dir = 'down/' . date('Y') . '/' . date('m') . '/' . date('d') . '/' . date('H') . '/';
            if (!empty($row['path'])) {
                $path = ROOT_PATH . $row['path'];
            } else {
                if (!file_exists($dir)) mkdir($dir, 0777, true);
                $path = ROOT_PATH . $dir . $row['resume_id'] . '.html';
            }

            $html = grab_curl($account, $row['url']);

            if(empty($html)){
                $GLOBALS['model']->update_resume_init($row['resume_id']);
                continue;
            }

            echo "get this resume html";
            save_log('得到简历详情，当前简历ID : '.$row['resume_id']);

            $workexps = grab_curl($account,WORKEXPS_DETAIL ,'res_id_encode='.$row['sign']);
            if(empty($workexps)){
                $GLOBALS['model']->update_resume_init($row['resume_id']);
                continue;
            }

            echo "get this resume workexps";
            save_log('得到简历工作经验，当前简历ID : '.$row['resume_id']);

            $html = str_replace('<div class="resume-work"  id="workexp_anchor" >', '<div class="resume-work"  id="workexp_anchor" >' . $workexps, $html);
            $html = preg_replace('/\/\/(.*?)\.lietou-static\.com/','https://$1.lietou-static.com',$html);
            $html = preg_replace('/\/\/(.*?)\.liepin\.com/','https://$1.liepin.com',$html);
            $html = str_replace('<div class="resume-basic">','<div class="resume-basic"><a target="_blank" href="'.$row['url'].'">来源链接</a>',$html);


            if(!deal_resume($row['resume_id'],$html)){
                continue;
            }

            $strlen = file_put_contents($path, trim($html));//返回定写入字节数
            echo 'resumt save html,bytes length : '.$strlen;

            $file_path = str_replace(ROOT_PATH , '' , $path);
            if ($strlen < 5000){
                $GLOBALS['model']->grab_resume_fail($row['resume_id'],$file_path);
                save_log('保存简历失败，当前简历ID : '.$row['resume_id'],'error');
            }else{
                $GLOBALS['model']->grab_resume_suc($row['resume_id'],$file_path);
                save_log('保存简历，当前简历ID : '.$row['resume_id']);
            }
        }
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
        $degree = array(
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

    private function _save_search_url ($search_url)
    {
        $data = array();
        $data['search_url'] = $search_url;
        $data['create_time'] = date('Y-m-d H:i:s');

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

}