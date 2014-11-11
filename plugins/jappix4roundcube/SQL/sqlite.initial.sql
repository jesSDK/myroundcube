CREATE TABLE 'jappix' (
  'id' INTEGER NOT NULL PRIMARY KEY ASC,
  'file' VARCHAR (255) NOT NULL,
  'lang' VARCHAR (10) NOT NULL,
  'contenttype' VARCHAR (255) NOT NULL,
  'ts' datetime NOT NULL,
  'content' TEXT NOT NULL
);

INSERT INTO system (name, value) VALUES ('myrc_jappix4roundcube', 'initial');