<?php
/****************************
 * Some wrapper functions to make SQL queries to CartoDB.
 * Making queries using the API is simple but repetitive, and separating it out makes code more concise.
 * This is taken from an existing program, and may need adaptation if you don't keep a global $CONFIG
 * e.g. with CodeIgniter you would probably use $this->config->item('cartodb_username')
 ****************************/

// CartoDB username, password, API key, ...
$CONFIG = array();
$CONFIG['cartodb_username'] = "myself";
$CONFIG['cartodb_apikey']   = "abcdefghi1234567890";


/*
 * Pass SQL and have it executed at CartoDB
 * Usage:    $result = CartoDBQuery($sql);
 * The return is whatever CartoDB returned: affected rows, error messages, etc.
 * The output is decoded but unchanged.
 */
function CartoDBQuery($sql) {
    global $CONFIG;

    // generate the URL and API key
    $url = sprintf("http://%s.cartodb.com/api/v2/sql", $CONFIG['cartodb_username'] );
    $data = array(
        'api_key' => $CONFIG['cartodb_apikey'],
        'q' => $sql
    );
    $data_body = http_build_query($data);

    // POST it and capture the output
    // while GET is simpler via file_get_contents() we often submit larger payloads, e.g. text fields
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_URL, $url);
    curl_setopt($curl,CURLOPT_POST, count($data));
    curl_setopt($curl,CURLOPT_POSTFIELDS, $data_body);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($curl);
    curl_close($curl);

    // the output was JSON, be it error or rows...
    return json_decode($result);
}



/*
 * Escape a string suitably for use with CartoDB's SQL API
 * Again, repeating yourself means multiple corrections if we ever improve upon this or the standard changes.
 */
function escapequote($string,$includeouterquotes=true) {
    // super simple: ' becomes '' on the presumption that we will use E'' syntax
    $output = str_replace("'", "''", $string);

    // if they want to include the E'' here, add it
    // this is the default, but they may want to leave out the E'' for some reason...
    if ($includeouterquotes) $output = sprintf("E'%s'", $output );

    // done
    return $output;
}
