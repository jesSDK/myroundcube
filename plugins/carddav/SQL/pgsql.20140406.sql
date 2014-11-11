UPDATE "system" SET value='initial|20130903|20131110|20140406' WHERE name='myrc_carddav';

CREATE SEQUENCE carddav_contacts_seq 
    START WITH 1 
    INCREMENT BY 1 
    NO MAXVALUE 
    NO MINVALUE 
    CACHE 1;