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

use think\helper\Str;
use think\initializer\BootService;
use think\initializer\Error;
use think\initializer\RegisterService;

/**
 * App 基础类
 * @property Route      $route
 * @property Config     $config
 * @property Cache      $cache
 * @property Request    $request
 * @property Http       $http
 * @property Console    $console
 * @property Env        $env
 * @property Event      $event
 * @property Middleware $middleware
 * @property Log        $log
 * @property Lang       $lang
 * @property Db         $db
 * @property Cookie     $cookie
 * @property Session    $session
 * @property Validate   $validate
 * @property Filesystem $filesystem
 */
class App extends Container
{
    const VERSION = '6.0.0RC3';

    /**
     * 应用调试模式
     * @var bool
     */
    protected $appDebug = false;

    /**
     * 应用开始时间
     * @var float
     */
    protected $beginTime;

    /**
     * 应用内存初始占用
     * @var integer
     */
    protected $beginMem;

    /**
     * 当前应用类库命名空间
     * @var string
     */
    protected $namespace = 'app';

    /**
     * 应用根目录
     * @var string
     */
    protected $rootPath = '';

    /**
     * 框架目录
     * @var string
     */
    protected $thinkPath = '';

    /**
     * 应用目录
     * @var string
     */
    protected $appPath = '';

    /**
     * Runtime目录
     * @var string
     */
    protected $runtimePath = '';

    /**
     * 配置后缀
     * @var string
     */
    protected $configExt = '.php';

    /**
     * 应用初始化器
     * @var array
     */
    protected $initializers = [
        Error::class,
        RegisterService::class,
        BootService::class,
    ];

    /**
     * 注册的系统服务
     * @var array
     */
    protected $services = [];

    /**
     * 初始化
     * @var bool
     */
    protected $initialized = false;

    /**
     * 容器绑定标识
     * @var array
     */
    protected $bind = [
        'app'                     => App::class,
        'cache'                   => Cache::class,
        'config'                  => Config::class,
        'console'                 => Console::class,
        'cookie'                  => Cookie::class,
        'db'                      => Db::class,
        'env'                     => Env::class,
        'event'                   => Event::class,
        'http'                    => Http::class,
        'lang'                    => Lang::class,
        'log'                     => Log::class,
        'middleware'              => Middleware::class,
        'request'                 => Request::class,
        'response'                => Response::class,
        'route'                   => Route::class,
        'session'                 => Session::class,
        'validate'                => Validate::class,
        'view'                    => View::class,
        'filesystem'              => Filesystem::class,
        'think\DbManager'         => Db::class,
        'think\LogManager'        => Log::class,
        'think\CacheManager'      => Cache::class,

        // 接口依赖注入
        'Psr\Log\LoggerInterface' => Log::class,
    ];

    /**
     * 架构方法
     * @access public
     * @param string $rootPath 应用根目录
     */
    public function __construct(string $rootPath = '')
    {
        //thinkPath目录：D:\dev\tp6\vendor\topthink\framework\src\
        $this->thinkPath   = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        //项目根目录，如：D:\dev\tp6\
        $this->rootPath    = $rootPath ? rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : $this->getDefaultRootPath();
        $this->appPath     = $this->rootPath . 'app' . DIRECTORY_SEPARATOR;
        $this->runtimePath = $this->rootPath . 'runtime' . DIRECTORY_SEPARATOR;
        
        //如果存在「绑定类库到容器」文件
        if (is_file($this->appPath . 'provider.php')) {
            //将文件里的所有映射合并到容器的「$bind」成员变量中
            $this->bind(include $this->appPath . 'provider.php');
        }
        
        //将当前容器实例保存到成员变量「$instance」中，也就是容器自己保存自己的一个实例
        static::setInstance($this);
        //保存绑定的实例到「$instances」数组中
        $this->instance('app', $this);
        $this->instance('think\Container', $this);
    }

    /**
     * 注册服务
     * @access public
     * @param Service|string $service 服务
     * @param bool           $force   强制重新注册
     * @return Service|null
     */
    public function register($service, bool $force = false)
    {
        $registered = $this->getService($service);

        if ($registered && !$force) {
            return $registered;
        }

        if (is_string($service)) {
            $service = new $service($this);
        }

        if (method_exists($service, 'register')) {
            $service->register();
        }

        if (property_exists($service, 'bind')) {
            $this->bind($service->bind);
        }

        $this->services[] = $service;
    }

    /**
     * 执行服务
     * @access public
     * @param Service $service 服务
     * @return mixed
     */
    public function bootService($service)
    {
        if (method_exists($service, 'boot')) {
            return $this->invoke([$service, 'boot']);
        }
    }

    /**
     * 获取服务
     * @param string|Service $service
     * @return Service|null
     */
    public function getService($service)
    {
        $name = is_string($service) ? $service : get_class($service);
        return array_values(array_filter($this->services, function ($value) use ($name) {
            return $value instanceof $name;
        }, ARRAY_FILTER_USE_BOTH))[0] ?? null;
    }

    /**
     * 开启应用调试模式
     * @access public
     * @param bool $debug 开启应用调试模式
     * @return $this
     */
    public function debug(bool $debug = true)
    {
        $this->appDebug = $debug;
        return $this;
    }

    /**
     * 是否为调试模式
     * @access public
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->appDebug;
    }

    /**
     * 设置应用命名空间
     * @access public
     * @param string $namespace 应用命名空间
     * @return $this
     */
    public function setNamespace(string $namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * 获取应用类库命名空间
     * @access public
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * 获取框架版本
     * @access public
     * @return string
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * 获取应用根目录
     * @access public
     * @return string
     */
    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    /**
     * 获取应用基础目录
     * @access public
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->rootPath . 'app' . DIRECTORY_SEPARATOR;
    }

    /**
     * 获取当前应用目录
     * @access public
     * @return string
     */
    public function getAppPath(): string
    {
        return $this->appPath;
    }

    /**
     * 设置应用目录
     * @param $path
     */
    public function setAppPath($path)
    {
        $this->appPath = $path;
    }

    /**
     * 获取应用运行时目录
     * @access public
     * @return string
     */
    public function getRuntimePath(): string
    {
        return $this->runtimePath;
    }

    /**
     * 设置runtime目录
     * @param $path
     */
    public function setRuntimePath($path)
    {
        $this->runtimePath = $path;
    }

    /**
     * 获取核心框架目录
     * @access public
     * @return string
     */
    public function getThinkPath(): string
    {
        return $this->thinkPath;
    }

    /**
     * 获取应用配置目录
     * @access public
     * @return string
     */
    public function getConfigPath(): string
    {
        return $this->rootPath . 'config' . DIRECTORY_SEPARATOR;
    }

    /**
     * 获取配置后缀
     * @access public
     * @return string
     */
    public function getConfigExt(): string
    {
        return $this->configExt;
    }

    /**
     * 获取应用开启时间
     * @access public
     * @return float
     */
    public function getBeginTime(): float
    {
        return $this->beginTime;
    }

    /**
     * 获取应用初始内存占用
     * @access public
     * @return integer
     */
    public function getBeginMem(): int
    {
        return $this->beginMem;
    }

    /**
     * 初始化应用
     * @access public
     * @return $this
     */
    public function initialize()
    {
        $this->initialized = true;

        //记录开始时间
        $this->beginTime = microtime(true);
        //记录起始内存使用量
        $this->beginMem  = memory_get_usage();

        // 加载环境变量
        // $this->env跟前面的(new App())->http和$this->config都是同样的套路
        if (is_file($this->rootPath . '.env')) {
            $this->env->load($this->rootPath . '.env');
        }
        //设置配置文件后缀
        //这里有个问题是，每次调用$this->env，都会重新实例化一次，
        //环境变量会改变，可能这种每次实例化是必要的
        $this->configExt = $this->env->get('config_ext', '.php');
        //设置调试模式
        $this->debugModeInit();

        // 加载全局初始化文件
        $this->load();

        // 加载框架默认语言包
        $langSet = $this->lang->defaultLangSet();
        // 框架目录下对应的语言包
        // 比如：D:\dev\tp6\vendor\topthink\framework\src\lang\zh-cn.php
        $this->lang->load($this->thinkPath . 'lang' . DIRECTORY_SEPARATOR . $langSet . '.php');

        // 加载应用默认语言包
        // 这个会扫描「app/lang」里面，对应语言包文件夹的所有「.php」文件
        // 比如，app/lang/zh-cn/* 下的所有文件
        // 然后加载解析
        $this->loadLangPack($langSet);

        // 监听AppInit
        $this->event->trigger('AppInit');

        // 设置时区
        date_default_timezone_set($this->config->get('app.default_timezone', 'Asia/Shanghai'));

        // 初始化
        // 初始化错误和异常处理、注册系统服务和初始化系统服务
        foreach ($this->initializers as $initializer) {
            $this->make($initializer)->init($this);
        }

        return $this;
    }

    /**
     * 是否初始化过
     * @return bool
     */
    public function initialized()
    {
        return $this->initialized;
    }

    /**
     * 加载语言包
     * @param string $langset 语言
     * @return void
     */
    public function loadLangPack($langset)
    {
        if (empty($langset)) {
            return;
        }

        // 加载系统语言包
        $files = glob($this->appPath . 'lang' . DIRECTORY_SEPARATOR . $langset . '.*');
        $this->lang->load($files);

        // 加载扩展（自定义）语言包
        $list = $this->config->get('lang.extend_list', []);

        if (isset($list[$langset])) {
            $this->lang->load($list[$langset]);
        }
    }

    /**
     * 引导应用
     * @access public
     * @return void
     */
    public function boot(): void
    {
        array_walk($this->services, function ($service) {
            $this->bootService($service);
        });
    }

    /**
     * 加载应用文件和配置
     * @access protected
     * @return void
     */
    protected function load(): void
    {
        $appPath = $this->getAppPath();
        
        // 加载「app」目录下的「common.php」文件
        if (is_file($appPath . 'common.php')) {
            include_once $appPath . 'common.php';
        }
        // 加载核心目录下的「helper.php」文件
        // 可以看到，这里的加载顺序：先「common.php」，后「helper.php」
        // 且「helper.php」中的函数包括在「if (!function_exists('abort'))」下
        // 所以可以在「common.php」文件中覆盖系统定义的助手函数
        include_once $this->thinkPath . 'helper.php';

        $configPath = $this->getConfigPath();

        $files = [];
        
        // glob的作用是扫描给定路径模式下的文件，非常好用
        // 这里扫描「config」目录下的所有「.php」文件
        if (is_dir($configPath)) {
            $files = glob($configPath . '*' . $this->configExt);
        }

        foreach ($files as $file) {
            // $this->config 还是同样的套路获得了「Config」类的实例
            // 「load」的第二个参数为一级配置名，这里传入一个文件名，所有文件名作为一级配置
            // 比如「app.php」配置文件，一级配置为「app」
            // 在 「Config」类作用域下的操作：
            // 「load」加载文件后，通过「parse」方法解析文件内容
            // 最后，通过「set」方法将所有配置合并了「config」成员变量
            $this->config->load($file, pathinfo($file, PATHINFO_FILENAME));
        }
        
        // 加载「app」目录下的「event.php」文件
        if (is_file($appPath . 'event.php')) {
            $this->loadEvent(include $appPath . 'event.php');
        }
        // 注册自定义的服务
        if (is_file($appPath . 'service.php')) {
            $services = include $appPath . 'service.php';
            foreach ($services as $service) {
                $this->register($service);
            }
        }
    }

    /**
     * 调试模式设置
     * @access protected
     * @return void
     */
    protected function debugModeInit(): void
    {
        // 应用调试模式
        // 「$appDebug」默认为false
        // 沒有发现别的地方修改了「$appDebug」，程序好像永远关闭调试模式
        // 这应该是一个Bug
        // 正确的做法应该是先从Env类获取「app_debug」的值来判断是否是调试模式
        if (!$this->appDebug) {
            $this->appDebug = $this->env->get('app_debug') ? true : false;
            // 关闭错误显示
            ini_set('display_errors', 'Off');
        }
        // 如果不是命令行模式
        if (!$this->runningInConsole()) {
            //重新申请一块比较大的buffer
            // php缓冲控制
            // 参考：https://www.php.net/manual/en/ref.outcontrol.php
            // https://www.cnblogs.com/saw2012/archive/2013/01/30/2882451.html
            // 新版PHP默认开启缓冲区ob_start()，ob_get_level() == 1
            if (ob_get_level() > 0) {
                //相当于ob_get_contents() 和 ob_clean()
                // 获取缓冲区内容并删除缓冲区内容
                $output = ob_get_clean();
            }
            // 开启新的缓冲区控制
            ob_start();
            if (!empty($output)) {
                // 由于开启了新的缓冲区控制，
                // 这里不会直接输出$output
                // 而是等到依次执行了ob_flush()和flash()之后才将内容输出到浏览器
                echo $output;
            }
        }
    }

    /**
     * 注册应用事件
     * @access protected
     * @param array $event 事件数据
     * @return void
     */
    public function loadEvent(array $event): void
    {
        if (isset($event['bind'])) {
            // 老方法，简单实用，实例化「Event」类，然后调用它的「bind」方法
            // 将事件标识到事件（操作，比如一个控制器操作）的映射合并到「Event」类「$bing」成员变量中
            // 比如 'UserLogin' => 'app\event\UserLogin',
            $this->event->bind($event['bind']);
        }

        if (isset($event['listen'])) {
            // 合并所有观察者（监听者）到Event类的listener数组
            // 其形式为实际事件（被观察者）到观察者的映射
            $this->event->listenEvents($event['listen']);
        }

        if (isset($event['subscribe'])) {
            // 订阅，实际上是一个批量的监听
            // 就像一个人可以订阅多份报纸
            // 一个订阅器，里面可以实现多个事件的监听
            // 比如，我在一个订阅器中，同时监听用户登录，用户退出等操作
            $this->event->subscribe($event['subscribe']);
        }
    }

    /**
     * 解析应用类的类名
     * @access public
     * @param string $layer 层名 controller model ...
     * @param string $name  类名
     * @return string
     */
    public function parseClass(string $layer, string $name): string
    {
        $name  = str_replace(['/', '.'], '\\', $name);
        $array = explode('\\', $name);
        $class = Str::studly(array_pop($array));
        $path  = $array ? implode('\\', $array) . '\\' : '';

        return $this->namespace . '\\' . $layer . '\\' . $path . $class;
    }

    /**
     * 是否运行在命令行下
     * @return bool
     */
    public function runningInConsole()
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * 获取应用根目录
     * @access protected
     * @return string
     */
    protected function getDefaultRootPath(): string
    {
        $path = dirname(dirname(dirname(dirname($this->thinkPath))));

        return $path . DIRECTORY_SEPARATOR;
    }

}
