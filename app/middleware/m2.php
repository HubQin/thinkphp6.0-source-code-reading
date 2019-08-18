<?php

namespace app\middleware;

class m2
{
    public function handle($request, \Closure $next)
    {
        // 当前调用的类名
        $class = __CLASS__;
        // 前置执行逻辑
        echo "我在".$class."前置行为中<br>";

        $response =  $next($request);

        //后置执行 后置执行逻辑
        echo "我在".$class."后置行为中<br>";

        return $response;
    }
}
