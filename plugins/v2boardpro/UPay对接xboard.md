# 2025-7-26 之后的版本：

新版 Xboard 插件，直接上传 UpayPro.zip 即可

# 2025-7-26 之前的版本：按下面上传插件文件

# UPay 对接 xboard，实现 xboard 使用 usdt 收款

---

### 一、Upay 篇 |建议参看视频：https://youtu.be/-jsk6_KKUy4

1. 启动 upay

   - 打开地址：https://github.com/wangegou/UPAY_PRO
   - 点击右面 Releases 进入编译后的文件，下载当前最新版本：20250701001-Linux-amd64.zip。
   - 上传 20250701001-Linux-amd64.zip 至服务器，解压。
   - 解压后文件包括：upay_pro、static。其中 upay_pro 为编译后的可执行文件，一会要执行它，static 中是前端代码。
   - 赋可执行权限：chmod +x upay_pro
   - 在 upay_pro 目录下，输入以下可以运行 upay：./upay_pro

2. 访问 upay 后台

   - 运行起来后，默认端口是 8090，用户可以通过:http://ip 地址:8090 可以访问 upay 后台。或者如果已经搭建了 nginx 和域名，使用 nginx 反代，使用域名方式访问 upay。。
   - 查看 logs 目录下的日志，找到下面的用户名密码，并保存好：
     - 2025-07-16T10:20:17.841Z INFO sdb/sdb.go:113 初始用户名: {"username": "CAOA1aR9A"}
     - 2025-07-16T10:20:17.841Z INFO sdb/sdb.go:114 初始密码: {"password": "g0ae0tyiP"}
   - 访问 http://ip 地址:8090，或 https://域名 进入后台，使用上面用户名和密码登陆。

3. 配置 upay 后台
   - 登陆成功后，点击钱包地址管理-添加钱包地址，在弹出窗口中输入币种，比如：USDT-TRC20，钱包地址输入自己的 Trc20 收款地址，汇率自己选择，保存。
   - 点击系统设置，应用名称，自己输入自定义名称，应用地址输入当前访问 upay 的 url 地址，url 最后不要带反斜杠。

4.别忘记添加守护进程

---

### 二、Xboard 篇

1. 配置支付方式\*\*：
   - xboard 搭建好后，访问：https://github.com/wangegou/UPAY_PRO/tree/main/plugins/v2boardpro
   - 下载上面链接中的 UpayPro.php，将文件拷贝到 xboard 目录中：app/Payments 下面。
   - 打开 xboard 管理端，点击系统管理-支付配置-添加支付方式。
   - 在弹出的窗口中录入，显示名称：USDT-TRC20，支付接口选择 UpayPro，Api 地址输入之前访问 upay 的网址，SecretKey 值需要打开 UPay 后台，点击系统设置，复制里面的：系统密钥，到 SecretKey，支付通道输入：USDT-TRC20，提交保存。

---

### 三 xboard+upay 调试

1. 调式
   - 打开 xboard 用户端，去填加订阅，或续费订阅，下单，选择支付方式：USDT-TRC20，点结账，跳出 upay 付款页面。此时可以真实付款 usdt，也可以打开 upay 后台，进入订单管理，复制订单号到搜索按钮前的文本框内，点击：补单。当转账 usdt 后或补单后，正常情况下，网页会从 upay 付款页面跳转到 xboard 页面，并且提示已完成。
   - 如果不正常，检查 upay 所在目录 logs 里面的 upay.log。并分析。

### 补充

- 如果付款后，一直未完成，可以尝试用 ip 地址+端口的方式，进入 xboard 测试，如果这种方式可以，检查域名问题，可托管域名的比如 cloudflare，是否开启小黄云，可以开启或不开启尝试。检查域名是否被墙。
