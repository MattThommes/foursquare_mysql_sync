<?php

	require_once("global/index.php");
	$db_conn = new Mysql("localhost", "root", "local", "foursquare");
	$auth_token = "";

	function get_total_checkins() {
		$c = curl_init("https://api.foursquare.com/v2/users/self/checkins?limit=1&offset=0&sort=oldestfirst&oauth_token=" . $GLOBALS["auth_token"] . "&v=20140520");
		curl_setopt($c, CURLOPT_HEADER, 0);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		$r = curl_exec($c);
//dbg($r);
		curl_close($c);
		$json = json_decode($r);
//dbg($json);
		$r = $json->response->checkins->count;
		return $r;
	}

	// total from foursquare.
	$total_live = get_total_checkins();

	if (isset($_GET["image"])) {
		// IE: "43-1" (this is row ID 43, and photo 1).
		list($row_id, $photo_number) = explode("-", $_GET["image"]);
		$photo = $db_conn->query("SELECT photo{$photo_number}_data AS data FROM checkins WHERE id = '$row_id'")->fetch();
		if ($photo["data"]) {
			header("Content-type: image/jpeg");
			echo $photo["data"];
		}
		exit;
	}

	if (isset($_GET["sync"])) {

		$per_page = 250;
		$total_pages = $total_live / $per_page;
		$start = floor($total_pages) * $per_page;
		
		$url = "https://api.foursquare.com/v2/users/self/checkins?limit=" . $per_page . "&offset=" . $start . "&sort=oldestfirst&oauth_token=" . $GLOBALS["auth_token"] . "&v=20140520";
		$c = curl_init($url);
		curl_setopt($c, CURLOPT_HEADER, 0);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		$r = curl_exec($c);
//dbg($r);
		curl_close($c);
		$json = json_decode($r);
//dbg($json);

		foreach ($json->response->checkins->items as $checkin) {
//dbg($checkin);
			$query = "SELECT * FROM checkins WHERE foursquare_id = '" . $checkin->id . "'";
//dbg($query);
			$exists = $db_conn->query($query);
			$exists = $exists->fetch();
//dbg($exists);
			if (!$exists) {
				if (!isset($checkin->venue)) {
					continue;
				}
				$cats = array();
				if (isset($checkin->venue->categories)) {
					foreach ($checkin->venue->categories as $cat) {
						$cats[] = $cat->pluralName;
					}
					$cats = implode(", ", $cats);
				}
				$location = $lat = $long = "";
				if (isset($checkin->venue->location)) {
					$location = $checkin->venue->location;
					if (isset($location->lat) && isset($location->lng)) {
						$lat = $location->lat;
						$long = $location->lng;
					}
				}
				$shout = (isset($checkin->shout)) ? $checkin->shout : "";
				$photo1_url = $photo2_url = $photo1_data = $photo2_data = "";
				if ((int)$checkin->photos->count) {
					$photo1 = $checkin->photos->items[0];
					$photo1_url = $photo1->prefix . $photo1->width . "x" . $photo1->height . $photo1->suffix;
					$photo1_data = addslashes(file_get_contents($photo1_url));
					if ($checkin->photos->count > 1) {
						$photo2 = $checkin->photos->items[1];
						$photo2_url = $photo2->prefix . $photo2->width . "x" . $photo2->height . $photo2->suffix;
						$photo2_data = addslashes(file_get_contents($photo2_url));
					}
				}
				$fields = array(
					"foursquare_id",
					"dt_unix",
					"dt_sql",
					"venue_id",
					"venue_name",
					"shout",
					"venue_location",
					"latitude",
					"longitude",
					"venue_categories",
					"photo1_url",
					"photo1_data",
					"photo2_url",
					"photo2_data",
				);
				$values = array(
					"'" . $checkin->id . "'",
					$checkin->createdAt,
					"'" . date("Y-m-d H:i:s", $checkin->createdAt) . "'",
					"'" . $checkin->venue->id . "'",
					"'" . str_replace("'", "\'", $checkin->venue->name) . "'",
					"'" . str_replace("'", "\'", $shout) . "'",
					"'" . str_replace("'", "\'", json_encode($location)) . "'",
					"'" . $lat . "'",
					"'" . $long . "'",
					"'" . str_replace("'", "\'", $cats) . "'",
					"'" . $photo1_url . "'",
					"'" . $photo1_data . "'",
					"'" . $photo2_url . "'",
					"'" . $photo2_data . "'",
				);
				$ins = $db_conn->query("INSERT INTO checkins (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")");
			}
		}
		header("Location: index.php");
	}

	$checkins = $db_conn->query("SELECT * FROM checkins ORDER BY dt_unix DESC")->fetch_array();

	?>

	<html>

	<head>

		<style type="text/css">

			blockquote {
				color: #666;
				font-style: italic;
				margin: 0;
				padding: 0;
			}

			table th {
				border: 1px solid #333;
				text-align: left;
			}

			table td {
				border: 1px dotted #999;
				vertical-align: top;
			}

		</style>
	
	</head>
	
	<body>

		<p><a href="index.php">View</a> | <a href="index.php?sync">Sync</a> | Total cached: <?php echo count($checkins); ?> | Total live: <?php echo $total_live; ?></p>

		<table>
	
			<tr>
				<th width="150">Date/Time</th>
				<th width="500">Venue Name</th>
				<th>Shout</th>
				<th>Categories</th>
				<th>Photo 1</th>
				<th>Photo 2</th>
			</tr>

			<?php

			foreach ($checkins as $checkin) {
				?>
				<tr>
					<td><?php echo $checkin["dt_sql"]; ?></td>
					<td><?php echo $checkin["venue_name"]; ?></td>
					<td><blockquote><?php echo $checkin["shout"]; ?></blockquote></td>
					<td><?php echo $checkin["venue_categories"]; ?></td>
					<td>
						<?php

							if ($checkin["photo1_data"]) {
								$embed_url1 = "index.php?image=" . $checkin["id"] . "-1";
								?>
									<a href="<?php echo $embed_url1; ?>" target="_blank">
										<embed src="<?php echo $embed_url1; ?>" type="image/jpeg" style="height: auto; max-width: 200px;"></embed>
									</a>
									<a href="<?php echo $embed_url1; ?>" target="_blank">Local</a> |
									<a href="<?php echo $checkin["photo1_url"]; ?>" target="_blank">Foursquare</a>
								<?php
							}

						?>
					</td>
					<td>
						<?php

							if ($checkin["photo2_data"]) {
								$embed_url2 = "index.php?image=" . $checkin["id"] . "-2";
								?>
									<a href="<?php echo $embed_url2; ?>" target="_blank">
										<embed src="<?php echo $embed_url2; ?>" type="image/jpeg" style="height: auto; max-width: 200px;"></embed>
									</a>
									<a href="<?php echo $embed_url2; ?>" target="_blank">Local</a> |
									<a href="<?php echo $checkin["photo2_url"]; ?>" target="_blank">Foursquare</a>
								<?php
							}

						?>
					</td>
				</tr>
				<?php
			}

			?>
	
		</table>

	</body>

	</html>
	
	<?php

?>