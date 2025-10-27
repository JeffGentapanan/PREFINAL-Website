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
$staffID = $_SESSION['userID']; // Assuming userID is the staffID

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = $_POST['customerName'] ?? '';
    $medicineName = $_POST['medicineBought'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $total = $price * $quantity;

    if (empty($customerName) || empty($medicineName) || $price <= 0 || $quantity <= 0) {
        $message = '<div class="alert error">Please fill in all fields correctly.</div>';
    } else {
        // 1. Check Medicine Existence and Stock
        $stmt_check = $conn->prepare("SELECT medicineID, stockquantity FROM medicines WHERE name = ?");
        $stmt_check->bind_param("s", $medicineName);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $medicine_data = $result_check->fetch_assoc();
        $stmt_check->close();

        if (!$medicine_data) {
            $message = '<div class="alert error">Medicine not found in inventory.</div>';
        } elseif ($medicine_data['stockquantity'] < $quantity) {
            $message = '<div class="alert error">Insufficient stock for ' . htmlspecialchars($medicineName) . '. Current stock: ' . $medicine_data['stockquantity'] . '</div>';
        } else {
            $medicineID = $medicine_data['medicineID'];

            // Start Transaction
            $conn->begin_transaction();
            try {
                // 2. Insert Transaction Record
                $stmt_insert = $conn->prepare("INSERT INTO transactions (customerName, medicineName, medicineID, price, quantity, total, staffID) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_insert->bind_param("ssiiddi", $customerName, $medicineName, $medicineID, $price, $quantity, $total, $staffID);
                $stmt_insert->execute();
                $new_transactionID = $stmt_insert->insert_id;
                $stmt_insert->close();

                // 3. Update Stock Quantity
                $new_stock = $medicine_data['stockquantity'] - $quantity;
                $stmt_update = $conn->prepare("UPDATE medicines SET stockquantity = ? WHERE medicineID = ?");
                $stmt_update->bind_param("ii", $new_stock, $medicineID);
                $stmt_update->execute();
                $stmt_update->close();

                $conn->commit();
                
                // ACTION: Redirect to receipt page after successful save
                header("Location: transaction_receipt.php?id=" . $new_transactionID);
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $message = '<div class="alert error">Transaction failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add New Transaction</title>
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
    color: #000; 
    font-weight: bold;
    font-size: 20px;
}
.container { width: 95%; max-width: 450px; margin: 18px auto; text-align: center; }

h1 { color: #0f8e33; margin-top: 0; font-size: 24px; }

/* Form Styles */
.form-group {
    margin-bottom: 20px;
    text-align: left;
}
.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}
.form-group input {
    width: 100%;
    padding: 12px 15px;
    border-radius: 10px;
    border: 1px solid #ccc;
    font-size: 16px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
    box-sizing: border-box;
    background: #e6ffe6; /* Light green input background */
}

/* Button Styles (Matching Image) */
.button-group {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin-top: 30px;
}
.btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 25px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.2s;
    font-size: 16px;
    text-decoration: none; /* For cancel link */
    text-align: center;
}
.btn-save {
    background: #d4f7d4; 
    color: #0f8e33; 
    border: 1px solid #0f8e33;
}
.btn-save:hover { background: #c3e6c3; }

.btn-cancel {
    background: #ffcdd2; 
    color: #d42d2d; 
    border: 1px solid #d42d2d;
}
.btn-cancel:hover { background: #ffb3b8; }

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
    <!-- FIX: Ensuring the back link is solid -->
    <a href="staff_dashboard.php" class="header-btn-back">⬅️ Back</a>
    <span class="header-title">Add New Transactions</span>
    <span></span>
</header>

<div class="container">
    <?php echo $message; // Display status messages ?>

    <form method="POST">
        <div class="form-group">
            <label for="customerName">Customer Name</label>
            <input type="text" id="customerName" name="customerName" required value="<?= htmlspecialchars($_POST['customerName'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="medicineBought">Medicine Bought</label>
            <input type="text" id="medicineBought" name="medicineBought" placeholder="e.g. Paracetamol" required value="<?= htmlspecialchars($_POST['medicineBought'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="price">Price (per unit)</label>
            <input type="number" id="price" name="price" step="0.01" min="0.01" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" min="1" required value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
        </div>

        <div class="button-group">
            <button type="submit" class="btn btn-save">Save Transaction</button>
            <a href="staff_dashboard.php" class="btn btn-cancel">Cancel Transaction</a>
        </div>
    </form>
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
