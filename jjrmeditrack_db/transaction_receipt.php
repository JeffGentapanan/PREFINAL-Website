<?php
session_start();
// Security Check
if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'staff') {
    header("Location: login.html");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "jjrmeditrack_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$transaction = null;
$message = '';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT transactionID, customerName, medicineName, price, quantity, total, transactionDate FROM transactions WHERE transactionID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $transaction = $result->fetch_assoc();
    } else {
        $message = '<div class="alert error">Transaction receipt not found.</div>';
    }
    $stmt->close();
} else {
    $message = '<div class="alert error">No transaction ID provided.</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Transaction Receipt</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: Arial; margin: 0; background: #f6fff6; color: #222; }

/* Styles taken from medicine_list.php */
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
.header-btn-back {
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #1e7d34; 
    background: #3e9b4a; 
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: normal;
    white-space: nowrap; 
}
.header-btn-back:hover {
    background: #2f7d38; 
}
.header-title {
    flex-grow: 1; 
    text-align: center;
    color: #0f8e33; /* Color matching the image's title color */
    font-weight: bold;
    font-size: 24px;
}
.container { width: 95%; max-width: 450px; margin: 18px auto; text-align: center; }

/* Table Styles (Matching Image) */
table.receipt-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden; /* Ensures rounded corners clip table */
}
table.receipt-table th, table.receipt-table td { 
    padding: 12px 10px; 
    text-align: center;
    font-size: 14px;
    border-bottom: 1px solid #d8f0db;
}
table.receipt-table th { 
    background: #d4f7d4; /* Light green header matching image */
    color: #0f8e33; 
    font-weight: bold;
}
table.receipt-table tr:nth-child(even) { background-color: #f0fff0; }
table.receipt-table tr:last-child td { border-bottom: none; }

.receipt-summary {
    text-align: right;
    margin-top: 20px;
    font-size: 16px;
    font-weight: bold;
}

.receipt-summary div {
    padding: 5px 0;
}
.receipt-summary .total-amount {
    font-size: 20px;
    color: #0f8e33;
    border-top: 2px solid #a8d9a8;
    padding-top: 10px;
    margin-top: 10px;
}

/* Alert Styles */
.alert { padding: 10px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* Bottom Nav */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#47d16b;display:flex;justify-content:space-around;padding:10px 0;}
.bottom-nav a{color:white;text-decoration:none;font-size:14px;text-align:center;}
.bottom-nav a:hover{color:#e6ffe6;}
</style>
</head>
<body>

<header>
    <!-- FIX: Ensuring the back link is solid -->
    <a href="staff_dashboard.php" class="header-btn-back">⬅️ Back</a>
    <span class="header-title">Receipt</span>
    <span></span>
</header>

<div class="container">
    <?php echo $message; ?>

    <?php if ($transaction): ?>
    <div style="text-align: left; margin-bottom: 15px;">
        <p><strong>Transaction ID:</strong> #<?= htmlspecialchars($transaction['transactionID']) ?></p>
        <p><strong>Date:</strong> <?= date('Y-m-d H:i:s', strtotime($transaction['transactionDate'])) ?></p>
        <p><strong>Customer:</strong> <?= htmlspecialchars($transaction['customerName']) ?></p>
    </div>

    <table class="receipt-table">
        <thead>
            <tr>
                <th>Customer Name</th>
                <th>Medicine Bought</th>
                <th>Price</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($transaction['customerName']) ?></td>
                <td><?= htmlspecialchars($transaction['medicineName']) ?></td>
                <td>₱ <?= number_format($transaction['price'], 2) ?></td>
                <td><?= htmlspecialchars($transaction['quantity']) ?></td>
            </tr>
            <!-- Note: For simplicity, this receipt shows one item per transaction. 
                 For multiple items, the transaction structure would need to be normalized 
                 (e.g., using a separate `transaction_items` table). -->
        </tbody>
    </table>

    <div class="receipt-summary">
        <div>Subtotal: ₱ <?= number_format($transaction['total'], 2) ?></div>
        <div class="total-amount">TOTAL: ₱ <?= number_format($transaction['total'], 2) ?></div>
    </div>

    <?php endif; ?>
</div>

<div class="bottom-nav">
    <a href="staff_dashboard.php">🏠 Home</a>
    <a href="medicine_list.php">💊 Medicine</a>
    <a href="staff_list.php">👨‍⚕️ Staff</a>
    <a href="sales_record.php">📈 Sales</a>
    <a href="settings.php">⚙️ Settings</a>
</div>

</body>
</html>
<?php $conn->close(); ?>
