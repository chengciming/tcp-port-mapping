<?php
namespace Mapping\Application\Kernel;

use Mapping\Application\Library\Event;

class BehaviorEvent
{
    /** @var string 行为事件定义 */
    const EVENT_AUTHORIZE     = 'authorize';   // 授权
    const EVENT_FORWARD       = 'forward';     // 数据转发
    const EVENT_COMMAND       = 'command';     // 指令
    const EVENT_CONNECT       = 'connect';     // 外网客户端连接
    const EVENT_CLOSE         = 'close';       // 外网客户端关闭连接
    const EVENT_HEARTBEAT     = 'heartbeat';   // 心跳包

    /**
     * 按事件分发数据
     *
     * @param $connection
     * @param array[\Mapping\Application\Library\Package\Context] $contextList
     * @return bool
     */
    public static function contextDistribute($connection, $contextList)
    {
        if (empty($contextList)) {
            return false;
        }
        foreach ($contextList as $context) {
            self::emit($context->getAttribute()->getEvent(), $connection, $context);
        }
        return true;
    }

    /**
     * 事件触发
     *
     * @param $event
     * @param $connection
     * @param \Mapping\Application\Library\Package\Context $context
     * @return void
     */
    public static function emit($event, $connection, $context = null)
    {
        // 事件触发
        Event::emit('behavior_event_' . $event, array($connection, $context));
    }

    /**
     * 订阅事件
     *
     * @param $event
     * @param $closure
     * @return void
     */
    public static function on($event, $closure)
    {
        // 储存事件到事件池
        Event::on('behavior_event_' . $event, $closure);
    }
}
