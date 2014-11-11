CREATE TABLE [planner] (
  [id] int NOT NULL IDENTITY(1,1),
  [user_id] int NOT NULL DEFAULT 0,
  [starred] int NOT NULL DEFAULT 0,
  [datetime] datetime2 DEFAULT NULL,
  [created] datetime2 DEFAULT NULL,
  [text] nvarchar(MAX) NOT NULL,
  [done] int NOT NULL DEFAULT 0,
  [deleted] int NOT NULL DEFAULT 0,
  PRIMARY KEY ([id]),
  CONSTRAINT [user_id_fk_planner] FOREIGN KEY ([user_id])
   REFERENCES [users]([user_id]) ON DELETE CASCADE ON UPDATE CASCADE
)
GO
