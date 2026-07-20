CREATE TABLE IF NOT EXISTS community_ratings (
    target_type VARCHAR(20) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (target_type, target_id, user_id),
    KEY idx_community_ratings_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
