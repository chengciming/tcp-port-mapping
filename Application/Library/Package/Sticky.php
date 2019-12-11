<?php
namespace Mapping\Application\Library\Package;

use Mapping\Application\Library\Tools;
use Workerman\Lib\Timer;

/**
 * 数据包粘包处理
 */
class Sticky
{
    /** @var string 包长度符号 */
    protected $packLengthString = "N";
    /** @var int 包长度位数 */
    protected $packLengthSize = 4;

    /** @var array 数据包临时储存 - 粘包处理 */
    protected $stickyPackage = array();

    /** @var null 定时器 */
    protected $timer = null;

    /** @var array 碎数据包初始化时间 */
    public $stickyPackageExpiredTime = array();

    /** @var int 碎数据过期时间 */
    public $expiredTime = 60;

    /**
     * 封包
     *
     * @param $data
     * @return string
     */
    public function pack($data)
    {
        $dataLenght = strlen($data);
        // 使用 N 打包 固定4bytes
        $contextLength = pack($this->packLengthString, $dataLenght);
        return $contextLength . $data;
    }

    /**
     * 解包
     *
     * @param $connectId
     * @param $package
     * @return array
     */
    public function unpack($connectId, $package)
    {
        // 上次有包未处理完成
        if (!empty($this->stickyPackage[$connectId])) {
            $package = $this->stickyPackage[$connectId] . $package;  // 把上次的连接起来解包
            // 清除碎包
            $this->clear($connectId);
        }

        // 此连接未有数据包接收过，初始化
        if (!isset($this->stickyPackage[$connectId])) {
            // 初始化临时包储存
            $this->stickyPackage[$connectId] = '';
            // 开启碎包有效期处理
            $this->monitorExpired();
        }

        // 完整的包列表，不完整的需要等待累积到完整
        $contextList = array();

        while ($package) {
            // 收到的包长度
            $packageLength = strlen($package);
            // 粘包
            if ($packageLength > 0) {  // 有数据
                $context = $this->sticky($connectId, $package, $packageLength);
                if ($context !== true) {
                    $contextList[] = $context;
                    continue;
                }
            }
            break;  // 没数据
        }

        return $contextList;
    }

    /**
     * 粘包处理
     *
     * @param $connectId
     * @param $package
     * @param $packageLength
     * @return bool|string
     */
    protected function sticky($connectId, &$package, $packageLength)
    {
        if ($packageLength > $this->packLengthSize) {  // 有包头+内容
            // 解析第1条消息 取前 4 bytes 按 n 解包
            $contextLenght = unpack($this->packLengthString, substr($package, 0, $this->packLengthSize));
            $contextLenght = isset($contextLenght[1]) ? $contextLenght[1] : 0;  // 包长度
            if ($packageLength - $this->packLengthSize > $contextLenght) {  // 有其他包粘着
                $context = substr($package, $this->packLengthSize, $contextLenght);  // 先取一个完整的包
                $package = substr($package, $contextLenght + $this->packLengthSize);  // 转移指针到下一个包
                return $context;  // 还有下一个包
            } else if ($packageLength - $this->packLengthSize < $contextLenght) {  // 只有半个包
                $this->stickyPackage[$connectId] .= $package;  // 半个包的内容，等待下个包
                $this->stickyPackageExpiredTime[$connectId] = time();  // 碎包时间
                return true;  // 结束了
            } else if ($contextLenght == 0) {
                return true;  // 没实际数据
            }

            /** 刚好一个包 */
            // 使用包消息体长度定义读取消息体
            // 从第 5 byte 开始读 前 4 bytes表示长度
            $context = substr($package, $this->packLengthSize, $contextLenght);
            $package = '';
            return $context;  // 结束了
        }
        /** 只有部分包头 */
        $this->stickyPackage[$connectId] .= $package;
        $this->stickyPackageExpiredTime[$connectId] = time();  // 碎包时间
        return true;  // 结束了
    }

    /**
     * 清除数据包
     *
     * @param $connectId
     * @return bool
     */
    public function clear($connectId)
    {
        if (isset($this->stickyPackage[$connectId])) {
            unset($this->stickyPackage[$connectId]);
        }
        if (isset($this->stickyPackageExpiredTime[$connectId])) {
            unset($this->stickyPackageExpiredTime[$connectId]);
        }
        return true;
    }

    /**
     * 定时清除过期数据
     *
     * @return void
     */
    public function monitorExpired()
    {
        if (is_null($this->timer)) {
            Tools::log('Start Timer For Sticky Data.');
            $obj = $this;
            // 定时处理过期的数据
            $this->timer = Timer::add($this->expiredTime, function () use (&$obj) {
                if (!empty($obj->stickyPackageExpiredTime)) {
                    Tools::log('Sticky Data For Connection: ' . count($obj->stickyPackageExpiredTime));
                    $nowTime = time();
                    foreach ($obj->stickyPackageExpiredTime as $connectId=>$time) {
                        if ($nowTime - $time > $obj->expiredTime) {
                            Tools::log('Sticky Data For Expired: connection_id = ' . $connectId);
                            $obj->clear($connectId);
                        }
                    }
                }
            });
        }
    }
}
