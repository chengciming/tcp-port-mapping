<?php
/**
 * ====================================
 * 客户端 HTTP 协议处理类
 * ====================================
 * Author: Lemonice
 * Date: 2019-11-23 14:21
 * ====================================
 */
namespace Mapping\Application\Kernel\Protocol\Client;

use Mapping\Application\Kernel\BehaviorEvent;
use Mapping\Application\Kernel\Protocol\ClientProtocol;
use Mapping\Application\Library\Package\Attribute;
use Mapping\Application\Library\Package\Package;
use Mapping\Application\Library\Tools;
use Mapping\Application\Service\Authorization;

class Http extends ClientProtocol
{
    /**
     * 发送授权信息
     *
     * @return bool|mixed
     */
    public function authorizeExecute()
    {
        // 认证
        $authObject = new Authorization();
        // 其他属性
        $attr = $authObject->build();
        // 唯一码
        $primary = Tools::config(Attribute::ATTR_PRIMARY, $this->protocol . 'Conf');

        // 初始化属性对象
        $attribute = new Attribute();
        $attribute->setEvent(BehaviorEvent::EVENT_AUTHORIZE);  // 设置行为事件
        $attribute->setPrimary($primary);  // 设置唯一码
        $attribute->setProtocol($this->protocol);  // 设置协议
        $attribute->set($attr);  // 设置其他属性

        // 构建认证数据包
        $context = Package::success($attribute);
        // 发送认证数据包
        $result = $this->connection->send($context);
        if (!$result) {
            $this->log('channel connection send authorize fail:  channel_connect_id='.$this->connection->channelId);
            return false;
        }
        return true;
    }

    /**
     * 收到服务器的数据转发
     *
     * @return bool|mixed
     */
    public function forwardExecute()
    {
        return true;
    }

    /**
     * 收到服务器的指令
     *
     * @return bool|mixed
     */
    public function commandExecute()
    {
        return true;
    }
}
?>