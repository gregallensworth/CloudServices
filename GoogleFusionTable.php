<?php
/*
 * A library for connecting to Google Docs Fusion Tables Spreadsheets and performing SQL queries.
 * This library handles the OAuth phase of authentication, submits SQL queries to the SQL API, parses the CSV output,
 * so you can simply do SQL:
 * $token = GoogleClientLogin($CONFIG['GOOGLE_USERNAME'], $CONFIG['GOOGLE_PASSWORD'], 'fusiontables');
 * $fusiontable = new FusionTable($token);
 * $points = $fusiontable->query("SELECT rowid, 'First Name', 'Last Name' FROM abc987-def432-xyz123");
 * foreach ($points as $point) { ... }
 * 
 * I (gregallensworth) didn't write the original versions of this, and I don't have credits for who did.
 * If you know, please let me know.
 * I corrected parse_output() to use fgetcsv() as the original version was unable to grok data containing
 * commas. (it used a simple split(',') without checking for delimiters)
 */

function GoogleClientLogin($username, $password, $service) {
	    // Check that we have all the parameters
	    if(!$username || !$password || !$service) {
	        throw new Exception("You must provide a username, password, and service when creating a new GoogleClientLogin.");
	    }
	     
	    // Set up the post body
	    $body = "accountType=GOOGLE &Email=$username&Passwd=$password&service=$service";
	     
	    // Set up the cURL
	    $c = curl_init ("https://www.google.com/accounts/ClientLogin");
	    curl_setopt($c, CURLOPT_POST, true);
	    curl_setopt($c, CURLOPT_POSTFIELDS, $body);
	    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	    $response = curl_exec($c);
	     
	    // Parse the response to obtain just the Auth token
	    // Basically, we remove everything before the "Auth="
	    return preg_replace("/[\s\S]*Auth=/", "", $response);
	}
	 
	class FusionTable {
	    var $token;
	     
	    function FusionTable($token) {
	        if (!$token) {
	            throw new Exception("You must provide a token when creating a new FusionTable.");      
	        }
	        $this->token = $token;
	    }
	     
	    function query($query) {
	        if(!$query) {
	            throw new Exception("query method requires a query.");
	        }
	        // Check to see if we have a query that will retrieve data
	        if(preg_match("/^select|^show tables|^describe/i", $query)) {
	            $request_url = "http://tables.googlelabs.com/api/query?sql=" . urlencode($query);
	            $c = curl_init ($request_url);
	            curl_setopt($c, CURLOPT_HTTPHEADER, array("Authorization: GoogleLogin auth=" . $this->token));
	            curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	             
	            // Place the lines of the output into an array
	            $results = curl_exec($c);
	             
	            // If we got an error, raise it
	            if(curl_getinfo($c, CURLINFO_HTTP_CODE) != 200) {
	                return $this->output_error($results);
	            }
	 
	            // Parse the output
	            return $this->parse_output($results);
	        }
        // Otherwise we are going to be updating the table, so we need to the POST method
	        else if(preg_match("/^update|^delete|^insert/i", $query)) {
	            // Set up the cURL
	            $body = "sql=" . urlencode($query);
	            $c = curl_init ("http://tables.googlelabs.com/api/query");
	            curl_setopt($c, CURLOPT_POST, true);
	            curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	            curl_setopt($c, CURLOPT_HTTPHEADER, array(
	                "Content-length: " . strlen($body),
	                "Content-type: application/x-www-form-urlencoded",
	                "Authorization: GoogleLogin auth=" . $this->token . " "     // I don't know why, but unless I add extra characters after the token, I get this error: Syntax error near line 1:1: unexpected token: null
	            ));
	            curl_setopt($c, CURLOPT_POSTFIELDS, $body);
	             
	            // Place the lines of the output into an array
	            $results = curl_exec($c);
	             
	            // If we got an error, raise it
	            if(curl_getinfo($c, CURLINFO_HTTP_CODE) != 200) {
	                return $this->output_error($results);
	            }
	 
	            // Drop the last (empty) array value
	            array_pop($results);
	             
	            return $this->parse_output($results);
	        }
	        else {
	            throw new Exception("Unknown SQL query submitted.");
	        }
	    }

	    private function parse_output($results) {
	        $output = array();

            // load the results (a CSV file, comma delim, \n line breaks and " text delims) into a virtual file
            // then rewind to the start of the file
            $csv = fopen('php://temp', 'r+');
            fwrite($csv,$results);
            rewind($csv);

            // fetch the first row: the field names
            $colnames = array();
            $row = fgetcsv($csv);
            for ($i=0; $i<sizeof($row); $i++) {
                $colname = $row[$i];
                if ($colname == 'rowid') $colname = 'ROWID';
                $colnames[] = $colname;
            }

            // now the later rows: create an associative array for each row
            // $row[$i] is the field data, $colnames[$i] is the column name
            while ($row = fgetcsv($csv)) {
                $thisrow = array();
                for ($i=0; $i<sizeof($row); $i++) {
                    $thisrow[ $colnames[$i] ] = $row[$i];
                }
                $output[] = $thisrow;
            }

	        // Return the output
	        return $output;
	    }

	    private function output_error($err) {
	        // Remove everything outside of the H1 tag
	        $err = preg_replace("/[\s\S]*<H1>|<\/H1>[\s\S]*/i", "", $err);
	        throw new Exception($err);
	    }
	}
	?>