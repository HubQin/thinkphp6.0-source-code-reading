<?php

namespace app\common\controller;

/**
 * Class MyTest
 * @package app\common\controller
 */
class MyTest
{
    public $param;

    public function __construct($param = 'param value in constructor')
    {
        $this->param = $param;
    }

    /**
     * @return string
     */
    public function sayHi()
    {
        return "Hello " . $this->param;
    }
}
