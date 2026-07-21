<?php
/*

*	File:			selectTable.php
*	By:			    Charles Fletcher
*	Date:		    3/6/2019
*
*	This script demonstrates SQL SELECT rendered in an  HTML Table 
*
*======================================================================
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

	
	echo "<table border='4'>";
		
		echo "<tr>";
		
			echo "<td>#</td>";
			
			echo "<td>FirstName</td>";
			echo "<td>LastName</td>";
			echo "<td>Email</td>";
	
		echo "</tr>";

	
	$indx = 1;	
	while ($row = mysql_fetch_array($aaa_Friends_SQLselect_Query, MYSQL_ASSOC)) {
	   
	    $fname = $row['aaa_fname'];
	    $lname = $row['aaa_lname'];
	    $email = $row['aaa_email'];
	    
		echo "<tr>";
		
			echo "<td>".$indx."</td>";       //  this is NOT  tPerson.ID
			
			echo "<td>".$fname."</td>";
			echo "<td>".$lname."</td>";
			echo "<td>".$email."</td>";
	
		echo "</tr>";

	    $indx++;
	    
	}
	
	echo "</table>";	

	
	
	mysql_free_result($aaa_Friends_SQLselect_Query);		
}

?>