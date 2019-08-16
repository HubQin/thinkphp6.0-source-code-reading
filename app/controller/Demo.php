<?php
namespace app\controller;

use app\BaseController;

class Demo extends BaseController
{
    public function index   ()
    {
        return 'demo index';
    }

    public function hello($name = 'ThinkPHP6')
    {
        return 'hello,' . $name;
    }

}
