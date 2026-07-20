<title>php connect contacts</title>



<?php
$servername = "allamericaatlanticco.mydomaincommysql.com";
$username = "charles";
$password = "FreeFall426$";
$dbname = "contacts";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT *  FROM people";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
echo "<table><tr><th>ID</th><th>Name</th><th>phone</th></tr>";
    // output data of each row
    while($row = $result->fetch_assoc()) {
	 echo "<tr><td>".$row["id"]."</td><td>".$row["pp_fname"]." ".$row["pp_lname"]."</td><td>".$row["pp_phone"]."</td></tr>";
        echo "<br> id: ". $row["id"]. " - Name: ". $row["pp_fname"]. " " . $row["pp_lname"] . " phone:". $row["pp_phone"]. "<br>";
    }
	echo "</table>";
} else {
    echo "0 results";
}

$conn->close();
?>
