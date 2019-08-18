<?php
namespace app\controller;

use app\BaseController;

class Demo extends BaseController
{
    public function hello($name = 'ThinkPHP6')
    {
        echo "这里是Demo控制器的Hello方法<br>";
        return 'hello,' . $name;
    }

}
