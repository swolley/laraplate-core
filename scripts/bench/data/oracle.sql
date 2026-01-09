-- 10k utenti
INSERT INTO users (email)
SELECT 'user' || LEVEL || '@example.com', CURRENT_TIMESTAMP
FROM dual
CONNECT BY LEVEL <= 10000;

-- 100k post
INSERT INTO posts (user_id, title, body)
SELECT MOD(DBMS_RANDOM.VALUE(1,10000),10000)+1,
       'Title ' || LEVEL,
       'Body ' || LEVEL,
       CURRENT_TIMESTAMP,
       1
FROM dual
CONNECT BY LEVEL <= 100000;

-- 500k commenti
INSERT INTO comments (post_id, body, created_at)
SELECT MOD(DBMS_RANDOM.VALUE(1,100000),100000)+1,
       'Comment ' || LEVEL,
       CURRENT_TIMESTAMP
FROM dual
CONNECT BY LEVEL <= 500000;