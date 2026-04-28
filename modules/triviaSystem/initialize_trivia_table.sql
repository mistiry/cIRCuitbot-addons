-- trivia table: one row per player, aggregate stats and badge list
CREATE TABLE IF NOT EXISTS `trivia` (
  `id`                int(11)       NOT NULL AUTO_INCREMENT,
  `userhostname`      varchar(128)  DEFAULT NULL,
  `known_user_id`     int(11)       DEFAULT NULL,
  `lastusednickname`  varchar(64)   DEFAULT NULL,
  `scores`            text          DEFAULT NULL,
  `lastwintime`       varchar(64)   DEFAULT NULL,
  `total_wins`        int(11)       NOT NULL DEFAULT 0,
  `games_started`     int(11)       NOT NULL DEFAULT 0,
  `fastest_win`       float         DEFAULT NULL,
  `badges`            text          DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hostname` (`userhostname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- trivia_wins table: one row per individual win, used for badge queries and website history
CREATE TABLE IF NOT EXISTS `trivia_wins` (
  `id`              int(11)       NOT NULL AUTO_INCREMENT,
  `userhostname`    varchar(128)  DEFAULT NULL,
  `known_user_id`   int(11)       DEFAULT NULL,
  `topic`           varchar(64)   DEFAULT NULL,
  `points_awarded`  int(11)       DEFAULT NULL,
  `time_to_answer`  float         DEFAULT NULL,
  `was_fuzzy`       tinyint(1)    NOT NULL DEFAULT 0,
  `timestamp`       int(11)       DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hostname` (`userhostname`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration: run these if upgrading an existing trivia table
-- ALTER TABLE `trivia` ADD COLUMN `known_user_id`   int(11)  DEFAULT NULL;
-- ALTER TABLE `trivia` ADD COLUMN `total_wins`       int(11)  NOT NULL DEFAULT 0;
-- ALTER TABLE `trivia` ADD COLUMN `games_started`    int(11)  NOT NULL DEFAULT 0;
-- ALTER TABLE `trivia` ADD COLUMN `fastest_win`      float    DEFAULT NULL;
-- ALTER TABLE `trivia` ADD COLUMN `badges`           text     DEFAULT NULL;
-- UPDATE `trivia` SET `total_wins` = (
--     SELECT COALESCE(SUM(val), 0) FROM (
--         SELECT CAST(JSON_EXTRACT(scores, CONCAT('$.', k)) AS UNSIGNED) AS val
--         FROM JSON_TABLE(JSON_KEYS(scores), '$[*]' COLUMNS (k VARCHAR(64) PATH '$')) jt
--     ) sub
-- ) WHERE scores IS NOT NULL;
