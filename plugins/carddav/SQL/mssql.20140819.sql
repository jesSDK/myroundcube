ALTER TABLE dbo.carddav_contactgroups
ALTER COLUMN addressbook int NOT NULL;

CREATE NONCLUSTERED INDEX [IDX_carddav_contactgroups_addressbook] 
    ON dbo.carddav_contactgroups (addressbook ASC);

ALTER TABLE dbo.carddav_contactgroups  WITH CHECK ADD  CONSTRAINT [FK_carddav_contactgroups_carddav_server] FOREIGN KEY(addressbook)
REFERENCES dbo.carddav_server (carddav_server_id)
ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE dbo.carddav_contactgroups CHECK CONSTRAINT [FK_carddav_contactgroups_carddav_server];

UPDATE dbo.[system] 
SET value = 'initial|20130903|20131110|20140406|20140410|20140411|20140809|20140819'
WHERE name = 'myrc_carddav';