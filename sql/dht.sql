/*
Navicat MySQL Data Transfer

Source Server         : google
Source Server Version : 50636
Source Host           : 35.185.171.254:3306
Source Database       : dht

Target Server Type    : MYSQL
Target Server Version : 50636
File Encoding         : 65001

Date: 2017-09-22 10:27:20
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for bt
-- ----------------------------
DROP TABLE IF EXISTS `bt`;
CREATE TABLE `bt` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(500) NOT NULL COMMENT '名称',
  `keywords` varchar(250) NOT NULL COMMENT '关键词',
  `length` bigint(20) NOT NULL DEFAULT '0' COMMENT '文件大小',
  `piece_length` int(11) NOT NULL DEFAULT '0' COMMENT '种子大小',
  `infohash` char(40) NOT NULL COMMENT '种子哈希值',
  `files` text NOT NULL COMMENT '文件列表',
  `hits` int(11) NOT NULL DEFAULT '0' COMMENT '点击量',
  `hot` int(11) NOT NULL DEFAULT '1' COMMENT '热度',
  `time` datetime NOT NULL COMMENT '收录时间',
  `lasttime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '最后下载时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `infohash` (`infohash`) USING BTREE,
  KEY `hot` (`hot`),
  KEY `time` (`time`),
  KEY `hits` (`hits`)
) ENGINE=MyISAM AUTO_INCREMENT=4929461 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for history
-- ----------------------------
DROP TABLE IF EXISTS `history`;
CREATE TABLE `history` (
  `infohash` char(40) NOT NULL,
  PRIMARY KEY (`infohash`),
  UNIQUE KEY `infohash` (`infohash`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
