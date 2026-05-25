-- Migration: Remove api_token column from utilisateurs table
-- Date: 2026-05-25

ALTER TABLE utilisateurs DROP COLUMN IF EXISTS api_token;