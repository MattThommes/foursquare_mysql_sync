# Foursquare to MySQL

This script will fetch your Foursquare checkins and insert them into a local MySQL database. It will also display a HTML webpage of every checkin, with various options.

![Screenshot of webpage with Foursquare checkins](http://media.thomm.es/images/Screen%20Shot%202014-05-25%20at%208.06.59%20AM.jpg)

To get your Foursquare auth token, [go here](https://developer.foursquare.com/docs/explore#req=users/self/checkins) and copy it from the example:

![Screenshot of getting Foursquare auth token](http://media.thomm.es/images/Screen%20Shot%202014-05-26%20at%208.17.04%20AM%202.jpg)

Then paste it into the script (`index.php`) near the top:

![Screenshot of code](http://media.thomm.es/images/Screen%20Shot%202014-05-26%20at%203.49.36%20PM.jpg)

Here is the database table structure:

	CREATE TABLE `checkins` (
		`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`foursquare_id` varchar(75) NOT NULL DEFAULT '',
		`dt_unix` int(11) unsigned NOT NULL,
		`dt_sql` datetime NOT NULL,
		`venue_id` varchar(75) NOT NULL DEFAULT '',
		`venue_name` varchar(250) NOT NULL DEFAULT '',
		`shout` varchar(250) DEFAULT NULL,
		`venue_location` text,
		`latitude` varchar(50) NOT NULL DEFAULT '',
		`longitude` varchar(50) NOT NULL,
		`venue_categories` varchar(250) DEFAULT NULL,
		`photo1_url` varchar(250) DEFAULT NULL,
		`photo1_data` mediumblob,
		`photo2_url` varchar(250) DEFAULT NULL,
		`photo2_data` mediumblob,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;