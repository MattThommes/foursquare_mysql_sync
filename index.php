<?php

	require "vendor/autoload.php";
	use MattThommes\Debug;
	$debug = new Debug;

	include "db_connect.php";
	require_once("auth_token.php");

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

	function get_total_local() {
		$qry = $GLOBALS["db_conn"]->query("SELECT COUNT(*) as total FROM checkins");
		$r = $qry->fetch();
		return $r["total"];
	}

	// total from foursquare.
	$total_live = get_total_checkins();
	$total_local = get_total_local();

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

	if (isset($_GET["delete"])) {
		$row_id = (int)$_GET["delete"];
		$db_conn->query("DELETE FROM checkins WHERE id = '$row_id' LIMIT 1");
		header("Location: index.php");
	}

	if (isset($_GET["sync"])) {

		$per_page = 250;
		$total_pages = $total_live / $per_page;
		$start = floor($total_pages) * $per_page;
		if ($start > $total_local) {
			// need to re-fetch old ones.
			$start = $total_local;
		}

		$url = "https://api.foursquare.com/v2/users/self/checkins?limit=" . $per_page . "&offset=" . $start . "&sort=oldestfirst&oauth_token=" . $GLOBALS["auth_token"] . "&v=20140520";
		$c = curl_init($url);
		curl_setopt($c, CURLOPT_HEADER, 0);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		$r = curl_exec($c);
		curl_close($c);
		$json = json_decode($r);
//$debug->dbg($json);

		foreach ($json->response->checkins->items as $checkin) {
//$debug->dbg($checkin);
			$query = "SELECT * FROM checkins WHERE foursquare_id = '" . $checkin->id . "'";
			$exists = $db_conn->query($query);
			$exists = $exists->fetch();
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
				if ((int)$checkin->comments->count) {
					foreach ($checkin->comments->items as $comment) {

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
	} else {
		// Pagination.
		$per_page = 100;
		$page = 1;
		if (isset($_GET["page"])) {
			$page = (int)$_GET["page"];
		}
		$skipover = ($page - 1) * $per_page;

		// Searching.
		$where = "1";
		if (isset($_GET["search"])) {
			$where = "venue_name LIKE '%$_GET[search]%' OR shout LIKE '%$_GET[search]%'";
		}
	}

	$checkins = $db_conn->query("SELECT * FROM checkins WHERE $where ORDER BY dt_unix DESC LIMIT $skipover, $per_page")->fetch_array();

	?>

	<html>

	<head>

		<link rel="stylesheet" href="styles.css" media="screen" />
		<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
	
	</head>
	
	<body>

		<div id="nav">

			<div id="search">
				<?php
					if ($where != "1") {
						?>
						<span>
						<?php echo count($checkins) . " results for"; ?>
						</span>
						<?php
					}
				?>
				<input type="text" value="<?php if (isset($_GET["search"])) echo $_GET["search"]; ?>" placeholder="Search..." />
				<?php
					if ($where != "1") {
						?>
						<button>Clear</button>
						<?php
					}
				?>
			</div>

			<a href="index.php">Home</a> | 
			<a href="index.php?sync">Sync</a> | 
			Total cached: <?php echo $total_local; ?> | 
			Total live: <?php echo $total_live; ?> |
			Pages:
			<?php

				$total_pages = ceil($total_local / $per_page);
				echo $total_pages;

			?>
			<select id="pages">
				<?php
					$i = 1;
					while ($i <= $total_pages) {
						?>
						<option value="<?php echo $i; ?>" <?php if ($page == $i) echo "selected='selected'"; ?>><?php echo $i; ?></option>
						<?php
						$i++;
					}
				?>
			</select>

		</div>

		<table id="data_header">

			<tr>
				<th width="70">Local ID</th>
				<th width="150">Date/Time</th>
				<th width="400">Venue Name</th>
				<th width="200">Shout</th>
				<th width="150">Categories</th>
				<th width="250">Photo 1</th>
				<th width="250">Photo 2</th>
				<th width="30">Actions</th>
			</tr>
		
		</table>

		<div id="data">
	
			<table>

				<?php

				foreach ($checkins as $checkin) {
					?>
					<tr>
						<td width="70"><?php echo $checkin["id"]; ?></td>
						<td width="150"><?php echo date("m/d/Y g:ia", $checkin["dt_unix"]); ?></td>
						<td width="400"><?php echo $checkin["venue_name"]; ?></td>
						<td width="200"><blockquote><?php echo $checkin["shout"]; ?></blockquote></td>
						<td width="150"><?php echo $checkin["venue_categories"]; ?></td>
						<td width="250">
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
						<td width="250">
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
						<td width="30"><a href="index.php?delete=<?php echo $checkin["id"]; ?>">Delete</a></td>
					</tr>
					<?php
				}

				?>
	
			</table>

		</div>

		<script>

			$(document).ready(function() {

				$("#pages").on("change", function() {
					window.location = "index.php?page=" + $(this).val();
				});

				$("#search input").on("keypress", function(e) {
					if (e.which == 13) {
						window.location = "index.php?search=" + encodeURIComponent($(this).val());
					}
				});

				// "Clear" button (if search is present).
				$("#search button").on("click", function() {
					window.location = "index.php";
				});

			});

		</script>

	</body>

	</html>
	
	<?php

?>