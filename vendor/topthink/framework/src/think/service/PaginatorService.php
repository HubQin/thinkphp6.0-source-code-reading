<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\service;

use think\Paginator;
use think\paginator\driver\Bootstrap;
use think\Service;

/**
 * 分页服务类
 */
class PaginatorService extends Service
{
    public function register()
    {
        //判断Paginator::class是否已经有实例
        if (!$this->app->bound(Paginator::class)) {
            // 绑定接口到实现
            $this->app->bind(Paginator::class, Bootstrap::class);
        }
    }

    public function boot()
    {
        // 将一个闭包保存到Paginator::$maker
        Paginator::maker(function (...$args) {
            // 这个闭包将取得一个实例
            // 因为前面绑定了Paginator::class到Bootstrap::class的映射
            // 所以闭包取得的是Bootstrap::class类的实例
            return $this->app->make(Paginator::class, $args, true);
        });
        // 将一个闭包保存到Paginator::$currentPathResolver
        Paginator::currentPathResolver(function () {
            // 该闭包获取获取当前URL（不含QUERY_STRING）
            return $this->app->request->baseUrl();
        });
        // 将一个闭包保存到Paginator::$currentPageResolver
        Paginator::currentPageResolver(function ($varPage = 'page') {
            // 该闭包获取页数
            $page = $this->app->request->param($varPage);

            if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
                return (int) $page;
            }

            return 1;
        });
    }
}
