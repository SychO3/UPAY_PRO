<?php
return [
    "module_name" => [
        "title" => "支付名称", 
        "type" => "text", 
        "value" => "UPAY支付", 
        "tip" => "友好的显示名称"
    ], 
    "upay_type" => [
        "title" => "支付类型", 
        "type" => "select", 
        "value" => "USDT-TRC20",
        "options" => [
            "USDT-TRC20" => "USDT-TRC20",
            "USDT-ERC20" => "USDT-ERC20",
            "USDT-Polygon" => "USDT-Polygon",
            "USDT-BSC" => "USDT-BSC",
            "USDT-ArbitrumOne" => "USDT-ArbitrumOne",
            "USDC-ERC20" => "USDC-ERC20",

            "USDC-BSC" => "USDC-BSC",
            "USDC-Polygon" => "USDC-Polygon",
            "USDC-ArbitrumOne" => "USDC-ArbitrumOne",
            "TRX" => "TRX"
        ],
        "tip" => "选择UPAY支持的支付类型"
    ],
    "upay_token" => [
        "title" => "UPAY密钥", 
        "type" => "text", 
        "value" => "", 
        "tip" => "UPAY的api接口认证token"
    ], 
    "upay_api" => [
        "title" => "UPAY接口地址", 
        "type" => "text", 
        "value" => "", 
        "tip" => "UPAY的api接口地址，例如：http://localhost:8090"
    ]
];

?>