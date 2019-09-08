<?php

namespace app\controller;

use think\facade\Event;

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

    public function test(){
        $data = \app\model\User::where('id', 1)->select();
        foreach ($data as $item) {
            var_dump($item['ne']);
        }
    }
}
