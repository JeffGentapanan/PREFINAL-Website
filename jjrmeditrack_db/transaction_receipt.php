<?php
session_start();
ob_start();

// Security Check
if (!isset($_SESSION['userID'])) { header("Location: login.html"); ob_end_flush(); exit(); }

$conn = mysqli_connect("localhost", "root", "", "jjrmeditrack_db");
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

$saleID = null;
$message = '';

// --- Get Sale ID from URL ---
if (isset($_GET['saleID'])) {
    $saleID = intval($_GET['saleID']);
} elseif (isset($_GET['id'])) { 
    $saleID = intval($_GET['id']);
}

// ------------------------------------
// --- READ (Display Receipt) Logic ---
// ------------------------------------
$transaction_data = []; // CHANGED to an array
$customerName = "Walk-in Customer"; 
$staffDetails = [];

if ($saleID) {
    // A. Fetch Sale Header Info
    $sq = "SELECT s.saleID, s.saleDate, s.totalPrice, us.firstname AS staffFirst, us.lastname AS staffLast
           FROM sale s
           LEFT JOIN staff st ON s.staffID = st.staffID
           LEFT JOIN users us ON st.userID = us.userID
           WHERE s.saleID = ?";

    $stmt_header = $conn->prepare($sq);
    if (!$stmt_header) { die("Header Prepare Error: " . $conn->error); }
    $stmt_header->bind_param("i", $saleID);
    $stmt_header->execute();
    $h_result = $stmt_header->get_result();
    $staffDetails = $h_result->fetch_assoc();
    $stmt_header->close();

    // Check if the sale still exists (it might have been deleted)
    if (!$staffDetails) {
        $message = '<div class="alert error">Sale record not found. It may have been deleted.</div>';
        $saleID = null; 
    } else {
        // B. Fetch Item Detail
        $itq = "SELECT m.name, si.quantitySold, si.itemPrice, (si.quantitySold * si.itemPrice) AS subtotal
                 FROM sale_items si JOIN medicines m ON si.medicineID = m.medicineID
                 WHERE si.saleID = ?";

        $stmt_items = $conn->prepare($itq);
        if (!$stmt_items) { die("Item Prepare Error: " . $conn->error); }
        $stmt_items->bind_param("i", $saleID);
        $stmt_items->execute();
        $items_result = $stmt_items->get_result();
        
        // FIX: Loop to fetch ALL rows
        while($row = $items_result->fetch_assoc()) {
            $transaction_data[] = $row;
        }
        $stmt_items->close();
    }
} elseif ($message === '') {
    $message = '<div class="alert error">No Sale ID provided. Cannot show receipt.</div>';
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt #<?= $saleID ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* ... (Your existing CSS styles go here) ... */
body { font-family: Arial; margin: 0; background: #f6fff6; color: #222; }
header { background: #47d16b; padding: 14px 18px; font-weight: bold; font-size: 20px; color: #000; display: flex; align-items: center; justify-content: space-between; }
.header-btn-back { cursor: pointer; padding: 6px 10px; border-radius: 6px; border: 1px solid #1e7d34; background: #3e9b4a; color: #fff; text-decoration: none; font-size: 14px; font-weight: normal; white-space: nowrap; }
.header-btn-back:hover { background: #2f7d38; }
.header-title { flex-grow: 1; text-align: center; color: #0f8e33; font-weight: bold; font-size: 24px; }
.container { width: 95%; max-width: 450px; margin: 18px auto; text-align: center; }

table.receipt-table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
table.receipt-table th, table.receipt-table td { padding: 12px 10px; text-align: center; font-size: 14px; border-bottom: 1px solid #d8f0db; }
table.receipt-table th { background: #d4f7d4; color: #0f8e33; font-weight: bold; }
table.receipt-table tr:nth-child(even) { background-color: #f0fff0; }
table.receipt-table tr:last-child td { border-bottom: none; }

.receipt-summary { text-align: right; margin-top: 20px; font-size: 16px; font-weight: bold; }
.receipt-summary div { padding: 5px 0; }
.receipt-summary .total-amount { font-size: 20px; color: #0f8e33; border-top: 2px solid #a8d9a8; padding-top: 10px; margin-top: 10px; }

.alert { padding: 10px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#47d16b;display:flex;justify-content:space-around;padding:10px 0;}
.bottom-nav a{color:white;text-decoration:none;font-size:14px;text-align:center;}
.bottom-nav a:hover{color:#e6ffe6;}

/* Delete Button Style */
.btn-delete {
    background: #d42d2d;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    font-weight: bold;
    cursor: pointer;
    margin-top: 20px;
}
.btn-delete:hover {
    background: #b52222;
}
</style>
</head>
<body>

<header>
    <a href="sales_record.php" class="header-btn-back">⬅️ Back</a>
    <span class="header-title">Receipt</span>
    <span></span>
</header>

<div class="container">
    <?php echo $message; ?>

    <?php if ($staffDetails && !empty($transaction_data)): // Check for both header and item data ?>
    <div style="text-align: left; margin-bottom: 15px;">
        <p><strong>Sale ID:</strong> #<?= htmlspecialchars($staffDetails['saleID']) ?></p>
        <p><strong>Date:</strong> <?= date('Y-m-d H:i:s', strtotime($staffDetails['saleDate'])) ?></p>
        <p><strong>Customer:</strong> <?= htmlspecialchars($customerName) ?></p>
        <p><strong>Staff:</strong> <?= htmlspecialchars($staffDetails['staffFirst'] . ' ' . $staffDetails['staffLast']) ?></p>
    </div>

    <table class="receipt-table">
        <thead>
            <tr>
                <th>Medicine Bought</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transaction_data as $item): // Loop through all items ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td>₱ <?= number_format($item['itemPrice'], 2) ?></td>
                <td><?= htmlspecialchars($item['quantitySold']) ?></td>
                <td>₱ <?= number_format($item['subtotal'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="receipt-summary">
        <div class="total-amount">TOTAL: ₱ <?= number_format($staffDetails['totalPrice'], 2) ?></div>
    </div>
    
    <button class="btn-delete" onclick="confirmDelete(<?= $saleID ?>)">DELETE TRANSACTION</button>
    
    <script>
    function confirmDelete(saleId) {
        if (confirm("WARNING: Are you sure you want to delete this transaction? This action will restore the stock quantity.")) {
            // Redirect to the dedicated delete file
            window.location.href = 'delete_sale.php?saleID=' + saleId;
        }
    }
    </script>

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
<?php ob_end_flush(); ?>