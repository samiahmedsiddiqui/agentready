#!/bin/zsh
# ./serve.sh start | stop  (PHP built-in server with 4 workers, so loopback requests cannot deadlock)
KIT=${0:A:h}; ROOT=$KIT/wp-test
case $1 in
  start) cd $ROOT && (PHP_CLI_SERVER_WORKERS=4 nohup php -d display_errors=0 -S 127.0.0.1:8899 -t $ROOT/wp $ROOT/router.php > $ROOT/server.log 2>&1 &); sleep 2; curl -s -o /dev/null -w "server: %{http_code}\n" http://127.0.0.1:8899/about/ ;;
  stop) pkill -f "127.0.0.1:8899" && echo stopped ;;
  *) echo "usage: serve.sh start|stop" ;;
esac
