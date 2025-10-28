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
    die("Connection failed: " . mysqli_connect_error());
}

$message = ''; 

// --- ADD MEDICINE LOGIC ---
if (isset($_POST['submit_medicine'])) {
    
    // 1. Collect and sanitize input
    $name = mysqli_real_escape_string($conn, $_POST['medicine_name']);
    $manufacture = mysqli_real_escape_string($conn, $_POST['manufacture']); 
    $description = mysqli_real_escape_string($conn, $_POST['description']); 
    $milligram = intval($_POST['milligram']); 
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $expiry = mysqli_real_escape_string($conn, $_POST['expiry_date']);
    $category = mysqli_real_escape_string($conn, $_POST['category']); 
    $image_path = 'placeholder.jpg'; // Default image

    // 2. Handle Image Upload (Same logic as before)
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/medicine_images/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid('med_') . '.' . $file_extension;
        $target_file = $target_dir . $new_file_name;

        if (getimagesize($_FILES["image"]["tmp_name"]) !== false) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_path = $target_file;
            } else {
                $message = '<div class="alert error">Error uploading image.</div>';
            }
        } else {
             $message = '<div class="alert error">File is not a valid image.</div>';
        }
    }

    // 3. Validate input 
    if (empty($name) || empty($manufacture) || $price <= 0 || $quantity <= 0 || empty($expiry) || empty($category)) {
        $message = '<div class="alert error">Please fill in all required fields correctly.</div>';
    } elseif (strpos($message, 'Error') === false) {
        
        // 4. Prepared statement for secure insertion (FIXED BIND PARAMETERS)
        // Fields: name, manufacture, description, milligram, price, stockquantity, expiredate, category, image_path
        $stmt = $conn->prepare("INSERT INTO medicines (name, manufacture, description, milligram, price, stockquantity, expiredate, category, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Corrected bind string for 9 parameters: s s s i d i s s s
        // (name, manufacture, description, milligram, price, quantity, expiry, category, image_path)
        $stmt->bind_param("sssidisss", $name, $manufacture, $description, $milligram, $price, $quantity, $expiry, $category, $image_path);

        if ($stmt->execute()) {
            // SUCCESS: REDIRECT TO MEDICINE LIST PAGE
            header("Location: medicine_list.php?status=added&name=" . urlencode($name));
            exit();
        } else {
            $message = '<div class="alert error">Error adding medicine: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
}
?>



























<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Medicine to Inventory</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: Arial; margin: 0; background: #f6fff6; color: #222; }
header { background: #47d16b; padding: 14px 18px; font-weight: bold; font-size: 20px; color: #000; display: flex; align-items: center; justify-content: space-between; }
.header-btn-back { cursor: pointer; padding: 6px 10px; border-radius: 6px; border: 1px solid #1e7d34; background: #3e9b4a; color: #fff; text-decoration: none; font-size: 14px; font-weight: normal; white-space: nowrap; }
.header-btn-back:hover { background: #2f7d38; }
.header-title { flex-grow: 1; text-align: center; color: #000; font-weight: bold; font-size: 20px; margin-right: 120px; }
.container { width: 95%; max-width: 700px; margin: 30px auto; text-align: center; }
h1 { color: #0f8e33; margin-bottom: 25px; }
form { background: #e2f7e2; padding: 25px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: left; }
label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: bold; color: #0f8e33; }
input[type=text], input[type=number], input[type=date], select, input[type=file], textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    box-sizing: border-box;
    font-size: 16px;
    background: #f0fff0; 
    resize: vertical;
}
input[type=file] { padding: 8px; }

/* Action Buttons Container */
.form-actions {
    display: flex;
    justify-content: space-between;
    gap: 20px; /* Space between buttons */
    margin-top: 25px;
}

/* Submit Button Style */
input[type=submit] { 
    flex-grow: 1;
    width: 48%; /* Adjust to fit alongside cancel button */
    background: #3e9b4a; 
    color: white; 
    padding: 12px 20px; 
    margin-top: 0; /* Remove top margin here, use .form-actions margin */
    border: none; 
    border-radius: 8px; 
    cursor: pointer; 
    font-size: 18px; 
    font-weight: bold;
}
input[type=submit]:hover { background: #2f7d38; }

/* Cancel Button Style (Anchor Tag) */
.cancel-btn {
    flex-grow: 1;
    display: block;
    width: 48%; /* Same width as submit */
    background: #b0b0b0; /* Neutral grey color */
    color: #333;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 18px;
    font-weight: bold;
    text-align: center; /* Center text since it's an anchor */
    text-decoration: none; /* Remove underline */
    box-sizing: border-box; /* Include padding/border in width */
    /* Ensure the cancel button has the same height as the submit button */
    line-height: normal;
}
.cancel-btn:hover {
    background: #888;
    color: white;
}

.alert { padding: 10px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.bottom-nav { position: fixed; bottom:0; left:0; right:0; background:#47d16b; display:flex; justify-content:space-around; padding:10px 0; }
.bottom-nav a { color:white; text-decoration:none; font-size:14px; text-align:center; }
.bottom-nav a:hover { color:#e6ffe6; }
</style>
</head>
<body>

<header>
    <a href="staff_dashboard.php" class="header-btn-back">⬅️ Dashboard</a>
    
    <span class="header-title">Add Medicine to Inventory</span>
    
    <span></span>
</header>

<div class="container">
    <h1>Medicines to Inventory</h1>

    <?php echo $message; // Display success or error message ?>

    <form method="POST" enctype="multipart/form-data">
        
        <label for="medicine_name">Medicine name</label>
        <input type="text" id="medicine_name" name="medicine_name" required>

        <label for="manufacture">Manufacturer</label>
        <input type="text" id="manufacture" name="manufacture" placeholder="e.g., Unilab, Pfizer" required>
        
        <label for="milligram">Strength (Milligram)</label>
        <input type="number" id="milligram" name="milligram" min="1" placeholder="e.g., 500" required>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" placeholder="Brief description and usage of the medicine"></textarea>

        <label for="category">Category</label>
        <select id="category" name="category" required>
            <option value="">Select Category</option>
            <option value="Vitamins">Vitamins</option>
            <option value="Pain Relief">Pain Relief</option>
            <option value="Prescription">Prescription</option>
            <option value="Others">Others</option>
        </select>

        <label for="price">Price (₱)</label>
        <input type="number" id="price" name="price" step="0.01" min="0.01" required>

        <label for="quantity">Quantity</label>
        <input type="number" id="quantity" name="quantity" min="1" required>

        <label for="expiry_date">Expiry</label>
        <input type="date" id="expiry_date" name="expiry_date" required>

        <label for="image">Medicine Image (Optional)</label>
        <input type="file" id="image" name="image" accept="image/*">

        <!-- New Button Group for Submit and Cancel -->
        <div class="form-actions">
            <a href="medicine_list.php" class="cancel-btn">Cancel</a>
            <input type="submit" name="submit_medicine" value="Submit">
        </div>
        <!-- End New Button Group -->
    </form>
</div>

<div class="bottom-nav">
    <a href="staff_dashboard.php">🏠 Home</a>
    <a href="medicine_list.php">💊 Medicine</a>
    <a href="staff_list.php">👨‍⚕️ Staff</a>
    <a href="settings.php">⚙️ Settings</a>
</div>

</body>
</html>

<?php $conn->close(); ?>