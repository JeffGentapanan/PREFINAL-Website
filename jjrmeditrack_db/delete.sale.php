<?php
session_start();
// Security Check
if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'staff') {
    header("Location: login.html");
    exit();
}

// Database Connection
$conn = mysqli_connect("localhost", "root", "", "jjrmeditrack_db");
if (!$conn) { 
    error_log("DB Connection Failed: " . mysqli_connect_error());
    header("Location: sales_record.php?status=error&msg=" . urlencode("Database connection failed. Check your server settings."));
    exit();
}

$saleID = isset($_GET['saleID']) ? intval($_GET['saleID']) : null;

if (!$saleID) {
    header("Location: sales_record.php?status=error&msg=" . urlencode("Invalid Sale ID provided for deletion."));
    exit();
}

// Start Transaction for atomic deletion
$conn->begin_transaction();
try {
    // 1. Get item details for stock restoration
    $stmt_get_item = $conn->prepare("SELECT medicineID, quantitySold FROM sale_items WHERE saleID = ?");
    if (!$stmt_get_item) {
        throw new Exception("Prepare failed for item selection: " . $conn->error);
    }
    $stmt_get_item->bind_param("i", $saleID);
    
    if (!$stmt_get_item->execute()) {
        throw new Exception("Execute failed for item selection: " . $stmt_get_item->error);
    }
    
    $items_to_restore = $stmt_get_item->get_result();
    $stmt_get_item->close();

    // 2. Restore Stock
    while ($row = $items_to_restore->fetch_assoc()) {
        $stmt_restore = $conn->prepare("UPDATE medicines SET stockquantity = stockquantity + ? WHERE medicineID = ?");
        if (!$stmt_restore) { throw new Exception("Prepare failed for stock update: " . $conn->error); }
        
        $stmt_restore->bind_param("ii", $row['quantitySold'], $row['medicineID']);
        if (!$stmt_restore->execute()) { throw new Exception("Execute failed for stock update: " . $stmt_restore->error); }
        
        $stmt_restore->close();
    }

    // 3. Delete sale items first
    $stmt_delete_items = $conn->prepare("DELETE FROM sale_items WHERE saleID = ?");
    if (!$stmt_delete_items) { throw new Exception("Prepare failed for item deletion: " . $conn->error); }
    
    $stmt_delete_items->bind_param("i", $saleID);
    if (!$stmt_delete_items->execute()) { throw new Exception("Execute failed for item deletion: " . $stmt_delete_items->error); }
    
    $stmt_delete_items->close();

    // 4. Delete the sale record
    $stmt_delete_sale = $conn->prepare("DELETE FROM sale WHERE saleID = ?");
    if (!$stmt_delete_sale) { throw new Exception("Prepare failed for sale deletion: " . $conn->error); }
    
    $stmt_delete_sale->bind_param("i", $saleID);
    if (!$stmt_delete_sale->execute()) { throw new Exception("Execute failed for sale deletion: " . $stmt_delete_sale->error); }
    
    $stmt_delete_sale->close();

    // Commit and redirect on success
    $conn->commit();
    $conn->close();
    
    header("Location: sales_record.php?status=deleted");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    error_log("Sale Deletion Error (SaleID $saleID): " . $e->getMessage());
    
    // Redirect with the specific error message
    header("Location: sales_record.php?status=error&msg=" . urlencode("Deletion failed. Error: " . $e->getMessage()));
    exit();
}
?>