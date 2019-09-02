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

use think\Model;
use think\Service;

/**
 * 模型服务类
 */
class ModelService extends Service
{
    public function boot()
    {
        // 设置Db对象
        Model::setDb($this->app->db);
        // 设置Event对象
        Model::setEvent($this->app->event);
        // 设置容器对象的依赖注入方法
        Model::setInvoker([$this->app, 'invoke']);
        // 保存闭包到Model::maker
        Model::maker(function (Model $model) {
            //保存db对象
            $db     = $this->app->db;
            //保存$config对象
            $config = $this->app->config;
            // 是否需要自动写入时间戳 如果设置为字符串 则表示时间字段的类型
            $isAutoWriteTimestamp = $model->getAutoWriteTimestamp();

            if (is_null($isAutoWriteTimestamp)) {
                // 自动写入时间戳 （从配置文件获取）
                $model->isAutoWriteTimestamp($config->get('database.auto_timestamp', 'timestamp'));
            }
            // 时间字段显示格式
            $dateFormat = $model->getDateFormat();

            if (is_null($dateFormat)) {
                // 设置时间戳格式 （从配置文件获取）
                $model->setDateFormat($config->get('database.datetime_format', 'Y-m-d H:i:s'));
            }

        });
    }
}
