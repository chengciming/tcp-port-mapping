<?php
namespace Mapping\Application\Kernel\Protocol;

use Mapping\Application\Library\Tools;

abstract class Protocol
{
    /** @var \Workerman\Connection\AsyncTcpConnection|null 连接对象 */
    protected $connection = null;
    /** @var \Mapping\Application\Library\Package\Context|null 数据包  */
    protected $context = null;
    /** @var null 协议类型 */
    protected $protocol = null;

    /**
     * 收到授权数据包
     * @return mixed
     */
    abstract public function authorize();

    /**
     * 收到数据转发数据包
     * @return mixed
     */
    abstract public function forward();

    /**
     * 收到指令数据包
     * @return mixed
     */
    abstract public function command();

    /**
     * 设置连接
     * @param \Workerman\Connection\AsyncTcpConnection $connection
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * 设置数据包
     * @param \Mapping\Application\Library\Package\Context $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    /**
     * 设置协议类型
     * @param string $protocol
     */
    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
    }

    /**
     * 日记打印
     * @param $message
     */
    protected function log($message)
    {
        Tools::log($message);
    }
}