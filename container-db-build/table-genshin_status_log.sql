-- FUNCTION
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';
-- TABLE
DROP VIEW IF EXISTS genshin_status_log_view;
DROP TABLE IF EXISTS genshin_status_log;
CREATE TABLE genshin_status_log (
    id SERIAL PRIMARY KEY,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    rawjson JSONB NOT NULL,
    deleted BOOLEAN default false
);
-- TRIGGER
CREATE OR REPLACE TRIGGER update_genshin_status_log_updated_at
    BEFORE UPDATE ON genshin_status_log
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
-- VIEW
CREATE OR REPLACE VIEW genshin_status_log_view AS
    SELECT
        g.id index,
        g.created_at,
        g.updated_at,
        g.rawjson->'enka'->>'uid' uid
    FROM
        genshin_status_log g;
