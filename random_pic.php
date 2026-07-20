<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title>all America atlantic</title>
</head>

<body>
<?php 
     srand(microtime(4) * 1000000);
	 $num = rand(1,4);
	 
	 switch($num){
	    case 1: $image_file = "/php/images/pic1.jpg";
		break;
		
		case 2: $image_file = "/php/images/pic2.jpg";
		break;
		
		case 3: $image_file = "/php/images/pic3.jpg";
		break;
		
		case 4: $image_file = "/php/images/pic4.jpg";
		break;
	 }
	 echo " Ramdom Image : <img src=$image_file />";

?>
</body>
</html>
