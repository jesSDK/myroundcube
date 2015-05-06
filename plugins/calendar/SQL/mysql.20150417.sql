CREATE TABLE `email2calendaruser` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `identity_id` int(10) unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `calendaruser` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `identity_id` (`identity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

UPDATE `system` SET `value` = 'initial|20141113|20141122|20141123|20141125|20141205|20141231|20150107|20150128|20150206|20150228|20150319|20150329|20150417' WHERE `name` = 'myrc_calendar';

ALTER TABLE `email2calendaruser`
  ADD CONSTRAINT `email2calendaruser_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email2calendaruser_ibfk_1` FOREIGN KEY (`identity_id`) REFERENCES `identities` (`identity_id`) ON DELETE CASCADE ON UPDATE CASCADE;
