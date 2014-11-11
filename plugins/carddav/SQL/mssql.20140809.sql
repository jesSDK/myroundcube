ALTER TABLE [dbo].[carddav_server] ADD subscribed NOT NULL DEFAULT 1
GO
UPDATE [system] SET [value]='initial|20130903|20131110|20140406|20140410|20140411|20140809' WHERE [name]='myrc_carddav'
GO