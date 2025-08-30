# UPay Pro

一个基于 Go 语言开发的现代化加密货币支付网关系统，支持多种数字货币支付，提供完整的订单管理和自动化支付验证功能。

本项目基于原 EPUSDT 项目进行第二次重写，添加了新的功能，支持了新的支付方式，并且优化了代码结构，提高了性能。

原项目地址：https://github.com/assimon/epusdt
首次二开项目地址：https://github.com/wangegou/UPAY

感谢原作者 assimon，因为 epusdt 项目，我才发现 go 这个语言，才会去学习 go 开发。

## 🚀 项目特性

- **多币种支持**: 支持 USDT-TRC20、TRX、USDT-Polygon、USDT-BSC 、USDT-ERC20 、USDT-ArbitrumOne、USDC-ERC20、USDC-Polygon、USDC-BSC、USDC-ArbitrumOne 等主流数字货币
- **自动化验证**: 实时监控区块链交易，自动验证支付状态
- **管理后台**: 完整的 Web 管理界面，支持订单管理、用户管理、钱包配置
- **API 接口**: RESTful API 设计，易于集成到现有系统
- **安全可靠**: MD5 签名验证，JWT 认证，确保交易安全
- **实时通知**: 支持 Telegram、Bark 等多种通知方式
- **高性能**: 基于 Gin 框架，支持高并发处理
- **补单功能**:支持手动补单
- **钱包轮询**: 真正支持自动轮询每笔交易钱包分配

## 📋 系统要求

- Go 1.24.4 或更高版本【二开推荐】
- SQLite 数据库
- Redis

## 🛠️ 安装部署

下载编译后的文件直接启动即可

YouTube：https://youtu.be/-jsk6_KKUy4

反向代理端口设置http://127.0.0.1:8090

尝鲜预览 [UPAY Pro 预览](预览.md)

### . 访问系统

- 主页: 你的网站域名

- 初始账号密码：在日志文件中，直接查看即可，保存后可以删除日志记录

### 插件

独角数卡插件： 参考 [ 独角数卡插件对接文档](plugins/独角数卡/独角数卡对接文档.md)

异次元发卡： 参考 [ 异次元发卡插件对接文档](plugins/异次元/异次元发卡对接文档.md)

萌次元发卡： 参考 [ 萌次元发卡插件对接文档](plugins/萌次元/萌次元对接文档.md)

v2boardpro 和 Xboard： 参考 [ v2boardpro 插件对接文档](plugins/v2boardpro)

易支付：参考 [ 易支付插件对接文档](plugins/易支付)

WHMCS 插件： 参考 [ WHMCS 插件对接文档](plugins/WHMCS插件/配置教程.md)
@Jason_0o 协助开发

[易支付源码下载](plugins/易支付)

#### APIkey 申请(系统已经自带，高频交易用户请自行申请 APIkey，在后台替换即可)

1. tronscan： https://tronscan.org/
2. TronGrid： https://www.trongrid.io/
3. etherscan： https://etherscan.io/

### Docker 傻瓜操作

```bash
docker run -d \
  --name upay_pro \
  -p 8090:8090 \
  -v upay_logs:/app/logs \
  -v upay_db:/app/DBS \
  --restart always \
wangergou111/upay:latest
```

默认日志挂载路径为：

```
/var/lib/docker/volumes/upay_logs/\_data
```

默认数据库挂载路径为：

```
/var/lib/docker/volumes/upay_db/\_data
```

反向代理设置：http://127.0.0.1:8090

#### Docker 高手 拉取镜像，自定义启动参数

```bash
docker pull wangergou111/upay:latest
```

### Docker 更新

1. 停止容器
2. 删除容器
3. 删除镜像
4. 拉取最新镜像
5. 启动容器
   关于数据：
   1 、因为之前你的日志和数据库是以卷方式挂载的，所以更新镜像后，数据不会丢失。
   2 、如果你之前是自定义挂载的，请重新挂载即可

#### 反馈与建议

欢迎反馈问题，请在 GitHub 上提交问题，或者在项目中提交 PR。

电报：https://t.me/hellokvm 群组：https://t.me/UPAY_BUG 邮箱：8888@iosapp.icu

## 📚 API 文档

### 创建订单

```http
POST /api/create_order
Content-Type: application/json

{
  "type": "USDT-TRC20",
  "order_id": "ORDER123456",
  "amount": 100.0,
  "notify_url": "https://example.com/notify",
  "redirect_url": "https://example.com/return",
  "signature": "calculated_md5_signature"
}
```

### 查询订单状态

```http
GET /pay/check-status/{trade_id}
```

### 支付页面

```http
GET /pay/checkout-counter/{trade_id}
```

详细的 API 文档请参考 [支付接口 API 文档.md](./支付接口API文档.md)

## 🔧 配置说明

### 支持的数字货币

- **USDT-TRC20**: 基于 TRON 网络的 USDT
- **TRX**: TRON 原生代币
- **USDT-Polygon**: 基于 Polygon 网络的 USDT
- **USDT-BSC**: 基于 BSC 网络的 USDT
- **USDT-ERC20**: 基于 ERC20 网络的 USDT
- **USDT-ArbitrumOne**: 基于 ArbitrumOne 网络的 USDT
- **USDC-ERC20**: 基于 ERC20 网络的 USDC
- **USDC-Polygon**: 基于 Polygon 网络的 USDC
- **USDC-BSC**: 基于 BSC 网络的 USDC
- **USDC-ArbitrumOne**: 基于 ArbitrumOne 网络的 USDC

### 钱包配置

在管理后台中配置各币种的收款钱包地址和汇率信息。

### 通知配置

支持以下通知方式：

- Telegram Bot 通知
- Bark 推送通知

## 🏗️ 项目结构

```
upay_pro/
├── main.go                 # 程序入口
├── web/                    # Web 服务和路由
│   ├── web.go             # 主要路由定义
│   └── function.go        # 业务逻辑函数
├── db/                     # 数据库相关
│   ├── sdb/               # SQLite 数据库操作
│   └── rdb/               # Redis 数据库操作
├── cron/                   # 定时任务
│   └── cron.go            # 支付状态检查任务
├── USDT_Polygon/          # Polygon 网络支付处理
├── tron/                   # TRON 网络支付处理
├── trx/                    # TRX 支付处理
├── notification/           # 通知服务
│   ├── telegram.go        # Telegram 通知
│   └── bark.go            # Bark 通知
├── dto/                    # 数据传输对象
├── mylog/                  # 日志服务
├── mq/                     # 消息队列
└── static/                 # 静态文件
    ├── admin.html         # 管理后台页面
    ├── index.html         # 主页
    ├── login.html         # 登录页面
    └── pay.html           # 支付页面
```

## 🔐 安全特性

- **签名验证**: 所有 API 请求都需要 MD5 签名验证
- **JWT 认证**: 管理后台使用 JWT 令牌认证
- **参数验证**: 严格的输入参数验证
- **HTTPS 支持**: 生产环境建议使用 HTTPS

## 📊 监控和日志

- **结构化日志**: 使用 Zap 日志库，支持日志轮转
- **订单监控**: 实时监控订单状态变化
- **性能监控**: 支持请求耗时和错误率监控

## 🔄 定时任务

系统包含以下定时任务：

- **支付检查**: 每 5 秒检查一次未支付订单的区块链状态

## 📄 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 🆘 支持

如果您在使用过程中遇到问题，请：

1. 查看 [Issues](https://github.com/your-username/upay_pro/issues) 中是否有类似问题
2. 创建新的 Issue 描述您的问题
3. 提供详细的错误信息和复现步骤

## 🙏 致谢

感谢以下开源项目：

- [Gin](https://github.com/gin-gonic/gin) - HTTP Web 框架
- [GORM](https://gorm.io/) - ORM 库
- [Zap](https://github.com/uber-go/zap) - 日志库
- [Cron](https://github.com/robfig/cron) - 定时任务库

---

**注意**: 本项目仅供学习和研究使用，请确保在合法合规的前提下使用本系统。
