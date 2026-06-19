-- Миграция: переход импорта с TMDB на ПоискКино (Кинопоиск).
-- Добавляет kinopoisk_id для дедупликации; tmdb_id остаётся для старых записей
-- (ПоискКино отдаёт и настоящий TMDB id в externalId.tmdb — он тоже сохраняется).
--
-- Выполнить один раз:  mysql -u root kino_db < database/migration-kinopoisk.sql

ALTER TABLE movies
    ADD COLUMN kinopoisk_id INT DEFAULT NULL AFTER tmdb_id,
    ADD UNIQUE KEY uq_kinopoisk (kinopoisk_id);

ALTER TABLE directors
    ADD COLUMN kinopoisk_id INT DEFAULT NULL AFTER tmdb_id,
    ADD UNIQUE KEY uq_kinopoisk (kinopoisk_id);

ALTER TABLE actors
    ADD COLUMN kinopoisk_id INT DEFAULT NULL AFTER tmdb_id,
    ADD UNIQUE KEY uq_kinopoisk (kinopoisk_id);
