<?php
/**
 * 应用内核
 */
namespace Mapping\Application\Kernel;

use Mapping\Application\Library\Tools;

class Kernel
{
    /** @var array 协议处理类储存 */
    protected $protocolObject = array();

    /**
     * @param string $terminal
     * @param string $event
     * @param \Workerman\Connection\AsyncTcpConnection $connection
     * @param \Mapping\Application\Library\Package\Context $context
     * @param string|null $protocol
     * @return bool|mixed
     */
    public function behavior($terminal, $event, $connection, $context, $protocol = null)
    {
        $protocol = is_null($protocol) ? $context->getAttribute()->getProtocol() : $protocol;
        $self = explode('\\', get_class($this));
        $self[count($self) - 1] = 'Protocol';
        $self[] = ucfirst($terminal);
        $self[] = ucfirst($protocol);
        $method = $event;
        $class = '\\' . implode('\\', $self);
        if (!class_exists($class)) {
            Tools::log('[Error] Class Not Exists: ' . $class);
            return false;
        }
        if (!method_exists($class, $method)) {
            Tools::log('[Error] Method Not Exists: ' . $class . '->' . $method);
            return false;
        }

        if (!isset($this->protocolObject[$class])) {
            $this->protocolObject[$class] = new $class();
        }
        $this->protocolObject[$class]->setConnection($connection);
        $this->protocolObject[$class]->setContext($context);
        $this->protocolObject[$class]->setProtocol($protocol);
        return call_user_func(array($this->protocolObject[$class], $method));
    }

    /**
     * 内核注册
     *
     * @param $terminal
     * @param \Mapping\Application\Kernel\Channel\Client|\Mapping\Application\Kernel\Channel\Server|null $protocol
     * @return void
     */
    public static function register($terminal, $protocol = null)
    {
        /** 管道数据事件注册 */
        $kernel = new self();
        // 授权数据监听
        BehaviorEvent::on(BehaviorEvent::EVENT_AUTHORIZE, function ($connection, $context) use ($terminal, &$kernel) {
            $kernel->behavior($terminal, BehaviorEvent::EVENT_AUTHORIZE, $connection, $context);
        });
        // 数据转发数据包监听
        BehaviorEvent::on(BehaviorEvent::EVENT_FORWARD, function ($connection, $context) use ($terminal, &$kernel) {
            $kernel->behavior($terminal, BehaviorEvent::EVENT_FORWARD, $connection, $context);
        });
        // 指令数据包监听
        BehaviorEvent::on(BehaviorEvent::EVENT_COMMAND, function ($connection, $context) use ($terminal, &$kernel) {
            $kernel->behavior($terminal, BehaviorEvent::EVENT_COMMAND, $connection, $context);
        });
        // 外网请求连接
        BehaviorEvent::on(BehaviorEvent::EVENT_CONNECT, function ($connection, $context) use ($terminal, &$kernel) {
            $kernel->behavior($terminal, BehaviorEvent::EVENT_CONNECT, $connection, $context);
        });
        // 外网请求断开连接
        BehaviorEvent::on(BehaviorEvent::EVENT_CLOSE, function ($connection, $context) use ($terminal, &$kernel) {
            $kernel->behavior($terminal, BehaviorEvent::EVENT_CLOSE, $connection, $context);
        });
        /** 内部事件注册 */
        // 管道客户端上线
        BehaviorEvent::on('online', function ($connection, $protocol = null) use ($terminal, &$kernel) {
            $kernel->behavior($terminal, 'online', $connection, null, $protocol);
        });
        // 管道客户端下线
        BehaviorEvent::on('offline', function ($connection, $protocol = null) use ($terminal, &$kernel) {
            $kernel->behavior($terminal, 'offline', $connection, null, $protocol);
        });
    }
}