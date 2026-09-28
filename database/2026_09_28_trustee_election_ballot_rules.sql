-- قواعد رأی‌گیری انتخابات هیئت امنا
-- برای دیتابیسی که migration قبلی انتخابات روی آن اجرا شده است.

ALTER TABLE election_candidates
    ADD COLUMN candidate_role VARCHAR(20) NOT NULL DEFAULT 'trustee' AFTER election_id,
    ADD KEY idx_candidates_role (election_id, candidate_role);

ALTER TABLE election_votes
    DROP INDEX uq_election_resident_vote,
    ADD UNIQUE KEY uq_election_resident_candidate (election_id, resident_id, candidate_id);
