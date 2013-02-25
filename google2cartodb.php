<?php
/* google2cartodb.php
 * A demonstration of both the CartoDB.php and GoogleFusionTable.php libraries
 * It simply loads records from a Google Fusion Spreadsheet into a CartoDB table.
 * 
 * A use case for this using a Google Docs as a simple database of customers,
 * then loading them into CartoDB for later processing such as plotting onto a map.
 *
 * ASSUMPTIONS:
 * - Your CartoDB table has a "rowid" numeric field; this is used to check records
 * already being in CartoDB so they don't need to be reinserted. For your own use
 * case maybe it's appropriate to delete and reinsert...
 * - For your own programming style, the use of $CONFIG may not be appropriate,
 * e.g. using CodeIgniter's $this->config->item()
 */

$CONFIG = array();
$CONFIG['GOOGLE_USERNAME']    = "nobody@gmail.com";
$CONFIG['GOOGLE_PASSWORD']    = "mysecret";
$CONFIG['GOOGLE_FUSIONTABLE'] = "abcd1234-ABCghj-asdfghjkl_qwer1234";
$CONFIG['CARTODB_USERNAME']   = "myself";
$CONFIG['CARTODB_APIKEY']     = "abcdefgh1234567890";
$CONFIG['CARTODB_POINTS']     = "registrants";



require 'GoogleFusionTable.php';
require 'CartoDB.php';

// connect to Google, then to the Fusion Table
$token = GoogleClientLogin($CONFIG['GOOGLE_USERNAME'], $CONFIG['GOOGLE_PASSWORD'], 'fusiontables');
$fusiontable = new FusionTable($token);

// Grab a list of all points from the spreadsheet, and make an assocarray of matching RowID#s from the CartoDB table
// A match between a Google rowid and a CartoDB rowid, allows us not to brutally re-insert records which were already there
$points = $fusiontable->query("SELECT rowid, Lat, Lon FROM {$CONFIG['GOOGLE_FUSIONTABLE']}");
if (! sizeof($points) ) die("No records in spreadsheet? That's not right.");
$already = array();
$as = sprintf("SELECT DISTINCT rowid FROM %s", $CONFIG['CARTODB_POINTS'] );
$as = CartoDBQuery($as);
foreach ($as->rows as $a) $already[ $a->rowid ] = true;

// iterate and insert!
foreach ($points as $point) {
    ob_flush();
    if (! $point['Lat'] or ! $point['Lon']) { // doesn't have both, so not valid
        printf("Skip %d, Missing lat / lon<br/>\n", $point['rowid'] );
        continue;
    }
    if (@$already[ $point['rowid'] ]) { // already in CartoDB
        printf("Skip %d, Already present<br/>\n", $point['rowid'] );
        continue;
    }
    printf("Insert %d, %f %f <br/>\n", $point['rowid'], $point['Lon'], $point['Lat'] );

    // the use of sprintf and %d effectively casts NULLs as 0
    $query = sprintf("INSERT INTO %s (rowid,the_geom) VALUES ( %d , ST_GEOMFROMTEXT('POINT(%f %f)',4326) )",
        $CONFIG['CARTODB_POINTS'],
        $point['rowid'], $point['Lon'], $point['Lat']
    );
    $result = CartoDBQuery($query);
    print_r($result);
}

// DONE!
print "<p>Done!</p>\n";
