--
-- Database schema for Haiku Userguide Translator
--    Created by PostgreSQL 9.6
--

CREATE TYPE translate_log_enum_log_action AS ENUM (
	'creat',
	'mod',
	'ed_block',
	'del',
	'trans'
);
CREATE TYPE translate_users_enum_user_role AS ENUM (
	'undef',
	'admin',
	'author',
	'lmanager',
	'translator'
);

CREATE TABLE translate_docs (
	doc_id SERIAL PRIMARY KEY,
	name varchar(64) NOT NULL,
	path_original varchar(256) NOT NULL,
	path_translations varchar(256) NOT NULL,
	edited_by integer DEFAULT 0 NOT NULL,
	edit_time bigint DEFAULT 0 NOT NULL,
	strings_count integer DEFAULT 0 NOT NULL,
	is_dirty smallint DEFAULT 1 NOT NULL,
	is_disabled smallint NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX translate_docs_path_original ON translate_docs USING btree (path_original);

CREATE TABLE translate_langs (
	lang_code varchar(5) NOT NULL PRIMARY KEY,
	lang_name varchar(32) NOT NULL,
	loc_name varchar(32) NOT NULL,
	is_disabled smallint DEFAULT 0 NOT NULL
);

CREATE TABLE translate_log (
	log_id SERIAL NOT NULL PRIMARY KEY,
	log_user integer NOT NULL,
	log_time bigint DEFAULT 0 NOT NULL,
	log_action translate_log_enum_log_action NOT NULL,
	log_doc integer NOT NULL,
	log_trans_number integer,
	log_trans_lang character(5),
	log_del_doc_title varchar(64)
);

CREATE TABLE translate_resources (
	resource_id SERIAL NOT NULL PRIMARY KEY,
	path_untranslated varchar(256) NOT NULL,
	path_translated varchar(256) NOT NULL
);
CREATE UNIQUE INDEX translate_resources_path_untranslated ON translate_resources USING btree (path_untranslated);

CREATE TABLE translate_strings (
	string_id SERIAL NOT NULL PRIMARY KEY,
	doc_id integer NOT NULL,
	source_md5 character(32) NOT NULL,
	unused_since bigint
);
CREATE UNIQUE INDEX translate_strings_doc_md5 ON translate_strings USING btree (doc_id, source_md5);

CREATE TABLE translate_users (
	user_id SERIAL NOT NULL PRIMARY KEY,
	username varchar(32) NOT NULL,
	real_name varchar(32) DEFAULT ''::varchar NOT NULL,
	email varchar(80),
	user_password character(40) NOT NULL,
	user_role translate_users_enum_user_role DEFAULT 'undef'::translate_users_enum_user_role NOT NULL,
	num_edits integer DEFAULT 0 NOT NULL,
	num_translations integer DEFAULT 0 NOT NULL
);
CREATE UNIQUE INDEX translate_users_username ON translate_users USING btree (username);
