UPDATE `system` SET `value` = 'initial|20141113|20141122|20141123|20141125|20141205|20141231|20150107' WHERE `name` = 'myrc_calendar';
ALTER TABLE `vevent_attachments` CHANGE `data` `data` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE `vtodo_attachments` CHANGE `data` `data` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL;