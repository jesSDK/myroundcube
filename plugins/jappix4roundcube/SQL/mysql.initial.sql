CREATE TABLE IF NOT EXISTS `jappix` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `file` varchar(255) CHARACTER SET utf8 NOT NULL,
  `contenttype` varchar(255) CHARACTER SET utf8 NOT NULL,
  `lang` varchar(10) CHARACTER SET utf8 NOT NULL,
  `ts` datetime NOT NULL,
  `content` longtext CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;

INSERT INTO `system` (name, value) VALUES ('myrc_jappix4roundcube', 'initial');