<?php
namespace Mapping\Application\Service;

use Mapping\Application\Library\Package\Attribute;
use Mapping\Application\Library\Tools;

/**
 * 授权
 */
class Authorization
{
    /**
     * 构建授权信息 - 客户端使用
     *
     * @return array
     */
    public static function build()
    {
        return array(
            // 暂时无更多属性
        );
    }

    /**
     * 校验授权信息 - 服务端使用
     *
     * @param array $auth
     * @return array|bool
     */
    public static function check($auth = array())
    {
        // 没传认证数据 || 没设置协议  || 没有唯一码
        if (empty($auth) ||
            !isset($auth[Attribute::ATTR_PROTOCOL]) || empty($auth[Attribute::ATTR_PROTOCOL]) ||
            !isset($auth[Attribute::ATTR_PRIMARY]) || empty($auth[Attribute::ATTR_PRIMARY])
        ) {
            return false;
        }
        // 加载协议允许授权的配置
        $authConfig = Tools::config('allow_auth', $auth[Attribute::ATTR_PROTOCOL] . 'Conf');
        // 协议不存在
        if (empty($authConfig)) {
            return false;
        }
        // 检查唯一码
        if (!isset($authConfig[$auth[Attribute::ATTR_PRIMARY]])) {
            return false;
        }
        $auth['config'] = $authConfig[$auth[Attribute::ATTR_PRIMARY]];  // 把授权信息给回去
        return $auth;
    }
}
