<?php
session_start();
// Security Check: Only 'staff' role can access this page
if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'staff') {
    header("Location: login.html");
    exit();
}

// Database Connection
$conn = mysqli_connect("localhost", "root", "", "jjrmeditrack_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$editMode = false;
$staff = []; // Initialize staff array for edit mode

// --- FETCH STAFF FOR EDIT ---
if (isset($_GET['edit'])) {
    $editMode = true;
    $id = intval($_GET['edit']);
    
    // Prepared statement for fetching staff data securely
    // NOTE: Password is NOT fetched here either, as we only need the other fields for the form
    $stmt = $conn->prepare("SELECT userID, firstname, lastname, username, email, role, createdAt FROM users WHERE userID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $staff = $res->fetch_assoc();
    $stmt->close();

    // If no staff found, redirect back to the list
    if (!$staff) {
        header("Location: staff_list.php");
        exit();
    }
}

// --- ADD STAFF (Form Submission) ---
if (isset($_POST['add_staff'])) {
    // Collect and sanitize input
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['lastname']);
    $username  = mysqli_real_escape_string($conn, $_POST['username']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
    $role      = mysqli_real_escape_string($conn, $_POST['role']);

    // 1. Insert into USERS table
    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $firstname, $lastname, $username, $email, $password, $role);
    $stmt->execute();
    $stmt->close();
    
    // 2. Get the new userID
    $new_user_id = mysqli_insert_id($conn);

    // 3. If the role is 'staff', also create a record in the STAFF table
    if ($role === 'staff' && $new_user_id) {
        // 4. Insert into STAFF table with minimal/placeholder data for staff details
        // We use NULL for bdate and default placeholders for other non-required fields in the form
        $stmt_staff = $conn->prepare("INSERT INTO staff (userID, bdate, address, gender, contactnumber) VALUES (?, NULL, '', 'Other', '')");
        $stmt_staff->bind_param("i", $new_user_id);
        $stmt_staff->execute();
        $stmt_staff->close();
    }
    
    header("Location: staff_list.php");
    exit();
}

// --- UPDATE STAFF (Form Submission) ---
if (isset($_POST['update_staff'])) {
    $id = intval($_POST['userID']);
    // Collect and sanitize input
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['lastname']);
    $username  = mysqli_real_escape_string($conn, $_POST['username']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $role      = mysqli_real_escape_string($conn, $_POST['role']);

    // Check if a new password was provided
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        // Update all fields, including the new password
        $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, username=?, email=?, password=?, role=? WHERE userID=?");
        $stmt->bind_param("ssssssi", $firstname, $lastname, $username, $email, $password, $role, $id);
    } else {
        // Update fields *excluding* the password
        $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, username=?, email=?, role=? WHERE userID=?");
        $stmt->bind_param("sssssi", $firstname, $lastname, $username, $email, $role, $id);
    }
    
    $stmt->execute();
    $stmt->close();

    // Since this is an update, we don't automatically insert into the staff table, 
    // assuming that creation already happened on initial role assignment.

    header("Location: staff_list.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $editMode ? 'Edit Staff' : 'Add Staff' ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: Arial; margin: 0; background: #f6fff6; color: #222; }
header { background: #47d16b; padding: 14px 18px; font-weight: bold; font-size: 20px; color: #000; text-align: center; }
.container { width: 95%; max-width: 500px; margin: 50px auto; text-align: center; }
form { background: #e2f7e2; padding: 25px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
form label { display: block; text-align: left; margin-top: 10px; font-weight: bold; }
form input[type=text], form input[type=email], form input[type=password], form select { margin-bottom: 12px; padding: 10px; width: calc(100% - 20px); border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; }
form input[type=submit], form button { cursor: pointer; padding: 10px 15px; border-radius: 6px; border: none; font-weight: bold; margin-top: 10px; }
form input[type=submit] { background: #3e9b4a; color: #fff; margin-right: 10px; }
form input[type=submit]:hover { background: #2f7d38; }
form button { background: #ccc; color: #333; }
form button:hover { background: #bbb; }
.bottom-nav { position: fixed; bottom:0; left:0; right:0; background:#47d16b; display:flex; justify-content:space-around; padding:10px 0; }
.bottom-nav a { color:white; text-decoration:none; font-size:14px; text-align:center; }
.bottom-nav a:hover { color:#e6ffe6; }
</style>
</head>
<body>
<header><?= $editMode ? 'Edit Staff Details' : 'Add New Staff' ?></header>
<div class="container">

<form method="POST">
<?php if($editMode): ?>
    <input type="hidden" name="userID" value="<?= $staff['userID'] ?>">
    <h3>Editing Staff ID: <?= $staff['userID'] ?></h3>
<?php else: ?>
    <h3>Enter Staff Information</h3>
<?php endif; ?>

<label>First Name:</label>
<input type="text" name="firstname" required value="<?= $editMode ? htmlspecialchars($staff['firstname']) : '' ?>">

<label>Last Name:</label>
<input type="text" name="lastname" required value="<?= $editMode ? htmlspecialchars($staff['lastname']) : '' ?>">

<label>Username:</label>
<input type="text" name="username" required value="<?= $editMode ? htmlspecialchars($staff['username']) : '' ?>">

<label>Email:</label>
<input type="email" name="email" required value="<?= $editMode ? htmlspecialchars($staff['email']) : '' ?>">

<label>Password: <?= $editMode ? '(Leave blank to keep current password)' : '' ?></label>
<input type="password" name="password" <?= $editMode ? '' : 'required' ?>>

<label>Role:</label>
<select name="role" required>
    <option value="staff" <?= $editMode && $staff['role']=='staff' ? 'selected' : '' ?>>Staff</option>
    <option value="patient" <?= $editMode && $staff['role']=='patient' ? 'selected' : '' ?>>Patient</option>
</select>

<input type="submit" name="<?= $editMode ? 'update_staff' : 'add_staff' ?>" value="<?= $editMode ? 'Update Staff' : 'Add Staff' ?>">
<a href="staff_list.php"><button type="button">Cancel / Back to List</button></a>

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
