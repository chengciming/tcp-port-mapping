<?php
namespace Mapping\Application\Kernel\Channel;

use Mapping\Application\Kernel\BehaviorEvent;
use Mapping\Application\Library\Event;
use Mapping\Application\Library\Package\Package;
use Mapping\Application\Library\Tools;
use Workerman\Worker;

/**
 * server.
 */
class Server
{
    /** 终端标志 */
    const TERMINAL = 'server';

    /** @var string|null 管道ID */
    protected $id = 'default';

    /** @var array 订阅的事件列表 */
    protected $eventList = array();

    /** @var Worker|null Worker对象 */
    protected $worker = null;

    /** @var string 监听的IP */
    protected $ip = '0.0.0.0';

    /** @var int 监听的端口 */
    protected $port = 0;

    /**
     * 设置ID
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * 获取ID
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 设置监听端口和IP
     *
     * @param string $ip
     * @param int $port
     * @return void
     */
    public function set($ip = '0.0.0.0', $port = 0)
    {
        $this->port = $port;
        $this->ip = $ip;
    }

    /**
     * 设置工作进程
     *
     * @param Worker $worker
     * @return void
     */
    public function setWorker(&$worker)
    {
        $this->worker = &$worker;
    }

    /**
     * 准备工作
     *
     * @return void
     */
    public function ready()
    {
        $this->worker = new Worker("tcp://$this->ip:$this->port");
        $this->worker->reusePort = true;  // 允许不同进程共同监听
        $this->worker->onMessage = array($this, 'onMessage') ;
        $this->worker->onClose = array($this, 'onClose');
        $this->worker->onConnect = array($this, 'onConnect');
        $this->worker->onError = array($this, 'onError');
    }

    /**
     * 开始工作
     *
     * @return void
     * @throws \Exception
     */
    public function listen()
    {
        $this->worker->listen();
    }

    /**
     * 获取监听状态
     * @return int
     */
    public function getStatus()
    {
        return $this->worker->getStatus();
    }

    /**
     * 关闭监听
     */
    public function unlisten()
    {
        $this->worker->unlisten();
        // 注销资源
        $this->__destory();
    }

    /**
     * 报错
     *
     * @param $connection
     * @param $code
     * @param $msg
     * @return void
     */
    public function onError($connection, $code, $msg)
    {
        // 事件触发
        Event::emit($this->buildEventName('error'), array($connection, $code, $msg));
        // 打印日记
        Tools::log('Error: ' . $code . ' - ' . $msg);
    }

    /**
     * 关闭连接事件
     *
     * @param \Workerman\Connection\TcpConnection $connection
     * @return void
     */
    public function onClose($connection)
    {
        // 事件触发
        Event::emit($this->buildEventName('close'), array($connection));
    }

    /**
     * 连接事件
     *
     * @param \Workerman\Connection\TcpConnection $connection
     * @return void
     */
    public function onConnect($connection)
    {
        // 事件触发
        $connection->channelId = $this->getId();
        Event::emit($this->buildEventName('connect'), array($connection));
    }

    /**
     * 收到消息事件
     *
     * @param \Workerman\Connection\TcpConnection $connection
     * @param $data
     * @return void
     */
    public function onMessage($connection, $data)
    {
        if(!$data) {
            return;
        }
        // 事件触发
        Event::emit($this->buildEventName('message'), array($connection, $data));
    }

    /**
     * 订阅事件
     *
     * @param $event
     * @param $closure
     * @return void
     */
    public function on($event, $closure)
    {
        $event = $this->buildEventName($event);
        // 本地储存事件名称
        $this->eventList[$event] = $event;
        // 储存事件到事件池
        Event::on($event, $closure);
    }

    /**
     * 取消订阅事件
     *
     * @param $event
     * @return void
     */
    public function un($event)
    {
        $event = $this->buildEventName($event);
        if (isset($this->eventList[$event])) {
            unset($this->eventList[$event]);
        }
        // 储存事件到事件池
        Event::un($event);
    }

    /**
     * 组装事件名称
     *
     * @param $event
     * @return string
     */
    protected function buildEventName($event)
    {
        return 'server_' . $this->port . '_' . $event . '_' . $this->getId();;
    }

    /**
     * 注销资源
     *
     * @return void
     */
    public function __destory()
    {
        // 取消订阅事件
        Event::un($this->eventList);
        $this->eventList = array();
    }

    /**
     * 注册服务端
     *
     * @param null $protocol
     * @return void
     * @throws \Exception
     */
    public static function register($protocol = null)
    {
        $server = new self();
        $packageObject = new Package();
        $channelConfig = Tools::config('channel_server');
        // 设置监听端口
        $server->set($channelConfig['ipaddress'], $channelConfig['port']);
        // 订阅事件
        $server->on('message', function ($connection, $data) use (&$packageObject) {
            // 解包
            $packageList = $packageObject->unpack($connection->id, $data);
            // 按事件类型分发数据包
            BehaviorEvent::contextDistribute($connection, $packageList);
        });
        // 准备工作
        $server->ready();
        // 开始工作
        $server->listen();
    }
}
