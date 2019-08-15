<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Slince <taosikai@yeah.net>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think;

use InvalidArgumentException;
use LogicException;
use think\exception\HttpResponseException;

/**
 * 中间件管理类
 * @package think
 */
class Middleware
{
    /**
     * 中间件执行队列
     * @var array
     */
    protected $queue = [];

    /**
     * 配置
     * @var array
     */
    protected $config = [];

    /**
     * 应用对象
     * @var App
     */
    protected $app;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    // Middleware实例化之前，「__make」方法会最先被执行
    public static function __make(App $app, Config $config)
    {
        return (new static($config->get('middleware')))->setApp($app);
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 设置应用对象
     * @access public
     * @param  App  $app
     * @return $this
     */
    public function setApp(App $app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * 导入中间件
     * @access public
     * @param  array  $middlewares
     * @param  string $type  中间件类型
     * @return void
     */
    public function import(array $middlewares = [], string $type = 'route'): void
    {
        foreach ($middlewares as $middleware) {
            $this->add($middleware, $type);
        }
    }

    /**
     * 注册中间件
     * @access public
     * @param  mixed  $middleware
     * @param  string $type  中间件类型
     * @return void
     */
    public function add($middleware, string $type = 'route'): void
    {
        //如果没有传入中间件，直接返回
        if (is_null($middleware)) {
            return;
        }

        $middleware = $this->buildMiddleware($middleware, $type);

        if ($middleware) {
            $this->queue[$type][] = $middleware;
        }
    }

    /**
     * 注册控制器中间件
     * @access public
     * @param  mixed  $middleware
     * @return void
     */
    public function controller($middleware): void
    {
        $this->add($middleware, 'controller');
    }

    /**
     * 移除中间件
     * @access public
     * @param  mixed  $middleware
     * @param  string $type  中间件类型
     */
    public function unshift($middleware, string $type = 'route')
    {
        if (is_null($middleware)) {
            return;
        }

        $middleware = $this->buildMiddleware($middleware, $type);

        if (!empty($middleware)) {
            array_unshift($this->queue[$type], $middleware);
        }
    }

    /**
     * 获取注册的中间件
     * @access public
     * @param  string $type  中间件类型
     */
    public function all(string $type = 'route'): array
    {
        return $this->queue[$type] ?? [];
    }

    /**
     * 中间件调度
     * @access public
     * @param  Request  $request
     * @param  string   $type  中间件类型
     */
    public function dispatch(Request $request, string $type = 'route')
    {
        return call_user_func($this->resolve($type), $request);
    }

    /**
     * 解析中间件
     * @access protected
     * @param  mixed  $middleware
     * @param  string $type  中间件类型
     * @return array
     */
    protected function buildMiddleware($middleware, string $type = 'route'): array
    {
        // 是否是数组
        if (is_array($middleware)) {
            // 列出中间件及其参数
            // 这里说明我们可以给中间件传入参数，且形式为 [中间件, 参数]
            list($middleware, $param) = $middleware;
        }
        // 是否是一个闭包
        // 说明中间件可以是一个闭包
        if ($middleware instanceof \Closure) {
            //返回闭包和参数
            return [$middleware, $param ?? null];
        }
        // 排除了上面几种类型，且不是字符串，抛出错误
        if (!is_string($middleware)) {
            throw new InvalidArgumentException('The middleware is invalid');
        }

        //检查「$config」成员变量中是否有别名，有则解析出来
        if (isset($this->config[$middleware])) {
            $middleware = $this->config[$middleware];
        }

        //如果中间件有包含中间件（说明中间件可以嵌套）
        //再走一遍「import」递归解析
        if (is_array($middleware)) {
            $this->import($middleware, $type);
            return [];
        }
        //返回解析结果
        return [[$middleware, 'handle'], $param ?? null];
    }

    protected function resolve(string $type = 'route')
    {
        return function (Request $request) use ($type) {
            $middleware = array_shift($this->queue[$type]);

            if (null === $middleware) {
                throw new InvalidArgumentException('The queue was exhausted, with no response returned');
            }

            list($call, $param) = $middleware;

            if (is_array($call) && is_string($call[0])) {
                $call = [$this->app->make($call[0]), $call[1]];
            }

            try {
                $response = $this->app->invoke($call, [$request, $this->resolve($type), $param]);
            } catch (HttpResponseException $exception) {
                $response = $exception->getResponse();
            }

            if (!$response instanceof Response) {
                throw new LogicException('The middleware must return Response instance');
            }

            return $response;
        };
    }

}
