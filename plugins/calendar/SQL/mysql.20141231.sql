ALTER TABLE `calendars_caldav_props` CHANGE `last_change` `last_change` VARCHAR(19) NOT NULL DEFAULT '1000-12-31 00:00:00';
ALTER TABLE `calendars_ical_props` CHANGE `last_change` `last_change` VARCHAR(19) NOT NULL DEFAULT '1000-12-31 00:00:00';
ALTER TABLE `calendars_google_xml_props` CHANGE `last_change` `last_change` VARCHAR(19) NOT NULL DEFAULT '1000-12-31 00:00:00';
ALTER TABLE `vevent_caldav_props` CHANGE `last_change` `last_change` VARCHAR(19) NOT NULL DEFAULT '1000-12-31 00:00:00';
ALTER TABLE `vevent_ical_props` CHANGE `last_change` `last_change` VARCHAR(19) NOT NULL DEFAULT '1000-12-31 00:00:00';
ALTER TABLE `vtodo_caldav_props` CHANGE `last_change` `last_change` VARCHAR(19) NOT NULL DEFAULT '1000-12-31 00:00:00';

UPDATE `system` SET `value` = 'initial|20141113|20141122|20141123|20141125|20141205|20141231' WHERE `name` = 'myrc_calendar';