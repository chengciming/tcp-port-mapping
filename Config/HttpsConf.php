<?php

return array(
    /**
     * 授权码，唯一码
     */
    \Mapping\Application\Library\Package\Attribute::ATTR_PRIMARY => 'test1',
    /**
     * 代理服务器 - 客户端用的
     */
    'channel_server'=>array(
        'ipaddress'=>'127.0.0.1',
        'port'=>10000
    ),
    /**
     * 被代理服务器 - 客户端用的
     */
    'proxy_client'=>array(
        'ipaddress'=>'127.0.0.1',
        'port'=>443
    ),
    /**
     * 允许授权的授权码与端口 - 服务器用的
     */
    'allow_auth' => array(
        // 唯一码 => array(port => 端口)
        'test1' => array(
            'ipaddress' => '0.0.0.0',
            'port' => 1232,  // 外网端口
        ),
    )
);