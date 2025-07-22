package trx

import (
	"encoding/json"
	"fmt"
	"io"
	"math"
	"net/http"
	"os"
	"time"
	"upay_pro/db/sdb"
	"upay_pro/mylog"

	"go.uber.org/zap"
)

// TronClient TRON API客户端
type TronClient struct {
	apiKey     string
	baseURL    string
	httpClient *http.Client
}

// NewTronClient 创建新的TRON客户端
func NewTronClient(apiKey string) *TronClient {
	return &TronClient{
		apiKey:  apiKey,
		baseURL: "https://apilist.tronscanapi.com/api",
		httpClient: &http.Client{
			Timeout: 30 * time.Second,
		},
	}
}

// GetTransfers 获取转账记录
func (c *TronClient) GetTransfers(address, toAddress string, limit, start int) (*Trx, error) {
	endpoint := fmt.Sprintf("%s/transfer?limit=%d&start=%d&address=%s&toAddress=%s&filterTokenValue=1",
		c.baseURL, limit, start, address, toAddress)

	req, err := http.NewRequest("GET", endpoint, nil)
	if err != nil {
		return nil, fmt.Errorf("创建请求失败: %w", err)
	}

	req.Header.Set("TRON-PRO-API-KEY", c.apiKey)
	req.Header.Set("Content-Type", "application/json")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("请求API失败: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("API返回错误状态码: %d", resp.StatusCode)
	}

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("读取响应失败: %w", err)
	}

	var result Trx
	if err := json.Unmarshal(body, &result); err != nil {
		return nil, fmt.Errorf("解析JSON失败: %w", err)
	}

	return &result, nil
}

func Start(order sdb.Orders) bool {
	// 从环境变量获取API密钥，如果没有则使用默认值
	apiKey := os.Getenv("TRON_API_KEY")
	if apiKey == "" {
		apiKey = "28b6e96a-4630-442e-8f2b-35f80c8b54d6"
	}

	client := NewTronClient(apiKey)
	address := order.Token
	toAddress := address

	// 获取转账记录
	result, err := client.GetTransfers(address, toAddress, 1, 0)
	if err != nil {
		// log.Printf("获取TRX转账记录失败: %v", err)
		mylog.Logger.Error("获取TRX转账记录失败", zap.Error(err))
		return false
	}

	if len(result.Data) == 0 {
		// 检查是否存在转账记录
		return false
	}

	if result.Data[0].TokenInfo.TokenAbbr == "trx" && order.StartTime < result.Data[0].Timestamp && result.Data[0].Timestamp < order.ExpirationTime && formatAmount(result.Data[0].Amount) == order.ActualAmount && result.Data[0].TransactionHash != "" {
		// 如果在指定时间内，并且金额正确，并且交易Hash不为空，则说明已经入账成功，可以更新数据库

		order.BlockTransactionId = result.Data[0].TransactionHash
		order.Status = sdb.StatusPaySuccess
		// 更新数据库订单记录
		re := sdb.DB.Save(&order)
		if re.Error == nil {
			return true
		}
		// go cron.ProcessCallback(order)

	}
	return false

}

type TokenInfo struct {
	TokenId      string `json:"tokenId"`
	TokenAbbr    string `json:"tokenAbbr"`
	TokenName    string `json:"tokenName"`
	TokenDecimal int    `json:"tokenDecimal"`
	TokenCanShow int    `json:"tokenCanShow"`
	TokenType    string `json:"tokenType"`
	TokenLogo    string `json:"tokenLogo"`
	TokenLevel   string `json:"tokenLevel"`
	Vip          bool   `json:"vip"`
}

type TransactionData struct {
	ContractRet         string    `json:"contractRet"`
	Amount              float64   `json:"amount"`
	Data                string    `json:"data"`
	TokenName           string    `json:"tokenName"`
	Confirmed           bool      `json:"confirmed"`
	TransactionHash     string    `json:"transactionHash"`
	TokenInfo           TokenInfo `json:"tokenInfo"`
	TransferFromAddress string    `json:"transferFromAddress"`
	TransferToAddress   string    `json:"transferToAddress"`
	Block               int64     `json:"block"`
	Id                  string    `json:"id"`
	CheatStatus         bool      `json:"cheatStatus"`
	RiskTransaction     bool      `json:"riskTransaction"`
	Timestamp           int64     `json:"timestamp"`
}

type AddressInfo struct {
	Risk bool `json:"risk"`
}

type Trx struct {
	Total             int                    `json:"total"`
	Data              []TransactionData      `json:"data"`
	ContractMap       map[string]bool        `json:"contractMap"`
	RangeTotal        int                    `json:"rangeTotal"`
	ContractInfo      map[string]interface{} `json:"contractInfo"`
	TimeInterval      int                    `json:"timeInterval"`
	NormalAddressInfo map[string]AddressInfo `json:"normalAddressInfo"`
}

func formatAmount(amount float64) float64 {
	// 直接将字符串转为 float64 类型

	// 使用 1e6 计算金额，转换为 float64 类型
	amountFloat := amount / 1e6 // 使用 1e6 来处理精度

	// 保留小数点后2位
	amountFloat = math.Round(amountFloat*100) / 100

	return amountFloat
}
