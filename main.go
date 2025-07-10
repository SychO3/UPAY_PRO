package main

import (
	"upay_pro/cron"
	"upay_pro/mylog"
	"upay_pro/web"

	"go.uber.org/zap"
)

func main() {

	defer func() {
		if err := recover(); err != nil {
			mylog.Logger.Error("程序发生恐慌导致崩溃", zap.Any("error", err))
		}

	}()

	{
		go cron.Start()
		web.Start()

	}

}
