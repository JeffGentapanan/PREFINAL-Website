<?php
session_start();
if (!isset($_SESSION['userID'])) {
  header("Location: login.html");
  exit();
}
?>













<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Customer Dashboard</title>
  <style>
    body{ font-family:Arial; background:#f6fff6; }
    .top { text-align:center; }
    .list { width:90%; margin:14px auto; }
    table { border-collapse:collapse; width:100%; }
    th,td { border:1px solid #ddd; padding:8px; text-align:left; }
    a.button { background:#3e9b4a; color:white; padding:6px 10px; text-decoration:none; border-radius:6px; }
  </style>
</head>
<body>
  <div class="top">
    <h1>Welcome Customer</h1>
    <p><a href="logout.php">Logout</a></p>
  </div>
  <div class="list">
    <h3>Available Medicines</h3>
    <?php
    $conn = mysqli_connect("localhost","root","","jjrmeditrack_db");
    $res = mysqli_query($conn, "SELECT * FROM medicines WHERE stockquantity > 0 ORDER BY name");
    echo "<table><tr><th>Name</th><th>Price</th><th>Stock</th></tr>";
    while($r = mysqli_fetch_assoc($res)){
      echo "<tr><td>{$r['name']}</td><td>{$r['price']}</td><td>{$r['stockquantity']}</td></tr>";
    }
    echo "</table>";
    mysqli_close($conn);
    ?>
  </div>
</body>
</html>