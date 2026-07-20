<?php 

// Temporary debugging lines - Remove before going live!
// Enable logging and disable on-screen output
ini_set('log_errors', 1);
ini_set('display_errors', 0);

// Name and location of your log file
ini_set('error_log', __DIR__ . '/error.log'); 


// Updated path to reach app/config/db.php from your root folder
require_once __DIR__ . '/../config/db.php';



if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success' => false, 'error' => 'Not logged in']); 
    exit; 
} 

$user_id = $_SESSION['user_id']; 
$five_minutes_ago = time() - 300; 

try {
    // This query pulls your friends and checks their activity status
    $query = "SELECT u.id, u.username, u.last_activity, f.status 
              FROM friends f
              JOIN users u ON (f.friend_id = u.id AND f.user_id = ?) 
                           OR (f.user_id = u.id AND f.friend_id = ?)
          WHERE f.status = 'accepted' AND u.id != ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $user_id, $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $friends = [];

    foreach ($rows as $row) {
        $is_online = (!empty($row['last_activity']) && $row['last_activity'] > $five_minutes_ago);
        
        $friends[] = [
            "id" => $row['id'],
            "username" => $row['username'],
            "is_online" => $is_online
        ];
    }

    echo json_encode([ 
        'success' => true, 
        'friends' => $friends 
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}
