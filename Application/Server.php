<?php
/**
 * 服务端应用类
 */
namespace Mapping\Application;

use Mapping\Application\Kernel\BehaviorEvent;
use Mapping\Application\Kernel\Kernel;
use Mapping\Application\Kernel\Channel\Server as Channel;
use Mapping\Application\Library\ConnectionPool;
use Mapping\Application\Library\Package\Attribute;
use Mapping\Application\Library\Package\Package;
use Mapping\Application\Library\Tools;
use Workerman\Lib\Timer;
use Workerman\Worker;

class Server
{
    /** @var Worker|null 工作进程 */
    protected $worker = null;

    /** @var Channel|null 管道对象 */
    protected $channel = null;

    /** @var Timer|null 定时器 */
    protected $timer = null;

    /**
     * 初始化
     */
    public function handle()
    {
        //初始化管道
        $this->channel = new Channel();

        $channelConfig = Tools::config('channel_server');
        $workerConfig = Tools::config('worker.server');
        // 设置监听端口
        $this->worker = new Worker("tcp://".$channelConfig['ipaddress'] . ":" . $channelConfig['port']);
        $this->worker->count = $workerConfig['worker_num'];
        $this->worker->name = 'Server:' . $workerConfig['name'];
        Worker::$pidFile = $workerConfig['pid_file'];
        Worker::$logFile = $workerConfig['log_file'];
        Worker::$daemonize = $workerConfig['daemonize'];
        if(isset($config['stdout_file']) && !empty($config['stdout_file'])){
            Worker::$stdoutFile = $config['stdout_file'];
        }
        $this->worker->onWorkerStart = array($this, 'workerStart');
        // 设置监听事件
        $this->worker->onMessage = array($this->channel, 'onMessage') ;
        $this->worker->onClose = array($this->channel, 'onClose');
        $this->worker->onConnect = array($this->channel, 'onConnect');
        $this->worker->onError = array($this->channel, 'onError');

        // 设置外部初始化的工作进程
        $this->channel->setWorker($this->worker);

        // 启动进程
        $this->run();
    }

    /**
     * 开始工作
     *
     * @return void
     */
    public function workerStart()
    {
        $obj = $this;

        $channelConfig = Tools::config('channel_server');
        // 设置监听端口
        $this->channel->set($channelConfig['ipaddress'], $channelConfig['port']);
        // 订阅消息事件
        $this->channel->on('message', function ($connection, $data) {
            // 解包
            $packageList = Package::unpack($connection->id, $data);
            // 按事件类型分发数据包
            BehaviorEvent::contextDistribute($connection, $packageList);
        });
        // 连接成功
        $this->channel->on('connect', function ($connection) use (&$obj) {
            // 添加自维护连接池
            ConnectionPool::add($connection, 'channel_server_connection');
            // 自维护
            $obj->monitor();
        });
        // 关闭连接
        $this->channel->on('close', function ($connection){
            // 添加自维护连接池
            ConnectionPool::remove($connection, 'channel_server_connection');
        });

        // 管道开始工作
        Kernel::register(Channel::TERMINAL);
    }

    /**
     * 连接池自维护
     *
     * @return void
     */
    public function monitor()
    {
        // 设置定时器，定时发送心跳包
        if (is_null($this->timer)) {
            $this->timer = Timer::add(25, function () {
                $connectionList = ConnectionPool::getAll('channel_server_connection');
                if (!empty($connectionList)) {
                    Tools::log('Send heartbeat to connection: count=' . count($connectionList));
                    foreach ($connectionList as $connect) {
                        // 通知客户端有外网客户端连接
                        $attribute = new Attribute();  // 属性对象
                        $attribute->setEvent(BehaviorEvent::EVENT_HEARTBEAT);  // 设置事件类型
                        $context = Package::success($attribute);
                        $connect->send($context);  // 发送心跳包
                    }
                }
            });
        }
    }

    /**
     * 启动进程
     *
     * @return void
     */
    public function run()
    {
        Worker::runAll();
    }
}