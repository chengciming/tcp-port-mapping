<?php
/**
 * 公共配置
 */

return array(
    /**
     * 是否开启debug模式
     */
    'debug' => false,
    /**
     * 开启的协议类型
     *
     * 注意：每个类型对应***Conf.php文件配置，无配置等同未开启
     *
     */
    'protocol_support' => array(
        'http',
        'https',
        'ssh',
    ),
    /**
     * 服务端配置 - 管道监听端口
     */
    'channel_server'=>array(
        'ipaddress'=>'0.0.0.0',
        'port'=>10000
    ),
    /**
     * 基础配置
     */
    'worker'=>array(
        'client' => array(
            'daemonize' => true,  //是否后台运行
            'worker_num' => 4,  //工作进程数量
            'name' => 'Proxy', //服务名称
            'log_file' => ROOT_PATH . '/Storage/logs/proxy.client.log',  //日记文件
            'pid_file' => ROOT_PATH . '/Storage/pid/proxy.client.pid',  //服务PID文件
            'stdout_file' => '',  //屏幕打印输出到文件，不设置或者为空则打印到频幕
        ),
        'server' => array(
            'daemonize' => true,  //是否后台运行
            'worker_num' => 4,  //工作进程数量
            'name' => 'Proxy', //服务名称
            'log_file' => ROOT_PATH . '/Storage/logs/proxy.server.log',  //日记文件
            'pid_file' => ROOT_PATH . '/Storage/pid/proxy.server.pid',  //服务PID文件
            'stdout_file' => '',  //屏幕打印输出到文件，不设置或者为空则打印到频幕
        )
    ),
);