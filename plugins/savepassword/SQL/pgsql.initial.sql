CREATE TABLE IF NOT EXISTS "system" (
    name varchar(64) NOT NULL PRIMARY KEY,
    value text
);
INSERT INTO "system" (name, value) VALUES ('myrc_savepassword', 'initial');
ALTER TABLE users ADD COLUMN password TEXT NULL;