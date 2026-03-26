<?php

namespace Luosimao\SmsSdk\Exceptions;

use Exception;

class LuosimaoException extends Exception
{
    /**
     * @var array|null 额外信息（例如敏感词 hit 属性）
     */
    protected $extraData;

    public function __construct(string $message = "", int $code = 0, Exception $previous = null, ?array $extraData = null)
    {
        parent::__construct($message, $code, $previous);
        $this->extraData = $extraData;
    }

    /**
     * 获取接口返回的额外信息
     * 
     * @return array|null
     */
    public function getExtraData(): ?array
    {
        return $this->extraData;
    }
}
