<?php
// index.php - простая навигация
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>lab_db demo</title></head>
<body>
  <h2>lab_db — demo pages</h2>
  <ul>
    <li><a href="/vulnerable.php">Vulnerable (unsafe) — demo SQLi</a></li>
    <li><a href="/safe.php">Safe — prepared statements</a></li>
  </ul>
  <p>DB master: <strong>127.0.0.1:3307</strong> (external client)</p>
</body>
</html>

