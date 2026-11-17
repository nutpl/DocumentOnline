-- Allow only one active authenticated session per user account.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS active_session_token_hash CHAR(64) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS active_session_at DATETIME NULL AFTER active_session_token_hash,
    ADD KEY IF NOT EXISTS idx_users_active_session (active_session_token_hash);

