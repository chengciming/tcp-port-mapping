<?php
/**
 * ====================================
 * 服务端 HTTPS 协议处理类
 * ====================================
 * Author: Lemonice
 * Date: 2019-11-23 14:21
 * ====================================
 */
namespace Mapping\Application\Kernel\Protocol\Server;

use Mapping\Application\Kernel\Protocol\ServerProtocol;
use Mapping\Application\Service\Authorization;

class Http extends ServerProtocol
{
    /**
     * 授权
     *
     * @return bool|mixed
     */
    public function authorizeExecute()
    {
        // 校验授权
        $auth = Authorization::check($this->context->getAttribute()->getAll());
        // 授权结果处理
        return $auth;
    }

    /**
     * 数据转发
     * @return mixed
     */
    public function forwardExecute()
    {
        return true;
    }

    /**
     * 指令
     * @return mixed
     */
    public function commandExecute()
    {
        $this->log('指令');
    }
}
?>