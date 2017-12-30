###模拟单点登录

#启动server

php -S 192.168.254.5:9000 -t ./server

#启动客户端

php -S 192.168.254.5:8002 -t ./client1

php -S 192.168.254.5:8003 -t ./client2
