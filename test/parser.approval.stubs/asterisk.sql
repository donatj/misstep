CREATE TABLE `test_asterisk` (
	`test_asterisk_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Column where * is replaced with table name (test_asterisk_id)',
	`user_test_asterisk` int unsigned NOT NULL COMMENT 'Column with * at end (user_test_asterisk)',
	`test_asterisk_middle_test_asterisk` varchar(50) NOT NULL COMMENT 'Column with multiple * (test_asterisk_middle_test_asterisk)',
	`*_literal` varchar(20) NOT NULL COMMENT 'Column with escaped asterisk (\* becomes literal *)',
	`count_*` int unsigned NOT NULL COMMENT 'Column with escaped asterisk at end',
	PRIMARY KEY (`test_asterisk_id`),
	KEY `idx_user_test_asterisk_k1` (`user_test_asterisk`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'Test table with asterisk substitution in column names';
