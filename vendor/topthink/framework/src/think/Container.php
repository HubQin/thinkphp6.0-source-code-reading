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

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;
use IteratorAggregate;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use think\exception\ClassNotFoundException;

/**
 * 容器管理类 支持PSR-11
 */
class Container implements ContainerInterface, ArrayAccess, IteratorAggregate, Countable
{
    /**
     * 容器对象实例
     * @var Container|Closure
     */
    protected static $instance;

    /**
     * 容器中的对象实例
     * @var array
     */
    protected $instances = [];

    /**
     * 容器绑定标识
     * @var array
     */
    protected $bind = [];

    /**
     * 容器回调
     * @var array
     */
    protected $invokeCallback = [];

    /**
     * 获取当前容器的实例（单例）
     * @access public
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        if (static::$instance instanceof Closure) {
            return (static::$instance)();
        }

        return static::$instance;
    }

    /**
     * 设置当前容器的实例
     * @access public
     * @param object|Closure $instance
     * @return void
     */
    public static function setInstance($instance): void
    {
        static::$instance = $instance;
    }

    /**
     * 注册一个容器对象回调
     *
     * @param  string|Closure $abstract
     * @param  Closure|null   $callback
     * @return void
     */
    public function resolving($abstract, Closure $callback = null): void
    {
        if ($abstract instanceof Closure) {
            $this->invokeCallback['*'][] = $abstract;
            return;
        }

        if (isset($this->bind[$abstract])) {
            $abstract = $this->bind[$abstract];
        }

        $this->invokeCallback[$abstract][] = $callback;
    }

    /**
     * 获取容器中的对象实例 不存在则创建
     * @access public
     * @param string     $abstract    类名或者标识
     * @param array|true $vars        变量
     * @param bool       $newInstance 是否每次创建新的实例
     * @return object
     */
    public static function pull(string $abstract, array $vars = [], bool $newInstance = false)
    {
        return static::getInstance()->make($abstract, $vars, $newInstance);
    }

    /**
     * 获取容器中的对象实例
     * @access public
     * @param string $abstract 类名或者标识
     * @return object
     */
    public function get($abstract)
    {
        //先检查是否有绑定实际的类或者是否实例已存在
        //比如，$abstract = 'http'
        if ($this->has($abstract)) {
            return $this->make($abstract);
        }

        throw new ClassNotFoundException('class not exists: ' . $abstract, $abstract);
    }

    /**
     * 绑定一个类、闭包、实例、接口实现到容器
     * @access public
     * @param string|array $abstract 类标识、接口
     * @param mixed        $concrete 要绑定的类、闭包或者实例
     * @return $this
     */
    public function bind($abstract, $concrete = null)
    {
        if (is_array($abstract)) {
            $this->bind = array_merge($this->bind, $abstract);
        } elseif ($concrete instanceof Closure) {
            $this->bind[$abstract] = $concrete;
        } elseif (is_object($concrete)) {
            $this->instance($abstract, $concrete);
        } else {
            $this->bind[$abstract] = $concrete;
        }

        return $this;
    }

    /**
     * 绑定一个类实例到容器
     * @access public
     * @param string $abstract 类名或者标识
     * @param object $instance 类的实例
     * @return $this
     */
    public function instance(string $abstract, $instance)
    {
        //检查「$bind」中是否保存了名称到实际类的映射，如 'app'=> 'think\App'
        //也就是说，只要绑定了这种对应关系，通过传入名称，就可以找到实际的类
        if (isset($this->bind[$abstract])) {
            //$abstract = 'app', $bind = "think\App"
            $bind = $this->bind[$abstract];
            //如果「$bind」是字符串，重走上面的流程
            if (is_string($bind)) {
                return $this->instance($bind, $instance);
            }
        }
        //保存绑定的实例到「$instances」数组中
        //比如，$this->instances["think\App"] = $instance;
        $this->instances[$abstract] = $instance;

        return $this;
    }

    /**
     * 判断容器中是否存在类及标识
     * @access public
     * @param string $abstract 类名或者标识
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        //是否有对应的绑定或者是否已经实例化
        return isset($this->bind[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * 判断容器中是否存在类及标识
     * @access public
     * @param string $name 类名或者标识
     * @return bool
     */
    public function has($name): bool
    {
        return $this->bound($name);
    }

    /**
     * 判断容器中是否存在对象实例
     * @access public
     * @param string $abstract 类名或者标识
     * @return bool
     */
    public function exists(string $abstract): bool
    {
        if (isset($this->bind[$abstract])) {
            $bind = $this->bind[$abstract];

            if (is_string($bind)) {
                return $this->exists($bind);
            }
        }

        return isset($this->instances[$abstract]);
    }

    /**
     * 创建类的实例 已经存在则直接获取
     * @access public
     * @param string $abstract    类名或者标识
     * @param array  $vars        变量
     * @param bool   $newInstance 是否每次创建新的实例
     * @return mixed
     */
    public function make(string $abstract, array $vars = [], bool $newInstance = false)
    {
        //如果已经存在实例，且不强制创建新的实例，直接返回已存在的实例
        if (isset($this->instances[$abstract]) && !$newInstance) {
            return $this->instances[$abstract];
        }
        //如果有绑定，比如 'http'=> 'think\Http',则 $concrete = 'think\Http'
        if (isset($this->bind[$abstract])) {
            $concrete = $this->bind[$abstract];

            if ($concrete instanceof Closure) {
                $object = $this->invokeFunction($concrete, $vars);
            } else {
                //重走一遍make函数，比如上面http的例子，则会调到后面「invokeClass()」处
                return $this->make($concrete, $vars, $newInstance);
            }
        } else {
            //实例化需要的类，比如'think\Http'
            $object = $this->invokeClass($abstract, $vars);
        }

        if (!$newInstance) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * 删除容器中的对象实例
     * @access public
     * @param string $name 类名或者标识
     * @return void
     */
    public function delete($name)
    {
        if (isset($this->bind[$name])) {
            $bind = $this->bind[$name];

            if (is_string($bind)) {
                $this->delete($bind);
                return;
            }
        }

        if (isset($this->instances[$name])) {
            unset($this->instances[$name]);
        }
    }

    /**
     * 执行函数或者闭包方法 支持参数调用
     * @access public
     * @param string|array|Closure $function 函数或者闭包
     * @param array $vars     参数
     * @return mixed
     */
    public function invokeFunction($function, array $vars = [])
    {
        try {
            $reflect = new ReflectionFunction($function);

            $args = $this->bindParams($reflect, $vars);

            if ($reflect->isClosure()) {
                // 解决在`php7.1`调用时会产生`$this`上下文不存在的错误 (https://bugs.php.net/bug.php?id=66430)
                return $function->__invoke(...$args);
            } else {
                return $reflect->invokeArgs($args);
            }
        } catch (ReflectionException $e) {
            // 如果是调用闭包时发生错误则尝试获取闭包的真实位置
            if (isset($reflect) && $reflect->isClosure() && $function instanceof Closure) {
                $function = "{Closure}@{$reflect->getFileName()}#L{$reflect->getStartLine()}-{$reflect->getEndLine()}";
            } else {
                $function .= '()';
            }
            throw new Exception('function not exists: ' . $function, 0, $e);
        }
    }

    /**
     * 调用反射执行类的方法 支持参数绑定
     * @access public
     * @param mixed $method 方法
     * @param array $vars   参数
     * @param bool  $accessible 设置是否可访问
     * @return mixed
     */
    public function invokeMethod($method, array $vars = [], bool $accessible = false)
    {
        try {
            if (is_array($method)) {
                $class   = is_object($method[0]) ? $method[0] : $this->invokeClass($method[0]);
                $reflect = new ReflectionMethod($class, $method[1]);
            } else {
                // 静态方法
                $reflect = new ReflectionMethod($method);
            }

            $args = $this->bindParams($reflect, $vars);

            if ($accessible) {
                $reflect->setAccessible($accessible);
            }

            return $reflect->invokeArgs($class ?? null, $args);
        } catch (ReflectionException $e) {
            if (is_array($method)) {
                $class    = is_object($method[0]) ? get_class($method[0]) : $method[0];
                $callback = $class . '::' . $method[1];
            } else {
                $callback = $method;
            }

            throw new Exception('method not exists: ' . $callback . '()', 0, $e);
        }
    }

    /**
     * 调用反射执行类的方法 支持参数绑定
     * @access public
     * @param object $instance 对象实例
     * @param mixed  $reflect  反射类
     * @param array  $vars     参数
     * @return mixed
     */
    public function invokeReflectMethod($instance, $reflect, array $vars = [])
    {
        $args = $this->bindParams($reflect, $vars);

        return $reflect->invokeArgs($instance, $args);
    }

    /**
     * 调用反射执行callable 支持参数绑定
     * @access public
     * @param mixed $callable
     * @param array $vars 参数
     * @param bool  $accessible 设置是否可访问
     * @return mixed
     */
    public function invoke($callable, array $vars = [], bool $accessible = false)
    {
        if ($callable instanceof Closure) {
            return $this->invokeFunction($callable, $vars);
        }

        return $this->invokeMethod($callable, $vars, $accessible);
    }

    /**
     * 调用反射执行类的实例化 支持依赖注入
     * @access public
     * @param string $class 类名
     * @param array  $vars  参数
     * @return mixed
     */
    public function invokeClass(string $class, array $vars = [])
    {
        try {
            //通过反射实例化类
            $reflect = new ReflectionClass($class);
            //检查是否有「__make」方法
            if ($reflect->hasMethod('__make')) {
                //返回的$method包含'__make'的各种信息，如公有/私有
                $method = new ReflectionMethod($class, '__make');
                //检查是否是公有方法且是静态方法
                if ($method->isPublic() && $method->isStatic()) {
                    //绑定参数
                    $args = $this->bindParams($method, $vars);
                    //调用该方法（__make），因为是静态的，所以第一个参数是null
                    /**********因此，可得知，一个类中，如果有__make方法，在类实例化之前会首先被调用**********/
                    return $method->invokeArgs(null, $args);
                }
            }
            //获取类的构造函数
            $constructor = $reflect->getConstructor();
            
            //有构造函数则绑定其参数
            $args = $constructor ? $this->bindParams($constructor, $vars) : [];
            //根据传入的参数，通过反射，实例化类
            $object = $reflect->newInstanceArgs($args);

            $this->invokeAfter($class, $object);

            return $object;
        } catch (ReflectionException $e) {
            throw new ClassNotFoundException('class not exists: ' . $class, $class, $e);
        }
    }

    /**
     * 执行invokeClass回调
     * @access protected
     * @param string $class  对象类名
     * @param object $object 容器对象实例
     * @return void
     */
    protected function invokeAfter(string $class, $object): void
    {
        if (isset($this->invokeCallback['*'])) {
            foreach ($this->invokeCallback['*'] as $callback) {
                $callback($object, $this);
            }
        }

        if (isset($this->invokeCallback[$class])) {
            foreach ($this->invokeCallback[$class] as $callback) {
                $callback($object, $this);
            }
        }
    }

    /**
     * 绑定参数
     * @access protected
     * @param \ReflectionMethod|\ReflectionFunction $reflect 反射类
     * @param array                                 $vars    参数
     * @return array
     */
    protected function bindParams($reflect, array $vars = []): array
    {
        if ($reflect->getNumberOfParameters() == 0) {
            return [];
        }

        // 判断数组类型 数字数组时按顺序绑定参数
        reset($vars);
        $type   = key($vars) === 0 ? 1 : 0;
        $params = $reflect->getParameters();
        $args   = [];

        foreach ($params as $param) {
            $name      = $param->getName();
            $lowerName = self::parseName($name);
            $class     = $param->getClass();

            if ($class) {
                $args[] = $this->getObjectParam($class->getName(), $vars);
            } elseif (1 == $type && !empty($vars)) {
                $args[] = array_shift($vars);
            } elseif (0 == $type && isset($vars[$name])) {
                $args[] = $vars[$name];
            } elseif (0 == $type && isset($vars[$lowerName])) {
                $args[] = $vars[$lowerName];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new InvalidArgumentException('method param miss:' . $name);
            }
        }

        return $args;
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @deprecated
     * @access public
     * @param string  $name    字符串
     * @param integer $type    转换类型
     * @param bool    $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public static function parseName(string $name = null, int $type = 0, bool $ucfirst = true): string
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }

    /**
     * 获取类名(不包含命名空间)
     * @deprecated
     * @access public
     * @param string|object $class
     * @return string
     */
    public static function classBaseName($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }

    /**
     * 创建工厂对象实例
     * @deprecated
     * @access public
     * @param string $name      工厂类名
     * @param string $namespace 默认命名空间
     * @param array  $args
     * @return mixed
     */
    public static function factory(string $name, string $namespace = '', ...$args)
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);

        if (class_exists($class)) {
            return Container::getInstance()->invokeClass($class, $args);
        }

        throw new ClassNotFoundException('class not exists:' . $class, $class);
    }

    /**
     * 获取对象类型的参数值
     * @access protected
     * @param string $className 类名
     * @param array  $vars      参数
     * @return mixed
     */
    protected function getObjectParam(string $className, array &$vars)
    {
        $array = $vars;
        $value = array_shift($array);

        if ($value instanceof $className) {
            $result = $value;
            array_shift($vars);
        } else {
            $result = $this->make($className);
        }

        return $result;
    }

    public function __set($name, $value)
    {
        $this->bind($name, $value);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name): bool
    {
        return $this->exists($name);
    }

    public function __unset($name)
    {
        $this->delete($name);
    }

    public function offsetExists($key)
    {
        return $this->exists($key);
    }

    public function offsetGet($key)
    {
        return $this->make($key);
    }

    public function offsetSet($key, $value)
    {
        $this->bind($key, $value);
    }

    public function offsetUnset($key)
    {
        $this->delete($key);
    }

    //Countable
    public function count()
    {
        return count($this->instances);
    }

    //IteratorAggregate
    public function getIterator()
    {
        return new ArrayIterator($this->instances);
    }
}
