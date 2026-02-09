CREATE TABLE `test_precision` (
	`id` int unsigned NOT NULL AUTO_INCREMENT,
	`price` float(10) unsigned NOT NULL COMMENT 'Float with precision 10',
	`weight` double(15) unsigned NOT NULL COMMENT 'Double with precision 15',
	`score` float(5) unsigned NOT NULL COMMENT 'Float with precision 5',
	`ratio` double(20) unsigned NOT NULL COMMENT 'Double with precision 20',
	PRIMARY KEY (`id`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'Test table with floating point precision';
