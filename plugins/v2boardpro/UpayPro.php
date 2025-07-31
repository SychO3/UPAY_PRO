<?php

namespace App\Payments;

/**
 * UpayPro 支付插件
 * 优化版本 - 修复异步回调500错误
 * @property array $config
 */
class UpayPro {
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'upaypro_url' => [
                'label' => 'API 地址',
                'description' => '您的 EPUSDT API 接口地址(例如: https://xxx.com)',
                'type' => 'input',
            ],
            'secret_key' => [
                 'label' => 'Secret Key',
                 'description' => '系统安全密钥（与后端SecretKey保持一致）',
                 'type' => 'input',
             ],
             'type' => [
                'label' => '支付通道可选|USDT-TRC20|TRX|USDT-Polygon|USDT-BSC|USDT-ERC20|USDT-ArbitrumOne|USDC-ERC20|USDC-Polygon|USDC-BSC|USDC-ArbitrumOne',
                'description' => '',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order)
    {
        $params = [
            'amount' => $order['total_amount'] / 100,
            'notify_url' => $order['notify_url'],
            'order_id' => $order['trade_no'],
            'redirect_url' => $order['return_url'],
            'type' => $this->config['type']
        ];
        ksort($params);
        reset($params);
        $str = stripslashes(urldecode(http_build_query($params))) . $this->getSecretKey();
        $params['signature'] = md5($str);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['upaypro_url'] . '/api/create_order');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_USERAGENT, 'EPUSDT');
        
        $response = curl_exec($ch);
        $result = json_decode($response);
        curl_close($ch);

        if (!isset($result->status_code) || $result->status_code != 200) {
            http_response_code(500);
            die(json_encode(['error' => "Failed to create order. Error: {$result->message}"]));
        }

        $paymentURL = $result->data->payment_url;
        return [
            'type' => 1, // 0:qrcode 1:url
            'data' => $paymentURL
        ];
    }

    public function notify($params)
    {
        try {
            // 如果$params为空或不是数组，尝试从JSON输入获取数据
            if (empty($params) || !is_array($params)) {
                $input = file_get_contents('php://input');
                if (!empty($input)) {
                    $jsonData = json_decode($input, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                        $params = $jsonData;
                    }
                }
            }
            
            // 如果仍然没有参数，尝试从$_POST获取
            if (empty($params)) {
                $params = $_POST;
            }
            
            // 记录接收到的回调参数
            error_log('[UpayPro] Received callback params: ' . json_encode($params));
            error_log('[UpayPro] Raw input: ' . file_get_contents('php://input'));
            
            // 验证必填参数
            $requiredParams = ['signature', 'trade_id', 'order_id', 'amount', 'actual_amount', 'token', 'block_transaction_id', 'status'];
            foreach ($requiredParams as $param) {
                if (!isset($params[$param])) {
                    throw new \Exception("Missing required parameter: {$param}");
                }
            }

            // 签名验证 - 按照cron.go中generateSignature的逻辑
            $sign = $params['signature'];
            unset($params['signature']);
            
            // 构建签名字符串，按照cron.go的顺序和逻辑
            $signParams = [
                'trade_id=' . $params['trade_id'],
                'order_id=' . $params['order_id'],
                'amount=' . $params['amount'],
                'actual_amount=' . $params['actual_amount'],
                'token=' . $params['token'],
                'block_transaction_id=' . $params['block_transaction_id'],
                'status=' . $params['status']
            ];
            
            // 过滤空值并排序 - 按照Go代码的逻辑，只过滤整个字符串为空的情况
            $filteredParams = [];
            foreach ($signParams as $param) {
                if ($param !== '') {
                    $filteredParams[] = $param;
                }
            }
            sort($filteredParams);
            
            $signString = implode('&', $filteredParams) . $this->getSecretKey();
            $calculatedSign = md5($signString);
            
            error_log('[UpayPro] Sign string: ' . $signString);
            error_log('[UpayPro] Calculated signature: ' . $calculatedSign);
            error_log('[UpayPro] Received signature: ' . $sign);
            
            if ($sign !== $calculatedSign) {
                throw new \Exception('Signature verification failed');
            }

            // 状态验证
            $status = (int)$params['status'];
            // 1: pending 2: success 3: expired
            if ($status != 2) {
                throw new \Exception("Invalid order status: {$status}");
            }

            // 记录成功日志
            error_log('[UpayPro] Callback verified successfully for order: ' . $params['order_id']);

            // 返回订单信息给v2board框架处理
            return [
                'trade_no' => $params['order_id'],
                'callback_no' => $params['trade_id'],
                'custom_result' => 'ok'  // 自定义返回结果，Go后端期望收到'ok'
            ];
            
        } catch (\Exception $e) {
            // 记录错误日志
            error_log('[UpayPro] Callback error: ' . $e->getMessage());
            
            // 抛出异常让框架处理，框架会返回500状态码
            throw $e;
        }
    }

    /**
     * 获取签名密钥
     * @return string
     */
    private function getSecretKey()
    {
        if (empty($this->config['secret_key'])) {
            throw new \Exception('Secret key not configured');
        }
        
        return $this->config['secret_key'];
    }
}