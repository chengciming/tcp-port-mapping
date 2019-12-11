<?php
namespace Mapping\Application\Kernel\Protocol;

use Mapping\Application\Kernel\BehaviorEvent;
use Mapping\Application\Kernel\Channel\Client;
use Mapping\Application\Library\ConnectionPool;
use Mapping\Application\Library\ConnectionRelationPool;
use Mapping\Application\Library\Package\Package;
use Mapping\Application\Library\TemporaryData;
use Mapping\Application\Library\Tools;
use Workerman\Connection\AsyncTcpConnection;

abstract class ClientProtocol extends Protocol
{
    /** @var array 授权信息 */
    protected $auth = array();

    /** @var AsyncTcpConnection|array|null  */
    protected $client = array();

    /**
     * 收到服务器的授权回应
     *
     * @return mixed
     */
    abstract public function authorizeExecute();

    /**
     * 收到服务器的数据转发
     *
     * @return mixed
     */
    abstract public function forwardExecute();

    /**
     * 收到服务器的指令
     *
     * @return mixed
     */
    abstract public function commandExecute();

    /**
     * 收到授权数据包
     */
    public function authorize()
    {
        $attr = $this->context->getAttribute();  // 属性
        $errorCode = $attr->getErrorCode();  // 获取错误码
        $protocol = $attr->getProtocol();  // 获取协议

        // 授权失败
        if ($errorCode != 0) {
            $this->log('Authorize Fail：' . $protocol . ' - ' . $errorCode . '-' . $attr->getErrorMessage());
            return false;
        }
        $this->log('Authorize： ' . $protocol . ' - Success!');
        // 授权信息
        $this->auth = Tools::config('proxy_client', $this->protocol . 'Conf');
    }

    /**
     * 收到数据转发数据包
     * @return mixed
     */
    public function forward()
    {
        // 执行
        if ($this->forwardExecute() === false) {
            return false;
        }
        $data = $this->context->getData();
        $extranetClientConnectId = $this->context->getAttribute()->get('extranet_client_connect_id');
        $group = ConnectionRelationPool::getGroup('local_client:' . $extranetClientConnectId);

        if (!empty($group)) {
            $id = array_rand($group);
            $connection = ConnectionPool::get($id, 'local_client');
            if ($connection) {
                $this->log('channel connection forward: channel_connect_id='.$this->connection->channelId.', client_connect_id='.$connection->id.', extranet_client_connect_id='.$extranetClientConnectId.', listen_ipaddress='.$this->auth['ipaddress'].', listen_port=' . $this->auth['port']);
                return $connection->send($data);
            }
            return false;
        }
        $this->log('channel connection forward: channel_connect_id='.$this->connection->channelId.', extranet_client_connect_id='.$extranetClientConnectId.', listen_ipaddress='.$this->auth['ipaddress'].', listen_port=' . $this->auth['port']);
        // 可能还未连接上，临时存储
        TemporaryData::save($extranetClientConnectId, $data);
        return true;
    }

    /**
     * 收到指令数据包
     * @return mixed
     */
    public function command()
    {
        // 执行
        if ($this->commandExecute() === false) {
            return false;
        }
        $this->log('receive command, nothing to do!');
    }

    /**
     * 管道上线 - 发送授权
     * @return mixed
     */
    public function online()
    {
        // 执行
        if ($this->authorizeExecute() === false) {
            return false;
        }
    }

    /**
     * 管道下线
     *
     * @return void
     */
    public function offline()
    {
        // 清除所有的客户端连接对象
        if ($this->client) {
            foreach ($this->client as $key=>$client) {
                if ($client->getStatus(false) != 'CLOSED') {
                    $client->close();
                }
                unset($this->client[$key]);
            }
        }
        // 清除未发送的数据
        TemporaryData::clear();
        // 清除所有连接
        ConnectionPool::clear();
    }

    /**
     * 外网客户端连接
     */
    public function connect()
    {
        $obj = $this;
        $extranetClientConnectId = $obj->context->getAttribute()->get('extranet_client_connect_id');
        $this->log('client connection link: channel_connect_id='.$this->connection->id.', extranet_client_connect_id='.$extranetClientConnectId.', listen_ipaddress='.$this->auth['ipaddress'].', listen_port=' . $this->auth['port']);
        // 建立本地端口的异步连接
        $this->client[$extranetClientConnectId] = new Client();
        $this->client[$extranetClientConnectId]->setId($extranetClientConnectId);
        // 设置监听端口
        $this->client[$extranetClientConnectId]->set($this->auth['ipaddress'], $this->auth['port']);
        // 订阅事件 - 连接成功
        $this->client[$extranetClientConnectId]->on('connect', function ($connection) use (&$obj) {
            $obj->log('client connection connect: channel_connect_id='.$connection->channelId.', client_connect_id='.$connection->id . ', listen_ipaddress='.$obj->auth['ipaddress'].', listen_port=' . $obj->auth['port']);
            // 储存连接
            ConnectionPool::add($connection, 'local_client');
            // 绑定关系
            ConnectionRelationPool::add($connection->id, 'local_client:' . $connection->channelId);
            // 补发临时存储的数据
            TemporaryData::send($connection->channelId, $connection);
        });
        // 订阅事件 - 收到消息
        $this->client[$extranetClientConnectId]->on('message', function ($connection, $data) use ($obj) {
            // 查找绑定的关系
            $groupId = ConnectionRelationPool::getGroupId($connection->id);
            if (!empty($groupId)) {
                $groupId = explode(':', $groupId);
                // 获取属性对象
                $attribute = $obj->context->getAttribute();  // 属性
                $attribute->setEvent(BehaviorEvent::EVENT_FORWARD);  // 设置事件类型
                $attribute->set('extranet_client_connect_id', $groupId[1]);

                $obj->log('client connection message: channel_connect_id='.$connection->channelId.', client_connect_id='.$connection->id . ', extranet_client_connect_id='.$groupId[1].', listen_ipaddress='.$obj->auth['ipaddress'].', listen_port=' . $obj->auth['port']);

                // 构建数据包
                $context = Package::success($attribute, $data);
                // 发送数据
                $obj->connection->send($context);
            }
        });
        // 订阅事件 - 断开连接
        $this->client[$extranetClientConnectId]->on('close', function ($connection) use (&$obj) {
            // 通知服务器本地客户端断开了
            $groupId = ConnectionRelationPool::getGroupId($connection->id);
            if (!empty($groupId)) {
                $groupId = explode(':', $groupId);
                if ($obj->context) {
                    $attribute = $obj->context->getAttribute();  // 属性
                    $attribute->setEvent(BehaviorEvent::EVENT_CLOSE);  // 设置行为事件
                    $attribute->set('extranet_client_connect_id', $groupId[1]);  // 设置其他属性

                    $obj->log('client connection close: channel_connect_id='.$connection->channelId.', client_connect_id='.$connection->id . ', extranet_client_connect_id='.$groupId[1].', listen_ipaddress='.$obj->auth['ipaddress'].', listen_port=' . $obj->auth['port']);

                    // 构建认证数据包
                    $context = Package::success($attribute);
                    // 发送数据包
                    $obj->connection->send($context);
                }

                // 删除关联关系
                ConnectionRelationPool::removeGroup('local_client:' . $groupId[1]);
            }
            // 清除未发送的数据
            TemporaryData::remove($connection->channelId);
            // 删除储存的连接
            ConnectionPool::remove($connection, 'local_client');
            // 删除关联关系
            ConnectionRelationPool::remove($connection->id);
        });
        // 准备工作
        $this->client[$extranetClientConnectId]->ready();
        // 开始工作
        $this->client[$extranetClientConnectId]->connect();
    }

    /**
     * 外网客户的断开连接
     */
    public function close()
    {
        $extranetClientConnectId = $this->context->getAttribute()->get('extranet_client_connect_id');
        // 注销client监听对象
        if (isset($this->client[$extranetClientConnectId])) {
            $this->client[$extranetClientConnectId]->close();
            unset($this->client[$extranetClientConnectId]);
        }
        // 发送通知
        $group = ConnectionRelationPool::getGroup('local_client:' . $extranetClientConnectId);
        if (!empty($group)) {
            foreach ($group as $id) {
                $connection = ConnectionPool::get($id, 'local_client');
                if ($connection) {
                    $this->log('extranet client connection close: channel_connect_id='.$connection->channelId.', extranet_client_connect_id='.$extranetClientConnectId.', listen_ipaddress='.$this->auth['ipaddress'].', listen_port=' . $this->auth['port']);
                    // 断开连接
                    $connection->close();
                }
                // 删除储存的连接
                ConnectionPool::remove($connection, 'local_client');
            }
        }
        // 删除关联关系
        ConnectionRelationPool::removeGroup('local_client:' . $extranetClientConnectId);
    }
}