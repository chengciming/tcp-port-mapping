<h1 align="left">TcpPortMapping</h1>

这是一个使用workerman框架开发的，针对内网环境映射TCP端口到外网环境指定端口。


## Client Requirement

1. PHP >= 5.6
2. **[Composer](https://getcomposer.org/)**

## Server Requirement

1. PHP >= 7
2. **[Composer](https://getcomposer.org/)**

## Installation

```shell
$ composer require "chengciming/tcp-port-mapping:^1.0" -vvv
```

## Usage

客户端:

```shell
php client start
php client stop
php client restart
php client reload
```
服务端:

```shell
php server start
php server stop
php server restart
php server reload
```

