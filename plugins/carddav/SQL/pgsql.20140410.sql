UPDATE "system" SET value='initial|20130903|20131110|20140406|20140410' WHERE name='myrc_carddav';
CREATE INDEX carddav_contactgroupmembers_contact_id_idx ON carddav_contactgroupmembers (contact_id);
ALTER TABLE carddav_contactgroupmembers ADD CONSTRAINT carddav_contactgroupmembers_contact_id_fkey FOREIGN KEY (contact_id) REFERENCES carddav_contacts (carddav_contact_id) ON DELETE CASCADE ON UPDATE CASCADE;
