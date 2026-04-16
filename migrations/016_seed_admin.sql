-- Migration 016: Seed default admin user
-- Username: admin
-- Plaintext password: ugly-koyot-sings
-- Hash generated via PHP password_hash(..., PASSWORD_BCRYPT) — verified by password_verify() in AuthController.
-- Idempotent: ON DUPLICATE KEY UPDATE refreshes the password hash on re-run.

INSERT INTO admin_users (username, password)
VALUES ('admin', '$2y$12$lwUUagdY9WCWuMlDaSUfAO8soIWWmfrpu.0ObflNuvk9zFvdpQWgm')
ON DUPLICATE KEY UPDATE password = VALUES(password);
