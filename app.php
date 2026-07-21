<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit;
}
$__app_base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/");
if ($__app_base === "." || $__app_base === "/") {
    $__app_base = "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>All American Network</title>
    <link rel="stylesheet" href="app.css">
    <link rel="stylesheet" href="assets/css/mobile.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- ================= TOP BAR ================= -->
<header class="topbar">

    <div class="topbar-left">
        <img src="assets/icons/logo_dark.png" alt="AAA" class="mini-logo">
    </div>

    <div class="topbar-center">
        <div class="topbar-pill-buttons">
            <button class="pill-btn">All American Network</button>
            <button class="pill-btn">All American Sports</button>
        </div>

        <div class="topbar-slogan-row">
            <span class="slogan">Be best of the best in every category</span>
        </div>

        <div class="topbar-actions">
            <button class="action-btn small">Daily Goal</button>
            <button class="action-btn">New Highlight Reel of the Day</button>
        </div>
    </div>

    <div class="topbar-right">
        <img src="assets/icons/aaa_logo_dark.png" alt="AAA logo" class="main-logo">
    </div>

</header>

<!-- ================= MAIN LAYOUT ================= -->
<div class="app-shell" data-view="main">
    <div class="layout">

    <nav class="mobile-layout-controls" aria-label="Mobile account and network controls">
        <button type="button" class="mobile-panel-button" data-mobile-panel="profile" aria-controls="mobile-profile-drawer" aria-expanded="false">
            <span aria-hidden="true">&#9776;</span> Profile
        </button>
        <strong>All American</strong>
        <button type="button" class="mobile-panel-button" data-mobile-panel="network" aria-controls="mobile-network-drawer" aria-expanded="false">
            Friends &amp; Network <span aria-hidden="true">&#128101;</span>
        </button>
    </nav>

    <section class="mobile-profile-summary" aria-label="Your profile summary">
        <button type="button" class="mobile-profile-summary-button" data-mobile-panel="profile" aria-controls="mobile-profile-drawer" aria-expanded="false">
            <img id="mobileProfilePic" src="assets/img/default-avatar.svg" alt="">
            <span class="mobile-profile-copy">
                <strong id="mobileProfileName">Loading...</strong>
                <span id="mobileProfileHandle">@loading</span>
                <small id="mobileProfileSport">Sport and position not set</small>
            </span>
            <span class="mobile-profile-chevron" aria-hidden="true">&#8250;</span>
        </button>
    </section>

    <button type="button" class="mobile-drawer-backdrop" aria-label="Close sidebar" hidden></button>

    <!-- ========== LEFT SIDEBAR: PROFILE ========== -->
  <!-- ========== LEFT SIDEBAR: PROFILE ========== -->
<aside id="mobile-profile-drawer" class="sidebar-left" aria-label="Profile sidebar">
    <div class="mobile-drawer-heading">
        <strong>Your profile</strong>
        <button type="button" class="mobile-drawer-close" data-mobile-close aria-label="Close profile sidebar">&times;</button>
    </div>
    <div class="profile-card">

        <div class="profile-photo-wrapper">
            <div class="profile-photo-inner">
                <img id="profilePic" src="assets/img/default-avatar.svg" class="profile-photo" alt="Profile picture">
            </div>
        </div>

        <h2 id="profileName" class="profile-name">Loading...</h2>
        <a id="profileHandle" class="profile-handle" href="#">@loading</a>

        <p id="profileSport" class="profile-role">Sport — Position not set</p>

        <p class="profile-location">
            From: <span id="profileLocation">Loading...</span>
        </p>

        <div class="profile-stats">
            <div class="stat-card">
                <span id="statHighlights" class="stat-value">0</span>
                <span class="stat-label">Highlights</span>
            </div>
            <div class="stat-card">
                <span id="statScouts" class="stat-value">0</span>
                <span class="stat-label">Scouts Saved</span>
            </div>
            <div class="stat-card">
                <span id="statRating" class="stat-value">0.0</span>
                <span class="stat-label">Rating</span>
            </div>
        </div>

        <p id="profileBio" class="profile-description">
            Loading bio...
        </p>

        <p id="profileGoals" class="profile-goals" hidden></p>

        <div class="achievements">
            <h4>Achievements</h4>
            <p>Likes: <span id="badgeLikes">0</span></p>
        </div>

        <button id="btn-edit-profile" class="edit-profile-btn">Edit Profile</button>

    </div>
	
	<!-- log out button -->
	<!-- Place this inside your left sidebar container, ideally at the bottom -->
		<div class="sidebar-logout-container">
			<a href="logout.php" class="logout-btn">
				<svg class="logout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
					<polyline points="16 17 21 12 16 7"></polyline>
					<line x1="21" y1="12" x2="9" y2="12"></line>
				</svg>
				<span>Logout</span>
			</a>
		</div>

</aside>
<!-- ========== PROFILE EDITOR (HIDDEN BY DEFAULT) ========== -->
<div class="profile-shell" data-view="profile" style="display:none;">

    <div class="profile-edit-card">

        <h2>Edit Profile</h2>

        <form id="profile-form">

            <p id="profile-form-error" class="profile-form-error" style="display:none;"></p>

            <div class="profile-row">
                <div>
                    <label for="profile-first-name">First name</label>
                    <input type="text" id="profile-first-name" name="first_name" placeholder="First name" required>
                </div>
                <div>
                    <label for="profile-last-name">Last name</label>
                    <input type="text" id="profile-last-name" name="last_name" placeholder="Last name">
                </div>
            </div>

            <label for="profile-username">Username</label>
            <input type="text" id="profile-username" name="username" placeholder="username (appears as @username)" required autocomplete="username">

            <label for="profile-sport">Sport</label>
            <input type="text" id="profile-sport" name="sport" placeholder="Football, basketball, etc.">

            <label for="profile-position">Position</label>
            <input type="text" id="profile-position" name="position" placeholder="e.g. WR, Point guard">

            <div class="profile-row">
                <div>
                    <label for="profile-city">City</label>
                    <input type="text" id="profile-city" name="city" placeholder="City">
                </div>
                <div>
                    <label for="profile-state">State</label>
                    <input type="text" id="profile-state" name="state" placeholder="State">
                </div>
            </div>

            <label for="profile-bio">Bio</label>
            <textarea id="profile-bio" name="bio" placeholder="Tell us about yourself"></textarea>

            <label for="profile-picture">Profile picture</label>
            <input type="file" id="profile-picture" name="profile_picture" accept="image/*">
            <img id="profile-picture-preview" class="profile-picture-preview" src="assets/img/default-avatar.svg" alt="Profile picture preview">
            <label class="profile-remove-pic-label" for="profile-remove-picture">
                <input type="checkbox" id="profile-remove-picture" name="remove_picture" value="1">
                Remove current profile picture
            </label>

            <label for="profile-goals-input">Goals</label>
            <textarea id="profile-goals-input" name="goals" placeholder="Your athletic or season goals"></textarea>

            <div class="profile-form-actions">
                <button type="submit" class="save-profile-btn">Save profile</button>
                <button type="button" class="cancel-profile-btn" onclick="setAppStage('main')">Cancel</button>
            </div>

        </form>

    </div>

</div>


    <!-- ========== CENTER FEED ========== -->
    <main class="center-feed">

        <!-- Sports Tabs -->
        <div class="sports-tabs">
            <button class="tab active">All Sports</button>
            <button class="tab">Football</button>
            <button class="tab">Basketball</button>
            <button class="tab">Baseball</button>
            <button class="tab">Soccer</button>
            <button class="tab">Hockey</button>
            <button class="tab">Tennis</button>
            <button class="tab">Golf</button>
            <button class="tab">Track & Field</button>
            <button class="tab">Wrestling</button>
            <button class="tab">Volleyball</button>
        </div>

        <!-- Facebook-style story rail: five network stories at a time -->
        <section class="story-strip-section" aria-labelledby="story-strip-title">
            <div class="story-strip-heading">
                <div>
                    <h2 id="story-strip-title">All American Stories</h2>
                    <p>Fresh moments from your network</p>
                </div>
                <button id="story-refresh" class="story-refresh" type="button">Refresh</button>
            </div>
            <div class="story-carousel">
                <button id="story-prev" class="story-arrow story-arrow-prev" type="button" aria-label="Previous stories">&#8249;</button>
                <div id="story-rail" class="story-rail" aria-live="polite">
                    <p class="story-rail-loading">Loading your network stories...</p>
                </div>
                <button id="story-next" class="story-arrow story-arrow-next" type="button" aria-label="Next stories">&#8250;</button>
            </div>
        </section>

        <!-- Feed / Stories / Reels tabs -->
        <div class="feed-toggle">
            <button type="button" class="toggle-btn active" data-feed-panel="posts">Feed</button>
            <button type="button" class="toggle-btn" data-feed-panel="stories">Stories</button>
            <button type="button" class="toggle-btn" data-feed-panel="reels">Reels</button>
        </div>

        <!-- Story upload and live story board -->
        <section class="stories-panel" aria-label="Stories">
            <form id="story-upload-form" class="story-composer" enctype="multipart/form-data">
                <div class="story-composer-heading">
                    <div>
                        <h2>Add a story</h2>
                        <p>Share a photo or a video up to 15 seconds. Stories expire after 24 hours.</p>
                    </div>
                    <label class="story-file-button" for="story-media">Choose media</label>
                </div>
                <input id="story-media" name="media" type="file"
                       accept="image/jpeg,image/png,image/gif,video/mp4,video/webm,video/quicktime,video/3gpp,video/x-m4v"
                       required hidden>
                <div id="story-preview" class="story-preview" hidden></div>
                <div class="story-composer-row">
                    <input id="story-caption" name="caption" type="text" maxlength="500"
                           placeholder="Add a caption (optional)">
                    <button id="story-submit" class="story-submit" type="submit">Post story</button>
                </div>
                <p id="story-upload-status" class="story-status" role="status" aria-live="polite"></p>
            </form>

            <div class="story-board-header"><h2>Story activity</h2></div>
            <div id="story-feed" class="story-feed" aria-live="polite">
                <p class="story-empty">Loading stories...</p>
            </div>
        </section>

        <!-- Posts feed -->
        <section class="posts-panel">
            <form id="daily-post-form" class="daily-post-composer" enctype="multipart/form-data">
                <div class="daily-post-heading">
                    <div class="avatar avatar-current">You</div>
                    <textarea id="daily-post-content" name="content" maxlength="5000"
                              placeholder="Share your All American moment..." aria-label="Write a daily post"></textarea>
                </div>
                <div id="daily-post-preview" class="daily-post-preview" hidden></div>
                <div class="daily-post-tools">
                    <label class="daily-post-tool" for="daily-post-images">Photo</label>
                    <input id="daily-post-images" name="images[]" type="file" accept="image/jpeg,image/png,image/gif" multiple hidden>
                    <label class="daily-post-tool" for="daily-post-video">Video</label>
                    <input id="daily-post-video" name="video" type="file" accept="video/mp4,video/webm,video/quicktime,video/x-m4v" hidden>
                    <span class="daily-rating-hint">Your network can rate it from 1 to 5 stars</span>
                    <button id="daily-post-submit" class="daily-post-submit" type="submit">Post</button>
                </div>
                <p id="daily-post-status" class="daily-post-status" role="status" aria-live="polite"></p>
            </form>

            <div class="daily-feed-heading">
                <h2>Daily Feed</h2>
                <button id="daily-feed-refresh" type="button">Refresh</button>
            </div>
            <div id="daily-post-list"></div>

            <article class="post-card featured-post-card">
                <header class="post-header">
                    <div class="post-user">
                        <div class="avatar">JW</div>
                        <div>
                            <h3 class="post-author">Jordan Walker</h3>
                            <p class="post-meta">Varsity • 4.8 avg • 2h ago</p>
                        </div>
                    </div>
                    <span class="post-tag">Friday Night Lights</span>
                </header>

                <p class="post-caption js-link-mentions">
                    Corner route, 4th & goal. Trusted the work, trusted the QB.
                    Shoutout to @charles_test for the scout notes. All American moments are built on days like this.
                </p>

                <div class="post-media-wrapper">
                    <img src="https://images.unsplash.com/photo-1518604666860-9ed391f76460?auto=format&fit=crop&w=1400&q=80"
                         class="post-media" alt="Highlight">
                </div>

                <div class="post-rating-row">
                    <span class="rating-label">Community Rating:</span>
                    <span class="rating-stars">★★★★☆</span>
                </div>

                <div class="post-actions">
                    <button class="post-btn active">Comment</button>
                    <button class="post-btn active">Share</button>
                    <button class="post-btn active">Save</button>
                    <button class="post-btn active">Message</button>
                </div>

                <div class="post-comments">
                    <div class="comment-row">
                        <div class="avatar-small">SC</div>
                        <div>
                            <p class="comment-author">Scout Central</p>
                            <p class="comment-text js-link-mentions">
                                Route discipline, separation, and hands. Ask @fletch if you want a second look.
                            </p>
                        </div>
                    </div>
                </div>
            </article>

        </section>

        <section class="reels-panel" aria-label="All American Reels">
            <div class="reels-heading">
                <div>
                    <h2>All American Reels</h2>
                    <p>Short videos from athletes in your network</p>
                </div>
            </div>
            <div id="reels-feed" class="reels-feed">
                <p class="story-empty">Loading reels...</p>
            </div>
        </section>

    </main>

    <!-- ========== RIGHT SIDEBAR: NETWORK ========== -->
<!-- ========== RIGHT SIDEBAR: NETWORK ========== -->
<aside id="mobile-network-drawer" class="sidebar-right" aria-label="Network sidebar">
    <div class="mobile-drawer-heading">
        <strong>Friends &amp; Network</strong>
        <button type="button" class="mobile-drawer-close" data-mobile-close aria-label="Close network sidebar">&times;</button>
    </div>
    <div class="sidebar-header">
        <h3 class="sidebar-title">Friends &amp; Network</h3>
        <span class="online-count" id="onlineCount">0 Online</span>
    </div>

    <div class="network-view-tabs" role="tablist" aria-label="Friends and network lists">
        <button type="button" class="network-view-tab" data-network-view="friends" role="tab" aria-selected="false">
            My Friends <span id="friendsTabCount">0</span>
        </button>
        <button type="button" class="network-view-tab is-active" data-network-view="network" role="tab" aria-selected="true">
            My Network <span id="networkTabCount">0</span>
        </button>
    </div>

    <ul id="friendsList" class="friends-list">
        <li class="loading-friends">Loading network...</li>
    </ul>

    <section id="friend-requests" class="request-section" hidden>
        <h4 class="request-section-title">
            Friend Requests <span id="requestCount" class="request-count">0</span>
        </h4>
        <ul id="requestList" class="request-list"></ul>
    </section>

    <section id="sent-requests" class="request-section" hidden>
        <h4 class="request-section-title">
            Sent Requests <span id="sentCount" class="request-count">0</span>
        </h4>
        <ul id="sentList" class="request-list"></ul>
    </section>

  <div class="add-friend-card">
    <h4>Add to your circle</h4>
    <input type="text" id="friend-search-input" placeholder="Search or add by name">
    
    <!-- ADD THIS CONTAINER FOR DYNAMIC SEARCH RESULTS -->
    <ul id="search-results-dropdown" class="search-results-dropdown" style="display:none; list-style:none; padding:0; margin: 10px 0; background: #fff; border-radius: 6px;"></ul>
    
    <button class="add-friend-btn" id="add-friend-submit-btn">Add Friend</button>
</div>
</aside>




</div> <!-- end layout -->
</div>  <!-- end app-shell -->

<!-- ================= CHAT POPUP ================= -->
<div id="chat-modal" class="chat-modal" hidden aria-hidden="true">
    <div class="chat-modal-backdrop" data-chat-close></div>
    <div class="chat-window" role="dialog" aria-modal="true" aria-label="Direct message">
        <header class="chat-header">
            <img id="chat-peer-avatar" class="chat-peer-avatar" src="assets/img/default-avatar.svg" alt="">
            <div class="chat-peer-meta">
                <strong id="chat-peer-name">Member</strong>
                <small id="chat-peer-handle"></small>
            </div>
            <button type="button" class="chat-close-btn" data-chat-close aria-label="Close conversation">&times;</button>
        </header>
        <div id="chat-messages" class="chat-messages" aria-live="polite">
            <p class="chat-empty">Say hello 👋</p>
        </div>
        <form id="chat-form" class="chat-form">
            <input id="chat-input" type="text" maxlength="2000" placeholder="Write a message..." autocomplete="off" required>
            <button type="submit" class="chat-send-btn">Send</button>
        </form>
        <p id="chat-status" class="chat-status" role="status" aria-live="polite"></p>
    </div>
</div>
<script>
window.__APP_BASE__ = <?= json_encode($__app_base, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.__CURRENT_USER_ID__ = <?= (int)$_SESSION['user_id'] ?>;
</script>
<script src="assets/js/main.js"></script>
<script src="assets/js/ratings.js"></script>
<script src="assets/js/stories.js"></script>
<script src="assets/js/posts.js"></script>
<script src="assets/js/messages.js"></script>
<script src="assets/js/network-friends.js"></script>
<script src="assets/js/mobile-layout.js"></script>

</body>
</html>
