<?php
session_start();
ob_start(); // Start output buffering

// Security Check: Only logged-in staff can access
if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'staff') {
    header("Location: login.html");
    ob_end_flush();
    exit();
}

$message = '';
$userID = $_SESSION['userID'];

// --- DATABASE CONNECTION ---
$conn = mysqli_connect("localhost", "root", "", "jjrmeditrack_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Validation Checks
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = '<div class="alert error">Please fill in all fields.</div>';
    } elseif ($new_password !== $confirm_password) {
        $message = '<div class="alert error">New password and confirmation password do not match.</div>';
    } elseif (strlen($new_password) < 6) {
        $message = '<div class="alert error">New password must be at least 6 characters long.</div>';
    } else {
        // 2. Fetch Stored Current Password Hash
        $stmt = $conn->prepare("SELECT password FROM users WHERE userID = ?");
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($current_password, $user['password'])) {
            // 3. Current Password is Correct - Update New Password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE userID = ?");
            $stmt_update->bind_param("si", $hashed_password, $userID);
            
            if ($stmt_update->execute()) {
                // Success: Display confirmation script similar to the image
                $message = '<script>
                    alert("Password updated successfully!");
                    window.location.href="settings.php"; 
                </script>';
            } else {
                $message = '<div class="alert error">Error updating password: ' . $conn->error . '</div>';
            }
            $stmt_update->close();

        } else {
            // 4. Current Password Incorrect
            $message = '<div class="alert error">The current password you entered is incorrect.</div>';
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Change Password</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* --- Common Styles --- */
body { font-family: Arial; margin: 0; background: #f6fff6; color: #222; }
header { background: #47d16b; padding: 14px 18px; font-weight: bold; font-size: 20px; color: #000; display: flex; align-items: center; justify-content: space-between; }
.header-btn-back { cursor: pointer; padding: 6px 10px; border-radius: 6px; border: 1px solid #1e7d34; background: #3e9b4a; color: #fff; text-decoration: none; font-size: 14px; font-weight: normal; white-space: nowrap; }
.header-btn-back:hover { background: #2f7d38; }
.header-title { flex-grow: 1; text-align: center; color: #000; font-weight: bold; font-size: 20px; }
.container { width: 95%; max-width: 450px; margin: 18px auto; text-align: center; }
h1 { color: #0f8e33; margin-top: 0; font-size: 24px; }

/* --- Form Styles --- */
.form-group { margin-bottom: 20px; text-align: left; }
.form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
.form-group input { 
    width: 100%; 
    padding: 12px 15px; 
    border-radius: 10px; 
    border: 1px solid #ccc; 
    font-size: 16px; 
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.05); 
    box-sizing: border-box; 
    background: #e6ffe6; 
}
.button-group { display: flex; justify-content: space-between; gap: 15px; margin-top: 30px; }
.btn { flex: 1; padding: 12px; border: none; border-radius: 25px; font-weight: bold; cursor: pointer; transition: background 0.2s; font-size: 16px; text-decoration: none; text-align: center; }
.btn-save { background: #d4f7d4; color: #0f8e33; border: 1px solid #0f8e33; }
.btn-save:hover { background: #c3e6c3; }
.btn-cancel { background: #ffcdd2; color: #d42d2d; border: 1px solid #d42d2d; }
.btn-cancel:hover { background: #ffb3b8; }

/* --- Alert Styles --- */
.alert { padding: 10px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; text-align: left;}
.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* Bottom Nav (If still desired, though not strictly needed on a utility page) */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#47d16b;display:flex;justify-content:space-around;padding:10px 0;}
.bottom-nav a{color:white;text-decoration:none;font-size:14px;text-align:center;}
.bottom-nav a.active { font-weight: bold; color: #f7ff00; }
</style>
</head>
<body>

<header>
    <a href="settings.php" class="header-btn-back">⬅️ Back</a>
    <span class="header-title">Change Password</span>
    <span></span>
</header>

<div class="container">
    <h1>Update Security Credentials</h1>

    <?php echo $message; // Display status/error message or success script ?>

    <form method="POST">
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        
        <div class="form-group">
            <label for="new_password">New Password (Min 6 characters)</label>
            <input type="password" id="new_password" name="new_password" required minlength="6">
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
        </div>

        <div class="button-group">
            <button type="submit" class="btn btn-save">Update Password</button>
            <a href="settings.php" class="btn btn-cancel">Cancel</a>
        </div>
    </form>
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
<?php ob_end_flush(); ?>