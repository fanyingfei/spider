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
$table['education_table'] = 'education';
$table['project_table'] = 'project';
$table['workexps_table'] = 'workexps';


const WEB_TYPE = 'liepin';
//程序结束时间，建议不超过20（晚上8点）
const END_TIME = 20;
//账号休息平均时间，建议不小于30
const ACCOUNT_SLEEP_TIME = 60;
//程序六日是否执行,`1执行，０不执行
const SATURDAY_AND_SUNDAY = 1;
//需要发送邮件的邮件
const EMAIL_ADDRESS = '929632454@qq.com';
//获得分类条件的js
const CONDITIONS_JS_URL = 'http://s.lietou-static.com/p/beta2/js/plugins/jquery.localdata.js';
//简历列表
const SEARCH_RESUME_LIST = 'https://h.liepin.com/cvsearch/soResume';
//简历详情页面
const RESUME_DETAIL = 'https://h.liepin.com/resume/showresumedetail';
//工作经验
const WORKEXPS_DETAIL = 'https://h.liepin.com/resume/showresumedetail/showworkexps';