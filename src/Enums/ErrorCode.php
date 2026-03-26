<?php

namespace Luosimao\SmsSdk\Enums;

class ErrorCode
{
    const SUCCESS = 0;
    const AUTH_FAILED = -10;
    const USER_DISABLED = -11;
    const BALANCE_FROZEN = -12;
    const INSUFFICIENT_BALANCE = -20;
    const EMPTY_MESSAGE = -30;
    const SENSITIVE_WORDS = -31;
    const MISSING_SIGNATURE = -32;
    const MESSAGE_TOO_LONG = -33;
    const SIGNATURE_UNAVAILABLE = -34;
    const TEST_SIGNATURE_LIMITED = -35;
    const INVALID_MOBILE = -40;
    const MOBILE_BLACKLISTED = -41;
    const FREQUENCY_LIMIT = -42;
    const IP_NOT_WHITELISTED = -50;

    /**
     * 获取错误码对应的中文描述
     *
     * @param int $code
     * @return string
     */
    public static function getMessage(int $code): string
    {
        $map = [
            self::SUCCESS => '发送成功',
            self::AUTH_FAILED => '验证信息失败',
            self::USER_DISABLED => '用户接口被禁用',
            self::BALANCE_FROZEN => '余额冻结',
            self::INSUFFICIENT_BALANCE => '短信余额不足',
            self::EMPTY_MESSAGE => '短信内容为空',
            self::SENSITIVE_WORDS => '短信内容存在敏感词',
            self::MISSING_SIGNATURE => '短信内容缺少签名信息',
            self::MESSAGE_TOO_LONG => '短信过长，超过300字（含签名）',
            self::SIGNATURE_UNAVAILABLE => '签名不可用',
            self::TEST_SIGNATURE_LIMITED => '测试签名受限',
            self::INVALID_MOBILE => '错误的手机号',
            self::MOBILE_BLACKLISTED => '号码在黑名单中',
            self::FREQUENCY_LIMIT => '验证码类短信发送频率过快',
            self::IP_NOT_WHITELISTED => '请求发送IP不在白名单内',
        ];

        return $map[$code] ?? '未知错误';
    }
}
