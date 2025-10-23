CREATE TABLE `user_article_block` (
	`user_article_block` int unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int unsigned NOT NULL,
	`article_id` mediumint unsigned NOT NULL,
	`user_id_blocking` int unsigned COMMENT 'The user who set the block. Nullable to make large scripted inserts easier.',
	`modified` timestamp NOT NULL,
	PRIMARY KEY (`user_article_block`),
	UNIQUE KEY `unq_user_id_and_article_id_u1` (`user_id`,`article_id`),
	KEY `idx_article_id_k1` (`article_id`),
	KEY `idx_user_id_blocking_k2` (`user_id_blocking`),
	FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'Existence of a record indicates a user is forbidden from reading a given article';
