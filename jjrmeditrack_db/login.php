<?php
session_start();

// Connect to database
$conn = mysqli_connect("localhost", "root", "", "jjrmeditrack_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Collect inputs safely
$email = $_POST['email'];
$password = $_POST['password'];

// Find user by email (no password here!)
$sql = "SELECT * FROM users WHERE email='$email' LIMIT 1";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);

    // ✅ Verify hashed password correctly
    if (password_verify($password, $row['password'])) {
        // Store session data
        $_SESSION['userID'] = $row['userID'];
        $_SESSION['role'] = $row['role'];

        // Redirect based on role
        if ($row['role'] == 'staff') {
            header("Location: staff_dashboard.php");
        } else {
            header("Location: customer_dashboard.php");
        }
        exit();
    } else {
        echo "<script>alert('Incorrect password! Please try again.'); window.location='login.html';</script>";
    }
} else {
    echo "<script>alert('Email not found! Please register first.'); window.location='login.html';</script>";
}

mysqli_close($conn);
?>
