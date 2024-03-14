# Mongo
  

## 安装

~~~
composer require thefunpower/think-mongodb
~~~

### 激活数据库连接

~~~
active_db($value = 'mongo')
~~~

### 添加记录

~~~
db_insert($table, $data, $use_action = true)
~~~

`action`

~~~
do_action("db.insert.before.".$table, $data);
~~~

~~~
do_action("db.insert.".$table, $ref);
~~~

$ref 为 
~~~
['data' => $data,'id' => $id];
~~~



### 更新记录

~~~
db_update($table, $update_data, $where, $use_action = true)
~~~

`action`

~~~
$ref = ['data' => $update_data,'where' => $where];
do_action("db.update.before.".$table, $ref);

do_action("db.update.".$table, $ref);
~~~

### 删除记录

~~~
db_del($table, $where)
~~~

`action`

~~~
$ref = ['where' => $where];
do_action("db.del.".$table, $ref);
~~~

### 字段允许

- 设置允许字段

~~~
db_allow_set($table, $data)
~~~

- 获取允许数据

~~~
$data = db_allow($table, $data);
~~~

### 设置或取ID

~~~
get_id_by_auto_insert($table, $data = [], $where = [], $has_time_and_update = false)
~~~



### 设置字段类型
~~~
db_set_field_type('table',[
  'num'=>'int',
  'price'=>'float',
]);
~~~

### 分页

~~~
db_pager($table, $field = []);

~~~

### 事务

~~~
db_action(function(){

});
~~~

### group by 查寻

~~~
$a = date("Y-m-d 00:00:00",$yesterday);
$b = date("Y-m-d 23:59:59",$yesterday);
$where['created_at[<>]'] = [$a,$b];
$where["GROUP"] = ['type','nid'];
$all = db_get("hardware",["SUM(price)"=>'amount',],$where);
$d['list'] = db_get("hardware",[
     "SUM(t)"=>'t',
     'min(t)'=>'t1',
],[
  'drive[!]'=>['gx'],
  'GROUP'=> [
    'tag',
    'drive',
  ],
  'HAVING'=>[
      't[>]'=>0
  ],
  'ORDER'=>[
    't'=>'asc'
  ]
]);
~~~




## LICENSE

[Apache License 2.0](LICENSE)

