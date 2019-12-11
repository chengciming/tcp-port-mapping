<?php
namespace Mapping\Application\Library\Package;

use Mapping\Application\Library\Tools;

/**
 * 包属性
 */
class Attribute
{
    /** 系统属性定义 */
    const ATTR_EVENT           = '__event__';     // 行为事件
    const ATTR_PRIMARY         = 'primary';       // 终端唯一码
    const ATTR_PROTOCOL        = 'protocol';      // 协议
    const ATTR_ERROR_CODE      = 'error_code';    // 错误码
    const ATTR_ERROR_MESSAGE   = 'error_message'; // 错误信息

    /** @var array 属性储存 */
    protected $attr = array(
        self::ATTR_EVENT => 'unknown',  // 行为事件
    );

    /**
     * Attribute constructor.
     * @param string $attrQuery
     */
    public function __construct($attrQuery = '')
    {
        $this->query($attrQuery);
    }

    /**
     * 设置行为事件
     *
     * @param $event
     * @return void
     */
    public function setEvent($event)
    {
        $this->set(self::ATTR_EVENT, $event);
    }

    /**
     * 获取行为事件
     * @return null
     */
    public function getEvent()
    {
        return $this->get(self::ATTR_EVENT);
    }

    /**
     * 设置协议
     * @param $protocol
     */
    public function setProtocol($protocol)
    {
        $this->set(self::ATTR_PROTOCOL, $protocol);
    }

    /**
     * 获取协议
     * @return null
     */
    public function getProtocol()
    {
        return $this->get(self::ATTR_PROTOCOL);
    }

    /**
     * 设置唯一码
     * @param $primary
     */
    public function setPrimary($primary)
    {
        $this->set(self::ATTR_PRIMARY, $primary);
    }

    /**
     * 获取唯一码
     * @return mixed|null
     */
    public function getPrimary()
    {
        return $this->get(self::ATTR_PRIMARY);
    }

    /**
     * 设置错误信息
     * @param string $errorMessage
     */
    public function setErrorMessage($errorMessage = 'success')
    {
        $this->set(self::ATTR_ERROR_MESSAGE, $errorMessage);
    }

    /**
     * 获取错误信息
     * @return mixed|null
     */
    public function getErrorMessage()
    {
        return $this->get(self::ATTR_ERROR_MESSAGE);
    }

    /**
     * 获取错误码
     * @return mixed|null
     */
    public function getErrorCode()
    {
        return $this->get(self::ATTR_ERROR_CODE);
    }

    /**
     * 设置错误码
     * @param int $errorCode
     */
    public function setErrorCode($errorCode = 0)
    {
        $this->set(self::ATTR_ERROR_CODE, $errorCode);
    }

    /**
     * 获取属性
     *
     * @param $name
     * @return mixed|null
     */
    public function get($name)
    {
        return isset($this->attr[$name]) ? $this->attr[$name] : null;
    }

    /**
     * 设置属性
     *
     * @param string|array$name
     * @param $value
     * @return bool
     */
    public function set($name, $value = null)
    {
        if (is_array($name)) {
            if (!empty($name)) {
                foreach ($name as $k => $v) {
                    $this->attr[$k] = $v;
                }
            }
            return true;
        }
        $this->attr[$name] = $value;
        return true;
    }

    /**
     * 删除属性
     *
     * @param $name
     * @return void
     */
    public function remove($name)
    {
        if (isset($this->attr[$name])) {
            unset($this->attr[$name]);
        }
    }

    /**
     * 获取所有属性
     *
     * @return array
     */
    public function getAll()
    {
        return $this->attr;
    }

    /**
     * 构建可传输属性
     *
     * @return string
     */
    public function buildQuery()
    {
        return Tools::buildQuery($this->attr);
    }

    /**
     * 解析build后的内容为属性
     *
     * @param $attrQuery
     * @return bool
     */
    public function query($attrQuery)
    {
        if (empty($attrQuery)) {
            return false;
        }
        $attribute = Tools::parseQuery($attrQuery);
        if (!empty($attribute)) {
            foreach ($attribute as $name => $value) {
                if (!empty($name)) {
                    $this->attr[$name] = $value;
                }
            }
        }
        return true;
    }
}
