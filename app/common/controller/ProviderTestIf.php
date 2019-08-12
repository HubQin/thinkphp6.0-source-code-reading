<?php

namespace app\common\controller;

use app\common\interfaces\MyTestIf;
/**
 * 在provider文件中绑定该实现到接口
 * Class MyTest
 * @package app\common\controller
 */
class MyTest2 implements MyTestIf
{
    public function sayHello(){
        echo "hello";
    }

}
