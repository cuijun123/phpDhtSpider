# phpDhtSpider
php实现的dht爬虫（分布式）

需要swoole拓展

swoole version 1.9.18

PHP Version 5.6+

#########运行说明##############

**dht_client目录** 为爬虫服务器 **环境要求**

1.php安装swoole拓展 (pecl install swoole 默认安装1.9.18版本)

2.设置服务器 ulimit -n 100000

3.关闭防火墙 有后台策略控制的也关闭测试(dht网络端口不确定)

4.运行 php go.php

**很多采集不到数据 是由于第三点导致的**

=============================================================

**dht_server目录** 接受数据服务器(可在同一服务器) **环境要求**

1.php安装swoole拓展 (pecl install swoole 默认安装1.9.18版本)

2.设置服务器 ulimit -n 100000

3.防火墙开发dht_client请求的对应端口(配置项中)

4.运行 php go.php
