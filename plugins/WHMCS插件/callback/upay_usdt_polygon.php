<?php

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

// 获取回调数据
$payload = json_decode(file_get_contents("php://input"), true);

// 获取网关配置参数
$gatewayParams = getGatewayVariables('upay_usdt_polygon');

// 如果模块未激活则终止
if (!$gatewayParams['type']) {
    header('HTTP/1.1 400 Bad Request');
    echo 'fail';
    exit();
}

// 从配置中获取API密钥
$apiKey = $gatewayParams['apikey'];

// 检查订单状态是否为支付成功（status=2）
if (isset($payload["status"]) && $payload["status"] == 2) {
    // 验证签名
    $signature = $payload['signature'];
    unset($payload['signature']);
    
    // 根据API文档要求，按指定格式拼接回调参数
    $params = [
        'actual_amount=' . $payload['actual_amount'],
        'amount=' . $payload['amount'],
        'block_transaction_id=' . $payload['block_transaction_id'],
        'order_id=' . $payload['order_id'],
        'status=' . $payload['status'],
        'token=' . $payload['token'],
        'trade_id=' . $payload['trade_id']
    ];
    
    // 参数按字母顺序排序
    sort($params);
    
    // 拼接密钥：{sorted_params}&{secret_key}
    $signString = implode('&', $params) . $apiKey;
    
    // 对拼接后的字符串进行 MD5 加密
    $calculatedSignature = md5($signString);
    
    // 签名验证通过
    if ($signature === $calculatedSignature) {
        try {
            // 获取必要参数
            $invoiceId = $payload['order_id'];
            $transactionId = $payload['trade_id'];
            $paymentAmount = $payload['amount'];
            
            // 获取货币转换配置
            $convertto = $gatewayParams['convertto'];
            
            if ($convertto) {
                // 获取用户ID 和 用户使用的货币ID
                $data = Capsule::table("tblinvoices")->where("id", $invoiceId)->first();
                $userid = $data->userid;
                $currency = getCurrency($userid);
                $paymentAmount = convertCurrency($payload['amount'], $convertto, $currency["id"]);
            }
            
            /**
             * Validate Callback Invoice ID.
             *
             * Checks invoice ID is a valid invoice number. Note it will count an
             * invoice in any status as valid.
             *
             * Performs a die upon encountering an invalid Invoice ID.
             *
             * Returns a normalised invoice ID.
             */
            $gatewayName = $gatewayParams['name'];
            $invoiceId = checkCbInvoiceID($invoiceId, $gatewayName);
            
            /**
             * Check Callback Transaction ID.
             *
             * Performs a check for any existing transactions with the same given
             * transaction number.
             *
             * Performs a die upon encountering a duplicate.
             */
            checkCbTransID($transactionId);
            
            /**
             * Log Transaction.
             *
             * Add an entry to the Gateway Log for debugging purposes.
             */
            logTransaction($gatewayName, $payload, "Successful");
            
            /**
             * Add Invoice Payment.
             *
             * Applies a payment transaction entry to the given invoice ID.
             */
            addInvoicePayment($invoiceId, $transactionId, $paymentAmount, 0, $gatewayName);
            
            echo 'success';
            exit();
        } catch (Exception $e) {
            // 记录错误到WHMCS日志
            $gatewayName = $gatewayParams['name'];
            logTransaction($gatewayName, $payload, "Error: " . $e->getMessage());
            echo 'success'; // 即使处理失败也返回success，因为支付本身是成功的
            exit();
        }
    } else {
        // 签名验证失败
        $gatewayName = $gatewayParams['name'];
        logTransaction($gatewayName, $payload, "Signature Verification Failed");
        header('HTTP/1.1 400 Bad Request');
        echo 'fail';
        exit();
    }
} else {
    // 订单状态不是支付成功
    $gatewayName = $gatewayParams['name'];
    logTransaction($gatewayName, $payload, "Unsuccessful Status");
    header('HTTP/1.1 400 Bad Request');
    echo 'fail';
    exit();
}