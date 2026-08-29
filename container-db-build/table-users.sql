-- FUNCTION
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';
-- TABLE
DROP VIEW IF EXISTS users_view;
DROP TABLE IF EXISTS users;
DROP VIEW IF EXISTS discord_webhooks_log_view;
DROP TABLE IF EXISTS discord_webhooks_log;
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE discord_webhooks_log (
    id SERIAL PRIMARY KEY,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    rawjson JSONB NOT NULL
);
-- TRIGGER
CREATE OR REPLACE TRIGGER update_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
-- VIEW
CREATE OR REPLACE VIEW users_view AS
    SELECT
        u.id,
        u.created_at,
        u.updated_at
    FROM
        users u
CREATE OR REPLACE VIEW discord_webhooks_log_view AS
    SELECT
        d.id index,
        d.created_at,
        d.updated_at,
        d.rawjson->'id' webhookid
    FROM
        discord_webhooks_log d;
-- BEGIN;
-- DROP VIEW IF EXISTS USERS_CURRENT_RESIN_LOG_VIEW;
--
-- CREATE OR REPLACE VIEW USERS_CURRENT_RESIN_LOG_VIEW AS
-- SELECT
--     TO_CHAR(
--         UPDATED_AT AT TIME ZONE 'Asia/Tokyo',
--         'FMMM/DD FMHH24:MI:SS'
--     ) AS UPDATED_AT,
--     ENKA_PLAYERINFO_NICKNAME AS PLAYER_NAME,
--     ENKA_PLAYERINFO_SIGNATURE AS PLAYER_SIGNATURE,
--     ENKA_UID,
--     HOYOLAB_CURRENT_RESIN AS CURRENT_RESIN,
--     HOYOLAB_MAX_RESIN AS MAX_RESIN,
--     RTRIM(
--         TO_CHAR(
--             (HOYOLAB_CURRENT_RESIN::NUMERIC / HOYOLAB_MAX_RESIN * 100),
--             'FM990.99'
--         ),
--         '.'
--     ) || '%' AS CURRENT_RESIN_PERCENT,
--     TO_CHAR(
--         (HOYOLAB_RESIN_RECOVERY_TIME * '1 second'::INTERVAL),
--         'FMHH24:MI:SS'
--     ) AS RESIN_RECOVERY_TIME
-- FROM
--     PUBLIC.USERS
-- WHERE
--     ENKA_PLAYERINFO_NICKNAME IS NOT NULL
--     AND ENKA_UID IS NOT NULL
--     AND HOYOLAB_CURRENT_RESIN IS NOT NULL
--     AND HOYOLAB_MAX_RESIN IS NOT NULL
--     AND HOYOLAB_RESIN_RECOVERY_TIME IS NOT NULL
-- ORDER BY
--     ID DESC;
--
-- COMMIT;
-- SELECT * FROM USERS_CURRENT_RESIN_LOG_VIEW;
