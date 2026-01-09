-- 10k utenti
INSERT INTO users (email)
SELECT 'user' || i || '@example.com'
FROM generate_series(1,10000) AS s(i);

-- 100k post
INSERT INTO posts (user_id, title, body)
SELECT (random()*9999+1)::int,
       'Title ' || i,
       'Body ' || i
FROM generate_series(1,100000) AS s(i);

-- 500k commenti
INSERT INTO comments (post_id, body)
SELECT (random()*99999+1)::int,
       'Comment ' || i
FROM generate_series(1,500000) AS s(i);