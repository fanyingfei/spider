<?php

include 'core/libraries/phpQuery.php';
include 'core/common.php';
include 'config/liepin.php';
include 'core/crawler.php';
include 'core/mysqli.php';
include 'core/model.php';

define('ROOT_PATH', str_replace('index.php', '', str_replace('\\', '/', __FILE__)));

$db = DBHelper::getIntance($database);
$model = new model($table);

save_log('数据抓取开始');
$crawler = new crawler();
$crawler->get_conditions();
$crawler->start();