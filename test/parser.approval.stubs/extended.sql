CREATE TABLE `initiative_task_activity` (
	`initiative_task_activity_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`initiative_id` int(10) unsigned NOT NULL,
	`title` varchar(255) NOT NULL,
	`descr` text NOT NULL,
	`atos` decimal(3,1) unsigned,
	`deleted` tinyint(1) unsigned NOT NULL,
	`sort_order` int(10) unsigned NOT NULL,
	PRIMARY KEY (`initiative_task_activity_id`),
	KEY `idx_initiative_id_and_deleted_k1` (`initiative_id`,`deleted`),
	FOREIGN KEY (`initiative_id`) REFERENCES `initiatives`(`initiative_id`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'Represents a task activity';

CREATE TABLE `initiative_task_activity_completion` (
	`initiative_task_activity_completion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`initiative_task_activity_id` int(10) unsigned NOT NULL,
	`path` varchar(300) NOT NULL COMMENT 'path to recording in S3 from the base prefix',
	`initiative_task_activity_result_id` int(10) unsigned COMMENT 'id of selected (most recent) result set',
	`duration` smallint(11) unsigned NOT NULL,
	`user_id` int(11) unsigned NOT NULL,
	`completed` timestamp NOT NULL,
	PRIMARY KEY (`initiative_task_activity_completion_id`),
	UNIQUE KEY `unq_initiative_task_activity_id_and_user_id_u1` (`initiative_task_activity_id`,`user_id`),
	UNIQUE KEY `unq_initiative_task_activity_result_id_u2` (`initiative_task_activity_result_id`),
	KEY `idx_user_id_k2` (`user_id`),
	FOREIGN KEY (`initiative_task_activity_id`) REFERENCES `initiative_task_activity`(`initiative_task_activity_id`),
	FOREIGN KEY (`initiative_task_activity_result_id`) REFERENCES `initiative_task_activity_results`(`initiative_task_activity_result_id`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'Marks the completion an activity by a user';

CREATE TABLE `initiative_task_activity_results` (
	`initiative_task_activity_result_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`is_error` tinyint(1) unsigned NOT NULL COMMENT 'Was the response an error?',
	`fluency_payload` json NOT NULL,
	`received` timestamp NOT NULL COMMENT 'time the payload was received',
	`initiative_task_activity_completion_id` int(10) unsigned NOT NULL COMMENT 'the activity task this belongs to',
	PRIMARY KEY (`initiative_task_activity_result_id`),
	FOREIGN KEY (`initiative_task_activity_completion_id`) REFERENCES `initiative_task_activity_completion`(`initiative_task_activity_completion_id`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'JSON payloads as received from the activity API';
