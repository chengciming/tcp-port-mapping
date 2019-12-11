<?php
namespace Mapping\Application\Kernel\Channel;
use Mapping\Application\Kernel\BehaviorEvent;
use Mapping\Application\Library\Event;
use Mapping\Application\Library\Package\Package;
use Mapping\Application\Library\Tools;
use Workerman\Connection\AsyncTcpConnection;

/**
 * client
 */
class Client
{
    /** 终端标志 */
    const TERMINAL = 'client';

    /** @var string|null 管道ID */
    protected $id = 'default';

    /** @var string|null 协议 */
    protected $protocol = null;

    /** @var array 订阅的事件列表 */
    protected $eventList = array();

    /** @var AsyncTcpConnection|null 管道 */
    protected $connection = null;

    /** @var string 链接的IP */
    protected $ip = '127.0.0.1';

    /** @var int 链接的端口 */
    protected $port = 80;

    /**
     * 设置监听端口和IP
     *
     * @param string $ip
     * @param int $port
     * @return void
     */
    public function set($ip = '0.0.0.0', $port = 2206)
    {
        $this->port = $port;
        $this->ip = $ip;
    }

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
     * 设置协议
     * @param $protocol
     */
    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
    }

    /**
     * 设置协议
     * @return string
     */
    public function getProtocol()
    {
        return $this->protocol;
    }

    /**
     * 准备工作
     *
     * @return void
     * @throws \Exception
     */
    public function ready()
    {
        $this->connection = new AsyncTcpConnection('tcp://'.$this->ip.':'.$this->port);
        $this->connection->onMessage = array($this, 'onMessage') ;
        $this->connection->onClose = array($this, 'onClose');
        $this->connection->onConnect = array($this, 'onConnect');
        $this->connection->onError = array($this, 'onError');
        $this->connection->channelId = $this->getId();
        $this->connection->channelProtocol = $this->getProtocol();
    }

    /**
     * 开始工作
     *
     * @return void
     * @throws \Exception
     */
    public function connect()
    {
        $this->connection->connect();
    }

    /**
     * 发送数据
     * @param $data
     * @return bool|null
     */
    public function send($data)
    {
        return $this->connection->send($data);
    }

    /**
     * 获取连接状态
     *
     * @param bool $rawOutput
     * @return int
     */
    public function getStatus($rawOutput = true)
    {
        return $this->connection->getStatus($rawOutput);
    }

    /**
     * 获取连接
     * @return null|AsyncTcpConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * 关闭连接
     */
    public function close()
    {
        $this->connection->close();
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
        Event::emit($this->buildEventName('error'), array($this, $connection, $code, $msg));
        // 打印日记
        Tools::log('Error: ' . $code . ' - ' . $msg);
    }

    /**
     * 关闭连接事件
     *
     * @param AsyncTcpConnection $connection
     * @return void
     */
    public function onClose($connection)
    {
        $event = $this->buildEventName('close');
        // 事件触发
        Event::emit($event, array($connection));
    }

    /**
     * 连接事件
     *
     * @param AsyncTcpConnection $connection
     * @return void
     */
    public function onConnect($connection)
    {
        $connection->channelId = $this->getId();
        // 事件触发
        Event::emit($this->buildEventName('connect'), array($connection));
    }

    /**
     * 收到消息事件
     *
     * @param AsyncTcpConnection $connection
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
        return 'client_' . $this->port . '_' . $event . '_' . $this->getId();
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
     * 注册客户端
     *
     * @param $protocol
     * @return void
     * @throws \Exception
     */
    public static function register($protocol)
    {
        $package = new Package();
        $channelServerConfig = Tools::config('channel_server', $protocol . 'Conf');
        // 初始化客户端对象
        $channelClient = new self();
        // 设置协议
        $channelClient->setProtocol($protocol);
        // 设置监听端口
        $channelClient->set($channelServerConfig['ipaddress'], $channelServerConfig['port']);
        // 订阅事件 - 连接成功
        $channelClient->on('connect', function ($connection) {
            // 客户端上线 - 授权
            BehaviorEvent::emit('online', $connection, $connection->channelProtocol);
        });
        // 订阅事件 - 收到消息
        $channelClient->on('message', function ($connection, $data) use (&$package) {
            // 解包
            $packageList = $package->unpack($connection->id, $data);
            // 按事件类型分发数据包
            BehaviorEvent::contextDistribute($connection, $packageList);
        });
        // 订阅事件 - 断开连接
        $channelClient->on('close', function ($connection) use (&$package, &$channelClient) {
            // 3秒后重连
            $connection->reconnect(3);  // 断开重连
            // 客户端下线操作
            BehaviorEvent::emit('offline', $connection, $connection->channelProtocol);
        });
        // 准备工作
        $channelClient->ready();
        // 开始工作
        $channelClient->connect();
    }
}
