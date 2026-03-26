<?php

namespace Luosimao\SmsSdk;

use Luosimao\SmsSdk\Http\Client;
use Luosimao\SmsSdk\Exceptions\LuosimaoException;

class Sms
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * Sms constructor.
     *
     * @param string|Client $apiKeyOrClient Luosimao API Key or a Client instance
     * @param array $config 可选配置 (仅在传入 API Key 时有效)
     */
    public function __construct($apiKeyOrClient, array $config = [])
    {
        if ($apiKeyOrClient instanceof Client) {
            $this->client = $apiKeyOrClient;
        } else {
            $this->client = new Client($apiKeyOrClient, $config);
        }
    }

    /**
     * 发送单条短信
     *
     * @param string $mobile 目标手机号码
     * @param string $message 短信内容 (必须包含签名，如：验证码：123456【公司名称】)
     * @return array 返回结果，成功时包含 error=>0 和 msg=>'ok'
     * @throws LuosimaoException
     * @throws \InvalidArgumentException
     */
    public function send(string $mobile, string $message): array
    {
        $this->validateMobile($mobile);

        return $this->client->post('send.json', [
            'mobile' => $mobile,
            'message' => $message,
        ]);
    }

    /**
     * 批量发送短信
     *
     * @param array|string $mobileList 目标手机号码列表 (数组或英文逗号分隔的字符串)
     * @param string $message 短信内容 (必须包含签名)
     * @param string|null $time 定时发送时间，格式: 2016-04-01 12:30:00 (选填)
     * @return array 返回结果，包含 batch_id
     * @throws LuosimaoException
     * @throws \InvalidArgumentException
     */
    public function sendBatch($mobileList, string $message, ?string $time = null): array
    {
        if (is_array($mobileList)) {
            foreach ($mobileList as $mobile) {
                $this->validateMobile($mobile);
            }
            $mobileList = implode(',', $mobileList);
        } else {
            $mobiles = explode(',', $mobileList);
            foreach ($mobiles as $mobile) {
                $this->validateMobile(trim($mobile));
            }
        }

        $params = [
            'mobile_list' => $mobileList,
            'message' => $message,
        ];

        if ($time !== null) {
            $params['time'] = $time;
        }

        return $this->client->post('send_batch.json', $params);
    }

    /**
     * 查询账户余额
     *
     * @return int 账户可用短信余额
     * @throws LuosimaoException
     */
    public function getBalance(): int
    {
        $result = $this->client->get('status.json');
        
        if (!isset($result['deposit'])) {
            throw new LuosimaoException('Response missing "deposit" field', -1);
        }

        return (int) $result['deposit'];
    }

    /**
     * 校验手机号格式 (简单校验)
     *
     * @param string $mobile
     * @throws \InvalidArgumentException
     */
    protected function validateMobile(string $mobile): void
    {
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            throw new \InvalidArgumentException('Invalid mobile number: ' . $mobile);
        }
    }
}
