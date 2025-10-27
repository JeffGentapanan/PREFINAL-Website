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

$message = '';

// --- Handle DELETE TRANSACTION LOGIC ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Note: A full implementation would require reversing the stock count here.
    // For this mock-up, we just delete the record.
    $stmt = $conn->prepare("DELETE FROM transactions WHERE transactionID = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = '<div class="alert success">Transaction deleted successfully! (Stock was NOT reversed).</div>';
    } else {
        $message = '<div class="alert error">Error deleting transaction.</div>';
    }
    $stmt->close();
    // Redirect to sales_record.php without the 'delete' parameter
    header("Location: sales_record.php");
    exit();
}

// --- FETCH SALES SUMMARY ---
$summary = $conn->query("
    SELECT 
        COUNT(transactionID) AS totalTransactions,
        SUM(quantity) AS totalMedicineSold,
        SUM(total) AS totalSales
    FROM transactions
")->fetch_assoc();

// Handle null values if no transactions exist
$totalTransactions = $summary['totalTransactions'] ?? 0;
$totalMedicineSold = $summary['totalMedicineSold'] ?? 0;
$totalSales = $summary['totalSales'] ?? 0.00;

// --- FETCH ALL TRANSACTIONS (FOR DETAILED LIST, currently simplified to summary table as per image) ---
// Since the image only shows a single summary row, we'll keep the main table focused on the summary for now.
// However, I will add a button to view the list of individual transactions.

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sales Record</title>
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
.container { width: 95%; max-width: 600px; margin: 18px auto; text-align: center; }

/* Table Styles (Matching Image) */
table.sales-summary-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
    text-align: center;
}
table.sales-summary-table th, table.sales-summary-table td { 
    padding: 12px 10px; 
    font-size: 14px;
    border-bottom: 1px solid #d8f0db;
    border-right: 1px solid #d8f0db;
}
table.sales-summary-table th:last-child, table.sales-summary-table td:last-child {
    border-right: none;
}
table.sales-summary-table th { 
    background: #d4f7d4; 
    color: #0f8e33; 
    font-weight: bold;
}
table.sales-summary-table tr:nth-child(even) { background-color: #f0fff0; }
table.sales-summary-table tr:last-child td { border-bottom: none; }

.actions a { text-decoration: none; font-weight: bold; padding: 4px 8px; border-radius: 4px;}
.actions a.delete { color: #d42d2d; border: 1px solid #d42d2d; }
.actions a.delete:hover { background: #fcd4d4; }

.add-btn { 
    padding: 10px 20px; 
    background: #d4f7d4; 
    color: #0f8e33; 
    text-decoration: none; 
    border-radius: 25px; 
    font-weight: bold;
    border: 1px solid #0f8e33;
    display: inline-flex;
    align-items: center;
    margin-bottom: 20px;
}
.add-btn:hover { background: #c3e6c3; }

/* Alert Styles */
.alert { padding: 10px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }


/* Bottom Nav */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#47d16b;display:flex;justify-content:space-around;padding:10px 0;}
.bottom-nav a{color:white;text-decoration:none;font-size:14px;text-align:center;}
.bottom-nav a:hover{color:#e6ffe6;}
.bottom-nav a.active { font-weight: bold; color: #f7ff00; }
</style>
</head>
<body>

<header>
    <!-- FIX: Updated to reliably link back to the staff dashboard -->
    <a href="staff_dashboard.php" class="header-btn-back">⬅️</a>
    <span class="header-title">Sales Record</span>
    <span></span>
</header>

<div class="container">
    <?php echo $message; // Display status messages ?>
    
    <a href="add_transaction.php" class="add-btn">➕ Add New Transaction</a>

    <table class="sales-summary-table">
        <thead>
            <tr>
                <th>Total Transaction</th>
                <th>Medicine Sold</th>
                <th>Total Sales</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= $totalTransactions ?></td>
                <td><?= $totalMedicineSold ?></td>
                <td>₱ <?= number_format($totalSales, 2) ?></td>
                <td class="actions">
                    <a href="#" class="delete" style="opacity: 0.5; pointer-events: none;">Delete*</a>
                </td>
            </tr>
        </tbody>
    </table>
    
    <p style="text-align: left; font-size: 12px; color: #777; margin-top: 15px;">
        *Deletion of the entire sales record summary is generally not recommended in a real system. 
        Delete links would typically be placed on individual transaction lines in a detailed view.
    </p>

    <!-- Optional: Detailed list of transactions could go here -->

</div>

<div class="bottom-nav">
    <a href="staff_dashboard.php">🏠 Home</a>
    <a href="medicine_list.php">💊 Medicine</a>
    <a href="staff_list.php">👨‍⚕️ Staff</a>
    <a href="sales_record.php" class="active">📈 Sales</a>
    <a href="settings.php">⚙️ Settings</a>
</div>

</body>
</html>
<?php $conn->close(); ?>
