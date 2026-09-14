UPDATE users
SET password = '$2y$12$oeUlbhW0FR39gdyjf2c.B.oco.hGTv.uEH8C22V/bFD5qtsUDT4B6'
WHERE email = 'britney@optimizedstrategicsolutions.com';

INSERT INTO users (name, email, password, created_at, updated_at)
SELECT 'Rupak', 'rupak@optimizedstrategicsolutions.com', '$2y$12$oeUlbhW0FR39gdyjf2c.B.oco.hGTv.uEH8C22V/bFD5qtsUDT4B6', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'rupak@optimizedstrategicsolutions.com');

SELECT id, email, password, LENGTH(password) AS pw_length, updated_at FROM users;
