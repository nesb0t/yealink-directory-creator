<?php

###################################################
// Header: Set our variables/constants/etc
###################################################

/**
*
*		Yealink Directory Creator by Brent Nesbit
*		Details: https://github.com/nesb0t/yealink-directory-creator
*		Version 3.1.1 -- Last updated: 2016-10-10
*
**/

date_default_timezone_set('America/New_York');				// Not required, but usually a good idea in php

define("SERVER", "nms.example.com");						// Base URL to your nms server. No https or trailing slashes.
define("SUPERUSER", "directorycreator@example.com"); 		// A superuser login. Should use "Super User Read Only" for security.
define("PASSWORD", "Strong-Password-Here");					// Password for the above account
define("CLIENTID", "Example_API_User");						// API key client ID
define("CLIENTSECRET", "ExampleKey123");					// API key secret key

define("DIRECTORYLOCATION", "/var/www/html/example/");		// Absolute path to folder where directories will be stored. INCLUDE TRAILING SLASH.
define("SRV_CODE", "example-service-code");					// See README for details. If you want to limit results to a certain service code, enter the service code here. If you do not want to limit to service codes then comment this line out.

# $startTime = __pageLoadTimer();				// Track script load/processing time. See comments in the function definition at the bottom of this file.
												// Must uncomment here and in footer at the bottom to use it.

###################################################
// Step 1: Get API access token from the NMS
###################################################

$query = array(
    'grant_type' => "password",
    'username' => SUPERUSER,
    'password' => PASSWORD,
    'client_id' => CLIENTID,
    'client_secret' => CLIENTSECRET
);

$postFields = http_build_query($query);

$curl_result = __doCurl("https://" . SERVER . "/ns-api/oauth2/token", CURLOPT_POST, NULL, NULL, $postFields, $http_response);

if (!$curl_result) {					// Check if curl result was unsuccessful. Check 1 of 3. 
	# echo "Server error";				// Uncomment for basic debugging
	exit;								// Curl failed. Exiting so we don't overwrite all of our directories with failed results.
}

if ($http_response != "200") {											// Check if we got something other than 200/OK on key request. Check 2 of 3. 
	# echo "Key status: FAIL. http_response: $http_response.<br>";		// Uncomment for basic debugging
	exit;																// Key retrieval failed. Exiting so we don't overwrite all of our directories with failed results.
}
else {
	# echo "Key status: PASS<br>";					// Uncomment for basic debugging
}

$token = json_decode($curl_result, true);			// Decode JSON response

if (!isset($token['access_token'])) {				// Verify we got a token
    # echo "No token received.";					// Uncomment for basic debugging
    exit;											// Key retrieval failed. Exiting so we don't overwrite all of our directories with failed results. Check 3 of 3. 
}

$token = $token['access_token'];					// Set our API token as $token
# echo "<br>Token: $token";							// Uncomment for basic debugging


###################################################
// Step 2: Retrieve list of domains
###################################################

$query = array(
    'object' => "domain",			// Request for list of all domains
    'action' => "read"
);

$domains  = __doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$domains = simplexml_load_string($domains);					// Load the XML response from the API

foreach($domains->domain as $key => $value) {  				// Look for the "domain" field in the API response
	if(isset($value->domain)){  							// If the "domain" field has a value then:
		$domainArray[] = "$value->domain";  				// Build an array with the domains
	}
}

###################################################
// Step 3a: Begin creating directories
###################################################

foreach ($domainArray as $key => $domain) {								// Loop through each item (domain) in the array, one at a time.
	# echo "Domain: $domain.<br>";										// Uncomment for basic debugging

	set_time_limit(30);  												// Prevent PHP timeout while building the directories. Sets it to 30 seconds at the beginning of each loop.
	
	$userCount = "";     												// Blank out our user count at the start of each loop
	$userList = "";     												// Blank out our user list at the start of each loop

	$userList .= '<?xml version="1.0" encoding="ISO-8859-1"?>'."\n";    // This is the XML header, required by Yealink for the directory
	$userList .= '<YealinkIPPhoneDirectory>'."\n";

###################################################
// Step 3b: Retrieve list of users (subscribers)
###################################################

	$query = array(
    'object' => "subscriber",											// Retrieve a full list of all subscribers for this domain
    'action' => "read",
	'domain' => htmlspecialchars($domain),								// Sanitizing the domain name, just to be safe
    'fields' => "user,srv_code,dir_list,first_name,last_name"			// We only need specific elements from the subscriber list
	);

	$subscribers = __doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

	$subscribers = simplexml_load_string($subscribers);   								// Load the XML response from the API

	foreach($subscribers->subscriber as $key => $value) {								// Take each subscriber (user) found for this domain, one subscriber at a time
			
			if (!defined('SRV_CODE') || SRV_CODE == NULL) {								// Check if we want to limit results to a certain service code by seeing if it exists at all, or if it exists but isn't set. See README and header for details.
		 		# echo "$domain: SRV_CODE is not being checked.<br>"; 					// Uncomment for basic debugging
				goto SKIP_SRV_CHECK;													// Skip next "if" statement by jumping over it if we don't want to limit results to service codes. As always, there is a relevant XKCD: http://xkcd.com/292/ . 
			}
			
			if(isset($value->srv_code) && $value->srv_code == SRV_CODE){				// Check to see if the service code matches what we set in SRV_CODE -- Don't add them if they aren't. See README for details.
				SKIP_SRV_CHECK: 														// Jumps to here if we aren't checking the service code, skipping the above if statement.
		
		if($value->dir_list == 'yes'){													// Check to see if they are shown in the portal directory -- Don't add them if they are hidden.
					$userCount++;														// Count how many users we add so we don't write empty directories
					$userList .= '<DirectoryEntry>'."\n";													// More Yealink XML
					$userList .= '<Name>' . $value->first_name . " " . $value->last_name ."</Name> \n";		// Add first name and last name to the directory
					$userList .= '<Telephone>' . $value->user . "</Telephone> \n";							// Add the telephone number to the directory
					$userList .= '</DirectoryEntry>'."\n";
				}
			}
		}
	$userList .= '</YealinkIPPhoneDirectory>'."\n";    									// Yealink XML footer

###################################################
// Step 4: Save directory to a file
###################################################
	if ($userCount > 0) {																			// Only write the directory if there are actually users in it
		# echo "Found $userCount user(s) for domain $domain <br>";									// Uncomment for basic debugging
		$saveDirectory = fopen(DIRECTORYLOCATION . $domain . ".xml", "w");							// Open location to save to. It always overwrites the whole file.
	
		if (fwrite($saveDirectory, $userList) === FALSE) {											// Write the file, and check if failed to save
			# echo "Failed to write to file: " . DIRECTORYLOCATION . $domain . ".xml <br>";			// Uncomment for basic debugging
		}
		else {
			# echo "Successfully wrote: " . DIRECTORYLOCATION . $domain . ".xml <br>";				// Uncomment for basic debugging
		}
		fclose($saveDirectory);																		// Close the file
	}
	else{
		# echo "No users found for domain $domain <br>";											// Uncomment for basic debugging
	}


}

###################################################
// Footer
###################################################

# echo "Directories generated in " . __pageLoadTimer($startTime) . " seconds.";					// Uncomment to use load timer (2 of 2)


###################################################
// Functions stored below 
###################################################

function __pageLoadTimer($startTime = NULL){				// Track load/processing time. Can be used for debugging purposes, or for adding additional checks to verify if the script
															// may have failed (ex: if it runs too quickly/too slowly compared to your baseline). Must uncomment in Header and Footer to use it.

		$time = microtime();								// Initialize microtime function
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];

	if ($startTime == NULL) {								// We don't have a start time yet, so set one
		$startTime = $time;

		return $startTime;
	}

	else {													// We have a start time. Calculate finish time and total time
		$finish = $time;
		$total_time = round(($finish - $startTime), 4);

		return $total_time;
	}

}


function __doCurl($url, $method, $authorization, $query, $postFields, &$http_response) {		// Function for our curl requests. Taken from aaker's github. Source: https://github.com/aaker/domain-selfsignup
	$start        = microtime(true);
	$curl_options = array(
		CURLOPT_URL => $url . ($query ? '?' . http_build_query($query) : ''),
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FORBID_REUSE => true,
		CURLOPT_TIMEOUT => 60
	);

	$headers = array();
	if ($authorization != NULL)
		{
		$headers[$authorization] = $authorization;
		} //$authorization != NULL



	$curl_options[$method] = true;
	if ($postFields != NULL)
		{
		$curl_options[CURLOPT_POSTFIELDS] = $postFields;
		} //$postFields != NULL

	if (sizeof($headers) > 0)
		$curl_options[CURLOPT_HTTPHEADER] = $headers;

	$curl_handle = curl_init();
	curl_setopt_array($curl_handle, $curl_options);
	$curl_result   = curl_exec($curl_handle);
	$http_response = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
	//print_r($http_response);
	curl_close($curl_handle);
	$end = microtime(true);
	if (!$curl_result)
		return NULL;
	else if ($http_response >= 400)
		return NULL;
	else
		return $curl_result;
	}

?>