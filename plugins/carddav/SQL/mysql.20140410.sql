ALTER TABLE `carddav_contactgroupmembers` ADD INDEX(`contact_id`);
UPDATE `system` SET `value`='initial|20130903|20131110|20140406|20140410' WHERE `name`='myrc_carddav';
