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
        id AS index,
        TO_CHAR(
            UPDATED_AT AT TIME ZONE 'Asia/Tokyo',
            'FMMM/DD FMHH24:MI:SS'
        ) AS updated_at,
        rawjson->'enka'->>'uid' AS uid,
        rawjson->'enka'->'playerInfo'->>'nickname' AS nickname,
        rawjson->'enka'->'playerInfo'->>'signature' AS signature,
        rawjson->'hoyolab'->>'current_resin' AS CURRENT_RESIN,
        rawjson->'hoyolab'->>'max_resin' AS MAX_RESIN,
        RTRIM(
            TO_CHAR(
                ((rawjson->'hoyolab'->>'current_resin')::NUMERIC / (rawjson->'hoyolab'->>'max_resin')::NUMERIC * 100),
                'FM990.99'
            ),
            '.'
        ) || '%' AS CURRENT_RESIN_PERCENT,
        -- 24時間以上の回復時間にも対応する場合（日・時間・分）
        TO_CHAR(
            ((rawjson->'hoyolab'->>'resin_recovery_time')::BIGINT * '1 second'::INTERVAL),
            'FMDD"d "HH24:MI:SS'
        ) AS RESIN_RECOVERY_TIME
    FROM
        genshin_status_log;
