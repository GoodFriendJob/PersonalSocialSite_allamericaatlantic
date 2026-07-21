<?php
/*

*	File:			selectJoin.php
*	By:			cHARLES fLETCHER
*	Date:		   3/8/2019
*
*	This script demonstrates SQL SELECT 
*		using tPerson LEFT OUTER JOIN tCompany
*		
*
*=========================================================================
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
	$selling_SQLselect .= "forsale_content.ID, forsale_content.postdate, ";	
	$selling_SQLselect .= "forsale_content.name, forsale_content.email, ";	
	$selling_SQLselect .= "forsale_cat.id, forsale_cat.forsalecat ";	
	$selling_SQLselect .= "FROM ";
	$selling_SQLselect .= "forsale_content ";	
	$selling_SQLselect .= "LEFT OUTER JOIN forsale_cat ON ";
	$selling_SQLselect .= "forsale_content.id = forsale_cat.id ";

	$selling_SQLselect_Query = mysql_query($selling_SQLselect); 	

	echo "<table border='1'>";
		
		echo "<tr>";
		
			echo "<td>#</td>";
			
			echo "<td>id</td>";
			echo "<td>NAME</td>";
			echo "<td>email</td>";
			echo "<td>catagory</td>";
	
		echo "</tr>";

	
	$indx = 1;	
	while ($row = mysql_fetch_array($selling_SQLselect_Query, MYSQL_ASSOC)) {
		
	    $id = $row['id'];
		
	    $name = $row['name'];
	   
	    $email = $row['email'];
	    $catagory = $row['catagory'];
	    
	    $CompanyFullName = trim($catagory." ".$id);
	    
		echo "<tr>";
		
			echo "<td>".$indx."</td>";       //  this is NOT  tPerson.ID
			echo "<td>".$id."</td>";
			echo "<td>".$name."</td>";
			echo "<td>".$email."</td>";
			echo "<td>".$catagory."</td>";
	
		echo "</tr>";

	    $indx++;
	    
	}
	
	echo "</table>";	


	mysql_free_result($forsale_content_SQLselect_Query);		
}

?>