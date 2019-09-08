<?php

namespace app\model;

use think\Model;

class User extends Model
{
//    protected $append = ['ne'];
    public function getDb(){
        // 因为程序会先使用ModelService服务类已经对$db成员变量进行了初始化
        // 所以这里可以获取到初始化后的值
        var_dump(self::$db);
    }

    public function getNeAttr($val, $data){
        return $data['name'] . '-' . $data['email'];
    }
}
