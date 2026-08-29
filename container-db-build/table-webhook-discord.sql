-- FUNCTION
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';
-- TABLE
DROP VIEW IF EXISTS discord_webhooks_log_view;
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
CREATE OR REPLACE VIEW discord_webhooks_log_view AS
    SELECT
        d.id index,
        d.created_at,
        d.updated_at,
        d.rawjson->'id' webhookid
    FROM
        discord_webhooks_log d;
