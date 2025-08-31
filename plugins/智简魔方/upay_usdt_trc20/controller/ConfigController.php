<?php
namespace gateways\upay_usdt_trc20\controller;

class ConfigController extends \think\Controller
{
    public function getConfig()
    {
        $get_name = new \gateways\upay_usdt_trc20\UpayUsdtTrc20Plugin();
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
        
        return json(["msg" => "请先将UPAY-USDT-TRC20相关信息配置收入", "status" => 400]);
    }
    
    public function upaySign(array $parameter, $signKey)
    {
        ksort($parameter);
        reset($parameter);
        $sign = "";
        
        foreach ($parameter as $key => $val) {
            if ($val != "" && $key != "signature") {
                if ($sign != "") {
                    $sign .= "&";
                }
                $sign .= $key . "=" . $val;
            }
        }
        
        return md5($sign . $signKey);
    }
}

?>