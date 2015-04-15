-- Database driver

CREATE TABLE IF NOT EXISTS `calendars` (
  `calendar_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `color` varchar(8) NOT NULL,
  `showalarms` tinyint(1) NOT NULL DEFAULT '1',
  `tasks` tinyint(1) NOT NULL DEFAULT '0',
  `subscribed` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY(`calendar_id`),
  INDEX `user_name_idx` (`user_id`, `name`),
  CONSTRAINT `fk_calendars_user_id` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE IF NOT EXISTS `tasklists` (
  `tasklist_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `color` varchar(8) NOT NULL,
  `showalarms` tinyint(2) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`tasklist_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_tasklist_user_id` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE IF NOT EXISTS `vevent` (
  `event_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `calendar_id` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `recurrence_id` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `exception` datetime NULL,
  `exdate` datetime NULL,
  `uid` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `sequence` int(1) UNSIGNED NOT NULL DEFAULT '0',
  `start` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `end` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `recurrence` text DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) NOT NULL DEFAULT '',
  `categories` varchar(255) NOT NULL DEFAULT '',
  `url` varchar(255) NOT NULL DEFAULT '',
  `all_day` tinyint(1) NOT NULL DEFAULT '0',
  `free_busy` tinyint(1) NOT NULL DEFAULT '0',
  `priority` tinyint(1) NOT NULL DEFAULT '0',
  `sensitivity` tinyint(1) NOT NULL DEFAULT '0',
  `alarms` varchar(255) DEFAULT NULL,
  `attendees` text DEFAULT NULL,
  `notifyat` datetime DEFAULT NULL,
  `del` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY(`event_id`),
  INDEX `uid_idx` (`uid`),
  INDEX `recurrence_idx` (`recurrence_id`),
  INDEX `calendar_notify_idx` (`calendar_id`,`notifyat`),
  CONSTRAINT `fk_events_calendar_id` FOREIGN KEY (`calendar_id`)
    REFERENCES `calendars`(`calendar_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE IF NOT EXISTS `vtodo` (
  `task_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `recurrence_id` int(11) unsigned NOT NULL DEFAULT '0',
  `tasklist_id` int(11) unsigned NOT NULL,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `uid` varchar(255) NOT NULL,
  `created` datetime NOT NULL,
  `changed` datetime NOT NULL,
  `del` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL,
  `description` text,
  `tags` text,
  `date` varchar(10) DEFAULT NULL,
  `time` varchar(5) DEFAULT NULL,
  `startdate` varchar(10) DEFAULT NULL,
  `starttime` varchar(5) DEFAULT NULL,
  `flagged` tinyint(4) NOT NULL DEFAULT '0',
  `complete` float NOT NULL DEFAULT '0',
  `status` enum('','NEEDS-ACTION','IN-PROCESS','COMPLETED','CANCELLED') NOT NULL DEFAULT '',
  `alarms` varchar(255) DEFAULT NULL,
  `recurrence` text DEFAULT NULL,
  `exception` datetime DEFAULT NULL,
  `exdate` datetime DEFAULT NULL,
  `organizer` varchar(255) DEFAULT NULL,
  `attendees` text,
  `notify` datetime DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `tasklisting` (`tasklist_id`,`del`,`date`),
  KEY `uid` (`uid`),
  CONSTRAINT `fk_tasks_tasklist_id` FOREIGN KEY (`tasklist_id`)
    REFERENCES `calendars`(`calendar_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE IF NOT EXISTS `vevent_attachments` (
  `attachment_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `filename` varchar(255) NOT NULL DEFAULT '',
  `mimetype` varchar(255) NOT NULL DEFAULT '',
  `size` int(11) NOT NULL DEFAULT '0',
  `data` longtext,
  PRIMARY KEY(`attachment_id`),
  CONSTRAINT `fk_attachments_event_id` FOREIGN KEY (`event_id`)
    REFERENCES `vevent`(`event_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE IF NOT EXISTS `vtodo_attachments` (
  `attachment_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `filename` varchar(255) NOT NULL DEFAULT '',
  `mimetype` varchar(255) NOT NULL DEFAULT '',
  `size` int(11) NOT NULL DEFAULT '0',
  `data` longtext,
  PRIMARY KEY(`attachment_id`),
  CONSTRAINT `fk_attachments_task_id` FOREIGN KEY (`task_id`)
    REFERENCES `vtodo`(`task_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE IF NOT EXISTS `itipinvitations` (
  `token` VARCHAR(64) NOT NULL,
  `event_uid` VARCHAR(255) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `event` TEXT NOT NULL,
  `expires` DATETIME DEFAULT NULL,
  `cancelled` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY(`token`),
  INDEX `uid_idx` (`user_id`,`event_uid`),
  CONSTRAINT `fk_itipinvitations_user_id` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

-- Kolab driver

CREATE TABLE IF NOT EXISTS `kolab_alarms` (
  `event_id` VARCHAR(255) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `notifyat` DATETIME DEFAULT NULL,
  `dismissed` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY(`event_id`),
  CONSTRAINT `fk_kolab_alarms_user_id` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */;

-- CalDAV driver

CREATE TABLE IF NOT EXISTS `calendars_caldav_props` (
  `obj_id` int(11) unsigned NOT NULL,
  `obj_type` enum('vcal','') NOT NULL,
  `url` varchar(255) NOT NULL,
  `tag` varchar(255) DEFAULT NULL,
  `user` varchar(255) DEFAULT NULL,
  `pass` varchar(1024) DEFAULT NULL,
  `last_change` varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE KEY `obj_id` (`obj_id`,`obj_type`),
  CONSTRAINT `fk_caldav_calendar_obj_id_calendar_id` FOREIGN KEY (`obj_id`)
    REFERENCES `calendars` (`calendar_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE IF NOT EXISTS `vevent_caldav_props` (
  `obj_id` int(11) unsigned NOT NULL,
  `obj_type` enum('vevent','vtodo','') NOT NULL,
  `url` varchar(255) NOT NULL,
  `tag` varchar(255) DEFAULT NULL,
  `user` varchar(255) DEFAULT NULL,
  `pass` varchar(1024) DEFAULT NULL,
  `last_change` varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE KEY `obj_id` (`obj_id`,`obj_type`),
  CONSTRAINT `fk_caldav_event_obj_id_calendar_id` FOREIGN KEY (`obj_id`)
    REFERENCES `vevent` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE IF NOT EXISTS `vtodo_caldav_props` (
  `obj_id` int(11) unsigned NOT NULL,
  `obj_type` enum('vevent','vtodo','') NOT NULL,
  `url` varchar(255) NOT NULL,
  `tag` varchar(255) DEFAULT NULL,
  `user` varchar(255) DEFAULT NULL,
  `pass` varchar(1024) DEFAULT NULL,
  `last_change` varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE KEY `obj_id` (`obj_id`,`obj_type`),
  CONSTRAINT `fk_caldav_task_obj_id_calendar_id` FOREIGN KEY (`obj_id`)
    REFERENCES `vtodo` (`task_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

-- iCal driver

CREATE TABLE IF NOT EXISTS `calendars_ical_props` (
  `obj_id` int(11) unsigned NOT NULL,
  `obj_type` enum('ical','') NOT NULL,
  `url` varchar(255) NOT NULL,
  `user` varchar(255) DEFAULT NULL,
  `pass` varchar(1024) DEFAULT NULL,
  `last_change` varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE KEY `obj_id` (`obj_id`,`obj_type`),
  CONSTRAINT `fk_ical_calendar_obj_id_calendar_id` FOREIGN KEY (`obj_id`)
    REFERENCES `calendars` (`calendar_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE IF NOT EXISTS `vevent_ical_props` (
  `obj_id` int(11) unsigned NOT NULL,
  `obj_type` enum('vevent','vtodo','') NOT NULL,
  `url` varchar(255) NOT NULL,
  `user` varchar(255) DEFAULT NULL,
  `pass` varchar(1024) DEFAULT NULL,
  `last_change` varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE KEY `obj_id` (`obj_id`,`obj_type`),
  CONSTRAINT `fk_ical_event_obj_id_calendar_id` FOREIGN KEY (`obj_id`)
    REFERENCES `vevent` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

-- Google XML driver

CREATE TABLE IF NOT EXISTS `calendars_google_xml_props` (
  `obj_id` int(11) unsigned NOT NULL,
  `url` varchar(255) NOT NULL,
  `last_change` varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE KEY `obj_id`(`obj_id`),
  CONSTRAINT `fk_google_xml_calendar_obj_id_calendar_id` FOREIGN KEY (`obj_id`)
    REFERENCES `calendars` (`calendar_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

DELETE FROM `system` WHERE `name` = 'myrc_calendar';

DELETE FROM db_config WHERE env = 'calendar';

DELETE FROM `plugin_manager` WHERE `conf` = 'defaults_overwrite';

INSERT INTO `system` (name, value) VALUES ('myrc_calendar', 'initial|20141113|20141122');