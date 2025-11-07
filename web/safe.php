<?php
// safe.php - prepared statements (PDO)
try {
    $pdo = new PDO('mysql:host=mariadb_master;dbname=demo;charset=utf8mb4', 'webapp', 'webapppass', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo 'DB connect error';
    exit;
}

$username = $_GET['username'] ?? '';
$password = $_GET['password'] ?? '';

$stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u AND password = :p LIMIT 1');
$stmt->execute([':u' => $username, ':p' => $password]);
$row = $stmt->fetch();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Safe</title></head>
<body>
  <h3>Safe login (prepared statements)</h3>
  <form method="get" action="/safe.php">
    Username: <input name="username" value="<?php echo htmlspecialchars($username); ?>"><br>
    Password: <input name="password" value="<?php echo htmlspecialchars($password); ?>"><br>
    <input type="submit" value="Login">
  </form>

  <h4>Result</h4>
  <?php
  if ($row) {
      echo '<p>Authenticated as: ' . htmlspecialchars($row['username']) . ' (role: ' . htmlspecialchars($row['role']) . ')</p>';
  } else {
      echo '<p>No user found or invalid credentials.</p>';
  }
  ?>

  <p><a href="/">Back</a></p>
</body>
</html>

