<?php
// 测试UPAY插件日志功能
require_once 'plugins/易支付/UPAY_plugin.php';

// 测试不同级别的日志
echo "开始测试UPAY插件日志功能...\n";

// 使用反射来访问私有方法进行测试
$reflection = new ReflectionClass('UPAY_plugin');
$writeLogMethod = $reflection->getMethod('writeLog');
$writeLogMethod->setAccessible(true);

// 测试不同级别的日志
$writeLogMethod->invoke(null, '这是一条INFO级别的测试日志', 'INFO');
$writeLogMethod->invoke(null, '这是一条ERROR级别的测试日志', 'ERROR');
$writeLogMethod->invoke(null, '这是一条DEBUG级别的测试日志', 'DEBUG');
$writeLogMethod->invoke(null, '这是一条WARN级别的测试日志', 'WARN');

echo "日志测试完成！请查看 /Users/niu/Documents/upay_pro/logs/upay_plugin.log 文件\n";
echo "您可以使用以下命令查看日志：\n";
echo "tail -f /Users/niu/Documents/upay_pro/logs/upay_plugin.log\n";
?>