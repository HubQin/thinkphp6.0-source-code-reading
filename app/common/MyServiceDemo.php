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
        var_dump(self::$myStaticVar);
    }
}
