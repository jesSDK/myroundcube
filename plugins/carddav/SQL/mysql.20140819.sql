ALTER TABLE carddav_contactgroups CHANGE addressbook addressbook INT(10) UNSIGNED NULL; 
ALTER TABLE carddav_contactgroups ADD INDEX(addressbook);
ALTER TABLE carddav_contactgroups ADD FOREIGN KEY (addressbook) REFERENCES carddav_server(carddav_server_id) ON DELETE CASCADE ON UPDATE CASCADE;
UPDATE system SET value='initial|20130903|20131110|20140406|20140410|20140411|20140809|20140819' WHERE name='myrc_carddav';