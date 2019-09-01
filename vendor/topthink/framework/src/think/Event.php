<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think;

/**
 * 事件管理类
 * @package think
 */
class Event
{
    /**
     * 监听者
     * @var array
     */
    protected $listener = [];

    /**
     * 观察者
     * @var array
     */
    protected $observer = [];

    /**
     * 事件别名
     * @var array
     */
    protected $bind = [
        'AppInit'     => event\AppInit::class,
        'HttpRun'     => event\HttpRun::class,
        'HttpEnd'     => event\HttpEnd::class,
        'RouteLoaded' => event\RouteLoaded::class,
        'LogWrite'    => event\LogWrite::class,
    ];

    /**
     * 是否需要事件响应
     * @var bool
     */
    protected $withEvent = true;

    /**
     * 应用对象
     * @var App
     */
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 设置是否开启事件响应
     * @access protected
     * @param  bool $event 是否需要事件响应
     * @return $this
     */
    public function withEvent(bool $event)
    {
        $this->withEvent = $event;
        return $this;
    }

    /**
     * 批量注册事件监听
     * @access public
     * @param  array $events 事件定义
     * @return $this
     */
    public function listenEvents(array $events)
    {
        // 如果关闭了事件响应，直接返回不绑定
        if (!$this->withEvent) {
            return $this;
        }

        //「$events」是事件标识到监听器的映射
        foreach ($events as $event => $listeners) {
            // 从事件标识中解析出实际的事件
            if (isset($this->bind[$event])) {
                $event = $this->bind[$event];
            }
            // 合并到监听者（观察者）数组
            $this->listener[$event] = array_merge($this->listener[$event] ?? [], $listeners);
        }

        return $this;
    }

    /**
     * 注册事件监听
     * @access public
     * @param  string $event    事件名称
     * @param  mixed  $listener 监听操作（或者类名）
     * @param  bool   $first    是否优先执行
     * @return $this
     */
    public function listen(string $event, $listener, bool $first = false)
    {
        if (!$this->withEvent) {
            return $this;
        }

        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        if ($first && isset($this->listener[$event])) {
            array_unshift($this->listener[$event], $listener);
        } else {
            $this->listener[$event][] = $listener;
        }

        return $this;
    }

    /**
     * 是否存在事件监听
     * @access public
     * @param  string $event 事件名称
     * @return bool
     */
    public function hasListen(string $event): bool
    {
        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        return isset($this->listener[$event]);
    }

    /**
     * 移除事件监听
     * @access public
     * @param  string $event 事件名称
     * @return $this
     */
    public function remove(string $event): void
    {
        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        unset($this->listener[$event]);
    }

    /**
     * 指定事件别名标识 便于调用
     * @access public
     * @param  array $events 事件别名
     * @return $this
     */
    public function bind(array $events)
    {
        $this->bind = array_merge($this->bind, $events);

        return $this;
    }

    /**
     * 注册事件订阅者
     * @access public
     * @param  mixed $subscriber 订阅者
     * @return $this
     */
    public function subscribe($subscriber)
    {
        if (!$this->withEvent) {
            return $this;
        }
        // 强制转换为数组
        $subscribers = (array) $subscriber;

        foreach ($subscribers as $subscriber) {
            if (is_string($subscriber)) {
                //实例化事件订阅类
                $subscriber = $this->app->make($subscriber);
            }
            // 如果该事件订阅类存在'subscribe'方法，执行该方法
            if (method_exists($subscriber, 'subscribe')) {
                // 手动订阅
                $subscriber->subscribe($this);
            } else {
                // 智能订阅
                $this->observe($subscriber);
            }
        }

        return $this;
    }

    /**
     * 自动注册事件观察者
     * @access public
     * @param  string|object $observer 观察者
     * @return $this
     */
    public function observe($observer)
    {
        if (!$this->withEvent) {
            return $this;
        }

        //如果是字符串，实例化对应的类
        if (is_string($observer)) {
            $observer = $this->app->make($observer);
        }
        // 获取listen数组所有的KEY
        $events = array_keys($this->listener);

        foreach ($events as $event) {
            // 如果存在「\」,获取「\」后面的字符
            $name   = false !== strpos($event, '\\') ? substr(strrchr($event, '\\'), 1) : $event;
            //事件订阅类中的方法，命名规则是on+事件类名/事件标识
            $method = 'on' . $name;
            // 如果方法存在，则添加到$listen数组，且入口方法为$method
            if (method_exists($observer, $method)) {
                $this->listen($event, [$observer, $method]);
            }
        }

        return $this;
    }

    /**
     * 触发事件
     * @access public
     * @param  string|object $event  事件名称
     * @param  mixed         $params 传入参数
     * @param  bool          $once   只获取一个有效返回值
     * @return mixed
     */
    public function trigger($event, $params = null, bool $once = false)
    {
        //如果设置了关闭事件，则直接返回，不再执行任何监听器
        if (!$this->withEvent) {
            return;
        }
        // 如果是一个对象，解析出对象的类
        if (is_object($event)) {
            //将对象实例作为传入参数
            $params = $event;
            $event  = get_class($event);
        }
        //根据事件标识解析出实际的事件
        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        $result    = [];
        // 解析出事件的监听者（可多个）
        $listeners = $this->listener[$event] ?? [];

        foreach ($listeners as $key => $listener) {
            // 执行监听器的操作
            // 这里使用反射类实例化监听器和执行监听器的操作，其过程类似前面的依赖注入的实现
            $result[$key] = $this->dispatch($listener, $params);
            // 如果返回false，或者没有返回值且 $once 为 true，直接中断，不再执行后面的监听器
            if (false === $result[$key] || (!is_null($result[$key]) && $once)) {
                break;
            }
        }
        // 是否返回多个监听器的结果
        // $once 为 false 则返回最后一个监听器的结果
        return $once ? end($result) : $result;
    }

    /**
     * 执行事件调度
     * @access protected
     * @param  mixed $event  事件方法
     * @param  mixed $params 参数
     * @return mixed
     */
    protected function dispatch($event, $params = null)
    {
        // 如果不是字符串，比如，一个闭包
        if (!is_string($event)) {
            $call = $event;
            //一个类的静态方法
        } elseif (strpos($event, '::')) {
            $call = $event;
        } else {
            $obj  = $this->app->make($event);
            $call = [$obj, 'handle'];
        }

        return $this->app->invoke($call, [$params]);
    }

}
