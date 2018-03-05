Migration to PostgreSQL
==================================

1. Download and extract the master branch of [this tool](https://github.com/mihailShumilov/mysql2postgresql#mysql2postgresql).
2. Dump the userguide MySQL database: `mysqldump --xml -u USERNAME userguide >userguide_db.xml`
3. Run the tool (`php converter.php`) with arguments `-i userguide_db.xml -o userguide_pg.sql`
4. Create a database called `userguide` on the Postgres server.
5. `psql -d userguide -f userguide_pg.sql`
6. Make sure `userguide` owns all tables, and update `config.php`.
7. Run these commands on the database:
  ```
  ALTER TABLE translate_langs ALTER COLUMN lang_code TYPE varchar(5);
  ALTER TABLE translate_users ALTER COLUMN real_name SET DEFAULT '''';
  UPDATE translate_langs SET lang_code=trim(both from lang_code);
  ```
8. Run this query on the database and execute all the commands it outputs:
  ```
  SELECT 'ALTER TABLE translate_strings ALTER COLUMN "' ||column_name|| '" SET DEFAULT '''';' FROM information_schema.columns WHERE table_name='translate_strings' AND column_name LIKE 'translation_%';
  ```