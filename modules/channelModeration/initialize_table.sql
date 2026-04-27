CREATE TABLE `moderation_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action_type` varchar(16) NOT NULL,
  `target_nick` varchar(64) NOT NULL,
  `target_mask` varchar(256) DEFAULT NULL,
  `applied_by` varchar(64) NOT NULL,
  `reason` varchar(512) DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `lifted_at` datetime DEFAULT NULL,
  `lifted_by` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`action_type`, `lifted_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
