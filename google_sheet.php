// Define constants for configuration, replace 'XXXXXXXX' with your ID
define('GOOGLE_SPREADSHEET_ID', 'XXXXXXXX');

// Include the Google API PHP client library
require_once( get_stylesheet_directory() . '/libphp/google-api-php-client/vendor/autoload.php' );

// Initialize the Google API client
// Update the path to JSON key file
$client = new Google_Client();
$client->setAuthConfig(get_stylesheet_directory() . '/scripts/key-file.json'); // path to JSON key file
$client->setScopes([Google_Service_Sheets::SPREADSHEETS]);

// Create a Google Sheets service
$service = new Google_Service_Sheets($client);

// The function to fetch events and parse mec_fields
// Use/Update the Hook name `minecraftevents` in the Themeco Looper
add_filter('cs_looper_custom_minecraftevents', function ($result) use ($service) {
	$eastern_timezone = new DateTimeZone('America/New_York'); // Create a DateTimeZone object for Eastern Time (ET)
	$et_date = new DateTime('now', $eastern_timezone); // Create a DateTime object for the current date in Eastern Time (ET)

	$current_date = $et_date->format('Ymd'); // Format the date as needed (YYYYMMDD)
    global $wpdb;
	
    // Fetch events that are today or have repeating date equal to today or in the future
    $query = $wpdb->prepare("
        SELECT		
			p.ID, p.post_title, p.post_content, p.post_name,
			d.dstart, d.dend, d.tstart, d.tend,
			FROM_UNIXTIME(d.tstart) AS start_datetime,
			FROM_UNIXTIME(d.tend) AS end_datetime,
			p2.guid AS featured_image,
			MAX(CASE WHEN pm3.meta_key = 'mec_fields' THEN pm3.meta_value END) AS mec_fields
		FROM 
			{$wpdb->posts} AS p
		INNER JOIN 
			{$wpdb->postmeta} AS pm ON p.ID = pm.post_id
		LEFT JOIN 
			{$wpdb->prefix}mec_dates AS d ON p.ID = d.post_id
		LEFT JOIN 
			{$wpdb->postmeta} AS pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_thumbnail_id'
		LEFT JOIN 
			{$wpdb->posts} AS p2 ON pm2.meta_value = p2.ID
		LEFT JOIN 
			{$wpdb->postmeta} AS pm3 ON p.ID = pm3.post_id AND pm3.meta_key LIKE 'mec_fields%'
		WHERE 			
			p.post_type = 'mec-events' 
			AND p.post_status = 'publish'
    		AND p.post_title LIKE '%minecraft%'
			AND (
				(d.dstart <= %s AND d.dend >= %s)  -- Check if the event is current
				OR
				(d.dstart > %s)  -- Check if the event is in the future
			)
		GROUP BY 
			d.ID
		ORDER BY 
			d.dstart ASC, d.tstart ASC
		LIMIT 1
    ", $current_date, $current_date, $current_date);

    // Run the custom query to fetch events
    $results = $wpdb->get_results($query, ARRAY_A);
	
	// Fetch data from google sheet
	$spreadsheetId = GOOGLE_SPREADSHEET_ID; // Spreadsheet ID
	$range = 'Sheet1'; // Sheet name or range

	$response = $service->spreadsheets_values->get($spreadsheetId, $range);	
	$values = $response->getValues(); // Check if there are values
	
	$today = new DateTime(null, $eastern_timezone); // Create a DateTime object for the current date in Eastern Time
	$today->setTime(0, 0, 0); // Set time to midnight
	
	$latest_start_date = null;
    $latest_end_date = null;
    $latest_theme = null;
	
	$found_match = false; // Flag to track if a match is found
	
	// Find the row where the date is greater than or equal to today
	foreach ($values as $index => $row) {
		if ($index === 0 || count($row) < 3) {
			// Skip the header row and rows without enough data
			continue;
		}

		$google_sheet_start_date = DateTime::createFromFormat('m/d/Y', $row[0]);
		$google_sheet_end_date = DateTime::createFromFormat('m/d/Y', $row[1]);
		
		// Condition 1 checks if the start date is today or in the past and if the end date is in the future or today.
		// Condition 2 checks if today is before the start date and if the end date is in the future or today.		
		if (
				$google_sheet_start_date && $google_sheet_end_date &&
				(
					($google_sheet_start_date <= $today && $google_sheet_end_date >= $today) || // Condition 1
					($google_sheet_start_date >= $today && $google_sheet_end_date >= $today)    // Condition 2
				)
			)
		{
			
			$latest_start_date = $google_sheet_start_date;
			$latest_end_date = $google_sheet_end_date;
			$latest_theme = isset($row[2]) ? $row[2] : null;			
			
			$found_match = true; // Set the flag to true
        	break; // Exit the loop as you found a match
		}
	}	
	// Now check the $found_match flag
	if (!$found_match) {
		// No matching row was found, handle this case as needed
		$latest_theme = 'null';
	}

	// Find the most current row with ALL winners listed
	$most_current_row = null;
	foreach ($values as $index => $row) {
		if ($index === 0 || count($row) < 7) {
			// Skip the header row and rows without enough data
			continue;
		}

		$row_start_date = DateTime::createFromFormat('m/d/Y', $row[0]);

		if (!$most_current_row || $row_start_date > $most_current_row[0]) {
			$most_current_row = $row;
		}
	}
	// Assign Winners to the variables
	if ($most_current_row) {
		list($winner_startDate, $winner_endDate, $winner_theme, $first_place, $second_place, $third_place, $fourth_place) = $most_current_row;
	} else {
		// Handle the case where no most current row was found
		$winner_startDate = 'N/A';
		$winner_endDate = 'N/A';
		$first_place = 'TBD';
		$second_place = 'TBD';
		$third_place = 'TBD';
		$fourth_place = 'TBD';
	}

    // `parse_mec_fields` Refers to another snippet, make sure it is active
    foreach ($results as &$result) {
		
		// Parse the serialized mec_fields data for each result
        if (isset($result['mec_fields'])) {
            $parsed_fields = parse_mec_fields($result['mec_fields']);
            $result = array_merge($result, $parsed_fields);
            unset($result['mec_fields']);
        }
		
		// Calculate the date 2 days before Next start date (d.tstart)
        if (isset($result['tstart'])) {
            $twodays_before = date('Y-m-d H:i:s', strtotime('-2 days', $result['tstart']));
            $result['twodays_before'] = $twodays_before;
        }
		
		// Calculate the date 6 days before Next start date (d.tstart)
        if (isset($result['tstart'])) {
            $sixdays_before = date('Y-m-d H:i:s', strtotime('-6 days', $result['tstart']));
            $result['sixdays_before'] = $sixdays_before;
        }
		
		// Add google sheet value to the results
		if ($latest_theme !== null) {
			$result['minecraft_theme'] = $latest_theme;
		} else {
			$result['minecraft_theme'] = 'TBD'; // Set to 'TBD' when the condition is not met
		}
		
		// Add winners row to the results
		$result['winner_start'] = $winner_startDate;
		$result['winner_end'] = $winner_endDate;
		$result['winner_theme'] = $winner_theme;
		$result['first_place'] = $first_place;
		$result['second_place'] = $second_place;
		$result['third_place'] = $third_place;
		$result['fourth_place'] = $fourth_place;
    }

    return $results;
}, 10, 2);
