<?php
session_start();
ob_start();

// Security Check
if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'staff') {
    header("Location: login.html");
    ob_end_flush();
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "jjrmeditrack_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$saleID = isset($_GET['saleID']) ? intval($_GET['saleID']) : 0;

if ($saleID <= 0) {
    // If invalid ID, redirect back with an error message
    header("Location: sales_record.php?status=error&msg=" . urlencode("Invalid Sale ID provided."));
    ob_end_flush();
    $conn->close();
    exit();
}

// --- Start Transaction for Safety ---
$conn->begin_transaction();

try {
    // 1. Get Sale Items and Quantities for Stock Restoration
    $stmt_items = $conn->prepare("SELECT medicineID, quantitySold FROM sale_items WHERE saleID = ?");
    $stmt_items->bind_param("i", $saleID);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    $stmt_items->close();

    // 2. Restore Stock
    $stmt_update_stock = $conn->prepare("UPDATE medicines SET stockquantity = stockquantity + ? WHERE medicineID = ?");
    
    while ($item = $items_result->fetch_assoc()) {
        $qty = $item['quantitySold'];
        $medID = $item['medicineID'];
        
        $stmt_update_stock->bind_param("ii", $qty, $medID);
        $stmt_update_stock->execute();
    }
    $stmt_update_stock->close();

    // 3. Delete related sale_items (The detail rows)
    $stmt_delete_items = $conn->prepare("DELETE FROM sale_items WHERE saleID = ?");
    $stmt_delete_items->bind_param("i", $saleID);
    $stmt_delete_items->execute();
    $stmt_delete_items->close();

    // 4. Delete the main sale record (The header row)
    $stmt_delete_sale = $conn->prepare("DELETE FROM sale WHERE saleID = ?");
    $stmt_delete_sale->bind_param("i", $saleID);
    $stmt_delete_sale->execute();
    $stmt_delete_sale->close();

    // --- Commit and Redirect Success ---
    $conn->commit();
    $conn->close();
    ob_end_clean();
    
    // Redirect with success message, similar to the image's concept
    // Note: PHP uses header() for redirection, not JavaScript alert()
    header("Location: sales_record.php?status=deleted");
    exit();

} catch (Exception $e) {
    // --- Rollback and Redirect Error ---
    $conn->rollback();
    $conn->close();
    ob_end_clean();
    header("Location: sales_record.php?status=error&msg=" . urlencode("Deletion failed: " . $e->getMessage()));
    exit();
}
?>