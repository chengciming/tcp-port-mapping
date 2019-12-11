<?php
/**
 * 公共工具类
 */
namespace Mapping\Application\Library;

use Workerman\Worker;

class Tools {
    /**
     * 缓存配置
     * @var array
     */
    protected static $config = array();

    /**
     * 日记打印
     * @param $message
     */
    public static function log($message)
    {
        $debug = Tools::config('debug');
        if (function_exists('getmypid')) {
            $message = '[PID:'.getmypid().'] ' . $message;
        } else if (function_exists('posix_getpid')) {
            $message = '[PID:'.posix_getpid().'] ' . $message;
        } else if (function_exists('getmyuid')) {
            $message = '[UID:'.getmyuid().'] ' . $message;
        }
        if ($debug) {
            Worker::log($message);
        }
    }

    /**
     * 获取公共配置
     *
     * @param null $field
     * @param string $configName
     * @return array|mixed|null
     */
    public static function config($field = null, $configName = 'Config')
    {
        $config = self::loadConf($configName);
        if (is_null($field)) {
            return $config;
        }
        return self::getNestedVar($config, $field);
    }

    /**
     * 加载Config目录下的配置
     * @param string $configName 配置文件名称
     * @return array
     */
    public static function loadConf($configName = 'Config'){
        if(isset(self::$config[$configName])){
            return self::$config[$configName];
        }
        $filename = ROOT_PATH . '/Config/' . ucfirst($configName) . '.php';
        if(file_exists($filename)){
            $conf = require($filename);
            self::$config[$configName] = $conf;
            return self::$config[$configName];
        }
        return array();
    }

    /**
     * 支持用xxx.xxx.xx获取数组
     *
     * @param $context
     * @param $name
     * @return mixed|null
     */
    public static function getNestedVar($context, $name)
    {
        $pieces = explode('.', $name);
        foreach ($pieces as $piece) {
            if (!is_array($context) || !array_key_exists($piece, $context)) {
                // error occurred
                return null;
            }
            $context = &$context[$piece];
        }
        return $context;
    }

    /**
     * http_build_query数组转字符串
     *
     * @param $attr
     * @return string
     */
    public static function buildQuery($attr)
    {
        return http_build_query($attr);
    }

    /**
     * http_build_query解析成数组
     *
     * @param $attrQuery
     * @return array
     */
    public static function parseQuery($attrQuery)
    {
        $data = array();
        if (empty($attrQuery)) {
            return $data;
        }
        $attr = explode('&', $attrQuery);
        foreach ($attr as $query) {
            $attribute = explode('=', $query);
            $data[$attribute[0]] = isset($attribute[1]) ? $attribute[1] : null;
        }
        return $data;
    }

    /**
     * 类注入 - PHP反射
     * @param $class
     * @param array $parameters
     * @return object
     * @throws \Exception
     */
    public static function injection($class, $parameters = array())
    {
        // 通过反射获取反射类
        $rel_class = new \ReflectionClass($class);

        // 查看是否可以实例化
        if (! $rel_class->isInstantiable()) {
            throw new \Exception($class . ' 类不可实例化');
        }

        // 查看是否用构造函数
        $rel_method = $rel_class->getConstructor();

        // 没有构造函数的话，就可以直接 new 本类型了
        if (is_null($rel_method)) {
            return new $class();
        }

        // 有构造函数的话就获取构造函数的参数
        $dependencies = $rel_method->getParameters();

        // 处理，把传入的索引数组变成关联数组， 键为函数参数的名字
        foreach ($parameters as $key => $value) {
            if (is_numeric($key)) {
                // 删除索引数组， 只留下关联数组
                unset($parameters[$key]);
                // 用参数的名字做为键
                $parameters[$dependencies[$key]->name] = $value;
            }
        }

        // 处理依赖关系
        $actual_parameters = array();

        foreach ($dependencies as $dependenci) {
            // 获取对象名字，如果不是对象返回 null
            $class_name = $dependenci->getClass();
            // 获取变量的名字
            $var_name = $dependenci->getName();

            // 如果是对象， 则递归new
            if (array_key_exists($var_name, $parameters)) {
                $actual_parameters[] = $parameters[$var_name];
            } elseif (is_null($class_name)) {
                // null 则不是对象，看有没有默认值， 如果没有就要抛出异常
                if (! $dependenci->isDefaultValueAvailable()) {
                    throw new \Exception($var_name . ' 参数没有默认值');
                }

                $actual_parameters[] = $dependenci->getDefaultValue();
            } else {
                $actual_parameters[] = self::make($class_name->getName());
            }
        }

        // 获得构造函数的数组之后就可以实例化了
        return $rel_class->newInstanceArgs($actual_parameters);
    }
}
