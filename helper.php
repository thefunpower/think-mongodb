<?php  
define('thefunpower_mongo_dir',__DIR__); 
/**
* 获取ENV
*/
function get_env($key)
{
    return env($key);
}
/**
* 设置ENV
*/
function set_env($key, $val)
{
    return think\facade\Env::set($key, $val);
} 
/**
 * 设置配置
 */
if(!function_exists('set_config')) {
    function set_config($title, $body)
    {
        if(!$title || !$body) {
            return;
        }
        $title = trim($title);
        $title = strtolower($title);
        $one = db_get("config", ['title' => $title], 1);
        if (!$one) {
            db_insert("config", ['title' => $title, 'body' => $body]);
        } else {
            db_update("config", ['body' => $body], ['id' => $one['id']]);
        }
    }
}
/**
 * 取配置
 */
if(!function_exists('get_config')) {
    function get_config($title)
    {
        if (is_array($title)) {
            $new_arr = [];
            foreach($title as $k) {
                $new_arr[] = strtolower(trim($k));
            }
            $title = $new_arr;
            $list = [];
            $all  = db_get("config", ['title' => $title]);
            foreach ($all as $one) {
                $body = $one['body'];
                $key  = $one['title'];
                $list[$key] = $body;
            }
            return $list;
        } else {
            $title = strtolower($title);
            $val = get_env($title);
            if($shop_id) {
                $title = $title.$shop_id;
            }
            $one  = db_get("config", ['title' => $title], 1);
            $body = $one['body'] ?: $val;
            return $body;
        }
    }
}
/**
 * 设置或取ID
 */
function get_id_by_auto_insert($table, $data = [], $where = [], $has_time_and_update = false)
{
    if(!$where) {
        $where = $data;
    }
    $res = db_get($table, $where, 1);
    if(!$res) {
        if($has_time_and_update) {
            $data['created_at'] = now();
            $data['updated_at'] = now();
        }
        $id = db_insert($table, $data);
    } else {
        $id = $res['id'];
        if($has_time_and_update) {
            $data['updated_at'] = now();
            db_update($table, $data, ['id' => $id]);
        }
    }
    return $id;
}
/**
* 取一条或多条记录
* get_all_or_one("novel_book",$where,'get_novel_book_row');
*/
function get_all_or_one($table, $where, $fun = '', $limit = '')
{
    static $_object;
    if($where && !is_array($where)) {
        $where = ['id' => $where];
        $limit = 1;
    }
    $cache_key = "get_all_or_one:".$table.":".md5(json_encode($where).$fun.$limit);
    $d = $_object[$cache_key];
    if($d) {
        return $d;
    }
    if(!$where) {
        return;
    }
    $all = db_get($table, $where, $limit);
    if($limit == 1) {
        if($fun) {
            $fun($all);
        }
        $_object[$cache_key] = $all;
        return $_object[$cache_key];
    } else {
        foreach($all as &$v) {
            if($fun) {
                $fun($v);
            }
        }
        $_object[$cache_key] = $all;
        return $_object[$cache_key];
    }
}


/**
* 设置字段类型
*/
global $think_db_fields;
function db_set_field_type($table, $data)
{
    global $think_db_fields;
    $think_db_fields[$table] = $data;
}
/**
* 权限字段类型设置值
*/
function db_reset_data_by_filed_type($table, &$data)
{
    global $think_db_fields;
    $d = $think_db_fields[$table];
    if(!$d) {
        return;
    }
    foreach($data as $k => &$v) {
        if($d[$k] && $v) {
            switch($d[$k]) {
                case "int":
                    $v = (int)$v;
                    break;
                case "float":
                    $v =  floatval($v);
                    break;
            }
        }
    }
}