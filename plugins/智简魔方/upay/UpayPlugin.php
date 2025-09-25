<?php
namespace gateways\upay;

class UpayPlugin extends \app\admin\lib\Plugin
{
    public $info = [
        "name" => "Upay", 
        "title" => "UPAY支付插件", 
        "description" => "UPAY支付插件", 
        "status" => 1, 
        "author" => "UPAY", 
        "version" => "1.0.0", 
        "module" => "gateways"
    ];
    
    public $hasAdmin = 0;
    
    public function upayidcsmartauthorize()
    {
    }
    
    public function install()
    {
        return true;
    }
    
    public function uninstall()
    {
        return true;
    }
    
    public function upayHandle($param)
    {
        $Con = new \gateways\upay\controller\ConfigController();
        $config = $Con->getConfig();
        $payData = $this->filterPay($param["out_trade_no"], $param["total_fee"]);
        
        if (isset($payData["code"]) && !$payData["code"]) {
            unset($payData["code"]);
            return $payData;
        }
        
        // 直接跳转到支付页面，而不是显示中间页面
        // header("Location: " . $payData["payment_url"]);
        // exit();
         // 返回跳转链接而不是直接跳转
        return ['type' => 'jump', 'data' => $payData["payment_url"]];
    }
    
    private function filterPay($order_no, $amount)
    {
        $domain = configuration("domain");
        $Con = new \gateways\upay\controller\ConfigController();
        $config = $Con->getConfig();
        
        $param = [
            "amount" => round($amount, 2),
            "order_id" => "mofang_pay_" . $order_no . "_" . time(),
            "notify_url" => $domain . "/gateway/upay/index/notify_handle",
            "redirect_url" => $domain . "/viewbilling?id=" . $order_no,
            "type" => $config["upay_type"]
        ];
        
        try {
            $param["signature"] = $Con->upaySign($param, $config["upay_token"]);
            $data = $this->curl_request($config["upay_api"] . "/api/create_order", json_encode($param));
            $data = json_decode($data, true);
            
            if (!isset($data["status_code"]) || $data["status_code"] != 200) {
                throw new \Exception("UPAY订单创建失败," . (isset($data["message"]) ? $data["message"] : "未知错误"));
            }
        } catch (\Exception $e) {
            return ["type" => "html", "data" => $e->getMessage(), "code" => 0];
        }
        
        return $data["data"];
    }
    
    private function curl_request($url, $data = NULL, $method = "POST", $header = ["content-type: application/json"], $https = true, $timeout = 5)
    {
        $method = strtoupper($method);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($https) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        
        if ($method != "GET") {
            if ($method == "POST") {
                curl_setopt($ch, CURLOPT_POST, true);
            }
            if ($method == "PUT" || strtoupper($method) == "DELETE") {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}

?>