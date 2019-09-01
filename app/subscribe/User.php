<?php

namespace app\subscribe;

class User
{
    public function onUserLogin(){
        echo '我知道用户登录了，因为我订阅了<br>';
    }
    public function onUserLogout(){
        echo '我知道用户退出了，因为我订阅了<br>';
    }
}
