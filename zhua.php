<?php
include 'phpQuery/phpQuery.php';
include 'core/mysqli.php';
include 'core/conditions.php';
$db=ConnectMysqli::getIntance();

$conditions = new conditions();
$sql = "select * from conditions";
$con_res = $GLOBALS['db']->getRow($sql);
if(empty($con_res)){
    $conditions->generator_conditions();
}

update_condition();
//search_page();
//save_resume();

function search_page(){
    $account = get_account();
    $sql = "select * from conditions";
    $row = $GLOBALS['db']->getRow($sql);
    $search_url = sprintf($row['search_url'], $row['cur_page']);
    $res = post_fsockopen($account,'https://h.liepin.com/cvsearch/soResume/?'.$row['search_url']);
    phpQuery::newDocumentHTML($res);
    resume_list();
}

function update_condition(){
    while(true){
        if(date('H') == 12){
            sleep(3000);
        }
        if(date('H') == 18){
            break;
        }
        $account = get_account();
        $sql = "select * from conditions where total_page = 0";
        $row = $GLOBALS['db']->getRow($sql);
        $search_url = sprintf($row['search_url'], $row['cur_page']);
        $res = post_fsockopen($account,'https://h.liepin.com/cvsearch/soResume/?'.$row['search_url']);
        phpQuery::newDocumentHTML($res);

        $num_str = pq('.pagerbar .addition')->text();
        $total_num = 0;
        if(preg_match('/共(\d+)页.*?/uis',$num_str,$match)){
            $total_num = $match[1];
        }
        if($total_num == 0){
            echo 'error:'.$row['rec_id'];
            $GLOBALS['db']->update("conditions",array('total_page'=>-1),"rec_id=".$row['rec_id']);
        }else{
            echo 'success:'.$row['rec_id'];
            $GLOBALS['db']->update("conditions",array('total_page'=>$total_num),"rec_id=".$row['rec_id']);
        }
        $time = rand(5,15);
        sleep($time);
    }
}

function get_account(){
    while(true){
        $time = time();
        $sql = "select * from account where status = 0 and used_time < $time";
        $account = $GLOBALS['db']->getRow($sql);
        if(empty($account)){
            sleep(10);
            continue;
        }

    //    $res = $GLOBALS['db']->update("account",array('status'=>1),"account_id=".$account['account_id'].' and status = 0');
        $res = 1;
        if($res) return $account;
    }
}

function resume_list(){
    $resume_list = pq(".result-list .table-list tr.table-list-peo");
    foreach($resume_list as $tr){
        $one = array();
        $one['status'] = 0;
        $one['username'] = trim(pq($tr)->find('td.td-name a strong')->text());
        $one['sign'] = trim(pq($tr)->find('td.td-name a')->attr('data-id'));
        $one['url'] = 'https://h.liepin.com/resume/showresumedetail/?res_id_encode='.$one['sign'];
        $one['sex'] = trim(pq($tr)->find('td')->eq(2)->text());
        $one['age'] = trim(pq($tr)->find('td')->eq(3)->text());
        $one['agree'] = trim(pq($tr)->find('td')->eq(4)->text());
        $one['workyear'] = trim(pq($tr)->find('td')->eq(5)->text());
        $one['workplace'] = trim(pq($tr)->find('td')->eq(6)->text());
        $one['curjob'] = trim(pq($tr)->find('td')->eq(7)->text());
        $one['company'] = trim(pq($tr)->find('td')->eq(8)->text());
        $one['logintime'] = trim(pq($tr)->find('td')->eq(9)->text());
        $one['createtime'] = date('Y-m-d H:i:s');

        $sql = "select resume_id,url,logintime from resume where sign = '".$one['sign']."'";
        $resume_info = $GLOBALS['db']->getRow($sql);
        if(empty($resume_info)){
            $GLOBALS['db']->insert("resume",$one);
        }else{
            if($resume_info['logintime'] == $one['logintime'] && $resume_info['url'] == $one['url']) continue;
            else $GLOBALS['db']->update("resume",$one,'resume_id = '.$resume_info['resume_id']);
        }
    }
}


function save_resume(){
    while(true){
        $account = get_account();
        $sql = "select resume_id,url,sign,path from resume where status = 0";
        $row = $GLOBALS['db']->getRow($sql);
        if(empty($row)){
            sleep(10);
            continue;
        }
        $res = $GLOBALS['db']->update("resume",array('status'=>1),"resume_id=".$row['resume_id'].' and status != 1');
        $res = 1;
        if($res){
            $dir = './down/'.date('Y').'/'.date('m').'/'.date('d').'/'.date('H').'/';
            if(!empty($row['path'])){
                $path = $row['path'];
            }else{
                if(!file_exists($dir)) mkdir($dir,0777,true);
                $path = $dir.$row['resume_id'].'.html';
            }

            $html = post_fsockopen($account , $row['url'],'','GET');
            $workexps = post_fsockopen($account , 'https://h.liepin.com/resume/showresumedetail/showworkexps',array('res_id_encode'=>$row['sign']));
            $html = str_replace('<div class="resume-work"  id="workexp_anchor" >','<div class="resume-work"  id="workexp_anchor" >'.$workexps , $html);

            $bytes = file_put_contents($path,trim($html));//返回定写入字节数

            $update = array('status'=>2,'path'=>$path);
            if($bytes < 1000) $update['status'] = 3;
            $GLOBALS['db']->update("resume",$update,'resume_id = '.$row['resume_id']);
            break;
        }
    }
}


function post_fsockopen($account , $url = '', $post = array() , $method = 'POST', $header = null, $timeout = 20 ){
    try{
        if (empty($url)) return '';
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
            //   return array('error' => mb_convert_encoding($errstr, 'UTF-8', 'UTF-8,GB2312'), 'errorno' => $errno);
        } else {
            $crlf = "\r\n";
            $commonHeader = $method == 'PROXY' ? array() : array(
                'Host' => $host,
                'Referer'=>'https://h.liepin.com/cvsearch/soResume/',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36',
                'Content-Type' => 'POST' == $method ? 'application/x-www-form-urlencoded' : 'text/html; charsert=UTF-8',
                'Connection' => 'keep-alive',
                'Cookie' => $account['cookie']
                );
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
                    $request =  "{$method} {$path}{$query} HTTP/1.0{$crlf}"
                        ."{$commonHeader}"
                        .$crlf;//表示提交结束了
            }

            fwrite($res, $request);
            $reponse = '';

            while (!feof($res)) {
                $reponse .= fgets($res, 128);
            }

            fclose($res);

            if(strlen($reponse) < 1000){
                //登录出错，账号标识错误
                $GLOBALS['db']->update("account",array('status'=>9),"account_id=".$account['account_id']);
                return $reponse;
            }

            $used_time = time() + rand(20,60);
            $res = $GLOBALS['db']->update("account",array('status'=>0,'used_time'=>$used_time),"account_id=".$account['account_id']);

            $pos = strpos($reponse, $crlf . $crlf);//查找第一个分隔
            if($pos === false) return $reponse;
            $header = substr($reponse, 0, $pos);
            $body = substr($reponse, $pos + 2 * strlen($crlf));
            return $body;
        }
    }catch(Exception $e){
        return '';
    }
}