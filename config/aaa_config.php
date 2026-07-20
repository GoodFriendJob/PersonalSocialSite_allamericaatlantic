<?

$db_user 		= 	"charles_007";					// db user
$db_pass 		= 	"freefall";					// db pass
$db_host 		= 	"allamericaatlanticco.mydomaincommysql.com";
$db 			= 	"friends";					// db

$adminmail		=	"admin@allamericaatlantic.com";					// admin email

$year 			= 	date( "Y" ); 
$posttime 		= 	date( "H:i");
$postdate		= 	date( "m-d-Y" );
$photostamp		= 	date( "YmdHi" );

$q    			= 	$_GET['q'];
$id    			= 	$_REQUEST['id'];
$password		= 	$_REQUEST['password'];
$category		=	$_REQUEST['category'];
$photo			= 	$_REQUEST['photo'];

$fname			= 	$_POST['fname'];
$lname          = 	$_POST['lname'];
$email			= 	$_POST['email'];
$phone		    = 	$_POST['phone'];  
$phone			= 	$_POST['phone'];
$city			= 	$_POST['city'];
$state		    = 	$_POST['state'];

$website		= 	$_POST['website'];
//$title			= 	$_POST['title'];
//$price			= 	$_POST['price'];
//$description	= 	$_POST['description'];
//$status			=	$_POST['status'];
//$evaluatemsg	= 	$_POST['evaluatemsg'];
//$emailevaluate	= 	$_POST['emailevaluate'];
$subject		= 	$_POST['subject'];
//$forsalecat		= 	$_POST['forsalecat'];
$contactname	=	$_POST['contactname'];
$contactemail	=	$_POST['contactemail'];
$contactmessage	=	$_POST['contactmessage'];
//$catsearch		=	$_POST['catsearch'];
//$searchstring	=	$_POST['searchstring'];

$useragent 		= 	($_SERVER['HTTP_USER_AGENT']);


//$forsaleurl		=	"http://www.allamericaatlantic.com/sell/marketplace/";			// script url with trailing slash
$websitetitle	=	"All America Atlantic";					// site title
//$currency		=	"USD";					// currency


//$uploadDir 		= 	"photos/";				// upload dir for photos
//$maxfilesize	=	"300000";	// in bytes		// max upload filesize in bytes
//$maxfilesizekb	=	"600";		// in KB		// max upload filesize in KB









?>