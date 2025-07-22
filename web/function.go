package web

import (
	"bytes"
	"context"
	"crypto/md5"
	"fmt"
	"io"
	"math"
	"math/rand"
	"net/http"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"
	"upay_pro/db/rdb"
	"upay_pro/db/sdb"
	"upay_pro/dto"
	"upay_pro/mq"
	"upay_pro/mylog"

	"github.com/gin-gonic/gin"
	"github.com/gin-gonic/gin/binding"
	"github.com/go-playground/validator/v10"
	"github.com/golang-jwt/jwt/v5"
	"github.com/hedzr/lb"
	"github.com/hedzr/lb/lbapi"
	"go.uber.org/zap"
)

// 自定义Claims结构
type MyClaims struct {
	UserID               int `json:"user_id"` // 自定义字段
	jwt.RegisteredClaims     // 内嵌标准字段（如过期时间、签发者等）
}

var (
	secret  = sdb.GenerateSecretKey(256)
	sync_mu sync.Mutex
)

func GenerateToken() string {

	// 1. 准备密钥（重要！实际使用要保密）
	secretKey := []byte(secret)

	// 2. 创建Claims（数据载体）
	claims := MyClaims{
		UserID: 123, // 自定义数据
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(time.Now().Add(24 * time.Hour)), // 1小时后过期
			Issuer:    "my-server",                                        // 签发者标识
		},
	}

	// 3. 创建Token对象
	token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)

	// 4. 生成签名字符串
	signedToken, err := token.SignedString(secretKey)
	if err != nil {
		panic(err) // 实际应返回错误
	}
	return signedToken
}

func ParseToken(tokenString string) (*MyClaims, error) {
	// 1. 定义用于接收数据的Claims对象
	claims := &MyClaims{}

	// 2. 解析Token
	parsedToken, err := jwt.ParseWithClaims(
		tokenString,
		claims,
		func(t *jwt.Token) (interface{}, error) {
			// 验证签名算法是否正确
			if _, ok := t.Method.(*jwt.SigningMethodHMAC); !ok {
				return nil, fmt.Errorf("unexpected signing method: %v", t.Header["alg"])
			}
			return []byte(secret), nil
		},
		jwt.WithLeeway(5*time.Second), // 允许5秒时间误差
	)

	// 3. 处理错误
	if err != nil {
		return nil, err
	}

	// 4. 验证Claims是否有效
	if !parsedToken.Valid {
		return nil, fmt.Errorf("invalid token")
	}

	return claims, nil
}

// gin中间件验证cookie是否有效

func JWTAuthMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		// 1. 获取cookie
		cookie, err := c.Cookie("token")
		if err != nil || cookie == "" {
			c.Redirect(302, "/login")
			/* c.JSON(http.StatusOK, gin.H{
				"code": -1,
				"msg":  "未登录",
			}) */

			c.Abort()
			return

		}
		// 验证cookie
		_, err = ParseToken(cookie)
		if err != nil {
			c.Redirect(302, "/login")
			/* 	c.JSON(http.StatusOK, gin.H{
				"code": -1,
				"msg":  "未登录",
			}) */

			c.Abort()
			return

		}

		c.Next()

	}
}

const ( // 定义常量
	CnyMinimumPaymentAmount  = 0.01 // cny最低支付金额
	UsdtMinimumPaymentAmount = 0.01 // usdt最低支付金额
	UsdtAmountPerIncrement   = 0.01 // usdt每次递增金额
	IncrementalMaximumNumber = 100  // 最大递增次数
)

func AuthMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		mylog.Logger.Info("进入中间件")

		// 读取原始请求体内容
		body, err := io.ReadAll(c.Request.Body)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": "读取请求体失败"})
			mylog.Logger.Error("读取请求体失败", zap.Error(err))
			c.Abort()
			return
		}
		// 打印原始请求体
		mylog.Logger.Info("原始请求体", zap.String("body", string(body)))

		// 重新设置请求体，以便后续绑定使用
		c.Request.Body = io.NopCloser(bytes.NewBuffer(body))

		// 获取请求体
		var requestParams dto.RequestParams

		if err := c.ShouldBindBodyWith(&requestParams, binding.JSON); err != nil {

			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			mylog.Logger.Info("请求体参数绑定失败")
			c.Abort()
			return

		}
		mylog.Logger.Info("请求体参数绑定成功")
		// 对请求参数进行验证
		validate := validator.New() //创建一个验证器实例：
		if err := validate.Struct(requestParams); err != nil {
			//如果验证错误，则返回错误信息，并终止请求

			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			mylog.Logger.Info("请求体参数验证失败", zap.String("error", err.Error()))
			c.Abort()
			return

		}
		mylog.Logger.Info("请求体参数验证成功")
		// 上面已经获取到了请求参数，我们也按照规则进行拼接字符串进行md5加密计算和传入的Signature值进行对比
		// 使用 fmt.Sprintf 生成查询字符串(拼接了api_auth_token)

		// 排序拼接

		// 签名生成：按规则拼接
		/* params := map[string]string{
			"amount":       fmt.Sprintf("%.2f", requestParams.Amount), // 保留两位小数
			"notify_url":   requestParams.NotifyURL,
			"order_id":     requestParams.OrderID,
			"redirect_url": requestParams.RedirectURL,
		} */

		params := []string{
			fmt.Sprintf("type=%s", requestParams.Type),
			fmt.Sprintf("amount=%g", requestParams.Amount),
			fmt.Sprintf("notify_url=%s", requestParams.NotifyURL),
			fmt.Sprintf("order_id=%s", requestParams.OrderID),
			fmt.Sprintf("redirect_url=%s", requestParams.RedirectURL),
		}
		// 打印拼接的参数
		mylog.Logger.Info("拼接的参数", zap.Any("params", params))

		// 打印原字符串
		/*
			mylog.Logger.Info("金额:", zap.Float64("amount", requestParams.Amount))
			mylog.Logger.Info("通知URL:", zap.String("notify_url", requestParams.NotifyURL))
			mylog.Logger.Info("订单ID:", zap.String("order_id", requestParams.OrderID))
			mylog.Logger.Info("重定向URL:", zap.String("redirect_url", requestParams.RedirectURL)) */

		/* // 排序拼接
		var keys []string
		for k := range params {
			keys = append(keys, k)
		}
		sort.Strings(keys) // 按键名排序
		*/
		// 排序参数
		sort.Strings(params)

		// 使用 strings.Join 连接排序后的参数
		signatureString := strings.Join(params, "&") + sdb.GetSetting().SecretKey
		/* var queryString string
		for _, key := range keys {
			value := params[key]
			if value != "" { // 跳过空值
				queryString += fmt.Sprintf("%s=%s&", key, value)
			}
		} */
		// queryString = strings.TrimRight(queryString, "&") + config.GetApiAuthToken()

		mylog.Logger.Info("拼接的查询字符串", zap.String("queryString", signatureString))

		/* 		queryString := fmt.Sprintf("amount=%f&notify_url=%s&order_id=%s&redirect_url=%s%s",
		requestParams.Amount, requestParams.NotifyURL, requestParams.OrderID, requestParams.RedirectURL, config.GetApiAuthToken())
		*/
		// 打印一下传入的签名
		mylog.Logger.Info("传入的签名", zap.String("signature", requestParams.Signature))
		// 对拼接的字符串进行md5加密，并验证如果传入的签名和计算的签名一致，则继续执行下一个中间件或者处理函数

		Signature := fmt.Sprintf("%x", md5.Sum([]byte(signatureString)))
		mylog.Logger.Info("计算的签名", zap.String("Signature", Signature))
		// 验证传入的签名和计算的签名是否一致
		if requestParams.Signature != Signature {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "签名验证失败"})
			mylog.Logger.Info("签名验证失败")
			c.Abort()
			return

		}
		mylog.Logger.Info("签名验证成功")
		// 继续执行下一个中间件或者处理函数

		c.Next()
	}
}
func CreateTransaction(c *gin.Context) {
	// 创建锁
	sync_mu.Lock()
	// 本函数最后释放锁
	defer sync_mu.Unlock()

	var requestParams dto.RequestParams
	if err := c.ShouldBindBodyWith(&requestParams, binding.JSON); err != nil {
		c.JSON(400, gin.H{"code": 1, "message": "参数错误"})
		return
	}

	// 在进行根据钱包类型查询钱包地址之前，先判断一下传入的订单号是否已经在数据中存在，如果存在，则返回错误，提示订单号已存在，重新创建订单
	if len(sdb.GetOrderByOrderId(requestParams.OrderID)) > 0 {
		c.JSON(400, gin.H{"code": 1, "message": "订单号已存在,请勿重复提交"})
		return
	}
	// 添加调试日志
	mylog.Logger.Info("CreateTransaction - 接收到的Type参数", zap.String("type", requestParams.Type))

	// 通过Type参数获取钱包地址的切片
	walletAddrs := sdb.GetWalletAddress(requestParams.Type)
	if len(walletAddrs) == 0 {
		c.JSON(400, gin.H{"code": 1, "message": "请先添加钱包地址"})
		return
	}
	var Token string
	var ActualAmount float64
	// 默认值为false
	var found = false
	// 创建 RoundRobin 负载均衡器
	b := lb.New(lb.RoundRobin)
	for _, node := range walletAddrs {
		b.Add(node)
	}

	// 记录每个钱包的尝试次数
	walletAttempts := make(map[string]int)

	for i := 0; i < IncrementalMaximumNumber && !found; i++ {
		address_rate, err := b.Next(lbapi.DummyFactor)
		if err != nil {
			mylog.Logger.Error("获取钱包地址失败", zap.Any("err", err))
			continue
		}

		s := strings.Split(address_rate.String(), ":")
		if len(s) != 2 {
			mylog.Logger.Error("钱包地址格式错误", zap.String("address_rate", address_rate.String()))
			continue
		}

		Token = s[0]
		rate, parseErr := strconv.ParseFloat(s[1], 64)
		if parseErr != nil {
			mylog.Logger.Error("汇率解析失败", zap.String("rate_str", s[1]), zap.Any("err", parseErr))
			continue
		}

		mylog.Logger.Info("获取钱包地址成功", zap.Any("address", Token))
		if rate <= 0 {
			mylog.Logger.Info("CreateTransaction - 汇率检查失败", zap.Float64("rate", rate))
			c.JSON(400, gin.H{"code": 1, "message": "钱包汇率配置错误,小于等于0"})
			return
		}

		// 计算基础金额
		baseAmount := math.Round((requestParams.Amount/rate)*100) / 100

		// 根据当前钱包的尝试次数计算递增金额
		attempts := walletAttempts[Token]
		ActualAmount = math.Round((baseAmount+float64(attempts)*UsdtAmountPerIncrement)*100) / 100

		// 检查换算后的金额是否符合最小支付金额
		if ActualAmount < UsdtMinimumPaymentAmount {
			c.JSON(400, gin.H{"code": 1, "message": "换算后的支付金额低于最小支付金额0.01"})
			return
		}

		ActualAmount_Token := fmt.Sprintf("%s_%f", Token, ActualAmount)

		// 获取 Redis 中当前金额
		currentAmount := getRedisAmount(ActualAmount_Token)

		// 如果钱包地址没有被占用，设置 Redis 值并退出循环
		if currentAmount == "" {
			rdb.RDB.Set(context.Background(), ActualAmount_Token, ActualAmount, sdb.GetSetting().ExpirationDate)
			found = true
			break
		} else {
			// 如果占用，增加该钱包的尝试次数
			walletAttempts[Token]++
		}
	}

	// 检查是否找到合适的配置
	if found == false {
		c.JSON(400, gin.H{"code": 1, "message": "递增金额次数超过最大次数,请稍后再创建订单"})
		return
	}

	order := &sdb.Orders{
		TradeId: generateOrderID(),
		OrderId: requestParams.OrderID,

		Amount:       requestParams.Amount,
		ActualAmount: ActualAmount,
		Type:         requestParams.Type,
		Token:        Token,
		Status:       sdb.StatusWaitPay,

		NotifyUrl:      requestParams.NotifyURL,
		RedirectUrl:    requestParams.RedirectURL,
		StartTime:      time.Now().UnixMilli(),
		ExpirationTime: time.Now().Add(sdb.GetSetting().ExpirationDate).UnixMilli(),
	}

	result := sdb.DB.Create(&order)
	if result.Error != nil {
		c.JSON(500, gin.H{"code": 1, "message": "创建订单失败1"})
		fmt.Println(result.Error)
		return
	}
	mylog.Logger.Info("创建订单成功")
	// 在队列中加入任务，延期执行函数，更新数据库中当前的订单的支付状态为已过期
	mq.TaskOrderExpiration(order.TradeId, sdb.GetSetting().ExpirationDate)
	// 返回响应的参数，格式为JSON
	// 准备返回订单信息的数据
	orderInfo := dto.Response{
		StatusCode: http.StatusOK,
		Message:    "success",
		Data: dto.Data{
			TradeID:        order.TradeId,
			OrderID:        order.OrderId,
			Amount:         order.Amount,
			ActualAmount:   order.ActualAmount,
			Token:          order.Token,
			ExpirationTime: order.ExpirationTime,
			PaymentURL:     fmt.Sprintf("%s%s%s", sdb.GetSetting().AppUrl, "/pay/checkout-counter/", order.TradeId),
		},
	}
	c.JSON(http.StatusOK, orderInfo)

}
func generateOrderID() string {
	// 获取当前时间，格式化为年月日时分秒
	timestamp := time.Now().Format("20060102150405") // 格式化为类似 20231010123456 的形式

	randomNum := rand.Int63n(9999) // 生成一个0到9999之间的随机数

	// 格式化订单号，例如：20231010123456_1234
	orderID := fmt.Sprintf("%s%04d", timestamp, randomNum)

	return orderID
}

// 返回支付页面【支付页面是静态页面，所以需要返回html文件，组装一下模版参数】
func CheckoutCounter(c *gin.Context) {

	// 获取请求参数
	trade_id := c.Param("trade_id")

	// 获取订单信息
	order := sdb.Orders{}
	err := sdb.DB.Find(&order, "trade_id=? and status=?", trade_id, sdb.StatusWaitPay).Error
	if err != nil {
		c.JSON(500, gin.H{"error": "获取订单信息失败"})
		return
	}

	// expirationMinutes := viper.GetInt("order_expiration_time")
	// 组装一下模版所需的参数
	viewModel := dto.PaymentViewModel{
		Currency:               order.Type,
		TradeId:                order.TradeId,
		ActualAmount:           order.ActualAmount,
		Token:                  order.Token,
		ExpirationTime:         order.ExpirationTime,
		RedirectUrl:            order.RedirectUrl,
		AppName:                sdb.GetSetting().AppName,
		CustomerServiceContact: sdb.GetSetting().CustomerServiceContact,
	}

	switch viewModel.Currency {
	case "TRX":
		viewModel.Logo = "https://static.tronscan.org/production/logo/trx.png"
	case "USDT-TRC20":
		viewModel.Logo = "https://static.tronscan.org/production/logo/usdtlogo.png"
	case "USDT-Polygon":
		viewModel.Logo = "https://st.softgamings.com/uploads/USDT-Polygon.png"
	case "USDT-BSC":
		viewModel.Logo = "https://bscscan.com/token/images/busdt_32.png"
	case "USDT-ERC20":
		viewModel.Logo = "https://static.tronscan.org/production/logo/usdtlogo.png"
	default:
		viewModel.Logo = "https://static.tronscan.org/production/logo/usdtlogo.png"
	}

	// 返回支付页面
	c.HTML(http.StatusOK, "pay.html", viewModel)

}

func CheckOrderStatus(c *gin.Context) {

	// 依据传入的路径参数【交易ID】，查询订单状态
	trade_id := c.Param("trade_id")

	// 查询订单状态
	order := sdb.Orders{}
	err := sdb.DB.Find(&order, "trade_id=?", trade_id).Error
	if err != nil {
		c.JSON(500, gin.H{"message": "获取订单信息失败"})
		return
	}

	// 返回订单状态
	c.JSON(200, gin.H{"data": gin.H{"status": order.Status},
		"message": "1-待支付，2-支付成功，3-支付过期"})

}

type Node struct {
	Address string
}

func (n Node) String() string {
	return n.Address
}

// 获取 Redis 中金额
func getRedisAmount(token string) string {
	result := rdb.RDB.Get(context.Background(), token).Val()
	return result
}
