<?php
session_start();
// ===== SECURITY CHECK =====
if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'staff') {
    header("Location: login.html");
    exit();
}

// --- Database Connection ---
$conn = mysqli_connect("localhost", "root", "", "jjrmeditrack_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// === DASHBOARD DATA ===
// Total Medicine Items
$total_medicine_items = $conn->query("SELECT COUNT(medicineID) AS total FROM medicines")->fetch_assoc()['total'] ?? 0;

// Today's Sales
$today_sales = $conn->query("
    SELECT SUM(totalPrice) AS total_sales
    FROM sale
    WHERE DATE(saleDate) = CURDATE()
")->fetch_assoc()['total_sales'] ?? 0;

// Total Transactions
$total_transactions = $conn->query("
    SELECT COUNT(saleID) AS total_txn
    FROM sale
")->fetch_assoc()['total_txn'] ?? 0;

// Low Stock Medicines (<=10 and active)
$low_stock_query = $conn->query("
    SELECT name, stockquantity
    FROM medicines
    WHERE stockquantity <= 10 
      AND status = 'active'
    ORDER BY stockquantity ASC
");
$low_stock_count = $low_stock_query->num_rows;

// Expiring Soon Medicines (within 30 days and active)
$expiring_query = $conn->query("
    SELECT name, expiredate
    FROM medicines
    WHERE expiredate <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
      AND status = 'active'
    ORDER BY expiredate ASC
");
$expiring_count = $expiring_query->num_rows;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <a href="staff_dashboard.php" class="active">🏠 Home</a>
    <a href="medicine_list.php">💊 Medicine</a>
    <a href="staff_list.php">👨‍⚕️ Staff</a>
    <a href="sales_record.php">📈 Sales</a>
    <a href="setting.php">⚙️ Settings</a>
</div>

<!-- Main -->
<div class="main-content">
    <header>
        <span class="header-title">JJR MediTrack Dashboard</span>
        <a href="transaction_receipt.php" class="receipt-btn">🧾 Receipt</a>
    </header>

    <div class="container">
        <h1 style="color:#0f8e33;"></h1>

        <div class="dashboard-grid">
            <!-- New Transaction Card - Spans full width in row 1 -->
            <a href="add_transaction.php" class="dashboard-card transaction-card">
                <span class="icon">🛒</span>
                <h3>New Transaction</h3>
                <p>Go Now</p>
                <small>Quick Sale</small>
            </a>
            <!-- Four Data Cards - Balanced in row 2, each in one column -->
            <div class="dashboard-card sales-card">
                <span class="icon">💰</span>
                <h3>Total Sales Today</h3>
                <p>₱ <?= number_format($today_sales, 2) ?></p>
            </div>
            <div class="dashboard-card transactions-card">
                <span class="icon">📊</span>
                <h3>Total Transactions</h3>
                <p><?= $total_transactions ?></p>
            </div>
            <div class="dashboard-card low-stock-card">
                <span class="icon">📉</span>
                <h3>Low Stock Medicine</h3>
                <p><?= $low_stock_count ?></p>
            </div>
            <div class="dashboard-card expiring-card">
                <span class="icon">⏰</span>
                <h3>Expiring Soon</h3>
                <p><?= $expiring_count ?></p>
            </div>
        </div>

        <div class="table-container">
            <div>
                <h3>Low Stock Medicine</h3>
                <table>
                    <tr><th>Medicine Name</th><th>Stock</th><th>Action</th></tr>
                    <?php if ($low_stock_count > 0): ?>
                        <?php while ($row = $low_stock_query->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= $row['stockquantity'] ?></td>
                                <!-- Changed reorder link to direct to add_medicine.php -->
                                <td><a href="add_medicine.php" class="reorder">Reorder</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center;">No low stock medicines</td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <div>
                <h3>Expiring Soon Medicine</h3>
                <table>
                    <tr><th>Medicine Name</th><th>Expiration Date</th><th>Action</th></tr>
                    <?php if ($expiring_count > 0): ?>
                        <?php while ($row = $expiring_query->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= date("m/d/Y", strtotime($row['expiredate'])) ?></td>
                                <td><a href="delete_medicine.php?id=<?= urlencode($row['name']) ?>" class="delete">Delete</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center;">No expiring medicines soon</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
