<?php
namespace gateways\upay_usdt_polygon;

class UpayUsdtPolygonPlugin extends \app\admin\lib\Plugin
{
    public $info = [
        "name" => "UpayUsdtPolygon", 
        "title" => "USDT-Polygon", 
        "description" => "USDT-Polygon", 
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
    
    public function UpayUsdtPolygonHandle($param)
    {
        $Con = new \gateways\upay_usdt_polygon\controller\ConfigController();
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
        $Con = new \gateways\upay_usdt_polygon\controller\ConfigController();
        $config = $Con->getConfig();
        
        $param = [
            "amount" => round($amount, 2),
            "order_id" => (string)$order_no,  // 确保订单ID作为字符串传递
            "notify_url" => $domain . "/gateway/upay_usdt_polygon/index/notify_handle",
            "redirect_url" => $domain . "/viewbilling?id=" . $order_no,
            "type" => "USDT-Polygon"  // 固定为USDT-Polygon
        ];
        
        try {
            $param["signature"] = $Con->upaySign($param, $config["upay_token"]);
            $data = $this->curl_request($config["upay_api"] . "/api/create_order", json_encode($param));
            $data = json_decode($data, true);
            
            if (!isset($data["status_code"]) || $data["status_code"] != 200) {
                throw new \Exception("UPAY订单创建失败," . (isset($data["message"]) ? $data["message"] : "未知错误") . 
                    " 返回数据: " . json_encode($data));
            }
        } catch (\Exception $e) {
            return ["type" => "html", "data" => $e->getMessage(), "code" => 0];
        }
        
        return $data["data"];
    }
    
    private function curl_request($url, $data = NULL, $method = "POST", $header = ["content-type: application/json"], $https = true, $timeout = 30)
    {
        $method = strtoupper($method);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($header, ["Content-Type: application/json", "User-Agent: UPAY Plugin"]));
        
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
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // 如果请求失败，抛出异常
        if ($result === false) {
            throw new \Exception("cURL错误: " . $curlError);
        }
        
        // 如果HTTP状态码不是200，抛出异常
        if ($httpCode !== 200) {
            throw new \Exception("HTTP错误: " . $httpCode . " 响应内容: " . $result);
        }
        
        return $result;
    }
}

?>