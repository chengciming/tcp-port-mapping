<?php
namespace Mapping\Application\Library\Package;

/**
 * 包对象
 */
class Context
{
    /** @var string 属性与数据分隔符 */
    protected static $flag = "\r";

    /** @var string|null 数据 */
    protected $data = null;
    /** @var Attribute|null 属性对象 */
    protected $attribute = null;

    /**
     * 获取包数据
     *
     * @return null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 设置包数据
     *
     * @param $data
     * @return void
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * 获取属性对象
     *
     * @return Attribute|null
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * 设置属性对象
     *
     * @param $attribute
     * @return void
     */
    public function setAttribute($attribute)
    {
        $this->attribute = $attribute;
    }

    /**
     * 获取包数据
     *
     * @return string
     */
    public function getContext()
    {
        $context = $this->attribute->buildQuery();
        $context .= self::$flag;
        $context .= $this->data ? $this->data : '';
        return $context;
    }

    /**
     * 设置包数据
     *
     * @param $context
     * @return Context
     */
    public static function setContext($context)
    {
        $object = new self();
        // 截取属性和数据
        $attrQueryLength = strpos($context, self::$flag);
        $attrQuery = substr($context, 0, $attrQueryLength);
        $data = substr($context, $attrQueryLength + 1);
        // 设置属性列表
        $attribute = new Attribute($attrQuery);
        $object->setAttribute($attribute);
        // 设置数据
        $object->setData($data);
        return $object;
    }
}
