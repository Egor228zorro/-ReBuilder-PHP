-- Для оптимистичной блокировки
ALTER TABLE workouts ADD COLUMN IF NOT EXISTS version INTEGER DEFAULT 1;
ALTER TABLE exercises ADD COLUMN IF NOT EXISTS version INTEGER DEFAULT 1;

-- Для мягкого удаления
ALTER TABLE workouts ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE exercises ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE tts_jobs ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;

-- Индексы для мягкого удаления
CREATE INDEX IF NOT EXISTS idx_workouts_deleted_at ON workouts(deleted_at) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_exercises_deleted_at ON exercises(deleted_at) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_tts_jobs_deleted_at ON tts_jobs(deleted_at) WHERE deleted_at IS NULL;