<?php

class parse extends basis
{
    private $_crawler;
    private $_resume_id;

    //解析结果
    private $_parse_result = array();
    private $_parse_work = array(
        'resume_id' => 0,
        'corporation_name' => '',
        'corporation_type' => '',
        'industry_name' => '',
        'scale' => '',
        'basic_salary' => '',
        'position_name' => '',
        'start_time' => '',
        'end_time' => '',
        'reporting_to' => '',
        'subordinates_count' => 0,
        'city' => '',
        'architecture_name' => '',
        'responsibilities' => ''
    );
    private $_parse_project = array(
        'resume_id' => 0,
        'project_name' => '',
        'start_time' => '',
        'end_time' => '',
        'position_name' => '',
        'corporation_name' => '',
        'position_describe' => '',
        'responsibility' => '',
        'achivement' => ''
    );
    private $_parse_education = array(
        'resume_id' => 0,
        'school_name' => '',
        'start_time' => '',
        'end_time' => '',
        'discipline_name' => '',
        'degree' => '',
        'is_entrance' => ''
    );

    public function start(){
        $GLOBALS['model']->parse_start();
        while(true){
            save_log('开始运行前内存：'.memory_get_usage());
            $row =  $GLOBALS['model']->get_parse_info();
            if(empty($row)){
                save_log('没有可以解析的简历，结束！ ');
                exit;
            }
            save_log('开始解析数据，简历ID : '.$row['resume_id']);
            $this->lie_parser($row);
            $GLOBALS['model']->resume_parse_suc($row);
            save_log('解析数据完成，简历ID : '.$row['resume_id']);
            unset($row);
        }
    }


    public function lie_parser($row){
        $this->_crawler = '';
        $this->_parse_result = array();

        $this->_resume_id = $row['resume_id'];
        $body = $row['content'];

        if(empty($body)) return false;

        $detail = str_replace(array("&nbsp;","&amp;"), array(' ','&'), $body);
        phpQuery::newDocumentHTML($detail, 'utf-8');
        $this->_crawler = pq(".retop-wrap");
        phpQuery::$documents = '';

        $this->lie_parse_info();
        $this->lie_parse_work();
        $this->lie_parse_project();
        $this->lie_parse_education();
        $this->lie_parse_language();
        $this->lie_parse_remark();
        $this->lie_resume_others();

        if(!empty($this->_parse_result['project'])) $GLOBALS['model']->save_project($this->_resume_id,$this->_parse_result['project']);
        if(!empty($this->_parse_result['work'])) $GLOBALS['model']->save_workepx($this->_resume_id,$this->_parse_result['work']);
        if(!empty($this->_parse_result['education'])) $GLOBALS['model']->save_education($this->_resume_id,$this->_parse_result['education']);
        if(!empty($this->_parse_result['basic'])) $GLOBALS['model']->update_resume_parse($this->_resume_id,$this->_parse_result['basic']);
    }


    public function _replace($str){
        $str =  str_replace(array("&nbsp;","&#13;","<br>","</br>","<br/>"),array(" ","","\n","\n","\n"),$str);
        return preg_replace("/\s(?=\s)/","\\1",$str);
    }


    /**
     * 自我评价
     */
    private function lie_parse_remark ()
    {
        try{
            $evaluation = $this->_crawler->find('.resume-comments table')->html();
            $this->_parse_result['basic']['evaluation'] = trim(strip_tags($this->_replace($evaluation)));
        }catch(Exception $e){
            return false;
        }
    }

    /**
     * 附加信息
     */
    private function lie_resume_others ()
    {
        try{
            $other_info = $this->_crawler->find('.resume-others table')->html();
            $this->_parse_result['basic']['other_info'] =trim(strip_tags(trim($this->_replace($other_info))));

        }catch(Exception $e){
            return false;
        }
    }

    /**
     * 求职意向
     */
    private function lie_parse_info ()
    {
        try{
            $marital = $this->_crawler->find('table.resume-basic-info tr')->eq(3)->find("td")->eq(1)->text();
            $marital = str_replace('婚姻状况：','',$marital);
            $this->_parse_result['basic']['marital'] = $marital;

            $work_status = $this->_crawler->find('p.text-center')->text();
            $this->_parse_result['basic']['current_status'] = trim($this->_replace($work_status));

            if(preg_match('/<strong>目前职业概况<\/strong>.*?目前薪资：(.*?)个月.*?<table>/is',$this->_crawler->find('.resume-basic')->html(),$mat)){
                $this->_parse_result['basic']['cur_salary'] = $mat[1].'个月';
            }

            $tr = $this->_crawler->find('.resume-basic > table')->eq(0)->find('tbody')->find("tr");
            $count = count($tr);

            for($i=0;$i<$count;++$i) {
                $name = $tr->eq($i)->find('td')->text();
                if (strpos($name, '期望行业：') !== false) $this->_parse_result['basic']['expect_industry'] = trim(str_replace('期望行业：','',$name));
                elseif (strpos($name, '期望职位：') !== false) $this->_parse_result['basic']['expect_position'] = trim(str_replace('期望职位：','',$name));
                elseif (strpos($name, '期望地点：') !== false) $this->_parse_result['basic']['expect_city'] = trim(str_replace('期望地点：','',$name));
                elseif (strpos($name, '期望月薪：') !== false) $this->_parse_result['basic']['expect_salary'] = trim(str_replace('期望月薪：','',$name));
                elseif (strpos($name, '勿推荐企业：') !== false) $this->_parse_result['basic']['not_expect_corporation'] = trim(str_replace('勿推荐企业：','',$name));
            }

        }catch (Exception $e){}
    }

    /**
     * 工作经历
     */
    private function lie_parse_work()
    {
        try{
            $work_title = $this->_crawler->find('.resume-work .resume-job-title');
            $work_info = $this->_crawler->find('.resume-work .resume-indent');
            $work_title_count = count($work_title);

            for($i=0;$i<$work_title_count;++$i) {
                $this->_parse_result['work'][$i] = $this->_parse_work;
                if(preg_match('/(.*?)<span>.*?$/is',$work_title->eq($i)->find('.compony')->html(),$mat)){
                    $this->_parse_result['work'][$i]['corporation_name'] = strip_tags($mat[1]);
                }

                $com_info = $work_info->eq($i)->find("table tr")->eq(0)->find("td")->text();
                if(!empty($com_info)){
                    $com_info = explode("|",$com_info);
                    foreach($com_info as $k=>$v){
                        if (strstr($v, '·')) $this->_parse_result['work'][$i]['corporation_type'] = trim($v);
                        elseif (strstr($v, '人')) $this->_parse_result['work'][$i]['scale'] = trim($v);
                        else $this->_parse_result['work'][$i]['industry_name'] = trim($v);
                    }
                }

                $job_list_count = count($work_info->eq($i)->find("table.job-list"));
                if($job_list_count >= 1){
                    $tr = $work_info->eq($i)->find("table.job-list")->eq(0)->find("tr");

                    $res = $tr->eq(0)->find('.job-list-title')->html();

                    if(preg_match('/.*?<\/strong>.*?(\d+)元.*?$/uis',$res,$mat)) $this->_parse_result['work'][$i]['basic_salary'] = $mat[1];

                    $this->_parse_result['work'][$i]['position_name'] =trim($tr->eq(0)->find('.job-list-title strong')->text());

                    $work_time = explode('-',$work_title->eq($i)->find('span.work-time')->text());
                    $this->_parse_result['work'][$i]['start_time'] = empty($work_time[0]) ? '' : $work_time[0];
                    $this->_parse_result['work'][$i]['end_time'] = empty($work_time[1]) ? '' : $work_time[1];

                    $position_info = explode("|",trim($tr->eq(1)->find('th')->text()));

                    foreach($position_info as $key=>$val){
                        if (strstr($val, '汇报对象：')) {
                            $this->_parse_result['work'][$i]['reporting_to'] = trim(str_replace("汇报对象：","",$val));
                        }elseif(strstr($val, '下属人数：')) {
                            $this->_parse_result['work'][$i]['subordinates_count'] = trim(str_replace("下属人数：","",$val));
                        }elseif(strstr($val, '所在地区：')) {
                            $this->_parse_result['work'][$i]['city'] = trim(str_replace("所在地区：","",$val));
                        }elseif(strstr($val, '所在部门：')) {
                            $this->_parse_result['work'][$i]['architecture_name'] = trim(str_replace("所在部门：","",$val));
                        }
                    }

                    $work_detail = '';
                    $work_desc_count = count($tr);
                    for($j=2;$j<$work_desc_count;++$j) {
                        if($j > 2)  $work_detail .= "\n";
                        $name = $tr->eq($j)->find('th')->text();
                        $work_detail .= trim(strip_tags($this->_replace($tr->eq($j)->find('td')->html())));
                    }
                    $this->_parse_result['work'][$i]['responsibilities'] = trim($work_detail);
                    $this->_parse_result['work'][$i]['resume_id'] = $this->_resume_id;
                }
            }

        }catch (Exception $e) {}

    }

    /**
     * 项目经历
     */
    private function lie_parse_project ()
    {
        try{
            $projects = $this->_crawler->find('.resume-project > table');
            $count =  count($projects);
            if($count < 1) return false;

            for($i=0;$i<$count;++$i) {
                $this->_parse_result['project'][$i] = $this->_parse_project;
                $this->_parse_result['project'][$i]['project_name'] = trim($projects->eq($i)->find('tr')->eq(0)->find('td strong')->text());
                $project_time = trim($projects->eq($i)->find('tr')->eq(0)->find('td span')->text());

                $project_time = explode("-",$project_time);
                $this->_parse_result['project'][$i]['start_time'] = empty($project_time[0]) ? '' : trim($project_time[0]);
                $this->_parse_result['project'][$i]['end_time'] = empty($project_time[1]) ? '' : trim($project_time[1]);

                $tr = $projects->eq($i)->find('tr');
                $count2 =  count($tr);

                for($j=1;$j<$count2;++$j) {
                    $name = $tr->eq($j)->find('th')->text();
                    if($name == "项目职务：") $this->_parse_result['project'][$i]['position_name'] = trim($tr->eq($j)->find('td')->text());
                    if($name == "所在公司：") $this->_parse_result['project'][$i]['corporation_name'] = trim($tr->eq($j)->find('td')->text());
                    if($name == "项目简介：") $this->_parse_result['project'][$i]['position_describe'] = trim(strip_tags($this->_replace($tr->eq($j)->find('td')->html())));
                    if($name == "项目职责：") $this->_parse_result['project'][$i]['responsibility'] = trim(strip_tags($this->_replace($tr->eq($j)->find('td')->html())));
                    if($name == "项目业绩：") $this->_parse_result['project'][$i]['achivement'] = trim(strip_tags($this->_replace($tr->eq($j)->find('td')->html())));
                }

                $this->_parse_result['project'][$i]['resume_id'] = $this->_resume_id;
            }
        }catch (Exception $e) { }

    }

    /**
     * 教育经历
     * @param $node
     */
    private function  lie_parse_education ()
    {
        try{
            $education = $this->_crawler->find('.resume-education > table');
            $count = count($education);
            for($i=0;$i<$count;++$i) {
                $this->_parse_result['education'][$i] = $this->_parse_education;
                $edu_info = trim($education->eq($i)->find('tr')->eq(0)->find('td')->eq(0)->html());
                $this->_parse_result['education'][$i]['school_name'] = trim($education->eq($i)->find('tr')->eq(0)->find('td')->eq(0)->find('strong')->text());
                if(preg_match('/(.*?)<\/strong>（(.*?)）$/is',$this->_replace($edu_info),$mat)){
                    $edu_time = explode("-",$mat[2]);
                    $this->_parse_result['education'][$i]['start_time'] = empty($edu_time[0]) ? '' : trim($edu_time[0]);
                    $this->_parse_result['education'][$i]['end_time'] = empty($edu_time[1]) ? '' : trim($edu_time[1]);
                }else{
                    $this->_parse_result['education'][$i]['start_time'] = '';
                    $this->_parse_result['education'][$i]['end_time'] = '';
                }
                $discipline = trim($education->eq($i)->find('tr')->eq(1)->find('td')->eq(0)->text());
                $this->_parse_result['education'][$i]['discipline_name'] = str_replace('专业：','',$discipline);
                $degree = trim($education->eq($i)->find('tr')->eq(1)->find('td')->eq(1)->text());
                $degree = str_replace('学历：','',$degree);
                $this->_parse_result['education'][$i]['degree'] = $degree;
                $is_entrance = trim($education->eq($i)->find('tr')->eq(1)->find('td')->eq(2)->text());
                $this->_parse_result['education'][$i]['is_entrance'] = str_replace('是否统招：','',$is_entrance);
                $this->_parse_result['education'][$i]['resume_id'] = $this->_resume_id;
            }
        }catch (Exception $e) {}

    }

    /**
     * 语言能力
     */
    private function lie_parse_language ()
    {
        //英语：读写能力良好 | 听说能力熟练
        try{
            $this->_parse_result['basic']['language'] = trim($this->_crawler->find('.resume-language table td')->text());
        }catch (Exception $e) {
            return false;
        }
    }

}
