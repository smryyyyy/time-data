#!/bin/bash
# 启动 Apache（前台）
apache2-foreground &
APACHE_PID=$!

# 内置定时器：每分钟触发 /cron/tick
while true; do
    curl -s http://localhost/cron/tick > /dev/null 2>&1
    sleep 60
done &

# 等待 Apache 进程
wait $APACHE_PID
