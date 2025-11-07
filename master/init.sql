-- init.sql: выполняется при первом запуске master
CREATE DATABASE IF NOT EXISTS demo;
USE demo;

-- таблица users (для аутентификации)
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(20) DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password, role) VALUES
('admin','adminpass','admin'),
('alice','alicepass','user'),
('bob','bobpass','user');

-- таблица с логами для partition demo
DROP TABLE IF EXISTS userslogs;
CREATE TABLE userslogs (
  username VARCHAR(50) NOT NULL,
  logdata BLOB NOT NULL,
  created DATETIME NOT NULL,
  PRIMARY KEY (username, created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY RANGE ( YEAR(created) ) (
  PARTITION p2018 VALUES LESS THAN (2019),
  PARTITION p2019 VALUES LESS THAN (2020),
  PARTITION p2020 VALUES LESS THAN (2021),
  PARTITION pmax VALUES LESS THAN MAXVALUE
);

-- таблица для LIST partition demo
DROP TABLE IF EXISTS serverlogs;
CREATE TABLE serverlogs (
  serverid INT NOT NULL,
  logdata BLOB NOT NULL,
  created DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY LIST ( serverid ) (
  PARTITION server_east VALUES IN (1, 43, 65, 73),
  PARTITION server_west VALUES IN (5, 642, 196, 22),
  PARTITION server_other VALUES IN (0)
);

INSERT INTO userslogs (username, logdata, created)
VALUES ('alice', 'first log', '2018-05-01 12:00:00'),
       ('bob',   'another', '2019-06-01 08:00:00');

INSERT INTO serverlogs (serverid, logdata, created)
VALUES (1, 'east1', NOW()), (5, 'west1', NOW());

-- таблица для AES demo (BLOB)
DROP TABLE IF EXISTS secret_data;
CREATE TABLE secret_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  secret_blob BLOB
);

INSERT INTO secret_data (name, secret_blob) VALUES
('token1', AES_ENCRYPT('supersecretvalue', 'StrongPasswordDemo')),
('token2', AES_ENCRYPT('anothersecret', 'StrongPasswordDemo'));

-- Создадим пользователя веб-приложения с минимальными правами (правильный подход)
CREATE USER IF NOT EXISTS 'webapp'@'%' IDENTIFIED BY 'webapppass';
GRANT SELECT, INSERT ON demo.users TO 'webapp'@'%';
GRANT SELECT ON demo.secret_data TO 'webapp'@'%';
GRANT SELECT ON demo.userslogs TO 'webapp'@'%';
FLUSH PRIVILEGES;

-- Создадим пользователя для репликации
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'replpass';
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%';
FLUSH PRIVILEGES;

