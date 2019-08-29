<?php

namespace app\controller;

use think\Request;
use app\model\User;

class UserLogin
{
    public function UserLogin(){
        $name = 'jack';
        $pwd = '123456';
        if ($user = User::where([['name', '=', $name], ['pwd', '=', md5($pwd)]])->find()) {
            echo $user->name . "登录成功" . PHP_EOL;
        }
    }
}
