<?php
/*

*	File:			select.php
*	By:			    Charles Fletcher
*	Date:		    3/6/2019
*
*	This script demnstrates SQL SELECT
*		and use of        mysql_fetch_array
*
*
*=====================================
*/

{ 		//	Secure Connection Script
		include('../htconfig/aaa_config.php'); 
		$dbSuccess = false;
		$dbConnected = mysql_connect($db_host,$db_user,$db_pass);
		
		if ($dbConnected) {		
			$dbSelected = mysql_select_db($db)or die( "Unable to select database");
			if ($dbSelected) {
				$dbSuccess = true;
			} else {
				echo "DB Selection FAILed";
			}
		} else {
				echo "MySQL Connection FAILed";
		}
		//	END	Secure Connection Script
}

if ($dbSuccess) {
	
	$aaa_Friends_SQLselect = "SELECT  ";
	$aaa_Friends_SQLselect .= "aaa_ID, aaa_fname, aaa_lname,aaa_email, aaa_phone ";	
	$aaa_Friends_SQLselect .= "FROM ";
	$aaa_Friends_SQLselect .= "aaa_friends ";			//	<< table name

	
	$aaa_Friends_SQLselect_Query = mysql_query($aaa_Friends_SQLselect); 	

	$indx = 1;	
	while ($row = mysql_fetch_array($aaa_Friends_SQLselect_Query, MYSQL_ASSOC)) {
	    $fname = $row['aaa_fname'];
	    $lname = $row['aaa_lname'];
	    $email = $row['aaa_email'];
	    $phone = $row['aaa_phone'];
	    
	    echo $indx." - ".$fname." ".$lname." ".$email." [phone ".$phone."]<br />";

	    $indx++;
	    
	}
	
	mysql_free_result($aaa_Friends_SQLselect_Query);		
}

?>