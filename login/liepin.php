<?php

header("Content-type: text/html; charset=utf-8");

include_once '../config/liepin.php';
include_once '../core/mysqli.php';

$db = DBHelper::getIntance($database);
if(empty($_POST['account'])){
    $sql = "select * from ".$table['account_table']." where status > 1 and error_num < 50";
}else{
    $sql = "select * from ".$table['account_table']." where account ='".$_POST['account']."'";
}
$account = $db->getRow($sql);

if(!empty($_POST['account'])){
    if(strpos($_POST['cookie'],'lt_auth=') === false && empty($_POST['is_delete'])){
        echo 1;exit;
    }
    $data['cookie'] = $_POST['cookie'];
    $data['status'] = 0;
    $data['error_num'] = 0;
    $data['password'] = $_POST['password'];
    if(!empty($_POST['is_delete'])) $data['is_delete'] = 1;
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
        <p class="main-title">账号密码<span>(登录后点击保存cookie)</span></p>
        <div class="main-body">
            <input type="text" id="account" value="<?php echo $account['account']; ?>" />
            <input type="text" id="password" value="<?php echo $account['password']; ?>" />
            <textarea id="cookie"></textarea>
            <p class="used"><span>是否删除</span><input <?php if($account['error_num'] >= 99) echo "checked" ?> value="1" name="is_delete" type="radio"><span>是</span><input <?php if($account['error_num'] < 99) echo "checked" ?> value="0" name="is_delete" type="radio"><span>否</span></p>
            <button onclick="save_cookie()">保存cookie</button>
        </div>
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
        cancel: ".main-body,input"
    });
    function save_cookie(){
        var account = $('#account').val();
        var password = $('#password').val();
        var cookie = $('#cookie').val();
        var is_delete = $("input[type='radio']:checked").val();

        $.ajax({
            url:  '/',
            data:{'account':account,'password':password,'cookie':cookie,'is_delete':is_delete},
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