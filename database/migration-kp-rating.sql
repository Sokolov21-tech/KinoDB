-- Миграция: рейтинг IMDb заменяется рейтингом Кинопоиска.
-- Колонка переименовывается с сохранением данных; импортёр пишет в неё rating.kp.
--
-- Выполнить один раз:  mysql -u root kino_db < database/migration-kp-rating.sql

ALTER TABLE movies
    CHANGE imdb_rating kp_rating DECIMAL(3,1) DEFAULT NULL;
