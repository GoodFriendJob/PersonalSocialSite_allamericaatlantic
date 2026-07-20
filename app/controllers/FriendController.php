<?php
/**
 * Friend requests and search.
 *
 * Replaces the loose app/accept_friend.php, app/routes/get_friends.php and
 * app/routes/search_users.php scripts, which were unreachable: two of them
 * did require 'config.php' (no such file, fatal) and get_friends.php selected
 * u.last_activity, a column that does not exist, without starting a session.
 *
 * A friendship is one row in `friends` (user_id = requester, friend_id =
 * addressee) with a status. The pair is unique in both directions, checked
 * explicitly before insert.
 */

class FriendController
{
    /* ------------------------------------------------------------------
       GET friends/search?q=...
       Users matching the query, annotated with the relationship state so
       the UI can show Add / Pending / Friends on each row.
    ------------------------------------------------------------------ */
    public static function search($params)
    {
        global $pdo;

        $me = AuthMiddleware::requireAuth();
        $q  = trim($_GET['q'] ?? '');

        if ($q === '') {
            Response::success(['results' => []]);
            return;
        }

        $like = '%' . $q . '%';

        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.first_name, u.last_name, u.city, u.state, u.profile_pic,
                   f.status     AS rel_status,
                   f.user_id    AS rel_requester
              FROM users u
              LEFT JOIN friends f
                     ON (f.user_id = :me AND f.friend_id = u.id)
                     OR (f.friend_id = :me2 AND f.user_id = u.id)
             WHERE u.id <> :me3
               AND u.banned = 0
               AND (u.username LIKE :like1 OR u.first_name LIKE :like2 OR u.last_name LIKE :like3)
             ORDER BY u.username
             LIMIT 20
        ");
        $stmt->execute([
            'me' => $me['id'], 'me2' => $me['id'], 'me3' => $me['id'],
            'like1' => $like, 'like2' => $like, 'like3' => $like,
        ]);

        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'id'          => (int)$row['id'],
                'username'    => $row['username'],
                'first_name'  => $row['first_name'],
                'last_name'   => $row['last_name'],
                'city'        => $row['city'],
                'state'       => $row['state'],
                'profile_pic' => $row['profile_pic'],
                // none | pending_sent | pending_received | accepted | blocked
                'friendship'  => self::describeRelationship($row['rel_status'], $row['rel_requester'], $me['id']),
            ];
        }

        Response::success(['results' => $results]);
    }

    /* ------------------------------------------------------------------
       GET friends  — accepted friends
    ------------------------------------------------------------------ */
    /**
     * Accepted friends as data. Split out from index() so the bootstrap
     * endpoint can reuse it — see BootstrapController.
     */
    public static function fetchFriends(int $meId): array
    {
        global $pdo;

        // Online friends first, so the sidebar reads top-down by availability.
        $isOnline = Presence::sqlIsOnline('u');

        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.first_name, u.last_name, u.profile_pic, u.sport, u.position,
                   $isOnline AS is_online
              FROM friends f
              JOIN users u
                ON u.id = CASE WHEN f.user_id = :me THEN f.friend_id ELSE f.user_id END
             WHERE (f.user_id = :me2 OR f.friend_id = :me3)
               AND f.status = 'accepted'
               AND u.banned = 0
             ORDER BY is_online DESC, u.username
        ");
        $stmt->execute(['me' => $meId, 'me2' => $meId, 'me3' => $meId]);

        return array_map([self::class, 'shapeUser'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function index($params)
    {
        $me = AuthMiddleware::requireAuth();

        Response::success(['friends' => self::fetchFriends($me['id'])]);
    }

    /* ------------------------------------------------------------------
       GET friends/pending — incoming requests awaiting my response
    ------------------------------------------------------------------ */
    /** Incoming pending requests as data. Reused by BootstrapController. */
    public static function fetchPending(int $meId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.first_name, u.last_name, u.profile_pic
              FROM friends f
              JOIN users u ON u.id = f.user_id
             WHERE f.friend_id = ? AND f.status = 'pending'
             ORDER BY f.id DESC
        ");
        $stmt->execute([$meId]);

        return array_map([self::class, 'shapeUser'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function pending($params)
    {
        $me = AuthMiddleware::requireAuth();

        Response::success(['requests' => self::fetchPending($me['id'])]);
    }

    /* ------------------------------------------------------------------
       GET friends/sent — requests I have sent that are still pending
    ------------------------------------------------------------------ */
    /** Outgoing pending requests as data. Reused by BootstrapController. */
    public static function fetchSent(int $meId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.first_name, u.last_name, u.profile_pic, u.sport
              FROM friends f
              JOIN users u ON u.id = f.friend_id
             WHERE f.user_id = ? AND f.status = 'pending'
             ORDER BY f.id DESC
        ");
        $stmt->execute([$meId]);

        return array_map([self::class, 'shapeUser'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function sent($params)
    {
        $me = AuthMiddleware::requireAuth();

        Response::success(['requests' => self::fetchSent($me['id'])]);
    }

    /* ------------------------------------------------------------------
       GET friends/network — everything the sidebar renders, in one call

       The sidebar needs three lists at once. Fetching them as three requests
       is not just three connections: PHP holds an exclusive lock on the
       session file for the length of a request, so they queue rather than
       overlap. One endpoint, one lock, one round trip.
    ------------------------------------------------------------------ */
    public static function network($params)
    {
        $me = AuthMiddleware::requireAuth();

        $friends = self::fetchFriends($me['id']);

        Response::success([
            'friends'      => $friends,
            'requests'     => self::fetchPending($me['id']),
            'sent'         => self::fetchSent($me['id']),
            'online_count' => count(array_filter($friends, function ($friend) {
                return $friend['is_online'];
            })),
        ]);
    }

    /* ------------------------------------------------------------------
       POST friends/{id}/request
    ------------------------------------------------------------------ */
    public static function request($params)
    {
        global $pdo;

        $me       = AuthMiddleware::requireAuth();
        $targetId = (int)$params['id'];

        if ($targetId === $me['id']) {
            Response::error("You cannot add yourself as a friend", 400);
            return;
        }

        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? AND banned = 0");
        $stmt->execute([$targetId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            Response::error("User not found", 404);
            return;
        }

        // Either direction counts — don't create a mirrored duplicate.
        $stmt = $pdo->prepare("
            SELECT id, status, user_id FROM friends
             WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
             LIMIT 1
        ");
        $stmt->execute([$me['id'], $targetId, $targetId, $me['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // They already asked me — treat this as accepting.
            if ($existing['status'] === 'pending' && (int)$existing['user_id'] === $targetId) {
                $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE id = ?")
                    ->execute([$existing['id']]);

                Response::success(['friendship' => 'accepted']);
                return;
            }

            Response::error(
                $existing['status'] === 'accepted' ? "You are already friends" : "A friend request is already pending",
                409
            );
            return;
        }

        $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')")
            ->execute([$me['id'], $targetId]);

        self::notify($targetId, $me['id'], 'friend_request', "{$me['username']} sent you a friend request");
        ActivityLogger::log($me['id'], 'friend_request', $targetId, 'user');

        Response::success(['friendship' => 'pending_sent']);
    }

    /* ------------------------------------------------------------------
       POST friends/{id}/accept
    ------------------------------------------------------------------ */
    public static function accept($params)
    {
        global $pdo;

        $me          = AuthMiddleware::requireAuth();
        $requesterId = (int)$params['id'];

        $stmt = $pdo->prepare("
            UPDATE friends SET status = 'accepted'
             WHERE user_id = ? AND friend_id = ? AND status = 'pending'
        ");
        $stmt->execute([$requesterId, $me['id']]);

        if ($stmt->rowCount() === 0) {
            Response::error("No pending request from that user", 404);
            return;
        }

        self::notify($requesterId, $me['id'], 'friend_accept', "{$me['username']} accepted your friend request");
        ActivityLogger::log($me['id'], 'friend_accept', $requesterId, 'user');

        Response::success(['friendship' => 'accepted']);
    }

    /* ------------------------------------------------------------------
       POST   friends/{id}/decline  — reject an incoming request
       DELETE friends/{id}          — unfriend, or cancel one I sent
    ------------------------------------------------------------------ */
    public static function decline($params)
    {
        global $pdo;

        $me          = AuthMiddleware::requireAuth();
        $requesterId = (int)$params['id'];

        $stmt = $pdo->prepare("
            DELETE FROM friends
             WHERE user_id = ? AND friend_id = ? AND status = 'pending'
        ");
        $stmt->execute([$requesterId, $me['id']]);

        if ($stmt->rowCount() === 0) {
            Response::error("No pending request from that user", 404);
            return;
        }

        Response::success(['friendship' => 'none']);
    }

    public static function remove($params)
    {
        global $pdo;

        $me       = AuthMiddleware::requireAuth();
        $otherId  = (int)$params['id'];

        $stmt = $pdo->prepare("
            DELETE FROM friends
             WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
        ");
        $stmt->execute([$me['id'], $otherId, $otherId, $me['id']]);

        if ($stmt->rowCount() === 0) {
            Response::error("You are not connected to that user", 404);
            return;
        }

        Response::success(['friendship' => 'none']);
    }

    /* ------------------------------------------------------------------ */

    /**
     * PDO hands back every column as a string. The sidebar branches on
     * is_online and keys rows by id, and "0" is truthy in JavaScript, so the
     * types have to be settled here rather than in the template.
     */
    private static function shapeUser(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['is_online'] = !empty($row['is_online']);
        $row['profile_pic'] = ($row['profile_pic'] ?? '') !== ''
            ? $row['profile_pic']
            : 'assets/img/default-avatar.svg';
        $row['display_name'] = trim(implode(' ', array_filter([
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
        ]))) ?: ($row['username'] ?? 'Member');

        return $row;
    }

    private static function describeRelationship(?string $status, $requesterId, int $meId): string
    {
        if ($status === null)      return 'none';
        if ($status === 'accepted') return 'accepted';
        if ($status === 'blocked')  return 'blocked';

        return ((int)$requesterId === $meId) ? 'pending_sent' : 'pending_received';
    }

    private static function notify(int $userId, int $fromUserId, string $type, string $message): void
    {
        global $pdo;

        $pdo->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, message)
            VALUES (?, ?, ?, ?)
        ")->execute([$userId, $fromUserId, $type, $message]);
    }
}
