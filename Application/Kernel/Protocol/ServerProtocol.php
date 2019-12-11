<?php
namespace Mapping\Application\Kernel\Protocol;

use Mapping\Application\Kernel\BehaviorEvent;
use Mapping\Application\Kernel\Channel\Server;
use Mapping\Application\Library\ConnectionPool;
use Mapping\Application\Library\Package\Package;

abstract class ServerProtocol extends Protocol
{
    /** @var array 对外服务端 */
    protected $extranetWorker = array();
    /** @var array 授权信息 */
    protected $auth = array();

    /**
     * 收到客户端的授权请求
     *
     * @return mixed
     */
    abstract public function authorizeExecute();

    /**
     * 收到客户端的数据转发
     *
     * @return mixed
     */
    abstract public function forwardExecute();

    /**
     * 收到客户端的指令
     *
     * @return mixed
     */
    abstract public function commandExecute();

    /**
     * 授权
     *
     * @return bool|mixed
     */
    public function authorize()
    {
        // 执行
        $auth = $this->authorizeExecute();

        $attribute = $this->context->getAttribute();  // 属性
        $protocol = $attribute->getProtocol();  // 获取协议
        $primary = $attribute->getPrimary();  // 获取唯一码

        // 发送通知 - 无论失败或成功都发送通知
        $context = $auth ? Package::success($attribute) : Package::error($attribute);
        $this->connection->send($context);

        $this->log('Authorize: ' . $protocol . '.' . $primary . ' - ' . ($auth ? 'Success' : 'Fail') . '!');

        // 处理授权成功其他事情
        if (!$auth) {
            return false;
        }

        // 储存授权信息
        $this->auth = $auth;

        $obj = $this;
        $closeCallback = $this->connection->onClose;
        $this->connection->onClose = function ($connection) use (&$obj, &$closeCallback) {
            $obj->log('channel connection close: channel_connect_id='.$obj->connection->id . ', listen_ipaddress='.$obj->auth['config']['ipaddress'].', listen_port=' . $obj->auth['config']['port']);
            // 解出监听
            if (isset($obj->extranetWorker[$connection->id])) {
                $obj->extranetWorker[$connection->id]->unlisten();
                unset($obj->extranetWorker[$connection->id]);
            }
            // 删除绑定的连接池
            ConnectionPool::remove($connection, 'channel_connection');
            // 旧事件触发
            call_user_func($closeCallback, $connection);
        };
        // 绑定连接池
        ConnectionPool::add($this->connection, 'channel_connection');

        // 监听外网端口
        $this->listen();

        return true;
    }

    /**
     * 数据转发
     * @return mixed
     */
    public function forward()
    {
        // 执行
        if ($this->forwardExecute() === false) {
            return false;
        }
        // 开始转发数据
        $extranetClientConnectId = $this->context->getAttribute()->get('extranet_client_connect_id');
        $this->log('channel connection forward: channel_connect_id='.$this->connection->id . ', extranet_client_connect_id='.$extranetClientConnectId.', lenght=' . strlen($this->context->getData()) . ', listen_ipaddress='.$this->auth['config']['ipaddress'].', listen_port=' . $this->auth['config']['port']);
        $connection = ConnectionPool::get($extranetClientConnectId, $this->auth['config']['port']);
        if ($connection) {
            return $connection->send($this->context->getData());
        }
        return false;
    }

    /**
     * 指令
     * @return mixed
     */
    public function command()
    {
        // 执行
        if ($this->commandExecute() === false) {
            return false;
        }
        $this->log('receive command, nothing to do!');
        return true;
    }

    /**
     * 客户端本地连接断开
     */
    public function close()
    {
        $extranetClientConnectId = $this->context->getAttribute()->get('extranet_client_connect_id');
        $connection = ConnectionPool::get($extranetClientConnectId, $this->auth['config']['port']);

        if ($connection) {
            $this->log('channel client connection close: channel_connect_id='.$connection->channelId.', extranet_client_connect_id='.$connection->id . ', listen_ipaddress='.$this->auth['config']['ipaddress'].', listen_port=' . $this->auth['config']['port']);
            $connection->close();  // 关闭外网连接
        }
        // 储存外网客户端
        ConnectionPool::remove($connection, $this->auth['config']['port']);
    }

    /**
     * 监听外网端口
     */
    protected function listen()
    {
        $obj = $this;
        // 建立本地端口的异步连接
        $this->extranetWorker[$this->connection->id] = new Server();
        $this->extranetWorker[$this->connection->id]->setId($this->connection->id);
        $this->log('channel connection be listen: channel_connect_id='.$this->connection->id . ', listen_ipaddress='.$this->auth['config']['ipaddress'].', listen_port=' . $this->auth['config']['port']);
        // 设置监听端口
        $this->extranetWorker[$this->connection->id]->set($this->auth['config']['ipaddress'], $this->auth['config']['port']);
        // 订阅事件 - 连接成功
        $this->extranetWorker[$this->connection->id]->on('connect', function ($connection) use ($obj) {
            $obj->log('extranet client connection connect: channel_connect_id='.$connection->channelId.', extranet_client_connect_id='.$connection->id . ', listen_ipaddress='.$obj->auth['config']['ipaddress'].', listen_port=' . $obj->auth['config']['port']);
            // 储存外网客户端
            ConnectionPool::add($connection, $obj->auth['config']['port']);
            // 通知客户端有外网客户端连接
            $attribute = $obj->context->getAttribute();  // 属性对象
            $attribute->setEvent(BehaviorEvent::EVENT_CONNECT);  // 设置事件类型
            $attribute->set('extranet_client_connect_id', $connection->id);
            // 构建数据包
            $context = Package::success($attribute);
            // 发送数据
            $channelConnection = ConnectionPool::get($connection->channelId, 'channel_connection');
            if ($channelConnection) {
                $channelConnection->send($context);
            }
        });
        // 订阅事件 - 收到消息
        $this->extranetWorker[$this->connection->id]->on('message', function ($connection, $data) use ($obj) {
            $attribute = $obj->context->getAttribute();  // 属性对象
            $attribute->setEvent(BehaviorEvent::EVENT_FORWARD);  // 设置事件类型
            $attribute->set('extranet_client_connect_id', $connection->id);
            $obj->log('extranet client connection message: channel_connect_id='.$connection->channelId.', extranet_client_connect_id='.$connection->id . ', listen_ipaddress='.$obj->auth['config']['ipaddress'].', listen_port=' . $obj->auth['config']['port']);
            // 构建数据包
            $context = Package::success($attribute, $data);
            // 发送数据
            $channelConnection = ConnectionPool::get($connection->channelId, 'channel_connection');
            if ($channelConnection) {
                $channelConnection->send($context);
            }
        });
        // 订阅事件 - 断开连接
        $this->extranetWorker[$this->connection->id]->on('close', function ($connection) use ($obj) {
            $obj->log('extranet client connection close: channel_connect_id='.$connection->channelId.', extranet_client_connect_id='.$connection->id . ', listen_ipaddress='.$obj->auth['config']['ipaddress'].', listen_port=' . $obj->auth['config']['port']);
            // 删除外网客户端储存
            ConnectionPool::remove($connection, $obj->auth['config']['port']);
            // 通知客户端有外网客户端连接
            $attribute = $obj->context->getAttribute();  // 属性对象
            $attribute->setEvent(BehaviorEvent::EVENT_CLOSE);  // 设置事件类型
            $attribute->set('extranet_client_connect_id', $connection->id);
            // 构建数据包
            $context = Package::success($attribute);
            // 发送数据
            $channelConnection = ConnectionPool::get($connection->channelId, 'channel_connection');
            if ($channelConnection) {
                $channelConnection->send($context);
            }
        });
        // 准备工作
        $this->extranetWorker[$this->connection->id]->ready();
        // 开始工作
        $this->extranetWorker[$this->connection->id]->listen();
    }
}