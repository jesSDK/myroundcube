CREATE TABLE 'carddav_contactgroupmembers_tmp' (
   'contactgroup_id' int(10) NOT NULL,
   'contact_id' int(10) NOT NULL,
   'created' datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
   PRIMARY KEY (contactgroup_id, contact_id),
   CONSTRAINT 'carddav_contactgroupmembers_contactgroup_id_fkey' FOREIGN 
KEY ('contactgroup_id') REFERENCES 'carddav_contactgroups' 
('contactgroup_id') ON DELETE CASCADE ON UPDATE CASCADE);
INSERT INTO carddav_contactgroupmembers_tmp (
   'contactgroup_id',
   'contact_id',
   'created'
)
SELECT
   'contactgroup_id',
   'contact_id',
   'created'
FROM carddav_contactgroupmembers;
DROP TABLE 'carddav_contactgroupmembers';

CREATE TABLE 'carddav_contactgroupmembers' (
   'contactgroup_id' int(10) NOT NULL,
   'contact_id' int(10) NOT NULL,
   'created' datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
   PRIMARY KEY (contactgroup_id, contact_id),
   CONSTRAINT 'carddav_contactgroupmembers_contactgroup_id_fkey' FOREIGN 
KEY ('contactgroup_id') REFERENCES 'carddav_contactgroups' 
('contactgroup_id') ON DELETE CASCADE ON UPDATE CASCADE
);
INSERT INTO carddav_contactgroupmembers (
   'contactgroup_id',
   'contact_id',
   'created'
)
SELECT
   'contactgroup_id',
   'contact_id',
   'created'
FROM carddav_contactgroupmembers_tmp;
DROP TABLE 'carddav_contactgroupmembers_tmp';

CREATE INDEX carddav_contactgroupmembers_contact_id_idx ON carddav_contactgroupmembers (contact_id);

UPDATE 'system' SET value='initial|20130903|20131110|20140406|20140410' WHERE name='myrc_carddav';