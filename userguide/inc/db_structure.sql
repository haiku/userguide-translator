--
-- Table structure for table `translate_docs`
--

DROP TABLE IF EXISTS `translate_docs`;

CREATE TABLE `translate_docs` (
  `doc_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `path_original` varchar(256) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `path_translations` varchar(256) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `edited_by` int(10) unsigned NOT NULL DEFAULT '0',
  `edit_time` bigint(20) unsigned NOT NULL DEFAULT '0',
  `strings_count` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty` tinyint(1) NOT NULL DEFAULT '1',
  `count_fr` int(10) unsigned NOT NULL DEFAULT '0',
  `count_fuzzy_fr` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty_fr` tinyint(1) NOT NULL DEFAULT '1',
  `count_de` int(10) unsigned NOT NULL DEFAULT '0',
  `count_fuzzy_de` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty_de` tinyint(1) NOT NULL DEFAULT '1',
  `count_it` int(10) unsigned NOT NULL DEFAULT '0',
  `count_fuzzy_it` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty_it` tinyint(1) NOT NULL DEFAULT '1',
  `count_ru` int(10) unsigned NOT NULL DEFAULT '0',
  `count_fuzzy_ru` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty_ru` tinyint(1) NOT NULL DEFAULT '1',
  `count_es` int(10) unsigned NOT NULL DEFAULT '0',
  `count_fuzzy_es` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty_es` tinyint(1) NOT NULL DEFAULT '1',
  `count_sv_SE` int(10) unsigned NOT NULL DEFAULT '0',
  `count_fuzzy_sv_SE` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty_sv_SE` tinyint(1) NOT NULL DEFAULT '1',
  `count_jp` int(10) unsigned NOT NULL DEFAULT '0',
  `count_fuzzy_jp` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty_jp` tinyint(1) NOT NULL DEFAULT '1',
  `count_uk` int(10) unsigned NOT NULL DEFAULT '0',
  `count_fuzzy_uk` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty_uk` tinyint(1) NOT NULL DEFAULT '1',
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  `count_zh_CN` int(10) unsigned NOT NULL DEFAULT '0',
  `count_fuzzy_zh_CN` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty_zh_CN` tinyint(1) NOT NULL DEFAULT '1',
  `count_pt_PT` int(10) unsigned NOT NULL DEFAULT '0',
  `count_fuzzy_pt_PT` int(10) unsigned NOT NULL DEFAULT '0',
  `is_dirty_pt_PT` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`doc_id`),
  UNIQUE KEY `path_original` (`path_original`)
) ENGINE=MyISAM AUTO_INCREMENT=104 DEFAULT CHARSET=utf8;

--
-- Table structure for table `translate_langs`
--

DROP TABLE IF EXISTS `translate_langs`;

CREATE TABLE `translate_langs` (
  `lang_code` char(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `lang_name` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `loc_name` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`lang_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `translate_log`
--

DROP TABLE IF EXISTS `translate_log`;

CREATE TABLE `translate_log` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `log_user` int(10) unsigned NOT NULL,
  `log_time` bigint(20) unsigned NOT NULL DEFAULT '0',
  `log_action` enum('creat','mod','ed_block','del','trans') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `log_doc` int(10) unsigned NOT NULL,
  `log_trans_number` int(10) unsigned DEFAULT NULL,
  `log_trans_lang` char(5) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `log_del_doc_title` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8182 DEFAULT CHARSET=utf8;

--
-- Table structure for table `translate_resources`
--

DROP TABLE IF EXISTS `translate_resources`;

CREATE TABLE `translate_resources` (
  `resource_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `path_untranslated` varchar(256) COLLATE utf8_bin NOT NULL,
  `path_translated` varchar(256) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`resource_id`),
  UNIQUE KEY `path_untranslated` (`path_untranslated`)
) ENGINE=MyISAM AUTO_INCREMENT=412 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Table structure for table `translate_strings`
--

DROP TABLE IF EXISTS `translate_strings`;

CREATE TABLE `translate_strings` (
  `string_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `doc_id` int(10) unsigned NOT NULL,
  `source_md5` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `unused_since` bigint(20) unsigned DEFAULT NULL,
  `translation_fr` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_fuzzy_fr` tinyint(1) NOT NULL DEFAULT '0',
  `translation_de` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_fuzzy_de` tinyint(1) NOT NULL DEFAULT '0',
  `translation_it` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_fuzzy_it` tinyint(1) NOT NULL DEFAULT '0',
  `translation_ru` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_fuzzy_ru` tinyint(1) NOT NULL DEFAULT '0',
  `translation_es` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_fuzzy_es` tinyint(1) NOT NULL DEFAULT '0',
  `translation_sv_SE` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_fuzzy_sv_SE` tinyint(1) NOT NULL DEFAULT '0',
  `translation_jp` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_fuzzy_jp` tinyint(1) NOT NULL DEFAULT '0',
  `translation_uk` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_fuzzy_uk` tinyint(1) NOT NULL DEFAULT '0',
  `translation_zh_CN` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_fuzzy_zh_CN` tinyint(1) NOT NULL DEFAULT '0',
  `translation_pt_PT` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `is_fuzzy_pt_PT` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`string_id`),
  UNIQUE KEY `doc_md5` (`doc_id`,`source_md5`)
) ENGINE=MyISAM AUTO_INCREMENT=4596 DEFAULT CHARSET=utf8;

--
-- Table structure for table `translate_users`
--

DROP TABLE IF EXISTS `translate_users`;

CREATE TABLE `translate_users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `real_name` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `user_password` char(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_role` enum('undef','admin','author','lmanager','translator') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'undef',
  `num_edits` int(10) unsigned NOT NULL DEFAULT '0',
  `num_translations` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=66 DEFAULT CHARSET=utf8;