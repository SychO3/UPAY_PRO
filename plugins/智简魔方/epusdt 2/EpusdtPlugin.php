<?php
namespace gateways\epusdt;

class EpusdtPlugin extends \app\admin\lib\Plugin
{
    public $info = ["name" => "Epusdt", "title" => "EpUSDT支付插件", "description" => "EpUSDT支付插件", "status" => 1, "author" => "kid_jok", "version" => "1.2.1", "module" => "gateways"];
    public $hasAdmin = 0;
    public function epusdtidcsmartauthorize()
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
    public function epusdtHandle($param)
    {
        $Con = new controller\ConfigController();
        $config = $Con->getConfig();
        $payData = $this->filterPay($param["out_trade_no"], $param["total_fee"]);
        if (isset($payData["code"]) && !$payData["code"]) {
            unset($payData["code"]);
            return $payData;
        }
        $order = \think\Db::table("shd_invoices")->where("id", $param["out_trade_no"])->find();
        $html = "<div style=\"margin: 0px -25px;\"><p>订单编号：" . $param["out_trade_no"] . "</p>";
        $html .= "<p>商品名称：" . $param["product_name"] . "</p>";
        $html .= "<p>商品价格：" . $param["total_fee"] . "</p>";
        $html .= "<p>货币单位：" . $param["fee_type"] . "</p>";
        $html .= "<p>订单创建时间：" . date("Y-m-d H:i:s", $order["create_time"]) . "</p>";
        $html .= "<p> </p>";
        $html .= "<p>支付方式：" . $config["module_name"] . "</p>";
        $html .= "<p>需支付金额(USDT)：" . $payData["actual_amount"] . "</p>";
        $html .= "<p> </p>";
        $html .= "<div style=\"text-align: center\"><a href=\"" . $payData["payment_url"] . "\" target=\"_blank\" id=\"goEpUSDTPay\" style=\"background-color: #6064ff;color: #ffffff;padding: 10px 15px;border-radius: 5px;\">立即前往支付</a></div></div>";
        $html .= "<p> </p>";
        $html .= "<p style=\"color:red;\">请等待支付界面自动跳转，勿主动关闭支付界面！</p>";
        $html .= "<p> </p>";
        $html .= "<script>";
        $html .= "var goEpUSDTPay = document.getElementById(\"goEpUSDTPay\");";
        $html .= "goEpUSDTPay.onclick=function(){if(this.disabled==true){return false;}else{this.innerHTML=\"等到支付中...\";this.style.backgroundColor=\"#ffc107\";this.disabled=true}}";
        $html .= "</script>";
        return ["type" => "html", "data" => $html];
    }
    private function filterPay($order_no, $amount)
    {
        $domain = configuration("domain");
        $Con = new controller\ConfigController();
        $config = $Con->getConfig();
        $param = ["amount" => round($amount, 2), "order_id" => "mofang_pay_" . $order_no . "_" . time(), "notify_url" => $domain . "/gateway/epusdt/index/notify_handle", "redirect_url" => $domain . "/viewbilling?id=" . $order_no];
        try {
            $param["signature"] = $Con->epusdtSign($param, $config["epusdt_token"]);
            $data = $this->curl_request($config["epusdt_api"] . "/api/v1/order/create-transaction", json_encode($param));
            $data = json_decode($data, true);
            if (!isset($data["status_code"]) || $data["status_code"] != 200) {
                throw new \Exception("EPUSDT订单创建失败," . $data["message"]);
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