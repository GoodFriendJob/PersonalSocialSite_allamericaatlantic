<?php
session_start();
require __DIR__ . "/app/config/db.php";

$u = isset($_GET["u"]) ? trim((string) $_GET["u"]) : "";
$u = ltrim($u, "@");

if ($u === "") {
    http_response_code(400);
    ?>
    <!DOCTYPE html>
    <html lang="en"><head><meta charset="UTF-8"><title>Profile</title><link rel="stylesheet" href="app.css"></head>
    <body style="padding:24px;font-family:system-ui;background:#050814;color:#fff;">
    <p>Missing username. Open a profile with <code>?u=username</code>.</p>
    <p><a href="app.php" style="color:#5eb4ff">Return to app</a></p>
    </body></html>
    <?php
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.id,
           u.username,
           u.first_name,
           u.last_name,
           u.city,
           u.state,
           u.sport,
           u.position,
           u.bio,
           u.goals,
           u.profile_pic,
           (SELECT AVG(NULLIF(p.rating, 0)) FROM posts p WHERE p.user_id = u.id) AS community_rating,
           (SELECT COUNT(*) FROM highlights h WHERE h.user_id = u.id) AS num_highlights,
           (SELECT COUNT(*) FROM saved_posts s WHERE s.user_id = u.id) AS num_saved_posts
    FROM users u
    WHERE u.username = ?
");
$stmt->execute([$u]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en"><head><meta charset="UTF-8"><title>Not found</title><link rel="stylesheet" href="app.css"></head>
    <body style="padding:24px;font-family:system-ui;background:#050814;color:#fff;">
    <p>No user found for <strong><?= htmlspecialchars('@' . $u, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
    <p><a href="app.php" style="color:#5eb4ff">Return to app</a></p>
    </body></html>
    <?php
    exit;
}

$viewerId = isset($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null;
$isOwn = $viewerId !== null && $viewerId === (int) $profile["id"];

$name = trim(($profile["first_name"] ?? "") . " " . ($profile["last_name"] ?? ""));
if ($name === "") {
    $name = $profile["username"] ?? "Member";
}

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, "UTF-8");
};

$sportLine = $profile["sport"] ? $h($profile["sport"]) : "Sport not set";
$pos = trim((string) ($profile["position"] ?? ""));
$sportLine .= " — " . ($pos !== "" ? $h($pos) : "Position not set");

$hometown = "";
if (!empty($profile["city"]) || !empty($profile["state"])) {
    $parts = array_filter([(string) $profile["city"], (string) $profile["state"]]);
    $hometown = implode(", ", array_map($h, $parts));
} else {
    $hometown = "Not set yet";
}

$pfp = $profile["profile_pic"] ? $h($profile["profile_pic"]) : "assets/img/charles.jpg";
$ratingVal = $profile["community_rating"] !== null ? number_format((float) $profile["community_rating"], 1) : "0.0";
$hl = (int) ($profile["num_highlights"] ?? 0);
$sv = (int) ($profile["num_saved_posts"] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $h($name) ?> — Profile</title>
    <link rel="stylesheet" href="app.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <a href="app.php"><img src="assets/icons/aaa_logo_dark.png" alt="AAA" class="mini-logo"></a>
    </div>
    <div class="topbar-center">
        <div class="topbar-slogan-row">
            <span class="slogan">@<?= $h($profile["username"]) ?></span>
        </div>
    </div>
    <div class="topbar-right">
        <?php if ($isOwn): ?>
            <a href="app.php?stage=profile" class="pill-btn" style="text-decoration:none;display:inline-block;">Edit profile</a>
        <?php endif; ?>
    </div>
</header>

<div class="layout" style="max-width:900px;margin:0 auto;">
    <aside class="sidebar-left" style="grid-column:1/-1;max-width:400px;margin:0 auto;">
        <div class="profile-card">
            <div class="profile-photo-wrapper">
                <div class="profile-photo-inner">
                    <img src="<?= $pfp ?>" alt="" class="profile-photo">
                </div>
            </div>

            <h2 class="profile-name"><?= $h($name) ?></h2>
            <p class="profile-handle">@<?= $h($profile["username"]) ?></p>

            <p class="profile-role"><?= $sportLine ?></p>
            <p class="profile-location">From: <?= $hometown ?></p>

            <div class="profile-stats">
                <div class="stat-card">
                    <span class="stat-value"><?= $hl ?></span>
                    <span class="stat-label">Highlights</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $sv ?></span>
                    <span class="stat-label">Scouts Saved</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $h($ratingVal) ?></span>
                    <span class="stat-label">Rating</span>
                </div>
            </div>

            <p class="profile-description"><?= $profile["bio"] !== null && $profile["bio"] !== "" ? nl2br($h($profile["bio"])) : $h("No bio yet.") ?></p>
            <?php if (!empty($profile["goals"])): ?>
                <p class="profile-goals"><strong>Goals:</strong> <?= nl2br($h($profile["goals"])) ?></p>
            <?php endif; ?>
        </div>
    </aside>
</div>

</body>
</html>
