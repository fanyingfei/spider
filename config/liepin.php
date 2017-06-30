<?php

$database['host'] = 'localhost';
$database['port'] = '3306';
$database['user'] = 'root';
$database['pass'] = 'root';
$database['charset'] = 'utf8';
$database['dbname'] = 'spider';

$table['resume_table'] = 'resume';
$table['account_table'] = 'account';
$table['condition_table'] = 'conditions';



const WEB_TYPE = 'liepin';
//账号休息平均时间
const ACCOUNT_SLEEP_TIME = 50; //不能小于30
//获得分类条件的js
const CONDITIONS_JS_URL = 'http://s.lietou-static.com/p/beta2/js/plugins/jquery.localdata.js';
//简历列表
const SEARCH_RESUME_LIST = 'https://h.liepin.com/cvsearch/soResume';
//简历详情页面
const RESUME_DETAIL = 'https://h.liepin.com/resume/showresumedetail';
//工作经验
const WORKEXPS_DETAIL = 'https://h.liepin.com/resume/showresumedetail/showworkexps';