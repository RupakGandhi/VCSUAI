INSERT INTO users (name, email, password, created_at, updated_at)
VALUES
  ('Britney', 'britney@optimizedstrategicsolutions.com', '$2y$12$4BcZW4NAwCyUBd6awqD0X.SzwjF2ELJDImCsyObbkg4Oj.wxNKL7K', NOW(), NOW()),
  ('Rupak', 'rupak@optimizedstrategicsolutions.com', '$2y$12$4BcZW4NAwCyUBd6awqD0X.SzwjF2ELJDImCsyObbkg4Oj.wxNKL7K', NOW(), NOW())
ON DUPLICATE KEY UPDATE password = VALUES(password);
