CREATE TABLE /*_*/webdav_locks (
	wdl_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
	wdl_owner varchar(255) binary NOT NULL,
	wdl_timeout int unsigned NULL default NULL,
	wdl_created varbinary(14) NULL default NULL,
	wdl_token varbinary(100),
	wdl_scope tinyint,
	wdl_depth tinyint,
	wdl_uri mediumblob
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/wdl_token ON /*_*/webdav_locks (wdl_token);
CREATE INDEX /*i*/wdl_uri ON /*_*/webdav_locks (wdl_uri(100));