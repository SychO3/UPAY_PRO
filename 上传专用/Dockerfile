FROM debian:12-slim

WORKDIR /app

# 复制应用程序文件
COPY . .

# 安装Redis和依赖
RUN apt-get update && apt-get install -y \
    redis-server \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && chmod +x ./upay_pro

# 暴露端口
EXPOSE 8090

# 创建数据目录和启动脚本
RUN mkdir -p /data && \
    echo '#!/bin/bash\n\
    redis-server --daemonize yes --bind 127.0.0.1 --port 6379 --save 60 1 --appendonly yes --dir /data\n\
    exec ./upay_pro' > /app/start.sh && \
    chmod +x /app/start.sh



# 启动服务
CMD ["/app/start.sh"]

