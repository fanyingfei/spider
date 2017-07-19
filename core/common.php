<?php

    function save_log($msg,$status = 'info'){
        $dir = ROOT_PATH .'logs/'.date('Y').'/'.date('m');
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        $file = $dir.'/'.WEB_TYPE . '-'.date('Y-m-d').'.log';
        file_put_contents($file,'['.$status.']------'.date('H:i:s').'------'.$msg."\n",FILE_APPEND);
    }

    function grab_curl($account,$url,$post=''){
        $header = array(
            'Connection: keep-alive',
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Encoding:gzip'
        );
        $ch = curl_init();//初始化curl模块
        curl_setopt($ch, CURLOPT_URL, $url);//登录提交的地址
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_REFERER, 'https://h.liepin.com');    //来路模拟
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);           //返回数据不直接输出
        if(!empty($post)){
            curl_setopt($ch, CURLOPT_POST, 1);                                 //post方式提交
            curl_setopt($ch, CURLOPT_POSTFIELDS,$post);               //要提交的信息
        }
        curl_setopt($ch, CURLOPT_COOKIE ,$account['cookie']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_TIMEOUT,15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        try{
            $reponse = curl_exec($ch);//执行cURL
        }catch(Exception $e){
            $reponse = '';
        }
        curl_close($ch);//关闭cURL资源，并且释放系统资源
        return $reponse;
    }


    function send_email($msg='',$title=''){
        $smtpserver = "smtp.163.com";//SMTP服务器
        $smtpserverport =25;//SMTP服务器端口
        $smtpusermail = "15821911446@163.com";//SMTP服务器的用户邮箱
        $smtpemailto = EMAIL_ADDRESS;//发送给谁
        $smtpuser = "15821911446";//SMTP服务器的用户帐号
        $smtppass = "qweasd12345";//SMTP服务器的用户密码
        $mailtitle = $title;//邮件主题
        $mailcontent = $msg;//邮件内容
        $mailtype = "HTML";//邮件格式（HTML/TXT）,TXT为文本邮件
        //************************ 配置信息 ****************************
        $smtp = new smtp($smtpserver,$smtpserverport,true,$smtpuser,$smtppass);//这里面的一个true是表示使用身份验证,否则不使用身份验证.
        $smtp->debug = false;//是否显示发送的调试信息
        $smtp->sendmail($smtpemailto, $smtpusermail, $mailtitle, $mailcontent, $mailtype);
    }

    function get_db_config(){
        return $GLOBALS['database'];
    }