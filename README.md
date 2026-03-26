# Luosimao SMS PHP SDK

这是一个用于 Luosimao（螺丝帽）短信发送的 PHP SDK。目前实现了第一期功能，包含：单条短信发送、批量短信发送和账户余额查询。

## 特性

- **统一的错误码枚举**：所有 API 错误码（如 `-10`、`-20`、`-31`）已被映射为 `Luosimao\SmsSdk\Enums\ErrorCode` 常量，方便排查。
- **自动重试机制**：遇到网络抖动（ConnectionTimeout 等）或服务端 5xx 错误时，会自动采用指数退避策略重试，提高请求成功率。
- **请求签名封装**：自动处理 API Key，开发者无需手动拼接 `api:key-` 进行 Basic Auth，内部已完成无感封装。
- **完善的单元测试**：核心功能已全部被 PHPUnit 覆盖，保障 SDK 的稳定性。

## 环境要求

- PHP >= 7.4
- Composer

## 安装

可以通过 Composer 直接安装（目前作为本地库开发中，未来将发布至 Packagist）：

```bash
composer require luosimao/sms-sdk
```

## 快速使用

### 初始化

```php
use Luosimao\SmsSdk\Sms;

// 填入你的 API Key，系统会自动补全 key- 前缀和鉴权细节
$apiKey = 'your_luosimao_api_key';

// 可选配置
$config = [
    'max_retries' => 3, // 网络抖动最大重试次数，默认为 3
    'timeout' => 10.0,  // 请求超时时间
];

$sms = new Sms($apiKey, $config);
```

### 单条短信发送

```php
try {
    $mobile = '13800138000';
    $message = '验证码：123456【你的公司名】'; // 请务必包含签名

    $result = $sms->send($mobile, $message);
    
    echo "发送成功！\n";
    print_r($result);
} catch (\Luosimao\SmsSdk\Exceptions\LuosimaoException $e) {
    echo "发送失败，错误码：" . $e->getCode() . "\n";
    echo "错误原因：" . $e->getMessage() . "\n";
    
    // 如果是敏感词错误 (-31)，可获取被拦截的敏感词
    if ($e->getCode() === \Luosimao\SmsSdk\Enums\ErrorCode::SENSITIVE_WORDS) {
        $extra = $e->getExtraData();
        echo "触发敏感词：" . ($extra['hit'] ?? '未知') . "\n";
    }
}
```

### 批量发送短信

```php
try {
    // 支持数组格式或逗号分隔的字符串
    $mobileList = ['13800138000', '13800138001'];
    $message = '温馨提示：您的服务即将到期，请及时续费。【你的公司名】';

    // 第三个参数为可选的定时发送时间
    $result = $sms->sendBatch($mobileList, $message, '2026-05-01 12:00:00');
    
    echo "批量发送成功，批次号：" . $result['batch_id'] . "\n";
} catch (\Luosimao\SmsSdk\Exceptions\LuosimaoException $e) {
    echo "发送失败：" . $e->getMessage();
}
```

### 查询账户余额

```php
try {
    $balance = $sms->getBalance();
    echo "当前可用短信余额：" . $balance . " 条\n";
} catch (\Luosimao\SmsSdk\Exceptions\LuosimaoException $e) {
    echo "查询失败：" . $e->getMessage();
}
```

## 测试

执行 PHPUnit 运行完整的单元测试：

```bash
./vendor/bin/phpunit
```

## 许可证

MIT License.