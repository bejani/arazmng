-- انتخابات هیئت امنا
-- این فایل را یک‌بار روی دیتابیس پروژه اجرا کنید.

CREATE TABLE IF NOT EXISTS elections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    results_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_elections_status (status),
    KEY idx_elections_published (results_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS election_candidates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    election_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    mobile VARCHAR(50) NULL,
    national_id VARCHAR(50) NULL,
    bio TEXT NULL,
    photo_url VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_candidates_election (election_id),
    CONSTRAINT fk_candidates_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS election_votes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    election_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    resident_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_election_resident_vote (election_id, resident_id),
    KEY idx_votes_candidate (candidate_id),
    CONSTRAINT fk_votes_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_votes_candidate FOREIGN KEY (candidate_id) REFERENCES election_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
