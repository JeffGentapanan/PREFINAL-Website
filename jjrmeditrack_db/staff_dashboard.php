<?php
session_start();
// Security Check
if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'staff') {
    header("Location: login.html");
    exit();
}
// Assume $userID is available for use if needed later
$userID = $_SESSION['userID'];

// --- DATABASE CONNECTION ---
$conn = mysqli_connect("localhost", "root", "", "jjrmeditrack_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// --- FETCH DASHBOARD DATA ---

// 1. Total Medicine Items (from 'medicines' table)
$medicine_count_result = $conn->query("SELECT COUNT(medicineID) AS total_items FROM medicines");
$total_medicine_items = $medicine_count_result->fetch_assoc()['total_items'] ?? 0;

// 2. Today's Sales (from 'sale' table)
$today_sales_result = $conn->query("
    SELECT SUM(totalPrice) AS total_sales
    FROM sale
    WHERE DATE(saleDate) = CURDATE()
");
$today_sales = $today_sales_result->fetch_assoc()['total_sales'] ?? 0.00;

// 3. Active Staff Count (from 'staff' table)
$staff_count_result = $conn->query("SELECT COUNT(staffID) AS active_staff FROM staff");
$active_staff_count = $staff_count_result->fetch_assoc()['active_staff'] ?? 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: Arial; margin: 0; background: #f6fff6; color: #222; }

/* Header & Receipt Button Styles */
header { 
    background: #47d16b; 
    padding: 14px 18px; 
    font-weight: bold; 
    font-size: 20px; 
    color: #000; 
    display: flex; 
    align-items: center;
    justify-content: space-between;
}
/* Removed header-btn-back and receipt-btn styles as they are no longer in the header */

.header-title {
    flex-grow: 1; 
    text-align: center;
    color: #000; 
    font-weight: bold;
    font-size: 20px;
}
.container { width: 95%; max-width: 1000px; margin: 18px auto; text-align: center; }

h1 { color: #0f8e33; margin-top: 0; }

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.dashboard-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    text-decoration: none;
    color: #222;
    box-shadow: 0 4px 8px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

.dashboard-card h3 {
    margin-top: 10px;
    font-size: 16px;
    color: #0f8e33;
}

.dashboard-card p {
    font-size: 24px;
    font-weight: bold;
    margin: 5px 0 0;
}
.dashboard-card .icon {
    font-size: 30px;
    display: block;
    margin-bottom: 5px;
}

/* Bottom Nav */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#47d16b;display:flex;justify-content:space-around;padding:10px 0;}
.bottom-nav a{color:white;text-decoration:none;font-size:14px;text-align:center;}
.bottom-nav a:hover{color:#e6ffe6;}
.bottom-nav a.active { font-weight: bold; color: #f7ff00; }
</style>
</head>
<body>

<header>
    <span></span> 
    
    <span class="header-title">Staff Dashboard</span>
    
    <span></span>
</header>

<div class="container">
    <h1>Welcome Back!</h1>

    <div class="dashboard-grid">
        <a href="medicine_list.php" class="dashboard-card">
            <span class="icon">💊</span>
            <h3>Manage Inventory</h3>
            <p><?= $total_medicine_items ?></p>
            <small>Total Items</small>
        </a>
        <a href="sales_record.php" class="dashboard-card">
            <span class="icon">📈</span>
            <h3>View Sales</h3>
            <p>₱ <?= number_format($today_sales, 2) ?></p>
            <small>Today's Sales</small>
        </a>
        <a href="add_transaction.php" class="dashboard-card">
            <span class="icon">🛒</span>
            <h3>New Transaction</h3>
            <p>Go Now</p>
            <small>Quick Sale</small>
        </a>
        <a href="staff_list.php" class="dashboard-card">
            <span class="icon">👨‍⚕️</span>
            <h3>Staff Details</h3>
            <p><?= $active_staff_count ?></p>
            <small>Active Staff</small>
        </a>
    </div>

    <h2 style="margin-top: 50px; color: #555;">Quick Actions</h2>
    <div style="margin-top: 20px;">
        <a href="add_medicine.php" class="add-btn" style="background:#d4f7d4; color:#0f8e33; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: bold;">➕ Add New Medicine</a>
    </div>

</div>

<div class="bottom-nav">
    <a href="staff_dashboard.php" class="active">🏠 Home</a>
    <a href="medicine_list.php">💊 Medicine</a>
    <a href="staff_list.php">👨‍⚕️ Staff</a>
    <a href="sales_record.php">📈 Sales</a>
    <a href="settings.php">⚙️ Settings</a>
</div>

</body>
</html>