CREATE TABLE IF NOT EXISTS `system` (
 `name` varchar(64) NOT NULL,
 `value` mediumtext,
 PRIMARY KEY(`name`)
);
INSERT INTO `system` (name, value) VALUES ('myrc_savepassword', 'initial');
ALTER TABLE users ADD password TEXT NULL AFTER last_login;