ALTER TABLE `calendars` ADD `freebusy` TINYINT(1) NOT NULL DEFAULT '0';

UPDATE `system` SET `value` = 'initial|20141113|20141122|20141123' WHERE `name` = 'myrc_calendar';