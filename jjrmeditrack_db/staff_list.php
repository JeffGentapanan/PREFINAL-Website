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

// DELETE STAFF logic
if (isset($_GET['delete'])) {
    // 1. Get the ID and ensure it is an integer. This is the sanitization step.
    $id = intval($_GET['delete']);
    
    // 2. Check if the ID is valid (not 0, which intval returns for non-numeric input)
    if ($id > 0) {
        // Prepared statement for secure deletion
        $stmt = $conn->prepare("DELETE FROM users WHERE userID = ?");
        
        // Check if the statement was successfully prepared
        if ($stmt === false) {
            die("Error preparing statement: " . $conn->error);
        }
        
        // 3. Bind the parameter. 'i' specifies integer type.
        // Use the $id variable directly as it's been validated as an integer.
        $stmt->bind_param("i", $id);
        
        // 4. Execute the statement
        $stmt->execute();
        
        // 5. Close the statement
        $stmt->close();
    }
    
    // Redirect regardless of whether a delete occurred, or check $stmt->affected_rows for success message
    header("Location: staff_list.php");
    exit();
}

// FETCH ALL STAFF (excluding the password column for security)
$result = $conn->query("SELECT userID, firstname, lastname, username, email, role, createdAt FROM users WHERE role='staff' ORDER BY userID DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Staff</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: Arial; margin: 0; background: #f6fff6; color: #222; }

/* MODIFIED HEADER STYLE */
header { 
    background: #47d16b; 
    padding: 14px 18px; 
    font-weight: bold; 
    font-size: 20px; 
    color: #000; 
    text-align: center; 
    
    /* Flexbox for positioning the back button and title */
    display: flex;
    justify-content: space-between; 
    align-items: center;
}

/* New style for the title text within the header */
.header-title {
    flex-grow: 1; /* Allows the title to take up remaining space */
    text-align: center;
    margin-right: 120px; /* Offset to center the title better since the button takes space on the left */
}

/* Style for the back button in the header */
.header-btn-back {
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #1e7d34; /* Darker green border */
    background: #3e9b4a; /* A matching green shade */
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: normal;
    white-space: nowrap; /* Keep text on one line */
}
.header-btn-back:hover {
    background: #2f7d38; 
}


.container { width: 95%; max-width: 1000px; margin: 18px auto; text-align: center; }

/* Adjusted table-header to remove old back button styling and positioning */
.table-header {
    display: flex;
    justify-content: flex-end; /* Puts "Staff List" title on the left and Add button on the right */
    align-items: center;
    margin-bottom: 12px;
}
.table-header h2 {
    margin: 0 auto 0 0; /* Puts "Staff List" title on the far left */
}
/* ----------------------------------------- */

.add-btn { cursor: pointer; padding: 6px 12px; border-radius: 6px; border: none; background: #3e9b4a; color: #fff; text-decoration: none; display: inline-block; }
.add-btn:hover { background: #2f7d38; }
table { width: 100%; border-collapse: collapse; margin-top: 12px; }
table th, table td { border: 1px solid #a8d9a8; padding: 8px; }
table th { background: #47d16b; color: white; }
.actions a { margin: 0 4px; padding: 4px 8px; background: #3e9b4a; color: #fff; text-decoration: none; border-radius: 6px; }
.actions a.delete { background: #d42d2d; }
.actions a:hover { opacity: 0.8; }
.bottom-nav { position: fixed; bottom:0; left:0; right:0; background:#47d16b; display:flex; justify-content:space-around; padding:10px 0; }
.bottom-nav a { color:white; text-decoration:none; font-size:14px; text-align:center; }
.bottom-nav a:hover { color:#e6ffe6; }
</style>
</head>
<body>

<header>
    <a href="staff_dashboard.php" class="header-btn-back">⬅️ Dashboard</a>
    
    <span class="header-title">Manage Staff</span>
    
    <span></span> 
</header>
<div class="container">

<div class="table-header">
    <h2>Staff List</h2>
    <a href="staff_form.php" class="add-btn">➕ Add New Staff</a>
</div>

<table>
<tr>
<th>ID</th>
<th>First Name</th>
<th>Last Name</th>
<th>Username</th>
<th>Email</th>
<th>Role</th>
<th>Created At</th>
<th>Action</th>
</tr>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
<td><?= $row['userID'] ?></td>
<td><?= htmlspecialchars($row['firstname']) ?></td>
<td><?= htmlspecialchars($row['lastname']) ?></td>
<td><?= htmlspecialchars($row['username']) ?></td>
<td><?= htmlspecialchars($row['email']) ?></td>
<td><?= $row['role'] ?></td>
<td><?= $row['createdAt'] ?></td>
<td class="actions">
    <a href="staff_form.php?edit=<?= $row['userID'] ?>">Edit</a>
    <a href="staff_list.php?delete=<?= $row['userID'] ?>" class="delete" onclick="return confirm('⚠️ Are you sure you want to delete this staff member? This cannot be undone.')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
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