IF NOT EXISTS (SELECT * FROM sysobjects WHERE id = object_id(N'[dbo].[carddav_contacts]') AND OBJECTPROPERTY(id, N'IsUserTable') = 1)
CREATE TABLE [carddav_contacts] (
  [carddav_contact_id] int NOT NULL IDENTITY(1,1),
  [carddav_server_id] int NOT NULL,
  [user_id] int NOT NULL,
  [etag] nvarchar(255) NOT NULL,
  [last_modified] nvarchar(128) NOT NULL,
  [vcard_id] nvarchar(255) NOT NULL,
  [vcard] nvarchar(MAX) NOT NULL,
  [words] nvarchar(MAX),
  [firstname] nvarchar(128) DEFAULT NULL,
  [surname] nvarchar(128) DEFAULT NULL,
  [name] nvarchar(255) DEFAULT NULL,
  [email] nvarchar(255) DEFAULT NULL,
  PRIMARY KEY ([carddav_contact_id])
)
GO
CREATE UNIQUE INDEX [carddav_server_id] ON [carddav_contacts] ([carddav_server_id],[user_id],[vcard_id])
GO
CREATE NONCLUSTERED INDEX [carddav_contacts_user_id] ON [carddav_contacts] ([user_id])
GO

IF NOT EXISTS (SELECT * FROM sysobjects WHERE id = object_id(N'[dbo].[carddav_server]') AND OBJECTPROPERTY(id, N'IsUserTable') = 1)
CREATE TABLE [carddav_server] (
  [carddav_server_id] int NOT NULL IDENTITY(1,1),
  [user_id] int NOT NULL,
  [url] nvarchar(255) NOT NULL,
  [username] nvarchar(128) NOT NULL,
  [password] nvarchar(128) NOT NULL,
  [label] nvarchar(128) NOT NULL,
  [read_only] int NOT NULL,
  [autocomplete] int NOT NULL DEFAULT 1,
  [idx] int NULL,
  [edt] int NULL,
  PRIMARY KEY ([carddav_server_id])
)
GO
CREATE NONCLUSTERED INDEX [carddav_server_user_id] ON [carddav_server] ([user_id])
GO

IF NOT EXISTS (SELECT * FROM sysobjects WHERE id = object_id(N'[dbo].[carddav_contactgroups]') AND OBJECTPROPERTY(id, N'IsUserTable') = 1)
CREATE TABLE [carddav_contactgroups] (
  [contactgroup_id] int NOT NULL IDENTITY(1,1),
  [user_id] int NOT NULL,
  [changed] datetime2 NOT NULL DEFAULT '1000-01-01 00:00:00',
  [del] int NOT NULL DEFAULT 0,
  [name] nvarchar(128) NOT NULL DEFAULT '',
  [addressbook] nvarchar(256) NOT NULL,
  PRIMARY KEY ([contactgroup_id])
)
GO
CREATE NONCLUSTERED INDEX [carddav_contactgroups_user_index] ON [carddav_contactgroups] ([user_id],[del])
GO

IF NOT EXISTS (SELECT * FROM sysobjects WHERE id = object_id(N'[dbo].[carddav_contactgroupmembers]') AND OBJECTPROPERTY(id, N'IsUserTable') = 1)
CREATE TABLE [carddav_contactgroupmembers] (
  [contactgroup_id] int NOT NULL,
  [contact_id] int NOT NULL,
  [created] datetime2 NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY ([contactgroup_id],[contact_id])
)
GO
CREATE NONCLUSTERED INDEX [carddav_contactgroupmembers_contact_index] ON [carddav_contactgroupmembers] ([contact_id])
GO
  
IF NOT EXISTS (SELECT * FROM sysobjects WHERE id = object_id(N'[dbo].[collected_contacts]') AND OBJECTPROPERTY(id, N'IsUserTable') = 1)
CREATE TABLE [collected_contacts] (
 [contact_id] int NOT NULL IDENTITY(1,1),
 [changed] datetime2 NOT NULL DEFAULT '1000-01-01 00:00:00',
 [del] int NOT NULL DEFAULT 0,
 [name] nvarchar(128) NOT NULL DEFAULT '',
 [email] nvarchar(MAX) NOT NULL,
 [firstname] nvarchar(128) NOT NULL DEFAULT '',
 [surname] nvarchar(128) NOT NULL DEFAULT '',
 [vcard] nvarchar(MAX) NULL,
 [words] nvarchar(MAX) NULL,
 [user_id] int NOT NULL,
 PRIMARY KEY([contact_id]),
 CONSTRAINT [user_id_fk_collected_contacts] FOREIGN KEY ([user_id])
   REFERENCES [users]([user_id]) ON DELETE CASCADE ON UPDATE CASCADE
)
GO
CREATE NONCLUSTERED INDEX [user_collected_contacts_index] ON [collected_contacts] ([user_id],[del])
GO

ALTER TABLE [carddav_contacts]
  ADD CONSTRAINT [carddav_contacts_ibfk_1] FOREIGN KEY ([carddav_server_id]) REFERENCES [carddav_server] ([carddav_server_id]) ON DELETE CASCADE
GO

ALTER TABLE [carddav_server]
  ADD CONSTRAINT [carddav_server_ibfk_1] FOREIGN KEY ([user_id]) REFERENCES [users] ([user_id]) ON DELETE CASCADE
GO
  
ALTER TABLE [carddav_contactgroups]
  ADD CONSTRAINT [carddav_contactgroups_ibfk_1] FOREIGN KEY ([user_id]) REFERENCES [users] ([user_id]) ON DELETE CASCADE ON UPDATE CASCADE
GO
  
ALTER TABLE [carddav_contactgroupmembers]
  ADD CONSTRAINT [carddav_contactgroupmembers_ibfk_1] FOREIGN KEY ([contactgroup_id]) REFERENCES [carddav_contactgroups] ([contactgroup_id]) ON DELETE CASCADE ON UPDATE CASCADE
GO

INSERT INTO [system] (name, value) VALUES ('myrc_carddav', 'initial|20130903|20131110|20140406|20140410')
GO
