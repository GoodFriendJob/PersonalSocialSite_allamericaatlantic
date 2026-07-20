<title>php connect</title>



<?php
$servername = "allamericaatlanticco.mydomaincommysql.com";
$username = "charles_007";
$password = "FreeFall426$";
$dbname = "selling";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT id, firstName, lastName, catagoryID  FROM Person";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
echo "<table><tr><th>ID</th><th>Name</th><th>catagory</th></tr>";
    // output data of each row
    while($row = $result->fetch_assoc()) {
	 echo "<tr><td>".$row["id"]."</td><td>".$row["firstName"]." ".$row["lastName"]."</td><td>".$row["catagoryID"]."</td></tr>";
        echo "<br> id: ". $row["id"]. " - Name: ". $row["firstName"]. " " . $row["lastName"] . " Catagory:". $row["catagoryID"]. "<br>";
    }
	echo "</table>";
} else {
    echo "0 results";
}

$conn->close();
?>
