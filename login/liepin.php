<?php

header("Content-type: text/html; charset=utf-8");

include_once '../config/liepin.php';
include_once '../core/mysqli.php';

$db = DBHelper::getIntance($database);
if(empty($_POST['account'])){
    $sql = "select * from ".$table['account_table']." where status > 1";
}else{
    $sql = "select * from ".$table['account_table']." where account ='".$_POST['account']."'";
}
$account = $db->getRow($sql);
if(!empty($_POST['account']) && !empty($_POST['cookie'])){
    if(strpos($_POST['cookie'],'lt_auth=') === false){
        echo 1;exit;
    }
    $data['cookie'] = $_POST['cookie'];
    $data['status'] = 0;
    $data['error_num'] = 0;
    $data['password'] = $_POST['password'];
    if(empty($account)){
        $data['account'] = $_POST['account'];
        $res = $db->insert($table['account_table'], $data);
    }else{
        $res = $db->update($table['account_table'], $data, "account='" . $_POST['account'] ."'");
    }
    if($res) echo 0;
    else echo 2;
    exit;
}

?>
<html>
<head>
    <title>猎聘抓取登录</title>
    <link href="css/main.css" rel="stylesheet" type="text/css">
</head>
<body>
    <div class="main ui-draggable">
        <p>账号密码<span>(登录后点击保存cookie)</span></p>
        <input id="account" value="<?php echo $account['account']; ?>" />
        <input id="password" value="<?php echo $account['password']; ?>" />
        <textarea id="cookie"></textarea>
        <button onclick="save_cookie()">保存cookie</button>
    </div>
    <iframe src="https://passport.liepin.com/h/account/#sfrom=click-pc_homepage-front_navigation-hunter_new">iframe页面</iframe>
</body>
<script src="js/jquery-1.10.2.min.js"></script>
<script src="js/jquery.cookie.js"></script>
<script src="js/jquery.ui.js"></script>
<script>
    $('.main').draggable({
        scroll: false,
        appendTo: "body",
        distance:15,
        cancel: "input,textarea"
    });
    function save_cookie(){
        var account = $('#account').val();
        var password = $('#password').val();
        var cookie = $('#cookie').val();

        $.ajax({
            url:  '/',
            data:{'account':account,'password':password,'cookie':cookie},
            type: "POST",
            dataType:'json',
            success:function(res){
                if(res == 0) alert('保存成功');
                else if(res == 1) alert('cookie不正确');
                else if(res == 2) alert('保存失败，请刷新重试');
            },
            error:function(e){}
        });
    }
</script>
</html>