/*
Navicat MySQL Data Transfer

Source Server         : google
Source Server Version : 50173
Source Host           : 127.0.0.1:3306
Source Database       : dht

Target Server Type    : MYSQL
Target Server Version : 50173
File Encoding         : 65001

Date: 2017-07-06 10:01:42
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for bt
-- ----------------------------
DROP TABLE IF EXISTS `bt`;
CREATE TABLE `bt` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(500) NOT NULL COMMENT '名称',
  `length` int(11) NOT NULL DEFAULT '0' COMMENT '文件大小',
  `piece_length` int(11) NOT NULL DEFAULT '0' COMMENT '种子大小',
  `infohash` char(40) NOT NULL COMMENT '种子哈希值',
  `files` text NOT NULL COMMENT '文件列表',
  `hits` int(11) NOT NULL DEFAULT '0' COMMENT '点击量',
  `hot` int(11) NOT NULL DEFAULT '1' COMMENT '热度',
  `time` datetime NOT NULL COMMENT '收录时间',
  PRIMARY KEY (`id`),
  KEY `infohash` (`infohash`),
  KEY `name` (`name`(333)),
  KEY `hot` (`hot`)
) ENGINE=MyISAM AUTO_INCREMENT=16400 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for history
-- ----------------------------
DROP TABLE IF EXISTS `history`;
CREATE TABLE `history` (
  `infohash` char(40) NOT NULL,
  KEY `infohash` (`infohash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
