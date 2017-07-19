<?php

class DBHelper
{
    private $link;
    private $error_message;
    private static $_instance ;

    private function __construct($config)
    {
        if(empty($config['host']) || empty($config['port'])){
            $this->ErrorMsg("Config cant't empty!");
        }

        $this->link=mysqli_connect($config['host'].':'.$config['port'],$config['user'],$config['pass']);
        if(!$this->link){
            $this->ErrorMsg("Can't Connect MySQL Server!");
        }

        $this->select_database($config['dbname']);
        return $this->link;
    }

    private function __clone()
    {
        $this->ErrorMsg("Can't clone!");
    }

    public static function getIntance($config){
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self($config);
        }
        return self::$_instance;
    }

    function select_database($dbname)
    {
        return mysqli_select_db($this->link, $dbname);
    }

    function set_mysql_charset($charset)
    {
        if (in_array(strtolower($charset), array('gbk', 'big5', 'utf-8', 'utf8'))) {
            $charset = str_replace('-', '', $charset);
        }
        if ($charset != 'latin1') {
            mysqli_query($this->link, "SET character_set_connection=$charset, character_set_results=$charset, character_set_client=binary");
        }
    }

    function fetch_array($query, $result_type = MYSQL_ASSOC)
    {
        return mysqli_fetch_array($query, $result_type);
    }

    function query($sql, $type = '')
    {

        if (!($query = mysqli_query($this->link, $sql)) && $type != 'SILENT') {
            $message = array(
                'message'=>'MySQL Query Error',
                'sql'=>$sql,
                'error'=>mysqli_error($this->link),
                'errno'=>mysqli_errno($this->link)
            );

            $this->error_message[] = $message;

            $this->ErrorMsg();

            return false;
        }

        return $query;
    }

    function affected_rows()
    {
        return mysqli_affected_rows($this->link);
    }

    function error()
    {
        return mysqli_error($this->link);
    }

    function errno()
    {
        return mysqli_errno($this->link);
    }

    function result($query, $row)
    {
        return @mysqli_data_seek($query, $row);
    }

    function num_rows($query)
    {
        return mysqli_num_rows($query);
    }

    function num_fields($query)
    {
        return mysqli_num_fields($query);
    }

    function free_result($query)
    {
        return mysqli_free_result($query);
    }

    function insert_id()
    {
        return mysqli_insert_id($this->link);
    }

    function fetchRow($query)
    {
        return mysqli_fetch_assoc($query);
    }

    function fetch_fields($query)
    {
        return mysqli_fetch_field($query);
    }

    function ping()
    {
        return mysqli_ping($this->link);
    }

    function close()
    {
        return mysqli_close($this->link);
    }

    function ErrorMsg()
    {
     //   header('HTTP/1.0 500 Server Internal Error');
     //   print_r($this->error_message);exit;
        save_log(print_r($this->error_message , 1),'error');
    }

    function selectLimit($sql, $num, $start = 0)
    {
        if ($start == 0) {
            $sql .= ' LIMIT ' . $num;
        } else {
            $sql .= ' LIMIT ' . $start . ', ' . $num;
        }

        return $this->query($sql);
    }

    function getOne($sql, $limited = false)
    {
        if ($limited == true) {
            $sql = trim($sql . ' LIMIT 1');
        }

        $res = $this->query($sql);
        if ($res !== false) {
            $row = mysqli_fetch_row($res);

            if ($row !== false) {
                return $row[0];
            } else {
                return '';
            }
        } else {
            return false;
        }
    }

    function getAll($sql)
    {
        $res = $this->query($sql);
        if ($res !== false) {
            $arr = array();
            while ($row = mysqli_fetch_assoc($res)) {
                $arr[] = $row;
            }

            return $arr;
        } else {
            return false;
        }
    }

    function getRow($sql, $limited = false)
    {
        if ($limited == true) {
            $sql = trim($sql . ' LIMIT 1');
        }

        $res = $this->query($sql);
        if ($res !== false) {
            return mysqli_fetch_assoc($res);
        } else {
            return false;
        }
    }

    function getCol($sql)
    {
        $res = $this->query($sql);
        if ($res !== false) {
            $arr = array();
            while ($row = mysqli_fetch_row($res)) {
                $arr[] = $row[0];
            }

            return $arr;
        } else {
            return false;
        }
    }


    function autoExecute($table, $field_values, $mode = 'INSERT', $where = '', $querymode = '')
    {
        $field_names = $this->getCol('DESC ' . $table);

        $sql = '';
        if ($mode == 'INSERT') {
            $fields = $values = array();
            foreach ($field_names AS $value) {
                if (array_key_exists($value, $field_values) == true) {
                    $fields[] = $value;
                    $values[] = "'" . $field_values[$value] . "'";
                }
            }

            if (!empty($fields)) {
                $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
            }
        } else {
            $sets = array();
            foreach ($field_names AS $value) {
                if (array_key_exists($value, $field_values) == true) {
                    $sets[] = $value . " = '" . $field_values[$value] . "'";
                }
            }

            if (!empty($sets)) {
                if(empty($where)) $where = ' 1=1 ';
                $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
            }
        }

        if ($sql) {
            return $this->query($sql, $querymode);
        } else {
            return false;
        }
    }

    function get_table_name($query_item)
    {
        $query_item = trim($query_item);
        $table_names = array();

        /* 判断语句中是不是含有 JOIN */
        if (stristr($query_item, ' JOIN ') == '') {
            /* 解析一般的 SELECT FROM 语句 */
            if (preg_match('/^SELECT.*?FROM\s*((?:`?\w+`?\s*\.\s*)?`?\w+`?(?:(?:\s*AS)?\s*`?\w+`?)?(?:\s*,\s*(?:`?\w+`?\s*\.\s*)?`?\w+`?(?:(?:\s*AS)?\s*`?\w+`?)?)*)/is', $query_item, $table_names)) {
                $table_names = preg_replace('/((?:`?\w+`?\s*\.\s*)?`?\w+`?)[^,]*/', '\1', $table_names[1]);

                return preg_split('/\s*,\s*/', $table_names);
            }
        } else {
            /* 对含有 JOIN 的语句进行解析 */
            if (preg_match('/^SELECT.*?FROM\s*((?:`?\w+`?\s*\.\s*)?`?\w+`?)(?:(?:\s*AS)?\s*`?\w+`?)?.*?JOIN.*$/is', $query_item, $table_names)) {
                $other_table_names = array();
                preg_match_all('/JOIN\s*((?:`?\w+`?\s*\.\s*)?`?\w+`?)\s*/i', $query_item, $other_table_names);

                return array_merge(array($table_names[1]), $other_table_names[1]);
            }
        }

        return $table_names;
    }

    public function insert($table,$data){
        //遍历数组，得到每一个字段和字段的值
        $key_str='';
        $v_str='';
        foreach($data as $key=>$v){
            if(!isset($v)){
                die("error");
            }
            //$key的值是每一个字段s一个字段所对应的值
            $key_str.=$key.',';
            $v_str.="'$v',";
        }
        $key_str=trim($key_str,',');
        $v_str=trim($v_str,',');
        //判断数据是否为空
        $sql="insert into $table ($key_str) values ($v_str)";
        $this->query($sql);
        //返回上一次增加操做产生ID值
        return $this->insert_id();
    }

    public function update($table,$data,$where = ' 1'){
        //遍历数组，得到每一个字段和字段的值
        $str='';
        foreach($data as $key=>$v){
            $str.="$key='$v',";
        }
        $str=rtrim($str,',');
        //修改SQL语句
        $sql="update $table set $str where $where";
        $this->query($sql);
        //返回受影响的行数
        return mysqli_affected_rows($this->link);
    }

    public function delete($table, $where = 1){
        if(is_array($where)){
            foreach ($where as $key => $val) {
                $condition = $key.'='.$val;
            }
        } else {
            $condition = $where;
        }
        $sql = "delete from $table where $condition";
        $this->query($sql);
        //返回受影响的行数
        return mysqli_affected_rows($this->link);
    }

}