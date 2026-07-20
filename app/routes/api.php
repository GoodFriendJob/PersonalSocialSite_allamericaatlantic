<?php

/* Load Controllers */
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/PostController.php';
require_once __DIR__ . '/../controllers/CommentController.php';
require_once __DIR__ . '/../controllers/LikeController.php';
require_once __DIR__ . '/../controllers/FollowController.php';
require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/ThreadController.php';
require_once __DIR__ . '/../controllers/MessageController.php';
require_once __DIR__ . '/../controllers/MessageReactionController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/AdminDashboardController.php';
require_once __DIR__ . '/../controllers/ReportController.php';
require_once __DIR__ . '/../controllers/StoryController.php';
require_once __DIR__ . '/../controllers/ProfileController.php';

/* -------------------------
   AUTH
--------------------------*/
$router->add('POST', 'auth/register', ['AuthController', 'register']);
$router->add('POST', 'auth/login',    ['AuthController', 'login']);

/* -------------------------
   STORIES
--------------------------*/
$router->add('POST',   'stories',              ['StoryController', 'create']);
$router->add('GET',    'stories',              ['StoryController', 'feed']);
$router->add('GET',    'stories/me',           ['StoryController', 'myStories']);
$router->add('POST',   'stories/{id}/view',    ['StoryController', 'view']);
$router->add('GET',    'stories/{id}/viewers', ['StoryController', 'viewers']);
$router->add('DELETE', 'stories/{id}',         ['StoryController', 'delete']);

/* -------------------------
   REPORTS
--------------------------*/
$router->add('POST', 'reports',      ['ReportController', 'create']);
$router->add('GET',  'reports/mine', ['ReportController', 'myReports']);

/* -------------------------
   PROFILE
--------------------------*/
$router->add('GET',  'me',           ['ProfileController', 'me']);
$router->add('PUT',  'me/profile',   ['ProfileController', 'updateProfile']);
$router->add('POST', 'me/avatar',    ['ProfileController', 'uploadAvatar']);

/* -------------------------
   ADMIN DASHBOARD
--------------------------*/
$router->add('GET', 'admin/dashboard/stats',    ['AdminDashboardController', 'stats']);
$router->add('GET', 'admin/dashboard/activity', ['AdminDashboardController', 'recentActivity']);

/* -------------------------
   ADMIN MODERATION
--------------------------*/
$router->add('POST',   'admin/users/{id}/ban',        ['AdminController', 'banUser']);
$router->add('POST',   'admin/users/{id}/unban',      ['AdminController', 'unbanUser']);
$router->add('DELETE', 'admin/posts/{id}',            ['AdminController', 'deletePost']);
$router->add('DELETE', 'admin/comments/{id}',         ['AdminController', 'deleteComment']);
$router->add('GET',    'admin/reports',               ['AdminController', 'reports']);
$router->add('POST',   'admin/reports/{id}/resolve',  ['AdminController', 'resolveReport']);

/* -------------------------
   MESSAGE REACTIONS
--------------------------*/
$router->add('POST',   'messages/{id}/react',     ['MessageReactionController', 'react']);
$router->add('DELETE', 'messages/{id}/react',     ['MessageReactionController', 'remove']);
$router->add('GET',    'messages/{id}/reactions', ['MessageReactionController', 'list']);

/* -------------------------
   MESSAGING
--------------------------*/
$router->add('GET',  'threads',              ['ThreadController', 'index']);
$router->add('POST', 'threads',              ['ThreadController', 'create']);
$router->add('GET',  'threads/{id}',         ['ThreadController', 'show']);
$router->add('GET',  'threads/{id}/messages',['MessageController', 'index']);
$router->add('POST', 'threads/{id}/messages',['MessageController', 'create']);

/* -------------------------
   POSTS
--------------------------*/
$router->add('GET',  'posts',              ['PostController', 'index']);
$router->add('GET',  'posts/{id}',         ['PostController', 'show']);
$router->add('POST', 'posts',              ['PostController', 'create']);
$router->add('PUT',  'posts/{id}',         ['PostController', 'update']);

$router->add('POST', 'posts/{id}/like',    ['LikeController', 'like']);
$router->add('DELETE','posts/{id}/like',   ['LikeController', 'unlike']);
$router->add('GET',  'posts/{id}/likes',   ['PostController', 'getLikes']);

/* COMMENTS */
$router->add('GET',  'posts/{id}/comments', ['CommentController', 'index']);
$router->add('POST', 'posts/{id}/comments', ['CommentController', 'addComment']);
$router->add('POST', 'posts/{postId}/comments/{commentId}/reply', ['CommentController', 'replyToComment']);
$router->add('DELETE', 'comments/{id}', ['CommentController', 'delete']);


// Add a comment
$router->add('POST', '/posts/{id}/comments', ['CommentController', 'addComment']);

// Reply to a comment
$router->add('POST', '/posts/{postId}/comments/{commentId}/reply', ['CommentController', 'replyToComment']);

/* -------------------------
   USERS
--------------------------*/
$router->add('POST',   'users/{id}/follow',  ['FollowController', 'follow']);
$router->add('DELETE', 'users/{id}/follow',  ['FollowController', 'unfollow']);
$router->add('GET',    'users/{id}',         ['UserController', 'show']);
// most specific first
// MOST SPECIFIC FIRST
$router->add('POST', 'notifications/{id}/read', ['NotificationController', 'markRead']);
$router->add('POST', 'notifications/read-all', ['NotificationController', 'markAllRead']);

// THEN the general route

/* -------------------------
   NOTIFICATIONS
--------------------------*/
$router->add('GET', 'notifications', ['NotificationController', 'index']);



