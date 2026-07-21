<?php
/*

*	File:			create_catagory.php
*	By:			    Charles Fletcher
*	Date:		    3/8/2019
*
*	This script catagory the forsale_cat TABLE
*
*
*=====================================
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

	
	{	//		Table Definition 
		$tableName = "catagory";	
		$CSVfilename = "csv_cat.txt";

		$tableField = array(
					'id',
					'forsalecat',
					'name'
									
		);
		$numFields = sizeof($tableField);
		
		echo '$numFields : '.$numFields.'<br />';

		$createTable_SQL = "
					CREATE TABLE ".$tableName." (
					id INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					forsalecat VARCHAR( 50 ) ,
					name VARCHAR( 250 ) 
					
		)";
	}
	
	//	=======^^^^^^^^^^^^^^^^^^^^^^^=========  End of Definition Part ======^^^^^^^^^=====

										
		{  //		read CSV data file
	
			$file = fopen($CSVfilename, "r"); 		
			$i = 0;
			while(!feof($file))
			  {		  	
				$thisLine = fgets($file);		
				$tableData[$i] = explode(",", $thisLine);
				$i++; 
			  }
			fclose($file);
			
			$numRows = sizeof($tableData);
		}
		echo '$numRows : '.$numRows.'<br />';
		echo '$tableField[$numFields-1] : '.$tableField[$numFields-1].'<br />';

		{	//		DROP table		
	
		
			$drop_SQL = "DROP TABLE ".$tableName;
			
			if (mysql_query($drop_SQL))  {	
				echo "'DROP TABLE ".$tableName."' -  Successful.";
			} else {
				echo "'DROP TABLE ".$tableName."' - Failed.";
			}
		}
		
		echo "<br /><hr /><br />";
	
		{	//		CREATE table		
			
			if (mysql_query($createTable_SQL))  {	
				echo "'CREATE ".$tableName."' -  Successful.";
			} else {
				echo "'CREATE ".$tableName."' - Failed.";
			}
		}		
		echo "<br /><hr /><br />";
			
			$table_SQLinsert = "INSERT INTO ".$tableName." (";
			
			//$table_SQLinsert .=   "x"; 
			foreach($tableField as $tableFieldName) {
				$table_SQLinsert .=  $tableFieldName;
				if($tableFieldName <> $tableField[$numFields-1]) {
					$table_SQLinsert .=  ", ";
				}
			}
			$table_SQLinsert .=  ") VALUES ";

			$indx = 0;		
			while($indx < $numRows) {			
				$table_SQLinsert .=  "(";
				
				foreach($tableField as $key => $tableFieldName) {
					
					$table_SQLinsert .=  "'".$tableData[$indx][$key]."'";
					if($tableFieldName <> $tableField[$numFields-1]) {
						$table_SQLinsert .=  ", ";
					}

				}

				$table_SQLinsert .=  ") ";
				if ($indx < ($numRows - 1)) {
					$table_SQLinsert .=  ",\n";
				}
				
				$indx++;
			}
		
			{	//	Echo and Execute the SQL and test for success   
			
						echo "<strong><u>SQL:<br /></u></strong>";
						echo $table_SQLinsert."<br /><br />";
							
						if (mysql_query($table_SQLinsert))  {				
							echo "was SUCCESSFUL.<br /><br />";
						} else {
							echo "FAILED.<br /><br />";		
						}
			}
}

?>