CREATE TABLE `test_types` (
	`id` int unsigned NOT NULL AUTO_INCREMENT,
	`age` tinyint unsigned NOT NULL COMMENT 'TinyInt for small integers (0-255)',
	`quantity` smallint unsigned NOT NULL COMMENT 'SmallInt for medium integers',
	`population` bigint unsigned NOT NULL COMMENT 'BigInt for very large integers',
	`note` tinytext NOT NULL COMMENT 'TinyText for short text',
	`price` decimal(10,2) unsigned NOT NULL COMMENT 'Decimal with precision and scale',
	`description` mediumtext NOT NULL COMMENT 'MediumText for longer content',
	`content` longtext NOT NULL COMMENT 'LongText for very long content',
	`code` char(10) NOT NULL COMMENT 'Char fixed length string',
	`birth_year` year(4) NOT NULL COMMENT 'Year column for years',
	`created_at` datetime NOT NULL COMMENT 'DateTime for timestamps without timezone',
	PRIMARY KEY (`id`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'Test table covering all column types';
