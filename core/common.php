<?php

    function save_log($msg,$status = 'info'){
        $dir = ROOT_PATH .'logs/'.date('Y').'/'.date('m');
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        $file = $dir.'/'.WEB_TYPE . '-'.date('Y-m-d').'.log';
        file_put_contents($file,'['.$status.']------'.date('H:i:s').'------'.$msg."\n",FILE_APPEND);
    }

    function save_mysql_log($msg,$status = 'info'){
        $dir = ROOT_PATH .'logs/'.date('Y').'/'.date('m');
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        $file = $dir.'/'.WEB_TYPE . '-'.date('Y-m-d').'-sql.log';
        file_put_contents($file,'['.$status.']------'.date('H:i:s').'------'.$msg."\n",FILE_APPEND);
    }

    function grab_curl($account,$url,$post='',$referer=''){
        $header = array(
            'Connection: keep-alive',
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Encoding:gzip'
        );

        if(!empty($referer)){
            array_push($header,'Referer:'.$referer);
            array_push($header,'X-Alt-Referer:'.$referer);
            array_push($header,'X-Requested-With:XMLHttpRequest');
        }

        $referer = empty($referer) ? 'https://h.liepin.com' : $referer;
        array_push($header,'Referer: '.$referer);
        $ch = curl_init();//初始化curl模块
        curl_setopt($ch, CURLOPT_URL, $url);//登录提交的地址
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_REFERER, $referer);    //来路模拟
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
        $smtpusermail = WEB_TYPE."账号登录问题";//SMTP服务器的用户邮箱
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


function post_fsockopen($url = '', $post = array() , $method = 'POST', $header = null, $cookie = '' ){
    if (empty($url)) return '';
    $timeout = 10;
    $url = parse_url($url);
    $method = strtoupper(trim($method));
    $method = empty($method) ? 'GET' : $method;
    $scheme = strtolower($url['scheme']);
    $host = $url['host'];
    $path = $url['path'];
    empty($path) and ($path = '/');
    $query = empty($url['query']) ? '' :  $url['query'];
    $port = isset($url['port']) ? (int)$url['port'] : ('https' == $scheme ? 443 : 80);
    $protocol = 'https' == $scheme ? 'ssl://' : '';

    if (!$res = fsockopen($protocol.$host, (int)$port, $errno, $errstr, (int)$timeout)) {
        return 'error';
    } else {
        $crlf = "\r\n";
        $commonHeader = $method == 'PROXY' ? array() : array(
            'Host' => $host,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36',
            'Content-Type' => 'POST' == $method ? 'application/x-www-form-urlencoded' : 'text/html; charsert=UTF-8',
            'Connection' => 'Close'
        );
        if(!empty($cookie)) $commonHeader['Cookie'] = $cookie;
        is_array($header) and ($commonHeader = array_merge($commonHeader, $header));

        foreach ($commonHeader as $key => & $val) {
            $val = str_replace(array("\n", "\r", ':'), '', $val);
            $key = str_replace(array("\n", "\r", ':'), '', $key);
            $val = "{$key}: {$val}{$crlf}";
        }

        if ($method == 'PROXY') {
            $post = trim(str_replace(array("\n", "\r"), '', $post)).$crlf;

            if (empty($post)) return array('error' => '使用代理时,必须指定代理请求方法($post参数)');
        } else if (!is_array($post)) {
            $post = array();
        }

        switch ($method) {
            case 'POST':
                $post_msg = '';
                foreach($post as $k => $v) {
                    $post_msg .= $k.'='.$v.'&';
                }
                $post = trim($post_msg,'&');
                $query = empty($query) ? '' : '?'.$query;
                $commonHeader[] = 'Content-Length: '.strlen($post).$crlf;
                $post = empty($post) ? '' : $crlf.$post.$crlf;
                $commonHeader = implode('', $commonHeader);
                $request = "{$method} {$path}{$query} HTTP/1.0{$crlf}"
                    ."{$commonHeader}"
                    .$post
                    .$crlf;//表示提交结束了
                break;
            case 'PROXY'://代理
                $commonHeader = implode('', $commonHeader);
                $request =  $post
                    .$commonHeader
                    .$crlf;//表示提交结束了
                break;
            case 'GET':
            default:
                empty($query) ? ($query = array()) : parse_str($query, $query);
                $query = array_merge($query, $post);
                $query = http_build_query($query);
                $commonHeader = implode('', $commonHeader);
                $query = empty($query) ? '' : '?'.$query;
                $request =  "{$method} {$path}{$query} HTTP/1.1{$crlf}"
                    ."{$commonHeader}"
                    .$crlf;//表示提交结束了
        }

        fwrite($res, $request);
        $reponse = '';

        while (!feof($res)) {
            $reponse .= fgets($res, 128);
        }

        fclose($res);
        $pos = strpos($reponse, $crlf . $crlf);//查找第一个分隔
        if($pos === false) return $reponse;
        $header = substr($reponse, 0, $pos);
        $body = substr($reponse, $pos + 2 * strlen($crlf));
        return $body;
    }
}