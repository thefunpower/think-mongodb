<?php
use think\facade\Db;
/**
 * mongodb操作
 */

/**
* 激活数据库连接
*/
function active_db($value = 'mongo')
{
    set_env("db_connection", $value);
}
/**
* 使用默认monogo连接
*/
function active_db_default()
{
    set_env("db_connection", 'mongo');
}
/**
* group by 查寻
*/
function db_get_group($table, $field = [], $options = [], $is_pager = false)
{
    $group = $options['GROUP'];
    $having = $options['HAVING'];
    $order = $options['ORDER'];
    unset($options['GROUP'],$options['HAVING'],$options['ORDER']);
    $where = $options;
    $connection = get_env("db_connection") ?: 'mongo';
    $list = Db::connect($connection)->name($table);
    if(get_env('database.type') == 'mongo') {
        $project = [];
        $new_group = [];
        $match = [];
        $n_field = [];
        foreach($field as $k => $v) {
            if(strpos($k, '(') !== false) {
                $a = trim(substr($k, 0, strpos($k, '(')));
                $b = trim(substr($k, strpos($k, '(') + 1));
                $b = str_replace(')', '', $b);
                $a = strtolower($a);
                if($b == 'id' || $b == '_id') {
                    $new_group[$v] = ['$'.$a => 1];
                } else {
                    $new_group[$v] = ['$'.$a => '$'.$b];
                }
                $n_field[] = $v;
            }
        }
        $group_array = [];
        $new_group_2 = [];
        foreach($group as $v) {
            $group_array[] = '$'.$v;
            $project[$v] = 1;
            $new_group[$v] = ['$first' => '$'.$v];
            $new_group_2[$v] = '$'.$v;
        }
        $new_group['_id'] = $group_array;
        $pipeline = [];
        if($having) {
            foreach($having as $k => $v) {
                $v1 = $v;
                $v2 = '';
                if(is_array($v)) {
                    $v1 = $v[0];
                    $v2 = $v[1];
                }
                $con = _db_group_con($k, $v1, $v2);
                if($con) {
                    $k = substr($k, 0, strpos($k, '['));
                    $match[$k] = $con;
                }
            }
        }
        if($where) {
            foreach($where as $k => $v) {
                $con = _db_group_con($k, $v);
                if($con) {
                    $k = substr($k, 0, strpos($k, '['));
                    $match[$k] = $con;
                } else {
                    if(is_array($v)) {
                        $match[$k] = ['$in' => $v];
                    } else {
                        $match[$k] = $v;
                    }
                }
            }
        }
        if($match) {
            $pipeline[] = [
                '$match' => $match
            ];
        }
        $pipeline[] = [
            '$group' => $new_group
        ];
        foreach($n_field as $v) {
            $project[$v] = 1;
        }
        $pipeline[] = [
            '$project' => [
                '_id' => 1,
            ] + $project
        ];
        if($order) {
            foreach($order as $field => $sorting) {
                $sorting = strtolower($sorting);
                if($sorting == 'desc') {
                    $sorting = -1;
                } elseif($sorting == 'asc') {
                    $sorting = 1;
                }
                $new_sort[$field] = $sorting;
            }
        }
        if($new_sort) {
            $pipeline[] = [
                '$sort' => $new_sort,
            ];
        }
        if($is_pager) {
            $mongo1 = $list;
            $mongo2 = $list;
            $page     = (int)(g('page') ?: 1);
            $per_page = (int)(g('per_page') ?: 20);
            $pipeline_2 = $pipeline;
            $pipeline_2[] = [
                 '$count' => 'total',
            ];
            $total = $mongo2->cmd([
                'aggregate' => $table,
                'pipeline'  => $pipeline_2,
                'cursor'    => new \stdClass()
            ]);
            $total = $total[0]['total'] ?: 0;
            if($total == 0) {
                return [
                    'current_page' => $page,
                    'data'         => [] ,
                    'last_page'    => 0,
                    'per_page'     => $per_page,
                    'total'        => 0,
                    'total_cur'    => 0,
                ];
            }
            $last_page = ceil($total / $per_page);
            if($page > $last_page) {
                $page = $last_page;
            }
            $index    = ($page - 1) * $per_page + 1;
            $offset   = (int)($index - 1);
            $pager_pipeline = $pipeline;
            $pager_pipeline[] = [
                '$skip' => $offset,
            ];
            $pager_pipeline[] = [
                '$limit' => $per_page,
            ];
            $list = $mongo1->cmd([
                'aggregate' => $table,
                'pipeline'  => $pager_pipeline,
                'cursor' => new \stdClass()
            ]);
            foreach($list as &$vv) {
                $vv['index'] = $index;
                do_action("db.get.$table", $vv);
            }
            return [
                'current_page' => $page,
                'data'        => $list,
                'last_page'   => $last_page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_cur'   => count($list),
                'code'        => 0,
            ];
        } else {
            $list = $list->cmd([
                'aggregate' => $table,
                'pipeline'  => $pipeline,
                'cursor' => new \stdClass()
            ]);
            foreach($list as &$vv) {
                do_action("db.get.$table", $vv);
            }
            return $list;
        }
    }
    //mysql兼容
    //如果需要直接兼容mysql,需要在此处实现！
    //一般不需要兼容MYSQL
}
/**
* mongodb groupby兼容
*/
function _db_group_con($k, $value, $up_value = '')
{
    if(strpos($k, "[") !== false && strpos($k, "]") !== false) {
        $k = trim($k);
        $f = trim(substr($k, 0, strpos($k, '[')));
        // [!] = <>, [~] =  LIKE
        $con = substr($k, strpos($k, '[') + 1, -1);
        switch($con) {
            case "!":
                if(is_array($value)) {
                    $ret_con = ['$nin' => $value];
                } else {
                    $ret_con = ['$ne' => $value];
                }
                break;
            case "~":
                $ret_con = ['$regexMatch' => ['input' => $value,'options' => 'i']];
                break;
            case ">":
                $ret_con = ['$gt' => $value];
                break;
            case '>=':
                $ret_con = ['$gte' => $value];
                break;
            case '<':
                $ret_con = ['$lt' => $value];
                break;
            case '<=':
                $ret_con = ['$lte' => $value];
                break;
            case '<>':
                if(!$up_value) {
                    $up_value = $value[1];
                    $value = $value[0];
                }
                $ret_con = ['$gte' => $value,'$lte' => $up_value];
                break;
            default:
                break;
        }
        return $ret_con;
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
/**
* 数据库更新记录
*/
function db_update($table, $update_data, $where, $use_action = true)
{
    db_reset_data_by_filed_type($table, $update_data);
    unset($update_data['_id'],$update_data['id']);
    $connection = get_env("db_connection") ?: 'mongo';
    $update_data['cache_at'] = now();
    $ref = ['data' => $update_data,'where' => $where];
    if($use_action) {
        do_action("db.update.before.".$table, $ref);
    }
    $update_data['updated_at'] = now();
    Db::connect($connection)->name($table)->where($where)->update($update_data);
    if($use_action) {
        do_action("db.update.".$table, $ref);
    }
}
/**
* 数据库删除记录
*/
function db_del($table, $where)
{
    $ref = ['where' => $where];
    do_action("db.del.".$table, $ref);
    $connection = get_env("db_connection") ?: 'mongo';
    Db::connect($connection)->name($table)->where($where)->delete();
}
/**
* 向数据库添加记录
*/
function db_insert($table, $data, $use_action = true)
{
    db_reset_data_by_filed_type($table, $data);
    unset($data['id'],$data['_id']);
    $connection = get_env("db_connection") ?: 'mongo';
    if($use_action) {
        do_action("db.insert.before.".$table, $data);
    }
    $data['created_at'] = now();
    $data['updated_at'] = now();
    $data['cache_at'] = now();
    $id = Db::connect($connection)->name($table)->insertGetId($data);
    $ref = ['data' => $data,'id' => $id];
    if($use_action) {
        do_action("db.insert.".$table, $ref);
    }
    return $id;
}
/**
* 内部调用，处理 where条件是数组的情况
*/
function _db_get_when_array($list, $call)
{
    if(get_env('database.type') == 'mongo') {
        if($call['ORDER']['id']) {
            $call['ORDER']['_id'] = $call['ORDER']['id'];
            unset($call['ORDER']['id']);
        }
    }
    foreach($call as $k => $v) {
        if($k == 'OR') {
            $where_or_con = [];
            foreach($v as $k1 => $v1) {
                if(strpos($k1, '[~]') !== false) {
                    $k1 = substr($k1, 0, strpos($k1, '['));
                    $where_or_con[] = [
                        $k1,'like',$v1
                    ];
                } else {
                    $where_or_con[$k1] = $v1;
                }
            }
            $list = $list->whereOr($where_or_con);
            unset($call[$k]);
            continue;
        }
        if(strpos($k, "[") !== false && strpos($k, "]") !== false) {
            unset($call[$k]);
            $k = trim($k);
            $f = trim(substr($k, 0, strpos($k, '[')));
            // [!] = <>, [~] =  LIKE
            $con = substr($k, strpos($k, '[') + 1, -1);
            switch($con) {
                case "!":
                    if(is_array($v)) {
                        $list = $list->whereNotIn($f, $v);
                    } else {
                        $list = $list->where([$f,"<>",$v]);
                    }
                    break;
                case "~":
                    $list = $list->where($f, "LIKE", $v);
                    break;
                case ">":
                case '>=':
                case '<':
                case '<=':
                    $list = $list->where($f, $con, $v);
                    break;
                case '<>':
                    $list = $list->whereBetween($f, $v);
                    break;
                case '><':
                    $list = $list->whereNotBetween($f, $v);
                    break;
                default:
                    break;
            }
        }
    }
    if($call['ORDER']) {
        $order = $call['ORDER'];
        unset($call['ORDER']);
        foreach($order as $k => &$v) {
            $v = strtolower($v);
            $list = $list->order($k, $v);
        }
    }
    if($call['DISTINCT']) {
        $distinct = $call['DISTINCT'];
        unset($call['DISTINCT']);
        $list = $list->distinct(true);
    }
    if($call['LOCK']) {
        $lock = $call['LOCK'];
        unset($call['LOCK']);
        $list = $list->lock(true);
    }
    if($call['LIMIT']) {
        $limit = $call['LIMIT'];
        unset($call['LIMIT']);
        $list = $list->limit($limit);
    }
    if($call['id'] && is_array($call['id'])) {
        $list = $list = $list->whereIn('id', $call['id']);
        unset($call['id']);
    }
    if($call) {
        $list = $list->where($call);
    }
    return $list;
}
/**
* 数据库查寻
*/
function db_get_one($table, $field, $call = null)
{
    if($field == '*') {
        $field = $call;
        $call = null;
    }
    return db_get($table, $field, $call = null, 1);
}
/**
* 设置数据库查寻字段走缓存
*/
function set_db_cache($table, $field)
{
    global $db_cache_tables;
    $db_cache_tables[$table][$field] = true;
}
/**
* 取缓存key
*/
function get_db_cache_key($table, $where)
{
    global $db_cache_tables;
    if(!$db_cache_tables[$table]) {
        return;
    }
    $where = get_db_cache_where($table, $where);
    if($where) {
        return 'db:'.$table.":".md5(json_encode($where));
    }
}
/**
* 取缓存where
*/
function get_db_cache_where($table, $where)
{
    global $db_cache_tables;
    if(!$db_cache_tables[$table]) {
        return;
    }
    if(isset($where['ORDER'])) {
        unset($where['ORDER']);
    }
    if(isset($where['GROUP'])) {
        unset($where['GROUP']);
    }
    if(isset($where['HAVING'])) {
        unset($where['HAVING']);
    }
    return $where;
}
/**
* 取数据，带缓存
*/
function db_get_cache($table, $where = [], $limit = null)
{
    $cache_key = get_db_cache_key($table, $where);
    $cache_key_max = $cache_key.":max";
    if($cache_key) {
        $cache_key = $cache_key.$limit;
        $cache_key_max_data = cache($cache_key_max);
        $cache_where  = get_db_cache_where($table, $where);
        if($cache_where) {
            $cache_where['ORDER'] = ['cache_at' => 'DESC'];
            $max  = db_get($table, $cache_where, 1);
            $max_id = $max['id'];
            if($cache_key_max_data && $max_id && $cache_key_max_data == $max_id) {
                $data = cache($cache_key);
            } else {
                $data = db_get($table, $where, $limit);
                cache($cache_key_max, $max_id);
                cache($cache_key, $data);
            }
            return $data;
        } else {
            return db_get($table, $where, $limit);
        }
    } else {
        return db_get($table, $where, $limit);
    }
}
/**
 * 取数据
 */
function db_get($table, $field = null, $call = null, $limit = null)
{
    if(!$call) {
        $call = $field;
        $field = '';
    } elseif(is_numeric($call)) {
        $limit = $call;
        $call = $field;
        $field = '';
    }
    $list = Db::connect($connection)->name($table);
    if($field && $field != '*') {
        $list = $list->field($field);
    }

    if(is_array($call)) {
        if($call['GROUP'] || $call['HAVING']) {
            $one = db_get_group($table, $field, $call);
            do_action("db.get.$table", $one);
            return $one;
        }
        $list = _db_get_when_array($list, $call);
    } elseif(is_string($call)) {
        $list = $list->where(['id' => $call]);
    } elseif(is_callable($call)) {
        $list = $call($list);
    }
    if($limit) {
        $list = $list->limit($limit);
    }
    $all = $list->select()->toArray();
    if($limit == 1) {
        $one = $all[0];
        do_action("db.get.$table", $one);
        return $one;
    } else {
        $index = 1;
        foreach($all as &$vv) {
            $vv['index'] = $index++;
            do_action("db.get.$table", $vv);
        }
        return $all;
    }
}
/**
* 数量
*/
function db_get_count($table, $call)
{
    $connection = get_env("db_connection") ?: 'mongo';
    $list = Db::connect($connection)->name($table);
    if(is_array($call)) {
        $list = _db_get_when_array($list, $call);
    } elseif(is_callable($call)) {
        $list = $call($list);
    }
    return $list->count();
}
/**
* 计算SUM
*/
function db_get_sum($table, $field, $call)
{
    $connection = get_env("db_connection") ?: 'mongo';
    $list = Db::connect($connection)->name($table);
    if(is_array($call)) {
        $list = _db_get_when_array($list, $call);
    } elseif(is_callable($call)) {
        $list = $call($list);
    }
    return $list->sum($field);
}
/**
* 数据库分页
*/
function db_pager($table, $field = [], $call = [])
{
    if($field == "*") {
        $field = $call;
        $call = [];
    }
    if(!$call) {
        do_action("db.pager.".$table, $field);
        return db_get_pager($table, $field);
    }
    if($call['GROUP'] || $call['HAVING']) {
        do_action("db.pager.".$table, $field);
        return db_get_group($table, $field, $call, true);
    }
}
/**
 * 分页
 */
function db_get_pager($table, $call)
{
    $connection = get_env("db_connection") ?: 'mongo';
    $list = Db::connect($connection)->name($table);
    $wq   = g('wq');
    $page = g('page') ?: 1;
    $per_page = g('per_page') ?: 20;
    $index = ($page - 1) * $per_page + 1;
    if(is_array($call)) {
        $list = _db_get_when_array($list, $call);
    } elseif(is_string($call)) {
        $list = $list->where(['id' => $call]);
    } elseif(is_callable($call)) {
        $list = $call($list);
    }
    $list = $list->paginate($per_page)->toArray();
    foreach($list['data'] as &$vv) {
        $vv['id'] = (string)$vv['id'];
        $vv['index'] = $index++;
        do_action("db.get.$table", $vv);
    }
    $list['total_cur'] = count($list['data']);
    $list['code'] = 0;
    return $list;
}

/**
 * mongodb事务
 */
function db_action($call)
{
    return mongo_action($call);
}
function mongo_action($call)
{
    $support = get_env('mongo_trans');
    if($support == 1) {
        Db::startTrans();
        try {
            $ret = $call();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $msg = $e->getMessage();
            trace($msg, 'error');
            return json_error(['msg' => $msg]);
            ;
        }
        return $ret;
    } else {
        return $call();
    }
}

/**
* 数据库字段允许
*/
function db_allow($table, $data)
{
    global $_db_allow_set;
    $allow = $_db_allow_set[$table];
    if($allow) {
        foreach($data as $k => $v) {
            if($k != 'id' && !$allow[$k]) {
                unset($data[$k]);
            }
        }
    }
    return $data;
}
/**
* 设置允许字段
*/
function db_allow_set($table, $data)
{
    global $_db_allow_set;
    if(!is_array($data)) {
        $data = [$data];
    }
    foreach($data as $k => $v) {
        $_db_allow_set[$table][$k] = $k;
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
