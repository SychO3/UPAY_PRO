# UPAY Pro 支付接口 API 文档

## 概述

本文档描述了 UPAY Pro 系统中的支付相关 API 接口，包括订单创建、支付页面和订单状态查询等功能。

## 基础信息

- **基础 URL**: `http://localhost:8090` (可通过系统设置配置)
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
type=USDT-TRC20
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
  "type": "USDT-TRC20",
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
| type | string | 是 | USDT-TRC20、TRX、 USDT-Polygon 等 |
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

请注意：payment_url 是你要跳转的支付页面，就是二维码付款的哪个页面地址

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

## 异步回调

当订单支付成功后，系统会向创建订单时提供的 `notify_url` 发送异步回调通知。

### 回调机制

- **触发时机**: 订单支付成功后自动触发
- **请求方式**: POST
- **Content-Type**: application/json
- **重试机制**: 最多重试 5 次，每次间隔 5 秒
- **成功标识**: 商户接口返回 "ok" 或 "success" 字符串

### 回调参数

```json
{
  "trade_id": "202507081930299469",
  "order_id": "ORDER123456789",
  "amount": 10.0,
  "actual_amount": 1.0001,
  "token": "TQn9Y2khEsLJW1ChVWFMSMeRDow5oNDMnt",
  "block_transaction_id": "abc123def456...",
  "status": 2,
  "signature": "calculated_md5_signature"
}
```

### 参数说明

| 参数名               | 类型    | 说明                             |
| -------------------- | ------- | -------------------------------- |
| trade_id             | string  | 系统生成的交易订单号             |
| order_id             | string  | 商户订单号                       |
| amount               | float64 | 原始订单金额                     |
| actual_amount        | float64 | 实际支付金额（含递增金额）       |
| token                | string  | 收款钱包地址                     |
| block_transaction_id | string  | 区块链交易哈希，如果为空则为 "0" |
| status               | int     | 订单状态：2=支付成功             |
| signature            | string  | 签名，用于验证回调数据完整性     |

### 签名验证

回调签名生成规则：

1. 将回调参数按以下格式拼接（排除 signature 字段）：

   ```
   actual_amount={actual_amount}&amount={amount}&block_transaction_id={block_transaction_id}&order_id={order_id}&status={status}&token={token}&trade_id={trade_id}
   ```

2. 参数按字母顺序排序

3. 拼接密钥：`{sorted_params}&{secret_key}`

4. 对拼接后的字符串进行 MD5 加密

### 商户响应要求

商户接收到回调后，需要：

1. **验证签名**: 使用相同算法验证 signature 参数
2. **验证订单**: 检查 order_id 和 amount 是否匹配
3. **返回成功**: 返回字符串 "ok" 或 "success"
4. **幂等处理**: 避免重复处理同一订单

### 示例代码（PHP）

按你自己系统需求写，不要照搬。

```php
<?php
// 接收回调数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 验证签名
function verifySignature($data, $secretKey) {
    $params = [
        'actual_amount=' . $data['actual_amount'],
        'amount=' . $data['amount'],
        'block_transaction_id=' . $data['block_transaction_id'],
        'order_id=' . $data['order_id'],
        'status=' . $data['status'],
        'token=' . $data['token'],
        'trade_id=' . $data['trade_id']
    ];

    sort($params);
    $signString = implode('&', $params) . $secretKey;
    return md5($signString) === $data['signature'];
}

// 处理回调
if (verifySignature($data, 'your_secret_key')) {
    // 验证订单并更新状态
    if ($data['status'] == 2) {
        // 订单支付成功，更新本地订单状态
        updateOrderStatus($data['order_id'], 'paid');
    }

    // 返回成功响应
    echo 'ok';
} else {
    // 签名验证失败
    http_response_code(400);
    echo 'signature error';
}
?>
```

### 注意事项

1. **安全性**: 必须验证回调签名，防止恶意请求
2. **幂等性**: 同一订单可能收到多次回调，需要做幂等处理
3. **超时设置**: 回调接口响应时间不应超过 10 秒
4. **状态检查**: 只有 status=2 时表示支付成功
5. **网络异常**: 如果回调失败，系统会自动重试最多 5 次

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

# 关于币种支持插件开发

1. 在根目录写好插件代码模块。
2. 在 cron.go 中大概 167 行新增 case 条件，新增插件模块调用代码。
3. 在 function.go 中大概 438 行新增 case 条件，新增图标地址。
4. 在 admin.html ，新增菜单选项。
5. 在你创建订单时，币种类型写你的开发的币种名称。
   **如何新增菜单选项**

以后如果需要新增币种选项，请按以下步骤操作：

1. **找到对应位置**：在 `admin.html` 文件中搜索 `<option value="USDT-Polygon">USDT-Polygon</option>`

2. **添加新选项**：在现有选项后面添加新的 `<option>` 标签，格式如下：

   ```html
   <option value="新币种代码">新币种显示名称</option>
   ```

3. **需要修改的位置**：

   - 添加钱包地址模态框（约第 1887 行附近）
   - 编辑钱包地址模态框（约第 1958 行附近）

4. **示例**：如果要添加 BTC 选项，在两个位置都添加：
   ```html
   <option value="BTC">BTC</option>
   ```

**注意**：两个模态框都需要同时修改，确保添加和编辑功能的一致性。
