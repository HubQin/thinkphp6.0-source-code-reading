<?php
/**
 * Created by PhpStorm.
 * User: hugh
 * Date: 2019/9/1
 * Time: 23:36
 */

namespace app\common;


class MyServiceDemo
{
    protected static $myStaticVar = '123';

    public static function setVar($value){
        self::$myStaticVar = $value;
    }

    public function showVar(){
        // 因为在服务提供类app\service\MyService的boot方法中设置了$myStaticVar=‘456’
        // 所以这里输出‘456’
        var_dump(self::$myStaticVar);
    }
}
