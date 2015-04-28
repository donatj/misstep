# misstep
Simple Plain Text MySQL Modeling Language

The goal is to allow you to quickly mock up SQL Tables in an easy to read, familiar feeling syntax.

As a very simple example example the following:

```
# user
! user_id  int *pk
- username varchar40 k1
- password varchar

# user_profile
- user_profile_id int *pk
? user_id int k1
- name_first varchar
- name_last  varchar
```

Translates to:

```sql
CREATE TABLE `user` (
	`user_id` int unsigned NOT NULL AUTO_INCREMENT,
	`username` varchar(40) NOT NULL,
	`password` varchar(255) NOT NULL,
	PRIMARY KEY (`user_id`),
	KEY `idx_username_k1` (`username`)
);

CREATE TABLE `user_profile` (
	`user_profile_id` int unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int unsigned NOT NULL,
	`name_first` varchar(255) NOT NULL,
	`name_last` varchar(255) NOT NULL,
	PRIMARY KEY (`user_profile_id`),
	KEY `idx_user_id_k1` (`user_id`),
	FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`)
);
```
