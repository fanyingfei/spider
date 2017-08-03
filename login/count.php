<?php

header("Content-type: text/html; charset=utf-8");

include_once '../config/liepin.php';
include_once '../core/mysqli.php';

$db = DBHelper::getIntance($database);


$start_time = empty($_GET['t']) ? date('Y-m-d') : $_GET['t'];
$end_time = date('Y-m-d',strtotime($start_time .'+1 day'));


$sql = "select count(*) from ".$table['resume_table']." where crawler_time > 0 and crawler_time <=".strtotime($end_time);
$total_resume =  $db->getOne($sql);

$sql = "select count(*) from ".$table['resume_table']." where parse_time > 0 and crawler_time <=".strtotime($end_time);
$total_parse =  $db->getOne($sql);

$sql = "select count(*) from ".$table['resume_table']." where crawler_time >= ".strtotime($start_time)." and crawler_time < ".strtotime($end_time)." and create_time = update_time";
$today_create =  $db->getOne($sql);

$sql = "select count(*) from ".$table['resume_table']." where crawler_time >= ".strtotime($start_time)." and crawler_time < ".strtotime($end_time)." and create_time != update_time";
$today_update =  $db->getOne($sql);

$sql = "select count(*) from ".$table['resume_table']." where parse_time >= ".strtotime($start_time)." and parse_time < ".strtotime($end_time);
$today_parse =  $db->getOne($sql);

?>

<html>
<head>
    <title>猎聘抓取统计</title>
    <link rel="stylesheet" href="https://cdn.bootcss.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body{padding:5% 20%;font-family: "Century Gothic", "Microsoft yahei"}
        table,th,h3{text-align: center}
    </style>
    <script src="https://cdn.bootcss.com/jquery/2.1.1/jquery.min.js"></script>
    <script src="https://cdn.bootcss.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</head>
<body>
<h3><?php echo $start_time; ?>抓取情况</h3><br>
<table class="table table-bordered">
    <thead>
    <tr>
        <th>新增简历</th>
        <th>更新简历</th>
        <th>抓取简历总数</th>
        <th>解析简历</th>
        <th>解析简历总数</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td><?php echo $today_create; ?></td>
        <td><?php echo $today_update; ?></td>
        <td><?php echo $total_resume; ?></td>
        <td><?php echo $today_parse; ?></td>
        <td><?php echo $total_parse; ?></td>
    </tr>
    </tbody>
</table>
</body>
</html>
