<?php

include_once '../config/liepin.php';
include_once '../core/smtp.php';
include_once '../core/mysqli.php';
include_once '../core/common.php';

$db = DBHelper::getIntance($database);

$today = strtotime(date('Y-m-d'));
$tomorrow = strtotime(date('Y-m-d').' 1 day');

$sql = "select count(resume_id) from ".$table['resume_table']." where status =2 and crawler_time >= $today and crawler_time < $tomorrow";
$crawler_num = $db->getOne($sql);

$sql = "select count(resume_id) from ".$table['resume_table']." where parse_status =2 and parse_time >= $today and parse_time < $tomorrow";
$parse_num = $db->getOne($sql);

$msg = date('Y-m-d').'成功抓取简历：'.$crawler_num.' , 解析简历：'.$parse_num;

send_email($msg,date('Y-m-d').'统计邮件');