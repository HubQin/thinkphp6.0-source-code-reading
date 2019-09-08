<?php

namespace app\service;

use think\Service;
use app\common\MyServiceDemo;

class MyService  extends Service
{

    public function register()
    {
        $this->app->bind('my_service', MyServiceDemo::class);
    }

    public function boot()
    {
        MyServiceDemo::setVar('456');
    }
}
