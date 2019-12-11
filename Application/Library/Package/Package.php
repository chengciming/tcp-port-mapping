<?php
namespace Mapping\Application\Library\Package;

/**
 * TCP底层数据包 - 粘包处理
 */
class Package
{
    /** @var Sticky|null 粘包处理类 */
    protected static $sticky = null;

    /**
     * 封装成功包
     * @param \Mapping\Application\Library\Package\Attribute $attribute
     * @param string $data
     * @return string
     */
    public static function success($attribute, $data = '')
    {
        $attribute->setErrorCode(0);  // 设置错误码
        $attribute->setErrorMessage('success');  // 设置错误信息
        return self::pack($attribute, $data);
    }

    /**
     * 封装报错包
     * @param \Mapping\Application\Library\Package\Attribute $attribute
     * @param int $errorCode
     * @param string $errorMessage
     * @param string $data
     * @return string
     */
    public static function error($attribute, $errorCode = 1, $errorMessage = 'Error', $data = '')
    {
        $attribute->setErrorCode($errorCode);  // 设置错误码
        $attribute->setErrorMessage($errorMessage);  // 设置错误信息
        return self::pack($attribute, $data);
    }

    /**
     * 封包
     * @param \Mapping\Application\Library\Package\Attribute $attribute
     * @param string $data
     * @return string
     */
    public static function pack($attribute, $data = '')
    {
        // 初始化包对象
        $context = new Context();
        // 设置属性
        $context->setAttribute($attribute);
        // 设置包数据
        $context->setData($data);
        // 封装包格式
        $sticky = self::sticky();
        return $sticky->pack($context->getContext());
    }

    /**
     * 解包
     *
     * @param $connectId
     * @param $package
     * @return array
     */
    public static function unpack($connectId, $package)
    {
        $sticky = self::sticky();
        $contextList = $sticky->unpack($connectId, $package);
        if (!empty($contextList)) {
            foreach ($contextList as $key => $context) {
                $contextList[$key] = Context::setContext($context);
            }
        }
        return $contextList;  // 可能一次性收到多个包
    }

    /**
     * 初始化粘包处理类
     *
     * @return Sticky|null
     */
    protected static function sticky()
    {
        if (is_null(self::$sticky)) {
            self::$sticky = new Sticky();
        }
        return self::$sticky;
    }

    /**
     * @return void
     */
    public function __destory()
    {
        self::$sticky = null;
    }
}
