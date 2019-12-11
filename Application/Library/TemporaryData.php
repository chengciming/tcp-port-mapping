<?php
namespace Mapping\Application\Library;

use Workerman\Lib\Timer;

/**
 * 临时数据储存
 */
class TemporaryData
{
    /** @var array 记录临时数据的储存时间，超时清除 */
    public static $forwardDataTime = array();

    /** @var int 数据过期时间 */
    public static $expiredTime = 60;

    /** @var array 临时数据储存 */
    protected static $forwardData = array();

    /** @var null 定时器 */
    protected static $timer = null;

    /**
     * 定时清除过期数据
     *
     * @return void
     */
    public static function monitorExpired()
    {
        if (is_null(self::$timer)) {
            Tools::log('Start Timer For Forward Data.');
            // 定时处理过期的数据
            self::$timer = Timer::add(self::$expiredTime, function () {
                if (!empty(TemporaryData::$forwardDataTime)) {
                    Tools::log('Forward Data For Connection: ' . count(TemporaryData::$forwardDataTime));
                    $nowTime = time();
                    foreach (TemporaryData::$forwardDataTime as $id=>$time) {
                        if ($nowTime - $time > TemporaryData::$expiredTime) {
                            Tools::log('Forward Data For Expired: id = ' . $id);
                            TemporaryData::remove($id);
                        }
                    }
                }
            });
        }
    }

    /**
     * 保存
     *
     * @param $id
     * @param $data
     * @return bool
     */
    public static function save($id, $data)
    {
        if (!$id || !$data) {
            return false;
        }
        if (!isset(self::$forwardData[$id])) {
            // 初始化数据空间
            self::$forwardData[$id] = array();
            // 记录初始化时间
            self::$forwardDataTime[$id] = time();
            // 启动定时清除过期数据
            self::monitorExpired();
        }
        // 储存
        self::$forwardData[$id][] = $data;
        // 更新初始化时间
        self::$forwardDataTime[$id] = time();
        return true;
    }

    /**
     * 删除
     *
     * @param $id
     * @return bool
     */
    public static function remove($id)
    {
        if (!$id) {
            return false;
        }
        // 不存在
        if (!isset(self::$forwardData[$id])) {
            return false;
        }
        // 释放数据
        unset(self::$forwardData[$id]);
        // 注销初始化时间
        if (isset(self::$forwardDataTime[$id])) {
            unset(self::$forwardDataTime[$id]);
        }
        return true;
    }

    /**
     * 清除所有
     *
     * @return void
     */
    public static function clear()
    {
        self::$forwardData = array();
        self::$forwardDataTime = array();
        // 清除定时器
        Timer::del(self::$timer);
    }

    /**
     * 把数据发送到某个链接，并且删除
     *
     * @param $id
     * @param $connection
     * @return bool
     */
    public static function send($id, $connection)
    {
        // 不存在
        if (!isset(self::$forwardData[$id])) {
            return false;
        }
        // 有数据，发送
        if (!empty(self::$forwardData[$id])) {
            foreach (self::$forwardData[$id] as $key=>$data) {
                $connection->send($data);
            }
            // 释放数据
            unset(self::$forwardData[$id]);
            unset(self::$forwardDataTime[$id]);
            return true;
        }
        return false;
    }

    /**
     * 获取
     *
     * @param $id
     * @return mixed|null
     */
    public static function get($id)
    {
        // 不存在
        if (!isset(self::$forwardData[$id])) {
            return null;
        }
        // 读取数据
        $data = self::$forwardData[$id];
        // 读取完成释放数据
        unset(self::$forwardData[$id]);
        unset(self::$forwardDataTime[$id]);
        return $data;
    }
}
