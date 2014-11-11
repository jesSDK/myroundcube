CREATE TABLE IF NOT EXISTS jappix (
	id serial NOT NULL,
	file varchar(255) NOT NULL,
	contenttype varchar(255) NOT NULL,
	lang varchar(10) NOT NULL,
	ts timestamp NOT NULL,
	content text NOT NULL,
	PRIMARY KEY (id)
);

INSERT INTO system (name, value) VALUES('myrc_jappix4roundcube', 'initial');