UPDATE `system` SET `value`='initial|20130903|20131110|20140406|20140410|20140411' WHERE `name`='myrc_carddav';
ALTER TABLE `carddav_contactgroupmembers` ADD CONSTRAINT `carddav_contactgroupmembers_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `carddav_contacts` (`carddav_contact_id`) ON DELETE CASCADE ON UPDATE CASCADE;
