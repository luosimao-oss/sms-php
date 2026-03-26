<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\ConnectException;
use Luosimao\SmsSdk\Sms;
use Luosimao\SmsSdk\Exceptions\LuosimaoException;
use Luosimao\SmsSdk\Enums\ErrorCode;

class SmsTest extends TestCase
{
    protected function getSmsClientWithMock(array $responses, string $apiKey = 'test-key'): Sms
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        return new Sms($apiKey, ['handler' => $handlerStack, 'max_retries' => 2]);
    }

    public function testSendSuccess()
    {
        $sms = $this->getSmsClientWithMock([
            new Response(200, [], json_encode(['error' => 0, 'msg' => 'ok'])),
        ]);

        $result = $sms->send('13800138000', '验证码：123456【测试】');

        $this->assertEquals(0, $result['error']);
        $this->assertEquals('ok', $result['msg']);
    }

    public function testSendBatchSuccess()
    {
        $sms = $this->getSmsClientWithMock([
            new Response(200, [], json_encode(['error' => 0, 'msg' => 'ok', 'batch_id' => '12345'])),
        ]);

        $result = $sms->sendBatch(['13800138000', '13800138001'], '验证码：123456【测试】');

        $this->assertEquals(0, $result['error']);
        $this->assertEquals('12345', $result['batch_id']);
    }

    public function testGetBalanceSuccess()
    {
        $sms = $this->getSmsClientWithMock([
            new Response(200, [], json_encode(['error' => 0, 'deposit' => '1000'])),
        ]);

        $balance = $sms->getBalance();

        $this->assertEquals(1000, $balance);
    }

    public function testLuosimaoExceptionThrownOnError()
    {
        $sms = $this->getSmsClientWithMock([
            new Response(200, [], json_encode(['error' => -20, 'msg' => '短信余额不足'])),
        ]);

        $this->expectException(LuosimaoException::class);
        $this->expectExceptionCode(ErrorCode::INSUFFICIENT_BALANCE);
        $this->expectExceptionMessage('短信余额不足');

        $sms->send('13800138000', '验证码：123456【测试】');
    }

    public function testSensitiveWordsExceptionIncludesHit()
    {
        $sms = $this->getSmsClientWithMock([
            new Response(200, [], json_encode(['error' => -31, 'msg' => '短信内容存在敏感词', 'hit' => '发票'])),
        ]);

        try {
            $sms->send('13800138000', '办理发票【测试】');
            $this->fail('Expected LuosimaoException to be thrown');
        } catch (LuosimaoException $e) {
            $this->assertEquals(ErrorCode::SENSITIVE_WORDS, $e->getCode());
            $this->assertArrayHasKey('hit', $e->getExtraData());
            $this->assertEquals('发票', $e->getExtraData()['hit']);
        }
    }

    public function testRetryOnNetworkError()
    {
        $sms = $this->getSmsClientWithMock([
            new ConnectException('Connection timeout', new Request('POST', 'test')),
            new Response(200, [], json_encode(['error' => 0, 'msg' => 'ok'])),
        ]);

        $result = $sms->send('13800138000', '验证码：123456【测试】');
        $this->assertEquals(0, $result['error']);
    }

    public function testRetryOnServerError()
    {
        $sms = $this->getSmsClientWithMock([
            new Response(502, [], 'Bad Gateway'),
            new Response(200, [], json_encode(['error' => 0, 'msg' => 'ok'])),
        ]);

        $result = $sms->send('13800138000', '验证码：123456【测试】');
        $this->assertEquals(0, $result['error']);
    }

    public function testExceedMaxRetriesThrowsException()
    {
        // 我们的 max_retries 设置为 2，这意味着：初始请求 (1) + 重试 1 次 + 重试 1 次 = 3 次请求
        $sms = $this->getSmsClientWithMock([
            new Response(500),
            new Response(500),
            new Response(500),
        ]);

        $this->expectException(LuosimaoException::class);

        $sms->send('13800138000', '验证码：123456【测试】');
    }

    public function testInvalidMobileThrowsException()
    {
        $sms = new Sms('test-key');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mobile number: 1234567890');

        $sms->send('1234567890', '验证码：123456【测试】');
    }

    public function testInvalidMobileInBatchThrowsException()
    {
        $sms = new Sms('test-key');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mobile number: 12345');

        $sms->sendBatch(['13800138000', '12345'], '验证码：123456【测试】');
    }

    public function testGetBalanceMissingDepositThrowsException()
    {
        $sms = $this->getSmsClientWithMock([
            new Response(200, [], json_encode(['error' => 0])),
        ]);

        $this->expectException(LuosimaoException::class);
        $this->expectExceptionMessage('Response missing "deposit" field');

        $sms->getBalance();
    }

    public function testConstructorWithClientInjection()
    {
        $mockClient = $this->createMock(\Luosimao\SmsSdk\Http\Client::class);
        $sms = new Sms($mockClient);

        $this->assertInstanceOf(Sms::class, $sms);
        
        // 使用反射检查私有属性 client 是否被正确设置
        $reflection = new \ReflectionClass($sms);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        
        $this->assertSame($mockClient, $property->getValue($sms));
    }
}
