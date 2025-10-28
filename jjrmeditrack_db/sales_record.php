<?php
session_start();
// Security Check
if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'staff') {
    header("Location: login.html");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "jjrmeditrack_db");
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

$message = '';

// Check for status messages after deletion
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deleted') {
        $message = '<div class="alert success">Transaction successfully deleted and stock restored.</div>';
    } elseif ($_GET['status'] == 'error' && isset($_GET['msg'])) {
        $message = '<div class="alert error">' . htmlspecialchars(urldecode($_GET['msg'])) . '</div>';
    }
}

// Fetch all sales records, joining staff/users for staff name
$query = "SELECT s.saleID, s.saleDate, s.totalPrice, 
                 us.firstname AS staffFirst, us.lastname AS staffLast
           FROM sale s
           LEFT JOIN staff st ON s.staffID = st.staffID
           LEFT JOIN users us ON st.userID = us.userID
           ORDER BY s.saleDate DESC";
          
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sales Record</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* --- STYLES --- */
body { font-family: Arial; margin: 0; background: #f6fff6; color: #222; }
header { background: #47d16b; padding: 14px 18px; font-weight: bold; font-size: 20px; color: #000; display: flex; align-items: center; justify-content: space-between; }
.header-btn-back { cursor: pointer; padding: 6px 10px; border-radius: 6px; border: 1px solid #1e7d34; background: #3e9b4a; color: #fff; text-decoration: none; font-size: 14px; font-weight: normal; white-space: nowrap; }
.header-btn-back:hover { background: #2f7d38; }
.header-title { flex-grow: 1; text-align: center; color: #000; font-weight: bold; font-size: 20px; }
.container { width: 95%; max-width: 900px; margin: 18px auto; text-align: center; }

table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
table th, table td { padding: 12px 10px; text-align: center; font-size: 14px; border-bottom: 1px solid #d8f0db; }
table th { background: #d4f7d4; color: #0f8e33; font-weight: bold; }
table tr:nth-child(even) { background-color: #f0fff0; }

.btn-action { padding: 5px 10px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 12px; margin: 0 2px; display: inline-block;}
.btn-view { background-color: #47d16b; color: white; border: none; }
.btn-delete { background-color: #d42d2d; color: white; border: none; cursor: pointer; }

.alert { padding: 10px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; text-align: left; }
.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e3cf; }
.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#47d16b;display:flex;justify-content:space-around;padding:10px 0;}
.bottom-nav a{color:white;text-decoration:none;font-size:14px;text-align:center;}
.bottom-nav a.active { font-weight: bold; color: #f7ff00; }
</style>
</head>
<body>

<header>
    <a href="staff_dashboard.php" class="header-btn-back">⬅️ Back</a>
    <span class="header-title">Sales Record</span>
    <span></span>
</header>

<div class="container">
    <?php echo $message; ?>
    <h2>All Sales Transactions</h2>
    
    <?php if ($result && $result->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Sale ID</th>
                <th>Date</th>
                <th>Total Price</th>
                <th>Staff</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td>#<?= htmlspecialchars($row['saleID']) ?></td>
                <td><?= date('Y-m-d', strtotime($row['saleDate'])) ?></td>
                <td>₱ <?= number_format($row['totalPrice'], 2) ?></td>
                <td><?= htmlspecialchars($row['staffFirst'] . ' ' . $row['staffLast']) ?></td>
                <td>
                    <a href="transaction_receipt.php?saleID=<?= $row['saleID'] ?>" class="btn-action btn-view">View</a>
                    <button class="btn-action btn-delete" onclick="confirmDelete(<?= $row['saleID'] ?>)">Delete</button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="alert error">No sales records found.</div>
    <?php endif; ?>
</div>

<script>
function confirmDelete(saleId) {
    if (confirm("WARNING: Are you sure you want to delete Sale ID #" + saleId + "? This action will restore the medicine stock.")) {
        // Correctly redirects to the dedicated delete script
        window.location.href = 'delete_sale.php?saleID=' + saleId;
    }
}
</script>

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