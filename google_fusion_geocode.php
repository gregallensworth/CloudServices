<?php
/* google_fusion_geocode.php
 * Read a Google Fusion Table and find any fields lacking Lat or Lon
 * Perform a Google geocode, and save the Lat & Lon back to the spreadsheet.
 * 
 * This can work well with the google2cartodb.php demo as well, as these two parts
 * geocode points and then upload them to CartoDB.
 *
 * Keep in mind the usage limits on Google's geocoder. You're allowed several thousand per day,
 * and if you're bulk geocoding like this, it may be an issue.
 */

$CONFIG = array();
$CONFIG['GOOGLE_USERNAME']    = "nobody@gmail.com";
$CONFIG['GOOGLE_PASSWORD']    = "mysecret";
$CONFIG['GOOGLE_FUSIONTABLE'] = "abcd1234-ABCghj-asdfghjkl_qwer1234";


require 'FusionTable.php';

// connect to Google, then to the Fusion Table
$token = GoogleClientLogin($CONFIG['GOOGLE_USERNAME'], $CONFIG['GOOGLE_PASSWORD'], 'fusiontables');
$fusiontable = new FusionTable($token);

// Make a query, the output is an array of associative arrays, simple fieldname=>value pairs
$rows    = $fusiontable->query("SELECT rowid, Address, Lat, Lon FROM {$CONFIG['GOOGLE_FUSIONTABLE']}");
$howmany = count($rows);
print "<p>Found $howmany records</p>\n";
ob_end_flush();

// fetch geocodes for any who need them, update the spreadsheet
for ($i=0; $i<$howmany; $i++) {
    // already has a geocode? skip it
    //printf("Next Row ID is %s\n", $rows[$i]['rowid'] );
    printf("[%d/%d] %s, %s        ", $i+1, $howmany, $rows[$i]['rowid'], htmlspecialchars($rows[$i]['Address']) );
    if ($rows[$i]["Lat"] and $rows[$i]["Lon"]) { print "ALREADY DONE<br/>\n"; continue; }

    // geocode this address, update the in-memory row with Lat and Lon so it is no longer blank
    // the existence of Lat and Lon can now be used to detect an error condition
    $url = sprintf("http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=%s", urlencode($rows[$i]['Address']) );
    $geocode = json_decode(file_get_contents($url));
    if ($geocode->status != 'OK') {
        print "FAILED<br/>\n";
        continue;
    }

    // update the spreadsheet; also update the array in-memory, because in a real program we may want to iterate over these addresses
    // and after this geocoding loop it will be as if these records always had addresses
    $rows[$i]['Lat'] = $geocode->results[0]->geometry->location->lat;
    $rows[$i]['Lon'] = $geocode->results[0]->geometry->location->lng;
    if ($rows[$i]['Lat'] and $rows[$i]['Lon']) {
        $fusiontable->query(sprintf("UPDATE %s set Lat=%f,Lon=%f WHERE rowid='%s'", $CONFIG['GOOGLE_FUSIONTABLE'], $rows[$i]['Lat'], $rows[$i]['Lon'], $rows[$i]['rowid'] ));
    }
    print "OK<br/>\n";
    sleep(2); // give Google a moment
}


// Done!
print "<p>Done!</p>\n";
