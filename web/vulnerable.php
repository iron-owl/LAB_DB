<?php
// vulnerable.php - intentionally vulnerable (for lab only)
$mysqli = new mysqli('mariadb_master', 'root', 'rootpass', 'demo');
if ($mysqli->connect_errno) {
    echo "DB connect error: " . htmlspecialchars($mysqli->connect_error);
    exit;
}

$username = $_GET['username'] ?? '';
$password = $_GET['password'] ?? '';

// Unsafe concatenation - SQLi demonstration
$sql = "SELECT id, username, role FROM users WHERE username='" . $username . "' AND password='" . $password . "' LIMIT 1";

$result = $mysqli->query($sql);
$error = $result === false ? $mysqli->error : null;
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Vulnerable</title></head>
<body>
  <h3>Vulnerable login (unsafe)</h3>
  <form method="get" action="/vulnerable.php">
    Username: <input name="username" value="<?php echo htmlspecialchars($username); ?>"><br>
    Password: <input name="password" value="<?php echo htmlspecialchars($password); ?>"><br>
    <input type="submit" value="Login">
  </form>

  <h4>Executed SQL</h4>
  <pre><?php echo htmlspecialchars($sql); ?></pre>

  <?php if ($error): ?>
    <h4>DB Error</h4><pre><?php echo htmlspecialchars($error); ?></pre>
  <?php endif; ?>

  <h4>Result</h4>
  <?php
  if ($result && $row = $result->fetch_assoc()) {
      echo '<p>Authenticated as: ' . htmlspecialchars($row['username']) . ' (role: ' . htmlspecialchars($row['role']) . ')</p>';
  } else {
      echo '<p>No user found or invalid credentials.</p>';
  }
  ?>

  <p><a href="/">Back</a></p>
</body>
</html>

