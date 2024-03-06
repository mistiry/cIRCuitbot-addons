CREATE TABLE `trivia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userhostname` varchar(128) DEFAULT NULL,
  `scores` varchar(16384) DEFAULT NULL,
  `lastwintime` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=latin1;