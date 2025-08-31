<?php
namespace gateways\upay\controller;

class IndexController extends \think\Controller
{
    public function notify_handle()
    {
        $param = request()->param();
        $Con = new ConfigController();
        $config = $Con->getConfig();
        
        $postData = [
            "trade_id" => $param["trade_id"], 
            "order_id" => $param["order_id"], 
            "amount" => floatval($param["amount"]), 
            "actual_amount" => floatval($param["actual_amount"]), 
            "token" => $param["token"], 
            "block_transaction_id" => $param["block_transaction_id"], 
            "status" => $param["status"]
        ];
        
        $postSign = $Con->upaySign($postData, $config["upay_token"]);
        
        if ($postSign !== $param["signature"]) {
            return "fail";
        }
        
        if ($param["status"] != 2) {
            return "fail";
        }
        
        $order_id = explode("_", $param["order_id"]);
        $out_trade_no = $order_id[2];
        $where = ["id" => $out_trade_no];
        
        if (!db("invoices")->where($where)->find()) {
            return "fail, no invoice";
        }
        
        $up_data = [];
        $up_data["invoice_id"] = $out_trade_no;
        $up_data["amount_in"] = $param["amount"];
        $up_data["trans_id"] = $param["trade_id"];
        $up_data["currency"] = $param["currency"] ?? "CNY";
        $up_data["paid_time"] = date("Y-m-d H:i:s");
        $up_data["payment"] = "Upay";
        
        $Order = new \app\home\controller\OrderController();
        $Order->orderPayHandle($up_data);
        return "ok";
    }
    
    public function redirect_handle()
    {
        echo "<script>console.log('upay redirect');this.window.opener = null;window.open('about:blank','_self');window.close();</script>";
    }
}

?>