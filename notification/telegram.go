package notification

// 这里是telegram的通知服务

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"upay_pro/db/sdb"
	"upay_pro/mylog"
)

// TelegramMessage 电报消息结构体
type TelegramMessage struct {
	ChatID    string `json:"chat_id"`
	Text      string `json:"text"`
	ParseMode string `json:"parse_mode,omitempty"`
}

// sendTelegramNotification 发送电报通知
func sendTelegramNotification(botToken, chatID, message string) error {
	// 构建电报API URL
	apiURL := fmt.Sprintf("https://api.telegram.org/bot%s/sendMessage", botToken)

	// 创建消息内容
	telegramMsg := TelegramMessage{
		ChatID:    chatID,
		Text:      message,
		ParseMode: "HTML", // 支持HTML格式
	}

	// 将消息内容编码为 JSON
	jsonData, err := json.Marshal(telegramMsg)
	if err != nil {
		return fmt.Errorf("编码JSON失败: %v", err)
	}

	// 发送 POST 请求
	resp, err := http.Post(apiURL, "application/json", bytes.NewBuffer(jsonData))
	if err != nil {
		return fmt.Errorf("发送HTTP请求失败: %v", err)
	}
	defer resp.Body.Close()

	// 检查响应状态
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("电报API返回错误状态码: %d", resp.StatusCode)
	}

	return nil
}

// StartTelegram 启动电报通知服务
func StartTelegram(order sdb.Orders) {
	setting := sdb.GetSetting()

	// 检查电报机器人配置
	if setting.Tgbotkey == "" {
		mylog.Logger.Info("Tgbotkey为空，不能发送电报通知")
		return
	}

	if setting.Tgchatid == "" {
		mylog.Logger.Info("Tgchatid为空，不能发送电报通知")
		return
	}

	// 将数据库中的数字翻译为自然语言
	var status string
	switch order.Status {
	case 1:
		status = "待支付"
	case 2:
		status = "支付成功"
	case 3:
		status = "已过期"
	default:
		status = "未知状态"
	}

	var callBackConfirm string
	if order.CallBackConfirm == sdb.CallBackConfirmOk {
		callBackConfirm = "已回调"
	} else {
		callBackConfirm = "未回调"
	}

	// 构建电报消息内容（使用HTML格式）
	message := fmt.Sprintf(
		"<b>🔔 UPAY_PRO 订单通知</b>\n\n"+
			"<b>订单号:</b> <code>%s</code>\n"+
			"<b>币种:</b> %s\n"+
			"<b>支付金额:</b> %.2f\n"+
			"<b>支付状态:</b> %s\n"+
			"<b>区块ID:</b> <code>%s</code>\n"+
			"<b>回调状态:</b> %s",
		order.TradeId,
		order.Type,
		order.ActualAmount,
		status,
		order.BlockTransactionId,
		callBackConfirm,
	)

	// 发送电报通知
	err := sendTelegramNotification(setting.Tgbotkey, setting.Tgchatid, message)
	if err != nil {
		mylog.Logger.Error(fmt.Sprintf("发送电报通知失败: %v", err))
	} else {
		mylog.Logger.Info("电报通知发送成功！")
	}
}
