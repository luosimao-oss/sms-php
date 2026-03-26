<?php

namespace Luosimao\SmsSdk\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Luosimao\SmsSdk\Exceptions\LuosimaoException;
use Luosimao\SmsSdk\Enums\ErrorCode;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client
{
    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var GuzzleClient
     */
    protected $guzzle;

    /**
     * @var string
     */
    protected $baseUri = 'https://sms-api.luosimao.com/v1/';

    /**
     * @var int
     */
    protected $maxRetries = 3;

    /**
     * Client constructor.
     *
     * @param string $apiKey 
     * @param array $config 
     */
    public function __construct(string $apiKey, array $config = [])
    {
        // 请求签名封装：不暴露 key- 拼接细节，自动补充前缀
        $this->apiKey = strpos($apiKey, 'key-') === 0 ? $apiKey : 'key-' . $apiKey;

        if (isset($config['base_uri'])) {
            $this->baseUri = rtrim($config['base_uri'], '/') . '/';
        }

        if (isset($config['max_retries'])) {
            $this->maxRetries = (int)$config['max_retries'];
        }

        $this->guzzle = $this->createGuzzleClient($config);
    }

    /**
     * 创建 Guzzle 客户端并注入重试中间件
     *
     * @param array $config
     * @return GuzzleClient
     */
    protected function createGuzzleClient(array $config): GuzzleClient
    {
        // 如果传入了 handler (例如测试用的 MockHandler)，则基于它创建，否则创建默认的
        if (isset($config['handler']) && $config['handler'] instanceof HandlerStack) {
            $stack = $config['handler'];
        } else {
            $stack = HandlerStack::create(isset($config['handler']) ? $config['handler'] : null);
        }
        
        $stack->push($this->retryMiddleware(), 'retry');

        $clientConfig = array_merge([
            'base_uri' => $this->baseUri,
            'timeout'  => 10.0,
            // 鉴权封装：自动携带 Basic Auth，不需每次手动添加
            'auth'     => ['api', $this->apiKey],
        ], $config);
        
        $clientConfig['handler'] = $stack;

        return new GuzzleClient($clientConfig);
    }

    /**
     * 网络抖动自动重试中间件
     *
     * @return callable
     */
    protected function retryMiddleware(): callable
    {
        return Middleware::retry(
            function (
                $retries,
                RequestInterface $request,
                ResponseInterface $response = null,
                \Exception $exception = null
            ) {
                // 限制最大重试次数
                if ($retries >= $this->maxRetries) {
                    return false;
                }

                // 仅在网络连接错误（网络抖动）或 500 以上服务端错误时重试
                if ($exception instanceof ConnectException) {
                    return true;
                }

                if ($response && $response->getStatusCode() >= 500) {
                    return true;
                }

                return false;
            },
            function ($retries) {
                // 延迟策略：指数退避 (1s, 2s, 4s...)
                return (int) pow(2, $retries) * 1000;
            }
        );
    }

    /**
     * 发起 POST 请求
     *
     * @param string $endpoint
     * @param array $params
     * @return array
     * @throws LuosimaoException
     */
    public function post(string $endpoint, array $params = []): array
    {
        return $this->request('POST', $endpoint, [
            'form_params' => $params,
        ]);
    }

    /**
     * 发起 GET 请求
     *
     * @param string $endpoint
     * @param array $query
     * @return array
     * @throws LuosimaoException
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, [
            'query' => $query,
        ]);
    }

    /**
     * 执行 HTTP 请求并处理响应
     *
     * @param string $method
     * @param string $endpoint
     * @param array $options
     * @return array
     * @throws LuosimaoException
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            $response = $this->guzzle->request($method, $endpoint, $options);
            $body = (string) $response->getBody();
            $result = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new LuosimaoException('解析响应失败: ' . json_last_error_msg());
            }

            if (isset($result['error']) && $result['error'] !== ErrorCode::SUCCESS) {
                $code = (int) $result['error'];
                // 使用文档返回的 msg，若无则取本地定义的中文映射
                $msg = $result['msg'] ?? ErrorCode::getMessage($code);
                
                // 提取敏感词等额外信息
                $extraData = [];
                if (isset($result['hit'])) {
                    $extraData['hit'] = $result['hit'];
                }
                if (isset($result['batch_id'])) {
                    $extraData['batch_id'] = $result['batch_id'];
                }

                throw new LuosimaoException($msg, $code, null, $extraData);
            }

            return $result;

        } catch (GuzzleException $e) {
            throw new LuosimaoException('HTTP请求失败: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
