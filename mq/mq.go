package mq

import (
	"context"
	"fmt"
	"time"
	"upay_pro/db/sdb"
	"upay_pro/mylog"

	"github.com/hibiken/asynq"
	"go.uber.org/zap"
)

var Client *asynq.Client

func init() {
	// 获取redis地址
	addr := fmt.Sprintf("%s:%d", sdb.GetSetting().Redishost, sdb.GetSetting().Redisport)

	client := asynq.NewClient(asynq.RedisClientOpt{Addr: addr})
	Client = client

	// 启动异步任务服务器
	go async_server_run()

}

// QueueOrderExpiration 订单过期任务的队列名称
const QueueOrderExpiration = "order:expiration"

// TaskOrderExpiration 创建任务和任务加入对列
func TaskOrderExpiration(payload string, expirationDuration time.Duration) {
	task := asynq.NewTask(QueueOrderExpiration, []byte(payload)) // 转换为字节切片
	// 将任务加入队列
	info, err := Client.Enqueue(task, asynq.ProcessIn(expirationDuration))
	if err != nil {
		mylog.Logger.Info("任务加入失败:" + err.Error())
	}
	mylog.Logger.Info("任务已加入队列:", zap.Any("info", info))
}

// 队列服务端
func async_server_run() {
	mux := asynq.NewServeMux()
	// 注册处理函数，根据任务名称，调用不同的处理函数
	mux.HandleFunc(QueueOrderExpiration, handleCheckStatusCodeTask)
	// 获取redis地址
	addr := fmt.Sprintf("%s:%d", sdb.GetSetting().Redishost, sdb.GetSetting().Redisport)
	server := asynq.NewServer(asynq.RedisClientOpt{Addr: addr}, asynq.Config{Concurrency: 10})
	if err := server.Run(mux); err != nil {
		mylog.Logger.Info("Error starting server:", zap.Any("err", err))
	}
}

// 处理过期任务
func handleCheckStatusCodeTask(ctx context.Context, t *asynq.Task) error {

	// 提取任务载荷传入的交易ID，根据ID去查一下订单记录里面的支付状态是否是待支付，如果是待支付，改为已过期
	// 订单过期后，需要解锁钱包地址和金额【从Redis里删除】
	payload := string(t.Payload())

	var order sdb.Orders

	err := sdb.DB.First(&order, "trade_id = ?", payload).Error
	if err != nil {
		mylog.Logger.Info("订单查询失败")
		return err
	}

	if order.Status == sdb.StatusWaitPay {
		order.Status = sdb.StatusExpired
		sdb.DB.Save(&order)
		mylog.Logger.Info(fmt.Sprintf("订单%v已设置为过期", order.TradeId))
	}

	return nil
}

//
