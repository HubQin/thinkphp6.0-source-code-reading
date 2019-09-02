<?php

namespace app\controller;

use app\common\MyServiceDemo;
use think\facade\Event;
use app\model\User as UserModel;

class User
{
    public function __construct(){
        //动态添加一个事件订阅
        Event::subscribe(\app\subscribe\User::class);
    }
    public function login(){
        echo "用户登录了<br>   ";
        Event::trigger('UserLogin');
    }

    public function logout(){
        echo "用户退出了<br>   ";
        Event::trigger('UserLogout');
    }

    public function modelDb(){
        (new \app\model\User())->getDb();
    }

    public function testService(MyServiceDemo $demo){
        $demo->showVar();
    }
}
