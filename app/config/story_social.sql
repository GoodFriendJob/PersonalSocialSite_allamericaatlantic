CREATE TABLE IF NOT EXISTS story_likes (
    story_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (story_id, user_id),
    KEY idx_story_likes_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS story_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    story_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    comment VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_story_comments_story (story_id, created_at),
    KEY idx_story_comments_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
