<?php

class App
{
    /**
     * 注册
     *
     * @return void
     */
    public static function register()
    {
        // Display errors.
        ini_set('display_errors', 'on');
        // Reporting all.
        error_reporting(E_ALL);
        // 定义根目录
        define('ROOT_PATH', str_replace(array('/'.basename(__FILE__), '\\'.basename(__FILE__)), '', __FILE__));
        // 自动加载
        require_once ROOT_PATH . '/vendor/autoload.php';
    }

    /**
     * 开始运行
     *
     * @param \Mapping\Application\Client|\Mapping\Application\Server $object
     * @return void
     * @throws Exception
     */
    public static function run($object)
    {
        // 注册
        self::register();
        // 启动
        \Mapping\Application\Handle::run($object);
    }
}



