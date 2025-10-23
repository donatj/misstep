CREATE TABLE `table_with_big_index` (
	`table_with_big_index_column_a` int unsigned NOT NULL,
	`table_with_big_index_column_b` int unsigned NOT NULL,
	`table_with_big_index_column_c` int unsigned NOT NULL,
	`table_with_big_index_column_d` int unsigned NOT NULL,
	KEY `idx_twbica_and_twbicb_and_twbicc_and_twbicd` (`table_with_big_index_column_a`,`table_with_big_index_column_b`,`table_with_big_index_column_c`,`table_with_big_index_column_d`),
	UNIQUE KEY `unq_twbica_and_twbicb_and_twbicc_and_twbicd` (`table_with_big_index_column_a`,`table_with_big_index_column_b`,`table_with_big_index_column_c`,`table_with_big_index_column_d`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'test the index shortener';

CREATE TABLE `table_with_extra_big_index` (
	`table_with_extra_big_index_column_a` int unsigned NOT NULL,
	`table_with_extra_big_index_column_b` int unsigned NOT NULL,
	`table_with_extra_big_index_column_c` int unsigned NOT NULL,
	`table_with_extra_big_index_column_d` int unsigned NOT NULL,
	`table_with_extra_big_index_column_e` int unsigned NOT NULL,
	`table_with_extra_big_index_column_f` int unsigned NOT NULL,
	`table_with_extra_big_index_column_g` int unsigned NOT NULL,
	`table_with_extra_big_index_column_h` int unsigned NOT NULL,
	`table_with_extra_big_index_column_i` int unsigned NOT NULL,
	`table_with_extra_big_index_column_j` int unsigned NOT NULL,
	`table_with_extra_big_index_column_k` int unsigned NOT NULL,
	`table_with_extra_big_index_column_l` int unsigned NOT NULL,
	`table_with_extra_big_index_column_m` int unsigned NOT NULL,
	`table_with_extra_big_index_column_n` int unsigned NOT NULL,
	`table_with_extra_big_index_column_o` int unsigned NOT NULL,
	KEY `i_tebabic_atecwe_ntatef_a_ebg_e_cidwb_a_tikd_eicd_tbica_tin_debi` (`table_with_extra_big_index_column_a`,`table_with_extra_big_index_column_b`,`table_with_extra_big_index_column_c`,`table_with_extra_big_index_column_d`,`table_with_extra_big_index_column_e`,`table_with_extra_big_index_column_f`,`table_with_extra_big_index_column_g`,`table_with_extra_big_index_column_h`,`table_with_extra_big_index_column_i`,`table_with_extra_big_index_column_j`,`table_with_extra_big_index_column_k`,`table_with_extra_big_index_column_l`,`table_with_extra_big_index_column_m`,`table_with_extra_big_index_column_n`,`table_with_extra_big_index_column_o`),
	UNIQUE KEY `unqead_ecbn_webc_andtic_wec__dwecic_n_bi_dbjad_twictiwei_nwbad_c` (`table_with_extra_big_index_column_a`,`table_with_extra_big_index_column_b`,`table_with_extra_big_index_column_c`,`table_with_extra_big_index_column_d`,`table_with_extra_big_index_column_e`,`table_with_extra_big_index_column_f`,`table_with_extra_big_index_column_g`,`table_with_extra_big_index_column_h`,`table_with_extra_big_index_column_i`,`table_with_extra_big_index_column_j`,`table_with_extra_big_index_column_k`,`table_with_extra_big_index_column_l`,`table_with_extra_big_index_column_m`,`table_with_extra_big_index_column_n`,`table_with_extra_big_index_column_o`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'test the ultralong index shortener';

CREATE TABLE `table_with_ordered_indexes` (
	`a` int unsigned NOT NULL,
	`b` int unsigned NOT NULL,
	`c` int unsigned NOT NULL,
	KEY `idx_c_and_b_and_a_k1` (`c`,`b`,`a`),
	KEY `idx_c_and_a_k2` (`c`,`a`),
	UNIQUE KEY `unq_c_and_a_and_b_u1` (`c`,`a`,`b`)
) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'test index ordering';
