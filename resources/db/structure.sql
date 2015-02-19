SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
/*!40101 SET NAMES utf8 */;
DROP TABLE IF EXISTS `cm_action`;


CREATE TABLE `cm_action` (
  `actorId` int(10) unsigned DEFAULT NULL,
  `ip` int(10) unsigned DEFAULT NULL,
  `verb` tinyint(3) DEFAULT NULL,
  `type` int(11) DEFAULT NULL,
  `actionLimitType` tinyint(3) unsigned DEFAULT NULL,
  `createStamp` int(10) unsigned NOT NULL,
  `count` int(10) unsigned DEFAULT '1',
  `interval` int(10) unsigned NOT NULL DEFAULT '1',
  KEY `actorId` (`actorId`),
  KEY `ip` (`ip`),
  KEY `action` (`verb`),
  KEY `createStamp` (`createStamp`),
  KEY `modelType` (`type`),
  KEY `actionLimitType` (`actionLimitType`),
  KEY `interval` (`interval`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_actionLimit`;


CREATE TABLE `cm_actionLimit` (
  `actionType` int(10) unsigned DEFAULT NULL,
  `actionVerb` tinyint(3) DEFAULT NULL,
  `type` int(10) unsigned NOT NULL,
  `role` tinyint(3) unsigned DEFAULT NULL,
  `limit` int(10) unsigned DEFAULT NULL,
  `period` int(10) unsigned NOT NULL,
  UNIQUE KEY `entityType` (`actionType`,`actionVerb`,`type`,`role`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_captcha`;


CREATE TABLE `cm_captcha` (
  `captcha_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `number` int(10) unsigned NOT NULL DEFAULT '0',
  `create_time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`captcha_id`),
  KEY `create_time` (`create_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_ipBlocked`;


CREATE TABLE `cm_ipBlocked` (
  `ip` int(10) unsigned NOT NULL,
  `createStamp` int(10) unsigned NOT NULL,
  PRIMARY KEY (`ip`),
  KEY `createStamp` (`createStamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_model_language`;


CREATE TABLE `cm_model_language` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `abbreviation` varchar(5) NOT NULL,
  `enabled` tinyint(1) unsigned NOT NULL,
  `backupId` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `abbreviation` (`abbreviation`),
  KEY `enabled` (`enabled`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_model_languagekey`;


CREATE TABLE `cm_model_languagekey` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `variables` text CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `updateCountResetVersion` int(10) unsigned DEFAULT NULL,
  `updateCount` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `javascript` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `javascript` (`javascript`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_languageValue`;


CREATE TABLE `cm_languageValue` (
  `languageKeyId` int(11) unsigned NOT NULL,
  `languageId` int(11) unsigned NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`languageKeyId`,`languageId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_model_location_city`;


CREATE TABLE `cm_model_location_city` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `stateId` int(10) unsigned DEFAULT NULL,
  `countryId` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `lat` float DEFAULT NULL,
  `lon` float DEFAULT NULL,
  `_maxmind` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `maxmind` (`_maxmind`),
  KEY `name` (`name`),
  KEY `stateId` (`stateId`),
  KEY `countryId` (`countryId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_model_location_country`;


CREATE TABLE `cm_model_location_country` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `abbreviation` char(2) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_model_location_ip`;


CREATE TABLE `cm_model_location_ip` (
  `id` int(10) unsigned NOT NULL,
  `level` int(10) unsigned NOT NULL,
  `ipStart` int(10) unsigned NOT NULL,
  `ipEnd` int(10) unsigned NOT NULL,
  KEY `ipEnd` (`ipEnd`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_model_location_state`;


CREATE TABLE `cm_model_location_state` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `countryId` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `_maxmind` char(5) DEFAULT NULL,
  `abbreviation` char(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `maxmind` (`_maxmind`),
  KEY `name` (`name`),
  KEY `countryId` (`countryId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_model_location_zip`;


CREATE TABLE `cm_model_location_zip` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(10) NOT NULL,
  `cityId` int(10) unsigned NOT NULL,
  `lat` float DEFAULT NULL,
  `lon` float DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cityId` (`cityId`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_log`;


CREATE TABLE `cm_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` int(10) unsigned NOT NULL,
  `msg` varchar(5000) NOT NULL,
  `timeStamp` int(10) unsigned NOT NULL,
  `metaInfo` text,
  PRIMARY KEY (`id`),
  KEY `type` (`type`,`timeStamp`),
  KEY `msg` (`msg`(333))
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_mail`;


CREATE TABLE `cm_mail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(256) DEFAULT NULL,
  `text` text,
  `html` mediumtext,
  `createStamp` int(10) unsigned NOT NULL,
  `sender` text,
  `replyTo` text,
  `to` text,
  `cc` text,
  `bcc` text,
  PRIMARY KEY (`id`),
  KEY `createStamp` (`createStamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_option`;


CREATE TABLE `cm_option` (
  `key` varchar(100) NOT NULL,
  `value` blob NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_cli_command_manager_process`;


CREATE TABLE `cm_cli_command_manager_process` (
  `commandName` varchar(100) NOT NULL,
  `hostId` int(10) unsigned NOT NULL,
  `processId` int(10) unsigned DEFAULT NULL,
  `timeoutStamp` int(10) unsigned NOT NULL,
  PRIMARY KEY (`commandName`),
  KEY `hostId` (`hostId`),
  KEY `timeoutStamp` (`timeoutStamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_requestClientCounter`;


CREATE TABLE `cm_requestClientCounter` (
  `counter` int(10) unsigned NOT NULL,
  PRIMARY KEY (`counter`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_role`;


CREATE TABLE `cm_role` (
  `userId` int(10) unsigned NOT NULL,
  `role` tinyint(3) unsigned NOT NULL,
  `startStamp` int(10) unsigned NOT NULL,
  `expirationStamp` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`userId`,`role`),
  KEY `expirationStamp` (`expirationStamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_session`;


CREATE TABLE `cm_session` (
  `sessionId` char(32) NOT NULL,
  `data` text NOT NULL,
  `expires` int(10) unsigned NOT NULL,
  PRIMARY KEY (`sessionId`),
  KEY `expires` (`expires`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_splitfeature`;


CREATE TABLE `cm_splitfeature` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `percentage` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_splitfeature_fixture`;


CREATE TABLE `cm_splitfeature_fixture` (
  `splitfeatureId` int(10) unsigned NOT NULL,
  `userId` int(10) unsigned NOT NULL,
  `fixtureId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`splitfeatureId`,`fixtureId`),
  UNIQUE KEY `userId_splitfeatureId` (`userId`,`splitfeatureId`),
  KEY `userId` (`userId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_splittest`;


CREATE TABLE `cm_splittest` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `createStamp` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `createStamp` (`createStamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_splittestVariation`;


CREATE TABLE `cm_splittestVariation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `splittestId` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `splittestId_name` (`splittestId`,`name`),
  KEY `splittestId` (`splittestId`),
  KEY `enabled` (`enabled`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_splittestVariation_fixture`;


CREATE TABLE `cm_splittestVariation_fixture` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `splittestId` int(10) unsigned NOT NULL,
  `requestClientId` int(10) unsigned DEFAULT NULL,
  `userId` int(10) unsigned DEFAULT NULL,
  `variationId` int(10) unsigned NOT NULL,
  `createStamp` int(10) unsigned NOT NULL,
  `conversionStamp` int(11) DEFAULT NULL,
  `conversionWeight` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `userSplittest` (`userId`,`splittestId`),
  UNIQUE KEY `requestClientSplittest` (`requestClientId`,`splittestId`),
  KEY `splittestId` (`splittestId`),
  KEY `conversionStamp` (`conversionStamp`),
  KEY `createStamp` (`createStamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_streamChannel`;


CREATE TABLE `cm_streamChannel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(64) NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `adapterType` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `adapterType-key` (`adapterType`,`key`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_streamChannelArchive_video`;


CREATE TABLE `cm_streamChannelArchive_video` (
  `id` int(10) unsigned NOT NULL,
  `userId` int(10) unsigned DEFAULT NULL,
  `width` int(10) unsigned NOT NULL,
  `height` int(10) unsigned NOT NULL,
  `duration` int(10) unsigned NOT NULL,
  `thumbnailCount` int(10) unsigned NOT NULL,
  `hash` char(32) NOT NULL,
  `streamChannelType` int(10) unsigned NOT NULL,
  `createStamp` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  KEY `createStamp` (`createStamp`),
  KEY `streamChannelType` (`streamChannelType`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_streamChannel_video`;


CREATE TABLE `cm_streamChannel_video` (
  `id` int(10) unsigned NOT NULL,
  `width` int(10) unsigned NOT NULL,
  `height` int(10) unsigned NOT NULL,
  `thumbnailCount` int(10) unsigned NOT NULL DEFAULT '0',
  `serverId` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `cm_streamChannel_video-cm_streamChannel` FOREIGN KEY (`id`) REFERENCES `cm_streamChannel` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_stream_publish`;


CREATE TABLE `cm_stream_publish` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned DEFAULT NULL,
  `start` int(10) unsigned NOT NULL,
  `allowedUntil` int(10) unsigned NOT NULL,
  `key` varchar(36) NOT NULL,
  `channelId` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channelId-key` (`channelId`,`key`),
  KEY `userId` (`userId`),
  CONSTRAINT `cm_stream_publish-cm_streamChannel` FOREIGN KEY (`channelId`) REFERENCES `cm_streamChannel` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_stream_subscribe`;


CREATE TABLE `cm_stream_subscribe` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned DEFAULT NULL,
  `start` int(10) unsigned NOT NULL,
  `allowedUntil` int(10) unsigned NOT NULL,
  `key` varchar(36) NOT NULL,
  `channelId` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channelId-key` (`channelId`,`key`),
  KEY `userId` (`userId`),
  CONSTRAINT `cm_stream_subscribe-cm_streamChannel` FOREIGN KEY (`channelId`) REFERENCES `cm_streamChannel` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_string`;


CREATE TABLE `cm_string` (
  `type` int(10) unsigned NOT NULL,
  `string` varchar(100) NOT NULL,
  PRIMARY KEY (`type`,`string`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_svm`;


CREATE TABLE `cm_svm` (
  `id` int(11) NOT NULL,
  `updateStamp` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_svmtraining`;


CREATE TABLE `cm_svmtraining` (
  `svmId` int(11) NOT NULL,
  `class` int(11) NOT NULL,
  `values` blob NOT NULL,
  `createStamp` int(10) unsigned NOT NULL,
  KEY `svmId` (`svmId`),
  KEY `createStamp` (`createStamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_tmp_location`;


CREATE TABLE `cm_tmp_location` (
  `level` tinyint(4) NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `1Id` int(10) unsigned DEFAULT NULL,
  `2Id` int(10) unsigned DEFAULT NULL,
  `3Id` int(10) unsigned DEFAULT NULL,
  `4Id` int(10) unsigned DEFAULT NULL,
  `name` varchar(120) DEFAULT NULL,
  `nameFull` varchar(480) DEFAULT NULL,
  `abbreviation` char(2) DEFAULT NULL,
  `lat` float DEFAULT NULL,
  `lon` float DEFAULT NULL,
  UNIQUE KEY `levelId` (`level`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_tmp_location_coordinates`;


CREATE TABLE `cm_tmp_location_coordinates` (
  `level` tinyint(4) NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `coordinates` point NOT NULL,
  PRIMARY KEY (`level`,`id`),
  SPATIAL KEY `coordinates_spatial` (`coordinates`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_tmp_userfile`;


CREATE TABLE `cm_tmp_userfile` (
  `uniqid` varchar(32) NOT NULL DEFAULT '',
  `filename` varchar(100) NOT NULL DEFAULT '',
  `createStamp` int(10) unsigned NOT NULL,
  PRIMARY KEY (`uniqid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_user`;


CREATE TABLE `cm_user` (
  `userId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activityStamp` int(10) unsigned NOT NULL,
  `createStamp` int(10) unsigned NOT NULL,
  `site` int(10) unsigned DEFAULT NULL,
  `languageId` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`userId`),
  KEY `activityStamp` (`activityStamp`),
  KEY `createStamp` (`createStamp`),
  KEY `languageId` (`languageId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_user_online`;


CREATE TABLE `cm_user_online` (
  `userId` int(10) unsigned NOT NULL,
  `visible` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `offlineStamp` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`userId`),
  KEY `visible` (`visible`),
  KEY `offlineStamp` (`offlineStamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_user_preference`;


CREATE TABLE `cm_user_preference` (
  `userId` int(10) unsigned NOT NULL,
  `preferenceId` int(10) unsigned NOT NULL,
  `value` tinyint(1) NOT NULL,
  PRIMARY KEY (`userId`,`preferenceId`),
  KEY `preferenceId` (`preferenceId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_user_preferenceDefault`;


CREATE TABLE `cm_user_preferenceDefault` (
  `preferenceId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `section` varchar(128) NOT NULL,
  `key` varchar(128) NOT NULL,
  `defaultValue` tinyint(1) NOT NULL,
  `configurable` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`preferenceId`),
  UNIQUE KEY `section` (`section`,`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cm_useragent`;


CREATE TABLE `cm_useragent` (
  `userId` int(10) unsigned NOT NULL,
  `createStamp` int(10) unsigned NOT NULL,
  `useragent` varchar(200) NOT NULL,
  PRIMARY KEY (`userId`,`useragent`),
  KEY `createStamp` (`createStamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

