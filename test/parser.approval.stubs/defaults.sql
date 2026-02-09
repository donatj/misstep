CREATE TABLE `test_defaults` (
	`id` int unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(50) NOT NULL DEFAULT 'John''s Default' COMMENT 'Column with quoted default containing escaped quote',
	`status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'Column with simple quoted default',
	`count` int unsigned NOT NULL DEFAULT '42' COMMENT 'Column with unquoted numeric default',
	`is_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Column with unquoted boolean default',
	`description` text NOT NULL DEFAULT 'It''s a ''test'' value' COMMENT 'Column with multiple escaped quotes',
	`code` varchar(10) NOT NULL DEFAULT 'NULL' COMMENT 'Column with unquoted NULL default',
	PRIMARY KEY (`id`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'Test table with various default values';
