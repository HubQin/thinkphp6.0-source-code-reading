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

use Closure;
use think\event\RouteLoaded;
use think\exception\Handle;
use think\exception\HttpException;
use Throwable;

/**
 * Web应用管理类
 * @package think
 */
class Http
{

    /**
     * @var App
     */
    protected $app;

    /**
     * 应用路径
     * @var string
     */
    protected $path;

    /**
     * 是否多应用模式
     * @var bool
     */
    protected $multi = false;

    /**
     * 是否域名绑定应用
     * @var bool
     */
    protected $bindDomain = false;

    /**
     * 应用名称
     * @var string
     */
    protected $name;

    public function __construct(App $app)
    {
        $this->app   = $app;
        //多应用解析，通过判断「app」目录下有无「controller」目录，没有就是多应用模式
        $this->multi = is_dir($this->app->getBasePath() . 'controller') ? false : true;
    }

    /**
     * 是否域名绑定应用
     * @access public
     * @return bool
     */
    public function isBindDomain(): bool
    {
        return $this->bindDomain;
    }

    /**
     * 设置应用模式
     * @access public
     * @param bool $multi
     * @return $this
     */
    public function multi(bool $multi)
    {
        $this->multi = $multi;
        return $this;
    }

    /**
     * 是否为多应用模式
     * @access public
     * @return bool
     */
    public function isMulti(): bool
    {
        return $this->multi;
    }

    /**
     * 设置应用名称
     * @access public
     * @param string $name 应用名称
     * @return $this
     */
    public function name(string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 获取应用名称
     * @access public
     * @return string
     */
    public function getName(): string
    {
        return $this->name ?: '';
    }

    /**
     * 设置应用目录
     * @access public
     * @param string $path 应用目录
     * @return $this
     */
    public function path(string $path)
    {
        if (substr($path, -1) != DIRECTORY_SEPARATOR) {
            $path .= DIRECTORY_SEPARATOR;
        }

        $this->path = $path;
        return $this;
    }

    /**
     * 执行应用程序
     * @access public
     * @param Request|null $request
     * @return Response
     */
    public function run(Request $request = null): Response
    {
        //自动创建request对象
        $request = $request ?? $this->app->make('request', [], true);
        // 将Request类的实例保存到「$instances」数组
        $this->app->instance('request', $request);

        try {
            $response = $this->runWithRequest($request);
        } catch (Throwable $e) {
            $this->reportException($e);

            $response = $this->renderException($request, $e);
        }
        // 设置cookie并返回响应对象
        return $response->setCookie($this->app->cookie);
    }

    /**
     * 初始化
     */
    protected function initialize()
    {
        //如果还未初始化，则初始化之
        if (!$this->app->initialized()) {
            $this->app->initialize();
        }
    }

    /**
     * 执行应用程序
     * @param Request $request
     * @return mixed
     */
    protected function runWithRequest(Request $request)
    {
        $this->initialize();

        // 加载全局中间件
        if (is_file($this->app->getBasePath() . 'middleware.php')) {
            $this->app->middleware->import(include $this->app->getBasePath() . 'middleware.php');
        }

        if ($this->multi) {
            $this->parseMultiApp();
        }

        // 设置开启事件机制
        $this->app->event->withEvent($this->app->config->get('app.with_event', true));

        // 监听HttpRun
        $this->app->event->trigger('HttpRun');
        // 是否开启路由
        // 开启的话将一个闭包赋值给$withRoute变量
        $withRoute = $this->app->config->get('app.with_route', true) ? function () {
            //加载路由文件
            $this->loadRoutes();
        } : null;
        // 路由调度
        return $this->app->route->dispatch($request, $withRoute);
    }

    /**
     * 加载路由
     * @access protected
     * @return void
     */
    protected function loadRoutes(): void
    {
        // 加载路由定义
        $routePath = $this->getRoutePath();

        if (is_dir($routePath)) {
            $files = glob($routePath . '*.php');
            foreach ($files as $file) {
                include $file;
            }
        }

        $this->app->event->trigger(RouteLoaded::class);
    }

    /**
     * 获取路由目录
     * @access protected
     * @return string
     */
    protected function getRoutePath(): string
    {
        if ($this->isMulti() && is_dir($this->app->getAppPath() . 'route')) {
            return $this->app->getAppPath() . 'route' . DIRECTORY_SEPARATOR;
        }

        return $this->app->getRootPath() . 'route' . DIRECTORY_SEPARATOR . ($this->isMulti() ? $this->getName() . DIRECTORY_SEPARATOR : '');
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param Throwable $e
     * @return void
     */
    protected function reportException(Throwable $e)
    {
        $this->app->make(Handle::class)->report($e);
    }

    /**
     * Render the exception to a response.
     *
     * @param Request   $request
     * @param Throwable $e
     * @return Response
     */
    protected function renderException($request, Throwable $e)
    {
        return $this->app->make(Handle::class)->render($request, $e);
    }

    /**
     * 获取当前运行入口名称
     * @access protected
     * @codeCoverageIgnore
     * @return string
     */
    protected function getScriptName(): string
    {
        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            $file = $_SERVER['SCRIPT_FILENAME'];
        } elseif (isset($_SERVER['argv'][0])) {
            $file = realpath($_SERVER['argv'][0]);
        }

        return isset($file) ? pathinfo($file, PATHINFO_FILENAME) : '';
    }

    /**
     * 解析多应用
     */
    protected function parseMultiApp(): void
    {
        // 虽然在「Http」的构造函数自动判断过是否开启多应用
        // 最终还要看配置文件是否有配置
        if ($this->app->config->get('app.auto_multi_app', false)) {
            // 自动多应用识别
            $this->bindDomain = false;
            // 获取域名绑定
            $bind = $this->app->config->get('app.domain_bind', []);
            // 如果有域名绑定
            if (!empty($bind)) {
                // 获取当前子域名
                $subDomain = $this->app->request->subDomain();
                $domain    = $this->app->request->host(true);

                //完整域名绑定
                if (isset($bind[$domain])) {
                    $appName          = $bind[$domain];
                    $this->bindDomain = true;
                    //子域名绑定
                } elseif (isset($bind[$subDomain])) {
                    $appName          = $bind[$subDomain];
                    $this->bindDomain = true;
                    //二级泛域名绑定
                } elseif (isset($bind['*'])) {
                    $appName          = $bind['*'];
                    $this->bindDomain = true;
                }
            }
            //如果没有域名绑定
            if (!$this->bindDomain) {
                //获取别名映射
                $map  = $this->app->config->get('app.app_map', []);
                //获取禁止URL访问目录
                $deny = $this->app->config->get('app.deny_app_list', []);
                //获取当前请求URL的pathinfo信息（含URL后缀）
                // 比如 index/index/index
                $path = $this->app->request->pathinfo();
                // 比如，从index/index/index获取得index
                $name = current(explode('/', $path));
                //解析别名映射
                if (isset($map[$name])) {
                    //如果这个别名映射到的是一个闭包
                    //这样不知有啥用
                    if ($map[$name] instanceof Closure) {
                        $result  = call_user_func_array($map[$name], [$this]);
                        $appName = $result ?: $name;
                        //直接取得应用名
                    } else {
                        $appName = $map[$name];
                    }
                    //$name不为空且$name在$map数组中作为KEY，或者$name是禁止URL方位的目录
                } elseif ($name && (false !== array_search($name, $map) || in_array($name, $deny))) {
                    throw new HttpException(404, 'app not exists:' . $name);
                } elseif ($name && isset($map['*'])) {
                    $appName = $map['*'];
                } else {
                    $appName = $name;
                }

                if ($name) {
                    $this->app->request->setRoot('/' . $name);
                    $this->app->request->setPathinfo(strpos($path, '/') ? ltrim(strstr($path, '/'), '/') : '');
                }
            }
        } else {
            $appName = $this->name ?: $this->getScriptName();
        }

        $this->loadApp($appName ?: $this->app->config->get('app.default_app', 'index'));
    }

    /**
     * 加载应用文件
     * @param string $appName 应用名
     * @return void
     */
    protected function loadApp(string $appName): void
    {
        //设置当前应用名，$this->app的值
        $this->name = $appName;
        $this->app->request->setApp($appName);
        //设置应用目录
        $this->app->setAppPath($this->path ?: $this->app->getBasePath() . $appName . DIRECTORY_SEPARATOR);
        $this->app->setRuntimePath($this->app->getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . $appName . DIRECTORY_SEPARATOR);

        //加载app文件
        if (is_dir($this->app->getAppPath())) {
            $appPath = $this->app->getAppPath();

            if (is_file($appPath . 'common.php')) {
                include_once $appPath . 'common.php';
            }

            $configPath = $this->app->getConfigPath();

            $files = [];

            if (is_dir($configPath . $appName)) {
                $files = array_merge($files, glob($configPath . $appName . DIRECTORY_SEPARATOR . '*' . $this->app->getConfigExt()));
            } elseif (is_dir($appPath . 'config')) {
                $files = array_merge($files, glob($appPath . 'config' . DIRECTORY_SEPARATOR . '*' . $this->app->getConfigExt()));
            }

            foreach ($files as $file) {
                $this->app->config->load($file, pathinfo($file, PATHINFO_FILENAME));
            }

            if (is_file($appPath . 'event.php')) {
                $this->app->loadEvent(include $appPath . 'event.php');
            }

            if (is_file($appPath . 'middleware.php')) {
                $this->app->middleware->import(include $appPath . 'middleware.php');
            }

            if (is_file($appPath . 'provider.php')) {
                $this->app->bind(include $appPath . 'provider.php');
            }
        }

        // 加载应用默认语言包
        $this->app->loadLangPack($this->app->lang->defaultLangSet());

        // 设置应用命名空间
        $this->app->setNamespace($this->app->config->get('app.app_namespace') ?: 'app\\' . $appName);
    }

    /**
     * HttpEnd
     * @param Response $response
     * @return void
     */
    public function end(Response $response): void
    {
        $this->app->event->trigger('HttpEnd', $response);

        // 写入日志
        $this->app->log->save();
        // 写入Session
        $this->app->session->save();
    }

    public function __debugInfo()
    {
        return [
            'path'       => $this->path,
            'multi'      => $this->multi,
            'bindDomain' => $this->bindDomain,
            'name'       => $this->name,
        ];
    }
}
