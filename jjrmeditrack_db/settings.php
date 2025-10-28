<?php
session_start();
// Security Check
if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'staff') {
    header("Location: login.html");
    exit();
}

$firstName = $_SESSION['firstName'] ?? 'Staff';
$lastName = $_SESSION['lastName'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Settings & Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* Reusing common styles */
body { font-family: Arial; margin: 0; background: #f6fff6; color: #222; }
header { background: #47d16b; padding: 14px 18px; font-weight: bold; font-size: 20px; color: #000; display: flex; align-items: center; justify-content: space-between; }
.header-btn-back { cursor: pointer; padding: 6px 10px; border-radius: 6px; border: 1px solid #1e7d34; background: #3e9b4a; color: #fff; text-decoration: none; font-size: 14px; font-weight: normal; white-space: nowrap; }
.header-btn-back:hover { background: #2f7d38; }
.header-title { flex-grow: 1; text-align: center; color: #000; font-weight: bold; font-size: 20px; }
.container { width: 95%; max-width: 450px; margin: 18px auto; text-align: center; }
h1 { color: #0f8e33; margin-top: 0; font-size: 24px; }

/* Settings specific styles */
.setting-group {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-top: 20px;
    text-align: left;
}
.setting-group h2 {
    color: #0f8e33;
    border-bottom: 2px solid #d4f7d4;
    padding-bottom: 10px;
    margin-bottom: 15px;
    font-size: 18px;
}
.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}
.setting-item:last-child {
    border-bottom: none;
}
.logout-btn {
    display: block;
    width: 100%;
    padding: 12px;
    margin-top: 30px;
    border: none;
    border-radius: 25px;
    font-weight: bold;
    cursor: pointer;
    background: #d42d2d; /* Red for destructive/critical action */
    color: white;
    text-decoration: none;
    text-align: center;
    transition: background 0.2s;
}
.logout-btn:hover {
    background: #b52222;
}

/* Bottom Nav */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#47d16b;display:flex;justify-content:space-around;padding:10px 0;}
.bottom-nav a{color:white;text-decoration:none;font-size:14px;text-align:center;}
.bottom-nav a.active { font-weight: bold; color: #f7ff00; }
</style>
</head>
<body>

<header>
    <a href="staff_dashboard.php" class="header-btn-back">⬅️ Back</a>
    <span class="header-title">Settings & Profile</span>
    <span></span>
</header>

<div class="container">
    <h1>Account Information</h1>

    <div class="setting-group">
        <h2>Profile Details</h2>
        
        <div class="setting-item">
            <strong>Name:</strong>
            <span><?= htmlspecialchars($firstName . ' ' . $lastName) ?></span>
        </div>
        
        <div class="setting-item">
            <strong>User ID:</strong>
            <span><?= htmlspecialchars($_SESSION['userID']) ?></span>
        </div>
        
        <div class="setting-item">
            <strong>Role:</strong>
            <span style="color: #0f8e33; font-weight: bold;"><?= htmlspecialchars($_SESSION['role']) ?></span>
        </div>
    </div>
    
    <div class="setting-group">
        <h2>System Actions</h2>
        
        <div class="setting-item">
            <strong>Change Password:</strong>
            <span><a href="change_password.php" style="color: #0f8e33;">Update</a></span>
        </div>
        
        <a href="logout.php" class="logout-btn">
            <span style="font-size: 16px;">🚪 LOGOUT</span>
        </a>
    </div>

</div>

<div class="bottom-nav">
    <a href="staff_dashboard.php">🏠 Home</a>
    <a href="medicine_list.php">💊 Medicine</a>
    <a href="staff_list.php">👨‍⚕️ Staff</a>
    <a href="sales_record.php">📈 Sales</a>
    <a href="settings.php" class="active">⚙️ Settings</a>
</div>

</body>
</html>