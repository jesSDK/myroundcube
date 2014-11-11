--If the [password] field already exists in the table, then this plugin is already installed... do nothing.
--Otherwise...
IF NOT EXISTS(SELECT * FROM [sys].[columns] WHERE [name] = N'password' AND [object_id] = OBJECT_ID(N'[dbo].[users]'))
	BEGIN

		--Some RoundCube installations do not have an existing [alias] column in the [dbo].[users] table
		IF NOT EXISTS(SELECT * FROM [sys].[columns] WHERE [name] = N'alias' AND [object_id] = OBJECT_ID(N'[dbo].[users]'))
			BEGIN
				ALTER TABLE [dbo].[users] 
				ADD alias [varchar](128) NOT NULL CONSTRAINT [DF_users_alias]  DEFAULT ('');
			END

		--Create the new password field
		ALTER TABLE [dbo].[users] 
		ADD password [varchar](max) NOT NULL CONSTRAINT [DF_users_password]  DEFAULT ('');

		--Add the entry to the dbo.system table
		IF NOT EXISTS (SELECT * FROM sysobjects WHERE id = object_id(N'[dbo].[system]') AND OBJECTPROPERTY(id, N'IsUserTable') = 1)
			BEGIN
				CREATE TABLE [dbo].[system](
					[name] [varchar](64) NOT NULL,
					[value] [varchar](max) NOT NULL,
				 CONSTRAINT [PK_system_name] PRIMARY KEY CLUSTERED 
				(
					[name] ASC
				)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
				) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];
			END

		INSERT INTO [dbo].[system] ([name], [value]) VALUES ('myrc_savepassword', 'initial');
	END