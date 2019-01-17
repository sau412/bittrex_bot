SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE `markets` (
  `uid` int(11) NOT NULL,
  `market` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `currency` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `bid` double NOT NULL,
  `ask` double NOT NULL,
  `is_enabled` int(11) NOT NULL,
  `lower_limit` double NOT NULL,
  `upper_limit` double NOT NULL,
  `step` double NOT NULL,
  `step_count` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `orders` (
  `uid` int(11) NOT NULL,
  `market` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `order_guid` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `is_completed` int(11) NOT NULL DEFAULT '0',
  `comment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


ALTER TABLE `markets`
  ADD PRIMARY KEY (`uid`);

ALTER TABLE `orders`
  ADD PRIMARY KEY (`uid`),
  ADD KEY `market` (`market`),
  ADD KEY `order_guid` (`order_guid`);


ALTER TABLE `markets`
  MODIFY `uid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `orders`
  MODIFY `uid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
