<?php

    function save_log($msg,$status = 'info'){
        $file = ROOT_PATH .'log/'.WEB_TYPE . '_'.strtotime(date('Y-m-d')).'.log';
        file_put_contents($file,$status.'------'.$msg."\n\r",FILE_APPEND);
    }

    function grab_curl($account,$url,$post=''){
        $header = array(
            'Connection: keep-alive',
            'Content-Type: application/x-www-form-urlencoded'
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
        curl_setopt($ch,CURLOPT_COOKIE ,$account['cookie']);
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

        if(deal_account($account,$reponse)){
            $GLOBALS['model']->update_account($account);
        }else{
            $reponse = '';
        }
        return $reponse;
    }

    function deal_account($account,$res){
        $account_id = $account['account_id'];
        if(empty($res)){
            //这个错可能不是账号的错，所以只是错误次数加1，返回真
            $GLOBALS['model']->add_account_error($account_id,$account['error_num']);
            return true;
        }

        if(strpos($res,'猎头顾问注册') !== false && strpos($res,'猎头顾问登录') !== false){
            $GLOBALS['model']->set_account_login($account_id);
            return false;
        }

        if(strpos($res,'猎聘网安全中心检测到您的账号') !== false && strpos($res,'账号登录异常') !== false){
            $GLOBALS['model']->set_account_phone($account_id);
            return false;
        }
        return true;
    }

    function deal_conditions($rec_id,$res){
        if(strpos($res,'没有找到符合条件的简历，建议您重新搜索') !== false){
            $GLOBALS['model']->grab_conditions_null($rec_id);
            return false;
        }
        return true;
    }

    function deal_resume($resume_id,$res){
        if(strpos($res,'参数非法，请稍后再试') !== false){
            $GLOBALS['model']->update_resume_not($resume_id);
            return false;
        }
        return true;
    }
