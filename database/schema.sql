





CREATE DATABASE IF NOT EXISTS kino_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE kino_db;





CREATE TABLE countries (
    id   INT          NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code CHAR(2)      NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_code (code)
) ENGINE=InnoDB;

CREATE TABLE genres (
    id   INT         NOT NULL AUTO_INCREMENT,
    name VARCHAR(60) NOT NULL,
    slug VARCHAR(60) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB;





CREATE TABLE directors (
    id            INT          NOT NULL AUTO_INCREMENT,
    name          VARCHAR(200) NOT NULL,
    original_name VARCHAR(200)          DEFAULT NULL,
    bio           TEXT                  DEFAULT NULL,
    photo_url     VARCHAR(500)          DEFAULT NULL,
    birth_date    DATE                  DEFAULT NULL,
    death_date    DATE                  DEFAULT NULL,
    nationality   VARCHAR(100)          DEFAULT NULL,
    tmdb_id       INT                   DEFAULT NULL,
    kinopoisk_id  INT                   DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tmdb (tmdb_id),
    UNIQUE KEY uq_kinopoisk (kinopoisk_id)
) ENGINE=InnoDB;

CREATE TABLE actors (
    id            INT          NOT NULL AUTO_INCREMENT,
    name          VARCHAR(200) NOT NULL,
    original_name VARCHAR(200)          DEFAULT NULL,
    bio           TEXT                  DEFAULT NULL,
    photo_url     VARCHAR(500)          DEFAULT NULL,
    birth_date    DATE                  DEFAULT NULL,
    death_date    DATE                  DEFAULT NULL,
    nationality   VARCHAR(100)          DEFAULT NULL,
    tmdb_id       INT                   DEFAULT NULL,
    kinopoisk_id  INT                   DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tmdb (tmdb_id),
    UNIQUE KEY uq_kinopoisk (kinopoisk_id)
) ENGINE=InnoDB;





CREATE TABLE movies (
    id             INT          NOT NULL AUTO_INCREMENT,
    title          VARCHAR(500) NOT NULL,
    original_title VARCHAR(500)          DEFAULT NULL,
    description    TEXT                  DEFAULT NULL,
    release_year   YEAR                  DEFAULT NULL,
    release_date   DATE                  DEFAULT NULL,
    duration       SMALLINT UNSIGNED     DEFAULT NULL COMMENT 'minutes',
    poster_url     VARCHAR(500)          DEFAULT NULL,
    backdrop_url   VARCHAR(500)          DEFAULT NULL,
    trailer_url    VARCHAR(500)          DEFAULT NULL,
    country_id     INT                   DEFAULT NULL,
    imdb_id        VARCHAR(20)           DEFAULT NULL,
    tmdb_id        INT                   DEFAULT NULL,
    kinopoisk_id   INT                   DEFAULT NULL,
    kp_rating      DECIMAL(3,1)          DEFAULT NULL,
    age_rating     VARCHAR(10)           DEFAULT NULL,
    budget         BIGINT UNSIGNED       DEFAULT NULL,
    revenue        BIGINT UNSIGNED       DEFAULT NULL,
    language       CHAR(5)               DEFAULT NULL,
    status         ENUM('released','upcoming','in_production','cancelled')
                                NOT NULL DEFAULT 'released',
    is_featured    TINYINT(1)   NOT NULL DEFAULT 0,
    views          INT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_imdb (imdb_id),
    UNIQUE KEY uq_tmdb (tmdb_id),
    UNIQUE KEY uq_kinopoisk (kinopoisk_id),
    KEY idx_year (release_year),
    KEY idx_status (status),
    KEY idx_featured (is_featured),
    FULLTEXT KEY ft_search (title, original_title, description),
    CONSTRAINT fk_movies_country FOREIGN KEY (country_id)
        REFERENCES countries (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE movie_genres (
    movie_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (movie_id, genre_id),
    CONSTRAINT fk_mg_movie  FOREIGN KEY (movie_id) REFERENCES movies(id)  ON DELETE CASCADE,
    CONSTRAINT fk_mg_genre  FOREIGN KEY (genre_id) REFERENCES genres(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE movie_directors (
    movie_id    INT NOT NULL,
    director_id INT NOT NULL,
    PRIMARY KEY (movie_id, director_id),
    CONSTRAINT fk_md_movie    FOREIGN KEY (movie_id)    REFERENCES movies(id)    ON DELETE CASCADE,
    CONSTRAINT fk_md_director FOREIGN KEY (director_id) REFERENCES directors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE movie_actors (
    movie_id       INT          NOT NULL,
    actor_id       INT          NOT NULL,
    character_name VARCHAR(200)          DEFAULT NULL,
    actor_order    TINYINT UNSIGNED      DEFAULT 0,
    PRIMARY KEY (movie_id, actor_id),
    CONSTRAINT fk_ma_movie FOREIGN KEY (movie_id) REFERENCES movies(id)  ON DELETE CASCADE,
    CONSTRAINT fk_ma_actor FOREIGN KEY (actor_id) REFERENCES actors(id)  ON DELETE CASCADE
) ENGINE=InnoDB;





CREATE TABLE users (
    id              INT           NOT NULL AUTO_INCREMENT,
    username        VARCHAR(50)   NOT NULL,
    email           VARCHAR(255)  NOT NULL,
    password_hash   VARCHAR(255)  NOT NULL,
    avatar          VARCHAR(255)            DEFAULT NULL,
    bio             TEXT                    DEFAULT NULL,
    role            ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
    is_verified     TINYINT(1)    NOT NULL  DEFAULT 0,
    is_banned       TINYINT(1)    NOT NULL  DEFAULT 0,
    twofa_enabled   TINYINT(1)    NOT NULL  DEFAULT 0,
    twofa_secret    VARCHAR(32)             DEFAULT NULL,
    login_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME                DEFAULT NULL,
    last_login      DATETIME                DEFAULT NULL,
    created_at      DATETIME      NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB;

CREATE TABLE email_verifications (
    id         INT          NOT NULL AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token),
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id         INT          NOT NULL AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE twofa_backup_codes (
    id         INT          NOT NULL AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    code_hash  VARCHAR(255) NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_tbc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;





CREATE TABLE ratings (
    id         INT          NOT NULL AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    movie_id   INT          NOT NULL,
    rating     TINYINT UNSIGNED NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_movie (user_id, movie_id),
    KEY idx_movie (movie_id),
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 10),
    CONSTRAINT fk_r_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT fk_r_movie FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE comments (
    id          INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    movie_id    INT          NOT NULL,
    parent_id   INT                   DEFAULT NULL,
    content     TEXT         NOT NULL,
    is_spoiler  TINYINT(1)   NOT NULL DEFAULT 0,
    is_deleted  TINYINT(1)   NOT NULL DEFAULT 0,
    likes_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_movie (movie_id),
    KEY idx_parent (parent_id),
    CONSTRAINT fk_c_user   FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_c_movie  FOREIGN KEY (movie_id)  REFERENCES movies(id)   ON DELETE CASCADE,
    CONSTRAINT fk_c_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE comment_likes (
    user_id    INT      NOT NULL,
    comment_id INT      NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, comment_id),
    CONSTRAINT fk_cl_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_cl_comment FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB;





CREATE TABLE watchlist (
    id         INT          NOT NULL AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    movie_id   INT          NOT NULL,
    type       ENUM('want','watching','watched') NOT NULL DEFAULT 'want',
    watched_at DATETIME              DEFAULT NULL,
    added_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_movie (user_id, movie_id),
    KEY idx_user (user_id),
    CONSTRAINT fk_wl_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT fk_wl_movie FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_lists (
    id          INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT                  DEFAULT NULL,
    cover_url   VARCHAR(500)          DEFAULT NULL,
    is_public   TINYINT(1)   NOT NULL DEFAULT 1,
    likes_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    CONSTRAINT fk_ul_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_list_movies (
    list_id   INT          NOT NULL,
    movie_id  INT          NOT NULL,
    note      TEXT                  DEFAULT NULL,
    order_pos SMALLINT UNSIGNED     DEFAULT 0,
    added_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (list_id, movie_id),
    CONSTRAINT fk_ulm_list  FOREIGN KEY (list_id)  REFERENCES user_lists(id) ON DELETE CASCADE,
    CONSTRAINT fk_ulm_movie FOREIGN KEY (movie_id) REFERENCES movies(id)     ON DELETE CASCADE
) ENGINE=InnoDB;





CREATE TABLE rate_limits (
    id             INT          NOT NULL AUTO_INCREMENT,
    ip_address     VARCHAR(45)  NOT NULL,
    endpoint       VARCHAR(255) NOT NULL,
    requests_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    window_start   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ip_ep  (ip_address, endpoint),
    KEY idx_window (window_start)
) ENGINE=InnoDB;

CREATE TABLE audit_log (
    id         INT          NOT NULL AUTO_INCREMENT,
    user_id    INT                   DEFAULT NULL,
    action     VARCHAR(100) NOT NULL,
    table_name VARCHAR(50)           DEFAULT NULL,
    record_id  INT                   DEFAULT NULL,
    old_data   JSON                  DEFAULT NULL,
    new_data   JSON                  DEFAULT NULL,
    ip_address VARCHAR(45)           DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user   (user_id),
    KEY idx_action (action),
    CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;





INSERT INTO countries (name, code) VALUES
('США',           'US'),('Великобритания','GB'),('Франция',       'FR'),
('Германия',      'DE'),('Италия',        'IT'),('Япония',        'JP'),
('Южная Корея',   'KR'),('Австралия',     'AU'),('Канада',        'CA'),
('Россия',        'RU'),('Испания',       'ES'),('Китай',         'CN'),
('Индия',         'IN'),('Дания',         'DK'),('Швеция',        'SE');

INSERT INTO genres (name, slug) VALUES
('Экшен',         'action'),    ('Приключения',   'adventure'),
('Анимация',      'animation'), ('Комедия',       'comedy'),
('Криминал',      'crime'),     ('Документальный','documentary'),
('Драма',         'drama'),     ('Фэнтези',       'fantasy'),
('Фильм ужасов',  'horror'),    ('Мюзикал',       'musical'),
('Мистерия',      'mystery'),   ('Романтика',     'romance'),
('Научная фантастика','sci-fi'),('Триллер',       'thriller'),
('Вестерн',       'western'),   ('Биография',     'biography'),
('История',       'history'),   ('Спорт',         'sport');


INSERT INTO users (username, email, password_hash, role, is_verified) VALUES
('admin', 'admin@kino.local',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin', 1);


INSERT INTO directors (name, original_name, nationality, tmdb_id) VALUES
('Кристофер Нолан',    'Christopher Nolan',    'British-American', 525),
('Квентин Тарантино',  'Quentin Tarantino',    'American',         138),
('Мартин Скорсезе',    'Martin Scorsese',      'American',         1032),
('Пон Чжун-хо',        'Bong Joon-ho',         'South Korean',     21684),
('Дени Вильнёв',       'Denis Villeneuve',     'Canadian',         137427);

INSERT INTO actors (name, original_name, nationality, tmdb_id) VALUES
('Килиан Мёрфи',       'Cillian Murphy',    'Irish',          2037),
('Леонардо ДиКаприо',  'Leonardo DiCaprio', 'American',       6193),
('Роберт Де Ниро',     'Robert De Niro',    'American',       380),
('Натали Портман',     'Natalie Portman',   'Israeli-American',524),
('Тимоти Шаламе',      'Timothée Chalamet', 'American-French', 1190668);

INSERT INTO movies
    (title, original_title, description, release_year, release_date, duration,
     poster_url, backdrop_url, country_id, imdb_id, tmdb_id, kp_rating,
     age_rating, language, status, is_featured, views)
VALUES
('Оппенгеймер','Oppenheimer',
 'История Дж. Роберта Оппенгеймера и его роли в создании первой атомной бомбы.',
 2023,'2023-07-21',180,
 'https://image.tmdb.org/t/p/w500/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg',
 'https://image.tmdb.org/t/p/original/rLb2cwF3Pazuxaj0sRXQ037tGI1.jpg',
 1,'tt15398776',872585,8.3,'R','en','released',1,0),

('Паразиты','Parasite',
 'Жизни двух семей — богатой и бедной — переплетаются невероятным образом.',
 2019,'2019-05-30',132,
 'https://image.tmdb.org/t/p/w500/7IiTTgloJzvGI1TAYymCfbfl3vT.jpg',
 NULL,
 7,'tt6751668',496243,8.5,'R','ko','released',1,0),

('Дюна','Dune',
 'Молодой дворянин отправляется на самую опасную планету во вселенной ради защиты своего народа.',
 2021,'2021-10-22',155,
 NULL,
 NULL,
 1,'tt1160419',438631,7.9,'PG-13','en','released',1,0),

('Начало','Inception',
 'Вор, способный проникать в сны людей, получает задание внедрить идею в чужое подсознание.',
 2010,'2010-07-16',148,
 'https://image.tmdb.org/t/p/w500/edv5CZvWj09upOsy2Y6IwDhK8bt.jpg',
 NULL,
 1,'tt1375666',27205,8.8,'PG-13','en','released',0,0),

('Бешеные псы','Reservoir Dogs',
 'После провала ограбления ювелирного магазина бандиты начинают подозревать друг друга в предательстве.',
 1992,'1992-10-23',99,
 'https://image.tmdb.org/t/p/w500/xi8Iu6qyTfyZVDVy60raIOYJJmk.jpg',
 NULL,
 1,'tt0105236',500,8.3,'R','en','released',0,0);


INSERT INTO movie_genres (movie_id, genre_id) VALUES
(1,16),(1,7),(1,17),
(2,7),(2,5),(2,14),
(3,13),(3,1),(3,2),
(4,13),(4,1),(4,14),
(5,5),(5,14),(5,7);


INSERT INTO movie_directors (movie_id, director_id) VALUES
(1,1),(3,5),(4,1),(5,2);

INSERT INTO movie_actors (movie_id, actor_id, character_name, actor_order) VALUES
(1,1,'J. Robert Oppenheimer',1),
(3,5,'Paul Atreides',1),
(4,2,'Dom Cobb',1);
