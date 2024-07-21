CREATE TABLE `quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submittedby` varchar(64) DEFAULT NULL,
  `quote` varchar(1024) DEFAULT NULL,
  `timestamp` varchar(64) DEFAULT NULL,
  `upvotes` bigint(20) DEFAULT NULL,
  `downvotes` bigint(20) DEFAULT NULL,
  `voted_hostnames` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4;