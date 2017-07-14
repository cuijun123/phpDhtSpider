# phpDhtSpider
php实现的dht爬虫

需要swoole拓展

swoole version 1.9.5

PHP Version 5.6.22

#########运行说明##############

1.php安装swoole拓展 (pecl install swoole 默认安装1.9.5版本)

2.设置服务器 ulimit -n 100000

3.关闭防火墙 有后台策略控制的也关闭测试(dht网络端口不确定)

4.运行 php go.php


**很多采集不到数据 是由于第三点导致的**
