package Autoprice

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strconv"
	"time"
)

func Start(C string) (float64, error) {

	client := http.Client{
		Timeout: 10 * time.Second,
		// 代理
		/* Transport: &http.Transport{
			Proxy: http.ProxyURL(&url.URL{
				Scheme: "http",
				Host:   "127.0.0.1:7890",
			}),
		}, */
	}

	url := fmt.Sprintf("https://www.okx.com/v4/c2c/express/price?crypto=%s&fiat=%s&side=sell", C, "CNY")

	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return 0, err
	}
	resp, err := client.Do(req)

	if err != nil {
		return 0, err
	}
	defer resp.Body.Close()
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return 0, err
	}
	var respData Response
	err = json.Unmarshal(body, &respData)
	if err != nil {
		return 0, err
	}

	// 字符串转为float64

	a, _ := strconv.ParseFloat(respData.Data.Price, 64)
	return a, nil

}

type Response struct {
	Code         int    `json:"code"`
	Data         Data   `json:"data"`
	DetailMsg    string `json:"detailMsg"`
	ErrorCode    string `json:"error_code"`
	ErrorMessage string `json:"error_message"`
	Msg          string `json:"msg"`
	RequestID    string `json:"requestId"`
}

type Data struct {
	Price string `json:"price"`
}
