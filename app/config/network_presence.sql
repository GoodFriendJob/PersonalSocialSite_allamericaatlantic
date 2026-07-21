CREATE TABLE IF NOT EXISTS user_presence (
    user_id INT NOT NULL,
    last_activity DATETIME NOT NULL,
    PRIMARY KEY (user_id),
    KEY idx_user_presence_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
