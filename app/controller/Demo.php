<?php
namespace app\controller;

use app\BaseController;
use app\common\MyServiceDemo;
use think\Container;

class Demo extends BaseController
{
    public function hello($name = 'ThinkPHP6')
    {
        echo "这里是Demo控制器的Hello方法<br>";
        return 'hello,' . $name;
    }

    public function modelDb(){
        (new \app\model\User())->getDb();
    }

    public function testService(MyServiceDemo $demo){
        // 因为在服务提供类app\service\MyService的boot方法中设置了$myStaticVar=‘456’
        // 所以这里输出‘456’
        $demo->showVar();
    }

    public function testServiceDi(){
        // 因为在服务提供类的register方法已经绑定了类标识到被服务类的映射
        // 所以这里可以使用容器类的实例来访问该标识，从而获取被服务类的实例
        $this->app->my_service->showVar();
    }

}
