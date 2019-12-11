<?php
/**
 * 客户端应用类
 */
namespace Mapping\Application;

use Mapping\Application\Kernel\Kernel;
use Mapping\Application\Library\Tools;
use Mapping\Application\Kernel\Channel\Client as Channel;
use Workerman\Worker;

class Client
{
    /** @var Worker|null 工作进程 */
    protected $worker = null;

    public function handle()
    {
        // 设置进程
        $workerConfig = Tools::config('worker.client');
        $this->worker = new Worker();
        $this->worker->count = $workerConfig['worker_num'];
        $this->worker->name = 'Client:' . $workerConfig['name'];
        Worker::$pidFile = $workerConfig['pid_file'];
        Worker::$logFile = $workerConfig['log_file'];
        Worker::$daemonize = $workerConfig['daemonize'];
        if(isset($config['stdout_file']) && !empty($config['stdout_file'])){
            Worker::$stdoutFile = $config['stdout_file'];
        }
        $this->worker->onWorkerStart = array($this, 'workerStart');

        // 启动进程
        $this->run();
    }

    /**
     * 开始工作
     *
     * @return void
     * @throws \Exception
     */
    public function workerStart()
    {
        // 管道开始工作
        $protocolSupport = Tools::config('protocol_support');
        if (!empty($protocolSupport)) {
            foreach ($protocolSupport as $protocol) {
                Kernel::register(Channel::TERMINAL, $protocol);  // 协议连接服务端
                // 管道开始工作
                Channel::register($protocol);
            }
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