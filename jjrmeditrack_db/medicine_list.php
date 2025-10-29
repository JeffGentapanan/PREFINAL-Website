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
    // In a production environment, you might log this instead of showing it to the user.
    die("Connection failed: " . mysqli_connect_error());
}

// --- Handle INLINE UPDATE MEDICINE LOGIC (AJAX POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_inline') {
    $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

    // Sanitize and validate inputs
    $id = filter_var($_POST['medicineID'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $name = trim($_POST['name'] ?? '');
    $manufacture = trim($_POST['manufacture'] ?? '');
    $stockquantity = filter_var($_POST['stockquantity'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    // Ensure decimal dot handled
    $price_raw = str_replace(',', '', $_POST['price'] ?? '0');
    $price = filter_var($price_raw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    // Basic input validation
    if (empty($name) || empty($manufacture) || $stockquantity < 0 || $price < 0 || $id <= 0) {
        $response['message'] = 'Invalid input. Please check all fields and ensure Name/Manufacturer are not empty.';
    } else {
        // Use prepared statement to safely update the database
        $stmt_upd = $conn->prepare("UPDATE medicines SET name = ?, manufacture = ?, stockquantity = ?, price = ? WHERE medicineID = ?");
        if ($stmt_upd) {
            $stmt_upd->bind_param("ssidi", $name, $manufacture, $stockquantity, $price, $id);

            if ($stmt_upd->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Medicine **' . htmlspecialchars($name) . '** updated successfully!';
            } else {
                $response['message'] = 'Database error: ' . $stmt_upd->error;
            }
            $stmt_upd->close();
        } else {
            $response['message'] = 'Database prepare error: ' . $conn->error;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    $conn->close();
    exit(); // Terminate script after sending JSON response
}
// --------------------------------------------------------

// --- Handle DEACTIVATE MEDICINE LOGIC (AJAX POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_medicine') {
    $response = ['status' => 'error', 'message' => 'Invalid request.'];

    $id = intval($_POST['medicineID'] ?? 0);

    if ($id > 0) {
        // Fetch image before inactivating (optional)
        $stmt_sel = $conn->prepare("SELECT image_path FROM medicines WHERE medicineID = ?");
        if ($stmt_sel) {
            $stmt_sel->bind_param("i", $id);
            $stmt_sel->execute();
            $res_sel = $stmt_sel->get_result();
            $path_to_delete = '';
            if ($res_sel && $res_sel->num_rows > 0) {
                $path_to_delete = $res_sel->fetch_assoc()['image_path'];
            }
            $stmt_sel->close();
        }

        // Mark as inactive instead of deleting
        $stmt = $conn->prepare("UPDATE medicines SET status = 'inactive' WHERE medicineID = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Medicine successfully marked as inactive.';
                // Optionally delete image file:
                // if (!empty($path_to_delete) && $path_to_delete != 'placeholder.jpg' && file_exists($path_to_delete)) {
                //     unlink($path_to_delete);
                // }
            } else {
                $response['message'] = 'Database error while updating status.';
            }
            $stmt->close();
        } else {
            $response['message'] = 'Database prepare error: ' . $conn->error;
        }
    } else {
        $response['message'] = 'Invalid medicine ID.';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// --------------------------------------------------------

$message = '';

// --- Display Success Message from Add Page ---
if (isset($_GET['status']) && $_GET['status'] == 'added' && isset($_GET['name'])) {
    $message = '<div class="alert success">Medicine **' . htmlspecialchars($_GET['name']) . '** added to inventory successfully!</div>';
}

// --- SEARCH & FILTER LOGIC (UPDATED TO USE manufacture) ---
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'All';

$whereClauses = [];
$queryParams = [];
$queryTypes = '';

// Always show only active items in main list
$whereClauses[] = "status = 'active'";

if ($category != 'All' && !empty($category)) {
    $whereClauses[] = "category = ?";
    $queryParams[] = $category;
    $queryTypes .= 's';
}

if (!empty($search)) {
    // Search by medicine name OR manufacturer
    $whereClauses[] = "(name LIKE ? OR manufacture LIKE ?)";
    $queryParams[] = "%{$search}%";
    $queryParams[] = "%{$search}%";
    $queryTypes .= 'ss';
}

$whereSQL = count($whereClauses) > 0 ? "WHERE " . implode(' AND ', $whereClauses) : '';

// --- FETCH MEDICINES (UPDATED TO USE manufacture) ---
$sql = "SELECT medicineID, name, manufacture, stockquantity, price, image_path FROM medicines {$whereSQL} ORDER BY name ASC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($queryTypes)) {
    // Dynamically call bind_param
    $stmt->bind_param($queryTypes, ...$queryParams);
}

$stmt->execute();
$result = $stmt->get_result();

// --- FETCH CATEGORY TOTALS (For the cards) ---
$totals = [];
$totalAll = 0;

$totalsResult = $conn->query("SELECT category, COUNT(medicineID) AS count FROM medicines GROUP BY category");
while ($row = $totalsResult->fetch_assoc()) {
    $totals[$row['category']] = $row['count'];
}

$totalAll = $conn->query("SELECT COUNT(*) AS total FROM medicines")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Medicine Inventory</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* (Your original CSS preserved) */
body { font-family: 'Inter', sans-serif; margin: 0; background: #f6fff6; color: #222; padding-bottom: 70px; /* Space for fixed nav */ }

/* Header & Back Button Styles */
header { 
    background: #47d16b; 
    padding: 14px 18px; 
    font-weight: bold; 
    font-size: 20px; 
    color: #000; 
    display: flex; 
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
    transition: background 0.2s;
}
.header-btn-back:hover {
    background: #2f7d38; 
}

.header-title { flex-grow: 1; text-align: center; color: #000; font-weight: bold; font-size: 20px; margin-right: 120px; }
.container { width: 95%; max-width: 1000px; margin: 18px auto; text-align: center; }

/* Search & Categories */
.search-bar { width: 100%; margin: 20px 0; }
.search-bar form { 
    display: flex; 
    width: 100%;
    /* Flex alignment added for future additions, currently just centers the input */
}
.search-bar input[type=text] {
    width: 100%; 
    padding: 12px 15px; 
    border-radius: 25px; 
    border: 1px solid #ccc; 
    font-size: 16px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    box-sizing: border-box;
}

.category-nav { display: flex; justify-content: start; gap: 15px; margin-bottom: 25px; border-bottom: 2px solid #d8f0db; padding-bottom: 5px; overflow-x: auto; }
.category-nav a { 
    padding: 8px 0; /* Adjusted padding to rely on gap for horizontal spacing */
    text-decoration: none; 
    color: #555; 
    font-weight: 600; 
    border-bottom: 3px solid transparent; 
    white-space: nowrap;
    transition: color 0.2s, border-bottom 0.2s;
}
.category-nav a.active { border-bottom: 3px solid #0f8e33; color: #0f8e33; }
.category-nav a:hover:not(.active) { color: #3e9b4a; }

.card-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 30px; }
@media (min-width: 768px) { .card-grid { grid-template-columns: repeat(4, 1fr); } }

.cat-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; display: flex; align-items: center; text-align: left; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-decoration: none; color: inherit; transition: transform 0.1s, box-shadow 0.1s; }
.cat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.card-image-icon { width: 50px; height: 50px; background: #d4f7d4; border-radius: 8px; margin-right: 15px; display: flex; align-items: center; justify-content: center; color: #0f8e33; font-size: 22px; }
.card-info { font-size: 16px; color: #000; font-weight: bold; }
.card-info small { display: block; font-size: 14px; color: #555; font-weight: normal; }

.table-controls { display: flex; justify-content: flex-end; margin-bottom: 15px; }
.add-btn { padding: 8px 15px; background: #d4f7d4; color: #0f8e33; text-decoration: none; border-radius: 20px; font-weight: 600; border: 1px solid #0f8e33; display: inline-flex; align-items: center; transition: background 0.2s; }
.add-btn:hover { background: #c3e6c3; }

table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
table th, table td { padding: 12px 10px; text-align: left; font-size: 14px; border-bottom: 1px solid #a8d9a8; }
table th { background: #3e9b4a; color: white; font-weight: 700; border-bottom: 2px solid #2f7d38; }
table th:first-child { border-top-left-radius: 8px; }
table th:last-child { border-top-right-radius: 8px; }
table tr:nth-child(even) { background-color: #f0fff0; }
table tr:last-child td { border-bottom: none; }

.medicine-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; display: block; margin: 0 auto; }

td[contenteditable="true"] { background-color: #ffffe0; outline: 2px solid #47d16b; border-radius: 4px; cursor: text; transition: background-color 0.2s; }
.actions { display: flex; gap: 8px; align-items: center; white-space: nowrap; }
.actions button { text-decoration: none; font-weight: 600; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; border: none; transition: background 0.2s, color 0.2s; }
.actions button.edit-btn { background: #e6e6e6; color: #444; }
.actions button.edit-btn:hover { background: #d4d4d4; }
.actions button.save-btn { background: #0f8e33; color: white; }
.actions button.save-btn:hover { background: #0b6b27; }
.actions button.delete-btn { background: #d42d2d; color: white; }
.actions button.delete-btn:hover { background: #b41f1f; }

#statusMessage { position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 1000; width: 90%; max-width: 500px; pointer-events: none; }

.alert { padding: 10px; margin-bottom: 0; border-radius: 8px; font-weight: bold; }
.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); padding-top: 50px; }
.modal-content { background-color: #fefefe; margin: 10% auto; padding: 25px; border: 1px solid #ccc; width: 90%; max-width: 400px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); text-align: center; }
.modal-content h3 { color: #d42d2d; margin-top: 0; }
.modal-actions { margin-top: 20px; display: flex; justify-content: space-around; gap: 10px; }
.modal-actions button, .modal-actions a { padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; transition: background 0.2s; border: none; }
.modal-cancel-btn { background: #e6e6e6; color: #222; }
.modal-confirm-btn { background: #d42d2d; color: white; }
.modal-cancel-btn:hover { background: #d4d4d4; }
.modal-confirm-btn:hover { background: #b41f1f; }

.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#47d16b;display:flex;justify-content:space-around;padding:10px 0;box-shadow: 0 -2px 5px rgba(0,0,0,0.1);}
.bottom-nav a{color:white;text-decoration:none;font-size:14px;text-align:center;padding:5px 0; flex-grow: 1;}
.bottom-nav a:hover{background-color: #3e9b4a;}

.hidden { display: none; }
</style>
</head>
<body>

<header>
    <!-- Back Button to Dashboard -->
    <a href="staff_dashboard.php" class="header-btn-back">⬅️ Dashboard</a>
    
    <span class="header-title">Medicine Inventory</span>
    
    <span></span> <!-- Placeholder for layout balance -->
</header>

<div class="container">
    <!-- Status Message Container (for AJAX and PHP messages) -->
    <div id="statusMessage">
        <?php echo $message; // Display initial PHP status messages ?>
    </div>
    
    <!-- Search Bar -->
    <div class="search-bar">
        <form method="GET">
            <input type="text" name="search" placeholder="🔍 Search for medicine by name or manufacturer..." value="<?= htmlspecialchars($search) ?>">
            <!-- Keep the current category filter active during search -->
            <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>"> 
        </form>
    </div>

    <!-- Category Filter Tabs -->
    <div class="category-nav">
        <!-- The default category=All link -->
        <a href="?category=All<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="<?= $category == 'All' || $category == '' ? 'active' : '' ?>">All</a>
        
        <?php foreach (['Vitamins', 'Pain Relief', 'Prescription'] as $cat): ?>
            <a href="?category=<?= urlencode($cat) ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="<?= $category == $cat ? 'active' : '' ?>">
                <?= htmlspecialchars($cat) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Category Cards -->
    <div class="card-grid">
        <?php
        $displayCards = [
            'All Inventory' => ['Total' => $totalAll, 'icon' => '📦', 'link' => 'All'],
            'Vitamins' => ['Total' => $totals['Vitamins'] ?? 0, 'icon' => '💊', 'link' => 'Vitamins'],
            'Pain Relief' => ['Total' => $totals['Pain Relief'] ?? 0, 'icon' => '🤕', 'link' => 'Pain Relief'],
            'Prescription' => ['Total' => $totals['Prescription'] ?? 0, 'icon' => '🩺', 'link' => 'Prescription'],
        ];
        
        foreach ($displayCards as $catName => $data):
        ?>
        <a href="?category=<?= urlencode($data['link']) ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="cat-card">
            <div class="card-image-icon"><?= $data['icon'] ?></div>
            <div class="card-info">
                <?= htmlspecialchars($catName) ?>
                <small>Items: <?= $data['Total'] ?></small>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Medicine Table Header & Add Button -->
    <div class="table-controls">
        <a href="add_medicine.php" class="add-btn">➕ Add Medicine</a>
    </div>

    <!-- Medicine List Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 50px; text-align: center;">Image</th>
                <th style="width: 25%;">Medicine Name</th>
                <th style="width: 25%;">Manufacturer</th>
                <th style="width: 10%; text-align: center;">Qty</th>
                <th style="width: 10%;">Price</th>
                <th style="width: 20%; text-align: center;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr data-id="<?= $row['medicineID'] ?>">
                    <td style="text-align: center;">
                        <img src="<?= htmlspecialchars($row['image_path'] ?? 'placeholder.jpg') ?>" 
                             alt="<?= htmlspecialchars($row['name']) ?>" 
                             class="medicine-img"
                             onerror="this.onerror=null; this.src='https://placehold.co/40x40/ccc/white?text=No+Img';">
                    </td>
                    <td contenteditable="false" data-field="name" data-type="text"><?= htmlspecialchars($row['name']) ?></td>
                    <td contenteditable="false" data-field="manufacture" data-type="text"><?= htmlspecialchars($row['manufacture'] ?? 'N/A') ?></td>
                    <td contenteditable="false" data-field="stockquantity" data-type="number" style="text-align: center;"><?= $row['stockquantity'] ?></td>
                    <td contenteditable="false" data-field="price" data-type="price">₱ <span data-value="<?= $row['price'] ?>"><?= number_format($row['price'], 2) ?></span></td>
                    <td class="actions" style="text-align: center;">
                        <!-- 'Edit' button toggles contenteditable state for the row -->
                        <button onclick="toggleEdit(<?= $row['medicineID'] ?>, this)" class="edit-btn" title="Edit Row">✏️ Edit</button>
                        <!-- 'Save' button is hidden until editing starts -->
                        <button onclick="saveInlineEdit(<?= $row['medicineID'] ?>, this)" class="save-btn hidden" title="Save Changes">💾 Save</button>
                        <!-- Delete Button (triggers modal) -->
                        <button onclick="openDeleteModal(<?= $row['medicineID'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')" class="delete-btn" title="Delete Row">🗑️ Delete</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">No medicines found matching your search/filter.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="bottom-nav">
    <a href="staff_dashboard.php">🏠 Home</a>
    <a href="medicine_list.php">💊 Medicine</a>
    <a href="staff_list.php">👨‍⚕️ Staff</a>
    <a href="settings.php">⚙️ Settings</a>
</div>

<!-- Custom Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h3>Confirm Action</h3>
        <p id="modal-text">Are you sure you want to mark this medicine as inactive?</p>
        <div class="modal-actions">
            <button id="cancelDelete" class="modal-cancel-btn">Cancel</button>
            <button id="confirmDelete" class="modal-confirm-btn">Continue</button>
        </div>
    </div>
</div>

<script>
/**
 * Displays a temporary status message (success or error).
 * @param {string} message - The message content.
 * @param {string} type - 'success' or 'error'.
 */
function showStatus(message, type) {
    const statusDiv = document.getElementById('statusMessage');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${type}`;
    alertDiv.innerHTML = message;

    statusDiv.innerHTML = ''; // Clear previous messages
    statusDiv.appendChild(alertDiv);

    // Automatically remove the message after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// --- Delete Modal Functions ---
const deleteModal = document.getElementById('deleteModal');
const confirmDeleteBtn = document.getElementById('confirmDelete');
const cancelDeleteBtn = document.getElementById('cancelDelete');

let deletingId = null;

function openDeleteModal(id, name) {
    const p = deleteModal.querySelector('#modal-text');
    // Sanitize the name before injecting into HTML
    const safeName = new DOMParser().parseFromString(name, 'text/html').body.textContent;
    p.innerHTML = `Are you sure you want to mark <strong>${safeName}</strong> as inactive? This will hide it from the active list but keep historical records intact.`;
    deletingId = id;
    deleteModal.style.display = 'block';
}

cancelDeleteBtn.onclick = function() {
    deletingId = null;
    deleteModal.style.display = 'none';
}

// Close the modal if the user clicks anywhere outside of it
window.onclick = function(event) {
    if (event.target == deleteModal) {
        deletingId = null;
        deleteModal.style.display = 'none';
    }
}

// Confirm button triggers AJAX to mark inactive
confirmDeleteBtn.onclick = async function() {
    if (!deletingId) return;
    deleteModal.style.display = 'none';

    try {
        const formData = new FormData();
        formData.append('action', 'delete_medicine');
        formData.append('medicineID', deletingId);

        const resp = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();

        if (data.status === 'success') {
            // Remove row visually
            const row = document.querySelector(`tr[data-id="${deletingId}"]`);
            if (row) row.remove();

            showStatus(data.message, 'success');
        } else {
            showStatus(data.message || 'Could not mark as inactive.', 'error');
        }
    } catch (err) {
        console.error(err);
        showStatus('Network error while marking inactive.', 'error');
    } finally {
        deletingId = null;
    }
};
// --- End Delete Modal Functions ---


/**
 * Toggles the contenteditable state for all editable cells in a row.
 * @param {number} id - The medicineID of the row.
 * @param {HTMLElement} button - The button element clicked (Edit/Cancel).
 */
function toggleEdit(id, button) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    const isEditing = row.getAttribute('data-editing') === 'true';
    const saveButton = row.querySelector('.save-btn');
    const deleteButton = row.querySelector('.delete-btn');
    
    // Select all cells that are meant to be editable
    const editableCells = row.querySelectorAll('td[data-field]');

    if (!isEditing) {
        // Start editing
        row.setAttribute('data-editing', 'true');
        editableCells.forEach(cell => {
            cell.setAttribute('contenteditable', 'true');
            
            // Store original value for revert
            let value;
            if (cell.dataset.type === 'price') {
                // For price, store the numerical value
                const span = cell.querySelector('span[data-value]');
                value = span ? span.dataset.value : '0.00';
            } else {
                value = cell.textContent.trim();
            }
            cell.dataset.originalValue = value;
        });

        button.textContent = '❌ Cancel';
        button.title = 'Cancel Edit';
        button.classList.add('edit-btn'); // Maintain edit-btn for styling consistency
        
        saveButton.classList.remove('hidden');
        deleteButton.classList.add('hidden'); // Hide delete button during edit

        // Focus on the first editable field
        if (editableCells.length > 0) {
            editableCells[0].focus();
        }

    } else {
        // Cancel editing (revert changes)
        row.setAttribute('data-editing', 'false');
        editableCells.forEach(cell => {
            cell.setAttribute('contenteditable', 'false');
            let originalValue = cell.dataset.originalValue;

            if (cell.dataset.type === 'price') {
                // Revert price display
                const span = cell.querySelector('span[data-value]');
                if (span) {
                    span.textContent = parseFloat(originalValue).toFixed(2);
                    // Ensure the content of the cell (what user sees) is correctly displayed
                    cell.childNodes.forEach(node => {
                        if (node.nodeType === 3) node.remove(); // Remove old text nodes
                    });
                    cell.prepend('₱ ');
                }
            } else {
                cell.textContent = originalValue;
            }
        });

        button.textContent = '✏️ Edit';
        button.title = 'Edit Row';
        saveButton.classList.add('hidden');
        deleteButton.classList.remove('hidden'); // Show delete button
    }
}


/**
 * Sends an AJAX request to update the medicine row.
 * @param {number} id - The medicineID of the row.
 * @param {HTMLElement} button - The save button element clicked.
 */
async function saveInlineEdit(id, button) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    const editButton = row.querySelector('.edit-btn');
    
    // Prepare data payload
    const data = {
        action: 'update_inline',
        medicineID: id
    };
    
    let isValid = true;

    // Collect data from editable cells
    row.querySelectorAll('td[data-field]').forEach(cell => {
        const field = cell.dataset.field;
        let value = cell.textContent.trim();
        
        // Validation and Value extraction
        if (field === 'price') {
             // Remove '₱' and non-numeric characters (except .)
            value = value.replace(/[^0-9.]/g, ''); 
            if (isNaN(parseFloat(value)) || parseFloat(value) < 0) {
                isValid = false;
                showStatus('Price must be a non-negative number.', 'error');
                return;
            }
        } else if (field === 'stockquantity') {
            if (isNaN(parseInt(value)) || parseInt(value) < 0) {
                isValid = false;
                showStatus('Quantity must be a non-negative whole number.', 'error');
                return;
            }
        } else if (field === 'name' || field === 'manufacture') {
            if (value === '') {
                isValid = false;
                showStatus(`${field.charAt(0).toUpperCase() + field.slice(1)} cannot be empty.`, 'error');
                return;
            }
        }
        
        data[field] = value;
    });

    if (!isValid) return;

    // Visually disable the save button during submission
    const originalBtnText = button.textContent;
    button.textContent = 'Saving...';
    button.disabled = true;

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data).toString()
        });

        const result = await response.json();

        if (result.status === 'success') {
            showStatus(result.message, 'success');
            
            // --- Success Actions ---
            // 1. Update the original values and formatting
            row.querySelectorAll('td[data-field]').forEach(cell => {
                const field = cell.dataset.field;
                if (cell.dataset.type === 'price') {
                    // Update display and stored data
                    const numericValue = parseFloat(data.price).toFixed(2);
                    const span = cell.querySelector('span[data-value]');
                    if (span) {
                        span.dataset.value = numericValue;
                        span.textContent = numericValue;
                    } else {
                        cell.innerHTML = `₱ <span data-value="${numericValue}">${numericValue}</span>`;
                    }
                    cell.dataset.originalValue = numericValue;
                    // Ensure '₱ ' prefix is present
                    cell.childNodes.forEach(node => {
                        if (node.nodeType === 3) node.remove(); // Remove old text nodes
                    });
                    cell.prepend('₱ ');
                } else {
                    cell.dataset.originalValue = data[field];
                    cell.textContent = data[field];
                }
            });

            // 2. Automatically switch back to view mode
            toggleEdit(id, editButton);

        } else {
            showStatus(result.message, 'error');
            // Revert back to cancel state so user can retry/cancel
            editButton.textContent = '❌ Cancel';
        }

    } catch (error) {
        console.error('Update failed:', error);
        showStatus('A network or server error occurred during update.', 'error');
        // Revert back to cancel state so user can retry/cancel
        editButton.textContent = '❌ Cancel';
    } finally {
        // Re-enable and reset the save button
        button.textContent = originalBtnText;
        button.disabled = false;
    }
}
</script>

</body>
</html>

<?php
// Clean up: close statement and connection if present
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();
?>
