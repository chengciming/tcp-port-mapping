<?php
namespace Mapping\Application\Library;

/**
 * 事件池
 */
class Event
{
    /** @var array 事件回调方法储存 */
    protected static $eventClosure = array();

    /**
     * 事件消费
     *
     * @param $event
     * @param $params
     * @return bool
     */
    public static function emit($event, $params)
    {
        if (!isset(self::$eventClosure[$event])) {
            return false;
        }
        call_user_func_array(self::$eventClosure[$event], $params);
        return true;
    }

    /**
     * 订阅事件
     *
     * @param $event
     * @param $closure
     * @return bool
     */
    public static function on($event, $closure)
    {
        // 追加事件
        self::$eventClosure[$event] = $closure;
        return true;
    }

    /**
     * 注销事件
     *
     * @param $event
     * @return bool
     */
    public static function un($event)
    {
        // 一次性注销多个
        if (is_array($event)) {
            if (!empty($event)) {
                foreach ($event as $e) {
                    unset(self::$eventClosure[$e]);
                }
            }
            return true;
        }
        // 事件不存在，直接返回
        if (!isset(self::$eventClosure[$event])) {
            return false;
        }
        unset(self::$eventClosure[$event]);
        return true;
    }
}
