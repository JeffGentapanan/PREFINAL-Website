<?php
session_start();

if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'staff') {
    header("Location: login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "jjrmeditrack_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Instead of deleting, mark as inactive
    $stmt = $conn->prepare("UPDATE medicines SET status = 'inactive' WHERE medicineID = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo "<script>
                alert('Medicine successfully removed from active list.');
                window.location.href='staff_dashboard.php';
              </script>";
    } else {
        echo "<script>
                alert('Error: Unable to update medicine status.');
                window.location.href='staff_dashboard.php';
              </script>";
    }

    $stmt->close();
}

$conn->close();
?>
