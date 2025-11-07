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
```sql
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
```sql
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
```

2. Вставка данных (INSERT)
```sql
-- одиночная вставка
INSERT INTO products (name, price) VALUES ('Apple', 1.20);

-- множественная вставка
INSERT INTO products (name, price) VALUES
  ('Banana', 0.80),
  ('Orange', 1.00),
  ('Grapes', 2.50);

-- вставка с явным указанием столбцов (без created_at)
INSERT INTO orders (product_id, qty) VALUES (1, 10);

-- получить id последней вставки в сессии
SELECT LAST_INSERT_ID();
```

3. Чтение данных (SELECT)
```sql
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
```

4. Обновление и удаление (UPDATE / DELETE)
```sql
-- обновление
UPDATE products SET price = price * 1.10 WHERE name = 'Apple';

-- частичное обновление (только одно поле)
UPDATE products SET name = 'Green Apple' WHERE id = 1;

-- удаление строк
DELETE FROM products WHERE id = 4;
```

5. Изменение структуры (ALTER)
```sql
-- добавить столбец
ALTER TABLE products ADD COLUMN sku VARCHAR(50) AFTER name;

-- изменить тип колонки
ALTER TABLE products MODIFY price DECIMAL(12,2) NOT NULL;

-- удалить колонку
ALTER TABLE products DROP COLUMN sku;

-- добавить индекс
ALTER TABLE products ADD INDEX idx_price (price);
```

6. Индексы и их использование
```sql
-- создать индекс (если не сделали через ALTER)
CREATE INDEX idx_name ON products(name);

-- удалить индекс
DROP INDEX idx_name ON products;

-- посмотреть существующие индексы
SHOW INDEX FROM products;
```

7. Транзакции, COMMIT, ROLLBACK и уровни изоляции
```sql
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
```

Демонстрация блокировок:
В двух сессиях:

Сессия A:
```sql
START TRANSACTION;
SELECT * FROM products WHERE id = 1 FOR UPDATE; -- захватит эксклюзивную блокировку
```

Сессия B попытается:
```sql
UPDATE products SET price = price + 0.1 WHERE id = 1; -- будет ждать, пока транзакция A не закоммитит/роллбекнет
```

9. Bulk load — LOAD DATA INFILE (локальный вариант)

В Docker-среде проще загружать через mysql client, либо использовать LOAD DATA LOCAL INFILE.

Пример CSV products.csv:
```csv
name,price
Pear,1.10
Mango,2.00
```

SQL:
```sql
LOAD DATA LOCAL INFILE '/tmp/products.csv'
INTO TABLE products
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(name, price);
```

10. Удаление таблиц и очистка окружения
```sql
-- удалить таблицу
DROP TABLE IF EXISTS orders;

-- удалить БД (в демонстрации осторожно)
DROP DATABASE IF EXISTS tempdb;
```


# Резервное копирование

1. Проверить наличие данных

Перед началом убедимся, что в базе demo есть таблицы и данные.
```sql
USE demo;
SHOW TABLES;
SELECT * FROM users LIMIT 5;
```

2. Полное резервное копирование (mysqldump)
```bash
# Выполнить внутри контейнера master
mysqldump -u root -p demo > /backup/demo_full.sql
```

Либо в самой ОС:
```bash
mysqldump -h 127.0.0.1 -P 3307 -u root -p demo > backup/demo_full.sql
```
Клиент базы данных должен быть установлен:
```bash
apt install -y mariadb-client
```

3. Восстановление базы данных из резервной копии

Удалим и восстановим базу demo из файла резервной копии.
```bash
mysql -u root -p -e "DROP DATABASE demo;"
mysql -u root -p -e "CREATE DATABASE demo;"
mysql -u root -p demo < /backup/demo_full.sql

mysql -h 127.0.0.1 -P 3307 -u root -p demo < backup/demo_full.sql
```

После выполнения этих команд база будет полностью восстановлена в том состоянии, в котором была при создании бэкапа.

4. Проверка восстановления
```sql
USE demo;
SHOW TABLES;
SELECT COUNT(*) FROM users;
```

5. Автоматизация резервного копирования (опционально)

Можно создать cron-задачу для ежедневного бэкапа.

Пример простого скрипта /usr/local/bin/backup_demo.sh:
```bash
#!/bin/bash
DATE=$(date +%F_%H-%M)
mysqldump -u root -pPassword123 demo > /backup/demo_$DATE.sql
find /backup -type f -mtime +7 -delete   # удаляем бэкапы старше 7 дней
```

Добавляем в cron:
```bash
echo "0 3 * * * /usr/local/bin/backup_demo.sh" >> /etc/crontab
```

# Репликация

Подключаемся к masterу:
```bash
docker exec -it mariadb_master mariadb -u root -p
```

Выполняем SQL:
```sql
-- создаём пользователя репликации
CREATE USER 'replicator'@'%' IDENTIFIED BY 'ReplPass123';

-- выдаём права на чтение бинарных логов
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'%';

-- применяем привилегии
FLUSH PRIVILEGES;

-- проверяем статус бинарных логов
SHOW MASTER STATUS;
```

Пример вывода:
```sql
File: mysql-bin.000003
Position: 456
```

>Эти значения (File и Position) понадобятся при настройке slave.

## Настройка slave

Подключаемся к slave:
```bash
docker exec -it mariadb_slave mariadb -u root -p
```

Выполняем SQL:
```sql
-- указываем параметры masterа
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
```

> Важно, чтобы поля Slave_IO_Running и Slave_SQL_Running были Yes. Это значит, что репликация установлена и работает корректно.

## Проверка работы репликации

На master:
```bash
docker exec -it mariadb_master mariadb -u root -p demo
```
```sql
CREATE TABLE test_repl (id INT PRIMARY KEY, msg VARCHAR(50));
INSERT INTO test_repl VALUES (1, 'Replication works!');
```

Теперь на slave:
```bash
docker exec -it mariadb_slave mariadb -u root -p demo
```
```sql
SELECT * FROM test_repl;
```

Ожидаемый результат:
```sql
+----+-------------------+
| id | msg               |
+----+-------------------+
|  1 | Replication works!|
+----+-------------------+
```

> Репликация работает!

## Проверка режима read_only

На slave:
```sql
INSERT INTO test_repl VALUES (2, 'Slave insert test');
```

Результат:
```sql
ERROR 1290 (HY000): The MySQL server is running with the --read-only option so it cannot execute this statement
```

> Это ожидаемое поведение — на ведомом сервере нельзя вносить изменения вручную.

Остановка и запуск репликации вручную:
```sql
STOP SLAVE;
START SLAVE;
SHOW SLAVE STATUS\G
```
# SQL-инъекции

Открыть [веб-страницу](http://localhost:8000/vulnerable.php).

2. Быстрая настройка логирования (чтобы видеть сформированные SQL)

Включаем general_log (лог в таблицу), делаем запросы, затем выключаем:
```bash
# выполнить на master
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SET GLOBAL log_output='TABLE'; SET GLOBAL general_log = 'ON';"
# потом после демонстрации
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SET GLOBAL general_log = 'OFF';"
```

Посмотреть недавние запросы:
```bash
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SELECT event_time, user_host, argument FROM mysql.general_log WHERE command_type='Query' ORDER BY event_time DESC LIMIT 50\G"
```

## Демонстрация 1 — обход авторизации (login bypass)

Что показываем: уязвимый vulnerable.php конкатенирует ввод — простой bypass.

Открой [страницу](http://localhost:8000/vulnerable.php)


Введи:
```
username: admin

password: ' OR '1'='1
```
Нажми Login.

Что происходит:

На странице видно поле Executed SQL, например:
```sql
SELECT id, username, role FROM users WHERE username='admin' AND password='' OR '1'='1' LIMIT 1
```

Сервер вернёт строку(и) — обход авторизации.

Пояснение: '1'='1' — всегда истинно, условие WHERE удовлетворяется.

Просмотр в логе:
```bash
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SELECT event_time, argument FROM mysql.general_log WHERE argument LIKE '%SELECT id, username, role FROM users%' ORDER BY event_time DESC LIMIT 5\G"
```

## Демонстрация 2 — экранирование хвоста запроса (комментарии)

Payload в поле password:
```
' OR '1'='1' -- 
```

`--` превращает остаток запроса в комментарий (MySQL-style).

## Демонстрация 3 — UNION-based (чтение другой таблицы)

Цель: показать, что при совпадении числа и типов колонок можно вытянуть другие данные.

Пример (теоретический / осторожно): если уязвимый запрос имеет 3 колонки (id, username, role), можно попробовать:
```sql
' UNION SELECT id, username, password FROM users -- 
```

В vulnerable.php это может вернуть столбец password (если типы совпадут).

## Демонстрация 4 — error-based exfiltration (ExtractValue)

Что показывает: как текст ошибки может содержать данные (имя БД, значения).

Запусти в mysql (master):
```bash
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "USE demo; SELECT ExtractValue(1, CONCAT(0x5C, (SELECT DATABASE())));"
```

Ожидаемый результат: ошибка типа
```sql
Error Code: 1105. XPATH syntax error: '\demo'
```

> Злоумышленник формирует выражение, которое вызывает ошибку — и в тексте ошибки попадает значение подзапроса.

## Логи и доказательства (как увидеть, что инъекция сработала)

Включённый general_log покажет сформированный SQL.

Выполни поиск в логе по части запроса:
```bash
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SELECT event_time, argument FROM mysql.general_log WHERE argument LIKE '%OR %1%=%1%' ORDER BY event_time DESC LIMIT 20\G"
```
## Как фиксировать — контрмеры

* Prepared statements (параметризованные запросы) — главная мера.

Пример на PHP (реализовано в safe.php):
```php
$stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u AND password = :p LIMIT 1');
$stmt->execute([':u' => $username, ':p' => $password]);
```

* Хеширование паролей — не хранить пароли в открытом виде; использовать `password_hash()/password_verify() (bcrypt/argon2)`.

* Whitelist/валидация входа — для ID/чисел использовать строгую проверку (`ctype_digit`, приведение в int); для строк — разрешать только ожидаемые символы.

* Минимальные привилегии — учётная запись веб-приложения должна иметь лишь нужные привилегии (SELECT/INSERT/...), никогда DROP/GRANT/SUPER. Демонстрируй на примере webapp.

* Неинформативные сообщения об ошибках — в проде скрывать текст ошибок от клиента, логировать их серверно.

* WAF и IPS — дополнительный уровень защиты, но не замена правильной разработки.

* Аудит и мониторинг — логировать необычные запросы, уведомления при всплесках.

## Полезные команды — копировать/вставлять

Включить логирование:
```bash
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SET GLOBAL log_output='TABLE'; SET GLOBAL general_log='ON';"
```

Посмотреть логи (последние 20):
```bash
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "SELECT event_time,user_host,argument FROM mysql.general_log WHERE command_type='Query' ORDER BY event_time DESC LIMIT 20\G"
```

Пример error-based (ExtractValue):
```bash
mysql -h 127.0.0.1 -P 3307 -u root -prootpass -e "USE demo; SELECT ExtractValue(1, CONCAT(0x5C,(SELECT DATABASE())));"
```
