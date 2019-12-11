<?php
/**
 * 应用注入
 */
namespace Mapping\Application;

class Handle
{
    /**
     * 初始化
     * @param $object
     * @return mixed
     * @throws \Exception
     */
    public static function run($object)
    {
        $object = new $object();
        return call_user_func(array($object, 'handle'));
    }
}
