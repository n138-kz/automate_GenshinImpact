-- FUNCTION
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';
-- TABLE
DROP TABLE IF EXISTS discord_webhooks_log;
CREATE TABLE discord_webhooks_log (
    id SERIAL PRIMARY KEY,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    rawjson JSONB NOT NULL,
    deleted BOOLEAN default false
);
-- TRIGGER
CREATE OR REPLACE TRIGGER update_discord_webhooks_log_updated_at
    BEFORE UPDATE ON discord_webhooks_log
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
-- VIEW
DROP VIEW IF EXISTS discord_webhooks_log_view;
CREATE OR REPLACE VIEW discord_webhooks_log_view AS
    SELECT
        id index,
        TO_CHAR(
            UPDATED_AT AT TIME ZONE 'Asia/Tokyo',
            'FMMM/DD FMHH24:MI:SS'
        ) AS updated_at,
        rawjson->>'id' webhookid,
        rawjson->>'url' webhookurl,
        deleted
    FROM
        discord_webhooks_log;
