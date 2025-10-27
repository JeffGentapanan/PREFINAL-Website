<?php
session_start();
if (!isset($_SESSION['userID'])) { header("Location: login.html"); exit(); }
if (!isset($_GET['saleID'])) { echo "Sale ID missing."; exit(); }
$saleID = intval($_GET['saleID']);
$conn = mysqli_connect("localhost","root","","pharmacy_db");

// header info
$sq = "SELECT s.saleID, s.saleDate, s.totalPrice, c.customerID, u.firstname AS custFirst, u.lastname AS custLast, st.staffID, us.firstname AS staffFirst, us.lastname AS staffLast
FROM sale s
LEFT JOIN customer c ON s.customerID = c.customerID
LEFT JOIN users u ON c.userID = u.userID
LEFT JOIN staff st ON s.staffID = st.staffID
LEFT JOIN users us ON st.userID = us.userID
WHERE s.saleID = $saleID";
$sh = mysqli_query($conn, $sq);
if (!$sh || mysqli_num_rows($sh)==0) { echo "Sale not found."; exit(); }
$h = mysqli_fetch_assoc($sh);

// items
$itq = "SELECT si.medicineID, m.name, si.quantitySold, si.itemPrice, (si.quantitySold*si.itemPrice) AS subtotal
FROM sale_items si JOIN medicines m ON si.medicineID = m.medicineID
WHERE si.saleID = $saleID";
$items = mysqli_query($conn, $itq);
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Receipt #<?= $saleID ?></title></head>
<body style="font-family:Arial; background:#f9fff9;">
  <div style="width:700px; margin:20px auto; background:white; padding:20px; border-radius:8px;">
    <h2>JJR MediTrack Receipt</h2>
    <p><strong>Receipt #: </strong><?= $saleID ?> <br>
    <strong>Date: </strong><?= $h['saleDate'] ?> <br>
    <strong>Customer: </strong><?= htmlspecialchars($h['custFirst'].' '.$h['custLast']) ?><br>
    <strong>Staff: </strong><?= htmlspecialchars($h['staffFirst'].' '.$h['staffLast']) ?></p>

    <table border="1" cellpadding="6" cellspacing="0" width="100%">
      <tr><th>Medicine</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
      <?php while($row = mysqli_fetch_assoc($items)) { ?>
      <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= $row['quantitySold'] ?></td>
        <td><?= number_format($row['itemPrice'],2) ?></td>
        <td><?= number_format($row['subtotal'],2) ?></td>
      </tr>
      <?php } ?>
      <tr>
        <td colspan="3" align="right"><strong>Total:</strong></td>
        <td><strong>₱ <?= number_format($h['totalPrice'],2) ?></strong></td>
      </tr>
    </table>

    <p><a href="sales_record.php">Back to Sales</a> | <a href="staff_dashboard.php">Dashboard</a></p>
  </div>
</body>
</html>
<?php mysqli_close($conn); ?>