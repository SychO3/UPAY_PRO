<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * WHMCS UPAY Payment Gateway Module for USDC-Polygon
 * 
 * This module allows WHMCS to integrate with UPAY digital currency payment system.
 * It is specifically for USDC-Polygon payments.
 */

/**
 * Define module metadata
 * 
 * @return array Module metadata
 */
function upay_usdc_polygon_MetaData()
{
    return array(
        'DisplayName' => 'USDC-Polygon',
        'APIVersion' => '1.1',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define module configuration
 * 
 * @return array Configuration fields
 */
function upay_usdc_polygon_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'USDC-Polygon',
        ),
        'apiurl' => array(
            'FriendlyName' => 'API接口地址',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => '以http://或https://开头，末尾不要有斜线/',
        ),
        'apikey' => array(
            'FriendlyName' => 'API密钥',
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => '在UPAY后台系统设置中获取的系统密钥',
        ),
        'paymenttype' => array(
            'FriendlyName' => '支付类型',
            'Type' => 'dropdown',
            'Options' => array(
                'USDC-Polygon' => 'USDC-Polygon',
            ),
            'Description' => '选择要使用的支付类型',
            'Default' => 'USDC-Polygon',
        ),
    );
}

/**
 * Generate payment link
 * 
 * @param array $params Payment parameters
 * @return string HTML form or error message
 */
function upay_usdc_polygon_link($params)
{
    $apiUrl = rtrim($params['apiurl'], '/');
    $apiKey = $params['apikey'];
    $paymentType = 'USDC-Polygon'; // 固定支付类型
    
    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];
    
    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $phone = $params['clientdetails']['phonenumber'];
    
    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];
    
    // Validate configuration
    if (empty($apiUrl)) {
        return '<div class="alert alert-danger">错误：API接口地址未配置</div>';
    }
    
    if (empty($apiKey)) {
        return '<div class="alert alert-danger">错误：API密钥未配置</div>';
    }
    
    if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
        return '<div class="alert alert-danger">错误：API接口地址格式不正确</div>';
    }
    
    // Create order parameters
    $order = array(
        'order_id' => (string)$invoiceId,
        'amount' => floatval($amount),
        'type' => $paymentType,
        'notify_url' => $systemUrl . 'modules/gateways/callback/upay_usdc_polygon.php',
        'redirect_url' => $returnUrl,
    );
    
    // Generate signature
    $signature = upay_usdc_polygon_generateSignature($order, $apiKey);
    $order['signature'] = $signature;
    
    // Log request data for debugging
    logModuleCall(
        'upay_usdc_polygon',
        'create_order_request',
        json_encode($order),
        "准备发送请求到: " . $apiUrl . '/api/create_order'
    );
    
    // Send request to UPAY
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/api/create_order');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'User-Agent: WHMCS UPAY Gateway Module'
    ));
    
    // For self-signed certificates
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log response for debugging
    logModuleCall(
        'upay_usdc_polygon',
        'create_order_response',
        "HTTP Code: " . $httpCode . ", cURL Error: " . $curlError,
        $response
    );
    
    // Handle connection errors
    if ($response === false) {
        return '<div class="alert alert-danger">支付接口请求失败：' . $curlError . '</div>';
    }
    
    // Handle HTTP errors
    if ($httpCode !== 200) {
        // 特别处理400错误
        if ($httpCode == 400) {
            $errorMsg = '请求参数错误';
            // 尝试解析错误响应
            $errorResult = json_decode($response, true);
            if ($errorResult && isset($errorResult['message'])) {
                $errorMsg = $errorResult['message'];
            }
            return '<div class="alert alert-danger">支付接口请求失败，HTTP状态码：' . $httpCode . ' (' . $errorMsg . ')。请检查API密钥和请求参数是否正确。</div>';
        } else {
            return '<div class="alert alert-danger">支付接口请求失败，HTTP状态码：' . $httpCode . '</div>';
        }
    }
    
    $result = json_decode($response, true);
    
    // Handle JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '<div class="alert alert-danger">支付接口返回数据解析失败：' . json_last_error_msg() . '</div>';
    }
    
    if (isset($result['status_code']) && $result['status_code'] == 200) {
        // Payment URL
        $paymentUrl = $result['data']['payment_url'];
        
        $code = '<form method="get" action="' . $paymentUrl . '">';
        $code .= '<input type="submit" value="' . $langPayNow . '" class="btn btn-success btn-block" />';
        $code .= '</form>';
        
        return $code;
    } else {
        $errorMsg = isset($result['message']) ? $result['message'] : '未知错误';
        return '<div class="alert alert-danger">创建支付订单失败: ' . $errorMsg . '</div>';
    }
}

/**
 * Generate signature for UPAY API
 * 
 * @param array $params Request parameters
 * @param string $apiKey API key
 * @return string MD5 signature
 */
function upay_usdc_polygon_generateSignature($params, $apiKey)
{
    // 根据API文档要求，先提取所有参数键名
    $keys = array_keys($params);
    
    // 对参数键名进行字母排序
    sort($keys);
    
    // 按照排序后的键名顺序拼接参数
    $paramStr = '';
    foreach ($keys as $key) {
        $value = $params[$key];
        // 根据API文档要求，跳过空值参数
        if ($value !== '') {
            $paramStr .= $key . '=' . $value . '&';
        }
    }
    
    // 根据API文档要求，去除末尾的&符号，然后拼接API密钥
    $paramStr = rtrim($paramStr, '&') . $apiKey;
    
    // 根据API文档要求，对拼接后的字符串进行MD5加密
    return md5($paramStr);
}