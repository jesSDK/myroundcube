CREATE TABLE 'carddav_contactgroups_tmp' (
  'contactgroup_id' INTEGER NOT NULL PRIMARY KEY ASC,
  'user_id' int(10) NOT NULL,
  'changed' datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  'del' tinyint(1) NOT NULL DEFAULT '0',
  'name' varchar(128) NOT NULL DEFAULT '',
  'addressbook' int(10) NOT NULL,
  CONSTRAINT 'carddav_addressbook_ibfk_1' FOREIGN KEY ('addressbook') REFERENCES 'carddav_server' ('carddav_server_id') ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT 'carddav_contactgroups_ibfk_1' FOREIGN KEY ('user_id') REFERENCES 'users' ('user_id') ON DELETE CASCADE ON UPDATE CASCADE
);
INSERT INTO carddav_contactgroups_tmp (
   'contactgroup_id',
   'user_id',
   'changed',
   'del',
   'name',
   'addressbook'
)
SELECT
   contactgroup_id,
   user_id,
   changed,
   del,
   name,
   CAST(addressbook AS int(10))
FROM carddav_contactgroups;

DROP TABLE 'carddav_contactgroups';
CREATE TABLE 'carddav_contactgroups' (
  'contactgroup_id' INTEGER NOT NULL PRIMARY KEY ASC,
  'user_id' int(10) NOT NULL,
  'changed' datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  'del' tinyint(1) NOT NULL DEFAULT '0',
  'name' varchar(128) NOT NULL DEFAULT '',
  'addressbook' int(10) NOT NULL,
  CONSTRAINT 'carddav_addressbook_ibfk_1' FOREIGN KEY ('addressbook') REFERENCES 'carddav_server' ('carddav_server_id') ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT 'carddav_contactgroups_ibfk_1' FOREIGN KEY ('user_id') REFERENCES 'users' ('user_id') ON DELETE CASCADE ON UPDATE CASCADE
);
INSERT INTO carddav_contactgroups (
   'contactgroup_id',
   'user_id',
   'changed',
   'del',
   'name',
   'addressbook'
)
SELECT
   contactgroup_id,
   user_id,
   changed,
   del,
   name,
   addressbook
FROM carddav_contactgroups_tmp;
DROP TABLE 'carddav_contactgroups_tmp';

CREATE INDEX carddav_contactgroups_addressbook_idx ON carddav_contactgroups (addressbook);
UPDATE 'system' SET value='initial|20130903|20131110|20140406|20140410|20140809|20140819' WHERE name='myrc_carddav';
