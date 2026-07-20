<?php
/**
 * One endpoint serving everything app.php needs to render.
 *
 * The page used to issue six separate API calls on load — auth/me, categories,
 * stories, posts, friends, friends/pending, friends/sent — and two of them were
 * sequential (categories waited on auth/me, the feed waited on categories).
 * Each call re-opened its own connection to the database, and because PHP holds
 * an exclusive lock on the session file for the length of a request, they
 * queued behind one another instead of running concurrently.
 *
 * Collapsing them into a single request removes that entirely: one connection,
 * one session read, one round trip. On a remote database — which production
 * uses — the saved connection handshakes dominate the win.
 *
 * The per-resource endpoints all still exist and still work. They are what the
 * page uses to refresh one section after an action (posting, accepting a friend
 * request, changing category tab), and this controller deliberately reuses
 * their fetch methods so there is only ever one copy of each query.
 */

class BootstrapController
{
    /* GET bootstrap */
    public static function index($params)
    {
        global $pdo;

        // Same contract as auth/me: an expired session must 401 so the client
        // can bounce to the login page rather than render an empty shell.
        $auth = AuthMiddleware::requireAuth();

        // Same columns and derived stats app/me.php returns, so the sidebar can
        // render from this response instead of making its own call.
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.first_name, u.last_name, u.email,
                   u.city, u.state, u.sport, u.position, u.bio, u.goals,
                   u.profile_pic,
                   COALESCE(b.likes, 0) AS likes,
                   (SELECT AVG(NULLIF(p.rating, 0)) FROM posts p WHERE p.user_id = u.id) AS community_rating,
                   (SELECT COUNT(*) FROM highlights h  WHERE h.user_id = u.id) AS num_highlights,
                   (SELECT COUNT(*) FROM saved_posts s WHERE s.user_id = u.id) AS num_saved_posts
              FROM users u
              LEFT JOIN achievement_badges b ON b.user_id = u.id
             WHERE u.id = ?
        ");
        $stmt->execute([$auth['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::error('Not authenticated', 401);
            return;
        }

        $meId = (int)$auth['id'];

        $user['id']              = (int)$user['id'];
        $user['rating']          = round((float)($user['community_rating'] ?? 0), 1);
        $user['num_highlights']  = (int)$user['num_highlights'];
        $user['num_saved_posts'] = (int)$user['num_saved_posts'];
        $user['role']            = $auth['role'];
        unset($user['community_rating']);

        Response::success([
            'user' => $user,
            'categories'      => CategoryController::fetchAll(),
            'stories'         => StoryController::fetchFeed($meId),
            'feed'            => PostController::fetchIndex(),
            'friends'         => FriendController::fetchFriends($meId),
            'friend_requests' => FriendController::fetchPending($meId),
            'friend_sent'     => FriendController::fetchSent($meId),
        ]);
    }
}
