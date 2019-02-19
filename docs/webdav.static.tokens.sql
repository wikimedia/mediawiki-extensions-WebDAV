CREATE TABLE /*_*/webdav_static_tokens (
	wdst_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
	wdst_user_id int NOT NULL,
	wdst_token varchar(255) NOT NULL,
	wdst_expire int NOT NULL
);
