# UPAY Pro 支付接口 API 文档

## 概述

本文档描述了 UPAY Pro 系统中的支付相关 API 接口，包括订单创建、支付页面和订单状态查询等功能。

## 基础信息

- **基础 URL**: `http://localhost:8080` (可通过系统设置配置)
- **Content-Type**: `application/json`
- **签名算法**: MD5

## 签名验证

### 签名生成规则

1. 将请求参数按以下格式拼接：

   ```
   type={type}&amount={amount}&notify_url={notify_url}&order_id={order_id}&redirect_url={redirect_url}
   ```

2. 对参数进行字母排序

3. 拼接密钥：`{sorted_params}&{secret_key}`

4. 对拼接后的字符串进行 MD5 加密

### 示例

```
原始参数：
type=USDT
amount=100.00
notify_url=https://example.com/notify
order_id=ORDER123
redirect_url=https://example.com/return

排序后：
amount=100.00&notify_url=https://example.com/notify&order_id=ORDER123&redirect_url=https://example.com/return&type=USDT

加密前字符串：
amount=100.00&notify_url=https://example.com/notify&order_id=ORDER123&redirect_url=https://example.com/return&type=USDT{secret_key}

签名：MD5(上述字符串)
```

## API 接口

### 1. 创建订单

**接口地址**: `POST /api/create_order`

**请求头**:

```
Content-Type: application/json
```

**请求参数**:

```json
{
  "type": "USDT",
  "order_id": "ORDER123456",
  "amount": 100.0,
  "notify_url": "https://example.com/notify",
  "redirect_url": "https://example.com/return",
  "signature": "calculated_md5_signature"
}
```

**参数说明**:
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| type | string | 是 | 货币类型，如：USDT、BTC 等 |
| order_id | string | 是 | 商户订单号，唯一标识 |
| amount | float64 | 是 | 订单金额，最小 0.01 |
| notify_url | string | 是 | 异步通知地址，必须是有效 URL |
| redirect_url | string | 是 | 支付完成后跳转地址，必须是有效 URL |
| signature | string | 是 | 签名，按照签名规则生成 |

**成功响应**:

```json
{
  "status_code": 200,
  "message": "success",
  "data": {
    "trade_id": "202507081930299469",
    "order_id": "ORDER123456789",
    "amount": 10,
    "actual_amount": 1,
    "token": "7410410sadsadad",
    "expiration_time": 1751974829,
    "payment_url": "http://localhost:/pay/checkout-counter/202507081930299469"
  }
}
```

**错误响应**:

```json
{
  "code": 1,
  "message": "错误描述"
}
```

**可能的错误**:

- `参数错误`: 请求参数格式不正确
- `签名验证失败`: 签名计算错误
- `没有配置这个货币类型的钱包地址`: 不支持的货币类型
- `钱包汇率配置错误`: 汇率配置异常
- `换算后的支付金额低于最小支付金额0.01`: 金额过小
- `经过100次最大递增次数，仍然没有合适的金额，请稍后再试`: 系统繁忙

### 2. 获取支付页面

**接口地址**: `GET /pay/checkout-counter/{trade_id}`

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| trade_id | string | 是 | 系统生成的交易 ID |

**响应**: 返回支付页面 HTML

**说明**: 该接口返回支付页面，包含二维码、倒计时、支付金额等信息

### 3. 查询订单状态

**接口地址**: `GET /pay/check-status/{trade_id}`

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| trade_id | string | 是 | 系统生成的交易 ID |

**成功响应**:

```json
{
  "data": {
    "status": 1
  },
  "message": "1-待支付，2-支付成功，3-支付过期"
}
```

**状态说明**:

- `1`: 待支付
- `2`: 支付成功
- `3`: 支付过期

**错误响应**:

```json
{
  "message": "获取订单信息失败"
}
```

## 管理后台 API

### 1. 手动完成订单

**接口地址**: `POST /admin/api/manual-complete-order`

**权限**: 需要管理员登录

**请求参数**:

```json
{
  "order_id": "ORDER123456"
}
```

**成功响应**:

```json
{
  "code": 0,
  "message": "订单已手动完成"
}
```

### 2. 获取订单列表

**接口地址**: `GET /admin/api/orders`

**权限**: 需要管理员登录

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认 1 |
| limit | int | 否 | 每页数量，默认 10，最大 100 |
| search | string | 否 | 搜索关键词，支持订单号和交易 ID |

**成功响应**:

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "orders": [
      {
        "ID": 1,
        "TradeId": "202312101234561234",
        "OrderId": "ORDER123456",
        "Amount": 100.0,
        "ActualAmount": 100.01,
        "Type": "USDT",
        "Token": "TRX_WALLET_ADDRESS",
        "Status": 1,
        "CreatedAt": "2023-12-10T12:34:56Z",
        "UpdatedAt": "2023-12-10T12:34:56Z"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 10
  }
}
```

## 数据结构

### Orders 订单结构

```go
type Orders struct {
    gorm.Model
    TradeId            string  // UPAY订单号
    OrderId            string  // 客户交易id
    BlockTransactionId string  // 区块id
    Amount             float64 // 订单金额，保留2位小数
    ActualAmount       float64 // 订单实际需要支付的金额，保留4位小数
    Type               string  // 钱包类型
    Token              string  // 所属钱包地址
    Status             int     // 1：等待支付，2：支付成功，3：已过期
    NotifyUrl          string  // 异步回调地址
    RedirectUrl        string  // 支付完成跳转地址
    StartTime          int64   // 开始时间
    ExpirationTime     int64   // 过期时间
}
```

### RequestParams 请求参数结构

```go
type RequestParams struct {
    Type        string  `json:"type" validate:"required"`
    OrderID     string  `json:"order_id" validate:"required"`
    Amount      float64 `json:"amount" validate:"required,gte=0.01"`
    NotifyURL   string  `json:"notify_url" validate:"required,url"`
    RedirectURL string  `json:"redirect_url" validate:"required,url"`
    Signature   string  `json:"signature" validate:"required"`
}
```

### Response 响应结构

```go
type Response struct {
    StatusCode int
    Message    string
    Data       Data
}

type Data struct {
    TradeID        string
    OrderID        string
    Amount         float64
    ActualAmount   float64
    Token          string
    ExpirationTime int64
    PaymentURL     string
}
```

## 常量定义

```go
const (
    CnyMinimumPaymentAmount  = 0.01 // CNY最低支付金额
    UsdtMinimumPaymentAmount = 0.01 // USDT最低支付金额
    UsdtAmountPerIncrement   = 0.01 // USDT每次递增金额
    IncrementalMaximumNumber = 100  // 最大递增次数
)
```

## 状态码说明

### 订单状态

- `1`: 等待支付 (StatusWaitPay)
- `2`: 支付成功 (StatusPaySuccess)
- `3`: 已过期 (StatusExpired)

### HTTP 状态码

- `200`: 成功
- `400`: 请求参数错误
- `401`: 签名验证失败
- `500`: 服务器内部错误

## 注意事项

1. **签名验证**: 所有创建订单的请求都需要进行签名验证
2. **金额精度**: 支付金额保留 2 位小数，实际支付金额保留 4 位小数
3. **订单过期**: 订单有过期时间限制，过期后无法支付
4. **金额递增**: 当相同金额的订单存在时，系统会自动递增 0.01 直到找到可用金额
5. **异步通知**: 支付成功后会向 notify_url 发送异步通知
6. **重定向**: 支付完成后会跳转到 redirect_url
