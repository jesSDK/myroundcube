ALTER TABLE carddav_contactgroups ALTER COLUMN addressbook TYPE integer USING addressbook::integer;
ALTER TABLE carddav_contactgroups ALTER COLUMN addressbook SET NOT NULL;
CREATE INDEX carddav_contactgroups_addressbook_idx ON carddav_contactgroups (addressbook);
ALTER TABLE carddav_contactgroups ADD CONSTRAINT carddav_contactgroups_addressbook_fkey FOREIGN KEY (addressbook) REFERENCES carddav_server (carddav_server_id) ON DELETE CASCADE ON UPDATE CASCADE;
UPDATE system SET value='initial|20130903|20131110|20140406|20140410|20140411|20140809|20140819' WHERE name='myrc_carddav';
