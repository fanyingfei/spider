<?php

include_once 'libraries/phpQuery.php';
include_once 'core/basis.php';
include_once 'core/common.php';
include_once 'config/liepin.php';
include_once 'liepin/crawler.php';
include_once 'core/mysqli.php';
include_once 'core/model.php';
include_once('core/smtp.php');

//sleep(rand(0,1200));

define('ROOT_PATH', str_replace('index.php', '', str_replace('\\', '/', __FILE__)));

$db = DBHelper::getIntance($database);
$model = new model($table);

save_log('数据抓取开始');
$crawler = new crawler();
$crawler->start();
