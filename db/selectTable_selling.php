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
		include('../htconfig/aaa_config_selling.php'); 
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
	
	$selling_SQLselect = "SELECT  ";
	$selling_SQLselect .= "name, phone, address,city, country,email,website ";	
	$selling_SQLselect .= "FROM ";
	$selling_SQLselect .= "forsale_content ";			//	<< table name
	
	$selling_SQLselect_Query = mysql_query($selling_SQLselect);  	

	
	echo "<table border='1'>";
		
		echo "<tr>";
		     echo "<td>#</td>";
			echo "<td>name</td>";
			echo "<td>Phone</td>";
			echo "<td>Address</td>";
			echo "<td>City</td>";
			echo "<td>country</td>";
			echo "<td>Email</td>";
			echo "<td>WebSite</td>";
	
		echo "</tr>";

	
	$indx = 1;	
	while ($row = mysql_fetch_array($selling_SQLselect_Query, MYSQL_ASSOC)) {
	    $name =    $row['name'];
	    $phone = $row['phone'];
	    $address = $row['address'];
	    $city = $row['city'];
	    $country = $row['country'];
		$email = $row['email'];
		$website = $row['website'];
		echo "<tr>";
		
			echo "<td>".$indx."</td>";       //  this is NOT  tPerson.ID
			echo "<td>".$name."</td>";
			echo "<td>".$phone."</td>";
			echo "<td>".$address."</td>";
			echo "<td>".$city."</td>";
			echo "<td>".$country."</td>";
			echo "<td>".$email."</td>";
			echo "<td>".$website."</td>";
	
		echo "</tr>";

	    $indx++;
	    
	}
	
	echo "</table>";	

	
	
	mysql_free_result($selling_SQLselect_Query);		
}

?>