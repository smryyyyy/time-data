#!/bin/bash

soffice --headless --accept="socket,host=127.0.0.1,port=2002;urp;" &

sleep 3

(
  while true; do
    curl -s http://localhost/cron/tick > /dev/null 2>&1 || true
    sleep 60
  done
) &

exec docker-php-entrypoint apache2-foreground
