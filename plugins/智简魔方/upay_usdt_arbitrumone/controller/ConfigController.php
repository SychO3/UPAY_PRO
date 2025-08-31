<?php
namespace gateways\upay_usdt_arbitrumone\controller;

class ConfigController extends \think\Controller
{
    public function index()
    {
    }
    
    public function getConfig()
    {
        $get_name = new \gateways\upay_usdt_arbitrumone\UpayUsdtArbitrumonePlugin();
        $name = $get_name->info["name"];
        $_config = [];
        
        if (isset($_config[$name])) {
            return $_config[$name];
        }
        
        $config = db("plugin")->where("name", $name)->value("config");
        if (!empty($config) && $config != "null") {
            $config = json_decode($config, true);
            $_config[$name] = $config;
            return $config;
        }
        
        return json(["msg" => "请先将USDT-ArbitrumOne相关信息配置收入", "status" => 400]);
    }
    
    public function upaySign($param, $key)
    {
        if (!empty($param)) {
            ksort($param);
            $str = "";
            foreach ($param as $k => $v) {
                $str .= $k . "=" . $v . "&";
            }
            $strs = rtrim($str, "&") . $key;
            return md5($strs);
        }
        return null;
    }
}

?>