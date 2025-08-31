<?php
namespace gateways\epusdt\controller;

class ConfigController extends \think\Controller
{
    public function getConfig()
    {
        $get_name = new \gateways\epusdt\EpusdtPlugin();
        $name = $get_name->info["name"];
        if (isset($_config[$name])) {
            return $_config[$name];
        }
        $config = db("plugin")->where("name", $name)->value("config");
        if (!empty($config) && $config != "null") {
            $config = json_decode($config, true);
            $_config[$name] = $config;
            return $config;
        }
        return json(["msg" => "请先将EPUSDT相关信息配置收入", "status" => 400]);
    }
    public function epusdtSign(array $parameter, $signKey)
    {
        ksort($parameter);
        reset($parameter);
        $sign = "";
        $urls = "";
        foreach ($parameter as $key => $val) {
            if ($val != "") {
                if ($key != "signature") {
                    if ($sign != "") {
                        $sign .= "&";
                        $urls .= "&";
                    }
                    $sign .= $key . "=" . $val;
                    $urls .= $key . "=" . urlencode($val);
                }
            }
        }
        return md5($sign . $signKey);
    }
}

?>