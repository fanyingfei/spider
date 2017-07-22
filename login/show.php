<?php

include_once '../config/liepin.php';
include_once '../core/mysqli.php';

$db = DBHelper::getIntance($database);

$resume_id = $_REQUEST['id'];
if(empty($resume_id)){
    echo '请输入简历id';
    exit;
}

$sql = "select content from ".$table['resume_table']." where resume_id = $resume_id";
$content = $db->getOne($sql);
if(empty($content)){
    echo '没有内容';
    exit;
}

echo base64_decode($content);

?>