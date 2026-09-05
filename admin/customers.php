<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

// Delete Customer
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if ($id > 0) {
        @mysqli_query($conn, "DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id='$id')");
        @mysqli_query($conn, "DELETE FROM orders WHERE user_id='$id'");
        mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
    }

    header("Location: customers.php");
    exit();
}

$successMsg = "";
$errorMsg = "";

// Add Customer POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_customer'])) {
    $name = trim($_POST['name'] ?? '');
    $regno = trim($_POST['regno'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $errorMsg = "Full Name, Email Address, and Password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Please enter a valid email address.";
    } elseif (strlen($password) < 4) {
        $errorMsg = "Password must be at least 4 characters long.";
    } else {
        $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
        if ($chk) {
            mysqli_stmt_bind_param($chk, "s", $email);
            mysqli_stmt_execute($chk);
            $cRes = mysqli_stmt_get_result($chk);
            if (mysqli_num_rows($cRes) > 0) {
                $errorMsg = "The email '" . htmlspecialchars($email) . "' is already registered.";
            }
            mysqli_stmt_close($chk);
        }

        if (empty($errorMsg)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = mysqli_prepare($conn, "INSERT INTO users (name, regno, department, email, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($ins) {
                mysqli_stmt_bind_param($ins, "sssss", $name, $regno, $department, $email, $hashed);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
                $successMsg = "Student '" . htmlspecialchars($name) . "' has been added successfully! They can now log in.";
            } else {
                $errorMsg = "Failed to add student: " . mysqli_error($conn);
            }
        }
    }
}

// Search
$search = "";
if (isset($_GET['search']) && trim($_GET['search']) != "") {
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));
    $query = mysqli_query($conn, "
        SELECT * FROM users
        WHERE name LIKE '%$search%'
           OR regno LIKE '%$search%'
           OR department LIKE '%$search%'
           OR email LIKE '%$search%'
        ORDER BY id DESC
    ");
} else {
    $query = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
}

$activePage = 'customers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - College Canteen Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_material.css">
</head>
<body>

<?php include("header_nav.php"); ?>

<main class="admin-container">

    <div class="admin-header-row" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 class="admin-page-title">
                <i class="fa-solid fa-users"></i> Registered Students & Customers
            </h1>
            <p class="admin-subtitle">View student account profiles, departments, and live registration records</p>
        </div>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <button type="button" onclick="openAddStudentModal()" class="btn-primary" style="background:#7c3aed; color:#fff; border:none; padding:10px 16px; border-radius:8px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-user-plus"></i> Add New Student
            </button>
            <a href="export_registrations_excel.php?format=csv" class="btn-primary" style="background:#16a34a; color:#fff; text-decoration:none; padding:10px 16px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-file-excel"></i> Export to Excel (.csv)
            </a>
            <button type="button" onclick="openExcelSyncModal()" class="btn-primary" style="background:#0284c7; color:#fff; border:none; padding:10px 16px; border-radius:8px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-arrows-rotate"></i> Connect Live to Excel
            </button>
            <form method="GET" class="search-container" style="margin:0;">
                <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8;"></i>
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search name, reg no..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                >
                <?php if (!empty($search)): ?>
                    <a href="customers.php" style="color:#94a3b8; text-decoration:none;"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (!empty($successMsg)): ?>
        <div style="padding:14px 18px; border-radius:10px; margin-bottom:20px; background:#dcfce7; color:#166534; border:1px solid #bbf7d0; display:flex; align-items:center; gap:10px; font-weight:500;">
            <i class="fa-solid fa-circle-check" style="font-size:18px;"></i>
            <span><?php echo $successMsg; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div style="padding:14px 18px; border-radius:10px; margin-bottom:20px; background:#fee2e2; color:#991b1b; border:1px solid #fecaca; display:flex; align-items:center; gap:10px; font-weight:500;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
            <span><?php echo $errorMsg; ?></span>
        </div>
    <?php endif; ?>

    <!-- STRUCTURED TABLE WITH RED-ORANGE HEADER -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="material-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Student Name</th>
                        <th>Register No</th>
                        <th>Department</th>
                        <th>Email Address</th>
                        <th style="text-align:right; width:250px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td>
                                <div style="font-weight:700; color:#0f172a; font-size:15px;"><?php echo htmlspecialchars($row['name']); ?></div>
                            </td>
                            <td>
                                <span style="font-family:monospace; font-weight:600; background:#f1f5f9; padding:3px 8px; border-radius:6px; color:#334155;">
                                    <?php echo htmlspecialchars($row['regno'] ?: 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="color:#475569; font-weight:500;">
                                    <?php echo htmlspecialchars($row['department'] ?: 'General'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="color:#2563eb; font-size:13px;">
                                    <i class="fa-regular fa-envelope" style="margin-right:4px;"></i><?php echo htmlspecialchars($row['email']); ?>
                                </span>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="customer_details.php?id=<?php echo $row['id']; ?>#securitySection" class="btn-material btn-primary" style="padding:6px 10px; font-size:12px; background:#0284c7; margin-right:4px;" title="Change Username or Password">
                                    <i class="fa-solid fa-key"></i> Edit / Password
                                </a>
                                <a href="customer_details.php?id=<?php echo $row['id']; ?>" class="btn-material btn-primary" style="padding:6px 10px; font-size:12px; margin-right:4px;">
                                    <i class="fa-solid fa-eye"></i> Details
                                </a>
                                <a href="customers.php?delete=<?php echo $row['id']; ?>" class="btn-material btn-danger" style="padding:6px 10px; font-size:12px;" onclick="return confirm('Delete this customer and all associated orders?');">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">
                                <i class="fa-solid fa-user-slash" style="font-size:36px; margin-bottom:10px; display:block; opacity:0.4;"></i>
                                No customers found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- EXCEL LIVE SYNC MODAL -->
    <div id="excelSyncModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:9999; backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:16px;">
        <div style="background:#ffffff; border-radius:16px; max-width:620px; width:100%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; animation:fadeIn 0.2s ease-out;">
            <div style="background:linear-gradient(135deg, #1e293b, #0f172a); color:#fff; padding:20px 24px; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="background:#16a34a; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                        <i class="fa-solid fa-file-excel"></i>
                    </div>
                    <div>
                        <h3 style="margin:0; font-size:18px; font-weight:700;">Live Excel Auto-Update Connection</h3>
                        <p style="margin:2px 0 0 0; font-size:13px; color:#94a3b8;">Keep Excel synchronized in real time with new registrations</p>
                    </div>
                </div>
                <button type="button" onclick="closeExcelSyncModal()" style="background:none; border:none; color:#cbd5e1; font-size:20px; cursor:pointer; padding:4px;">&times;</button>
            </div>

            <div style="padding:24px; max-height:80vh; overflow-y:auto;">
                <!-- METHOD 1: 1-Click IQY -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                        <h4 style="margin:0; color:#0f172a; font-size:15px; display:flex; align-items:center; gap:8px;">
                            <span style="background:#0284c7; color:#fff; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; font-size:12px;">1</span>
                            Method 1: One-Click Excel Web Query (.iqy)
                        </h4>
                        <span style="background:#dcfce7; color:#166534; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px;">Recommended</span>
                    </div>
                    <p style="margin:0 0 12px 0; font-size:13px; color:#64748b; line-height:1.5;">
                        Downloads an official Microsoft Excel Web Query file. When opened, Excel automatically creates a live link to the canteen database.
                    </p>
                    <a href="export_registrations_excel.php?format=iqy" class="btn-primary" style="background:#16a34a; color:#fff; text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-download"></i> Download Live Excel Connector (.iqy)
                    </a>
                </div>

                <!-- METHOD 2: Direct Power Query Web Feed -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:16px;">
                    <h4 style="margin:0 0 8px 0; color:#0f172a; font-size:15px; display:flex; align-items:center; gap:8px;">
                        <span style="background:#0284c7; color:#fff; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; font-size:12px;">2</span>
                        Method 2: Excel Power Query / "From Web" Feed
                    </h4>
                    <p style="margin:0 0 10px 0; font-size:13px; color:#64748b; line-height:1.5;">
                        Connect any existing Excel spreadsheet manually using Excel's built-in Web Data Connector.
                    </p>
                    <div style="display:flex; gap:8px; margin-bottom:12px;">
                        <input type="text" id="liveSyncUrlInput" readonly value="<?php 
                            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            echo htmlspecialchars($proto . $host . dirname($_SERVER['SCRIPT_NAME']) . '/export_registrations_excel.php?format=web&key=canteen_live_sync');
                        ?>" style="flex:1; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-family:monospace; font-size:12px; background:#fff; color:#334155;">
                        <button type="button" onclick="copyLiveSyncUrl()" id="copySyncBtn" style="background:#0f172a; color:#fff; border:none; padding:8px 14px; border-radius:6px; font-weight:600; font-size:12px; cursor:pointer;">
                            <i class="fa-regular fa-copy"></i> Copy
                        </button>
                    </div>
                    <div style="font-size:12px; color:#475569; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; line-height:1.6;">
                        <strong>Steps in Microsoft Excel:</strong>
                        <ol style="margin:6px 0 0 0; padding-left:18px;">
                            <li>Open Microsoft Excel &rarr; Click <strong>Data</strong> tab &rarr; <strong>From Web</strong>.</li>
                            <li>Paste the copied Live URL above and click <strong>OK</strong>.</li>
                            <li>Select <strong>Table 0</strong> and click <strong>Load</strong>.</li>
                            <li>Go to <strong>Data &rarr; Queries &amp; Connections &rarr; Properties</strong> &rarr; Check <em>"Refresh every 1 minute"</em> and <em>"Refresh data when opening the file"</em>.</li>
                        </ol>
                    </div>
                </div>

                <!-- METHOD 3: One-time CSV -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px;">
                    <h4 style="margin:0 0 8px 0; color:#0f172a; font-size:15px; display:flex; align-items:center; gap:8px;">
                        <span style="background:#64748b; color:#fff; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; font-size:12px;">3</span>
                        Method 3: Standard Offline Excel Snapshot (.csv)
                    </h4>
                    <p style="margin:0 0 10px 0; font-size:13px; color:#64748b; line-height:1.5;">
                        For quick offline analysis or printing. Does not auto-refresh.
                    </p>
                    <a href="export_registrations_excel.php?format=csv" class="btn-primary" style="background:#475569; color:#fff; text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-file-csv"></i> Download CSV File
                    </a>
                </div>
            </div>

            <div style="background:#f1f5f9; padding:12px 24px; text-align:right; border-top:1px solid #e2e8f0;">
                <button type="button" onclick="closeExcelSyncModal()" style="background:#94a3b8; color:#fff; border:none; padding:8px 18px; border-radius:8px; font-weight:600; cursor:pointer;">Close</button>
            </div>
        </div>
    </div>

    <!-- ADD NEW STUDENT MODAL -->
    <div id="addStudentModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:9999; backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:16px;">
        <div style="background:#ffffff; border-radius:16px; max-width:540px; width:100%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; animation:fadeIn 0.2s ease-out;">
            <div style="background:linear-gradient(135deg, #6d28d9, #4c1d95); color:#fff; padding:20px 24px; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="background:rgba(255,255,255,0.15); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div>
                        <h3 style="margin:0; font-size:18px; font-weight:700;">Add New Student</h3>
                        <p style="margin:2px 0 0 0; font-size:13px; color:#ddd6fe;">Create a student account with immediate login credentials</p>
                    </div>
                </div>
                <button type="button" onclick="closeAddStudentModal()" style="background:none; border:none; color:#ddd6fe; font-size:24px; cursor:pointer; padding:4px;">&times;</button>
            </div>

            <form method="POST" autocomplete="off" style="padding:24px;">
                <input type="hidden" name="action_add_customer" value="1">

                <div style="margin-bottom:16px;">
                    <label style="display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#334155;">Full Name <span style="color:#ef4444;">*</span></label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <i class="fa-solid fa-user" style="position:absolute; left:14px; color:#94a3b8; font-size:14px;"></i>
                        <input type="text" name="name" required placeholder="e.g. John Doe" style="width:100%; padding:10px 14px 10px 40px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; outline:none;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#334155;">Register Number</label>
                        <div style="position:relative; display:flex; align-items:center;">
                            <i class="fa-solid fa-graduation-cap" style="position:absolute; left:14px; color:#94a3b8; font-size:14px;"></i>
                            <input type="text" name="regno" placeholder="e.g. 13562" style="width:100%; padding:10px 14px 10px 40px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; outline:none;">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#334155;">Department</label>
                        <div style="position:relative; display:flex; align-items:center;">
                            <i class="fa-solid fa-building-columns" style="position:absolute; left:14px; color:#94a3b8; font-size:14px;"></i>
                            <input type="text" name="department" placeholder="e.g. BCA, CSE" style="width:100%; padding:10px 14px 10px 40px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; outline:none;">
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#334155;">Login Email Address <span style="color:#ef4444;">*</span></label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <i class="fa-solid fa-envelope" style="position:absolute; left:14px; color:#94a3b8; font-size:14px;"></i>
                        <input type="email" name="email" required placeholder="student@example.com" style="width:100%; padding:10px 14px 10px 40px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; outline:none;">
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#334155;">Initial Password <span style="color:#ef4444;">*</span></label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <i class="fa-solid fa-lock" style="position:absolute; left:14px; color:#94a3b8; font-size:14px;"></i>
                        <input type="password" id="modal_student_password" name="password" required minlength="4" placeholder="Enter student password" style="width:100%; padding:10px 40px 10px 40px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; outline:none;">
                        <button type="button" onclick="togglePasswordVisibility('modal_student_password', this)" style="position:absolute; right:12px; background:none; border:none; color:#94a3b8; cursor:pointer; font-size:14px; padding:4px;">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #e2e8f0; padding-top:16px;">
                    <button type="button" onclick="closeAddStudentModal()" style="background:#e2e8f0; color:#475569; border:none; padding:10px 18px; border-radius:8px; font-weight:600; cursor:pointer;">Cancel</button>
                    <button type="submit" style="background:#7c3aed; color:#fff; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-user-plus"></i> Create Student
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openAddStudentModal() {
        const modal = document.getElementById('addStudentModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeAddStudentModal() {
        const modal = document.getElementById('addStudentModal');
        if (modal) modal.style.display = 'none';
    }

    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }

    function openExcelSyncModal() {
        const modal = document.getElementById('excelSyncModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeExcelSyncModal() {
        const modal = document.getElementById('excelSyncModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Close on backdrop click
    window.addEventListener('click', function(e) {
        const m1 = document.getElementById('excelSyncModal');
        const m2 = document.getElementById('addStudentModal');
        if (e.target === m1) closeExcelSyncModal();
        if (e.target === m2) closeAddStudentModal();
    });

    function copyLiveSyncUrl() {
        const input = document.getElementById('liveSyncUrlInput');
        const btn = document.getElementById('copySyncBtn');
        if (!input) return;
        
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(function() {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
            btn.style.background = '#16a34a';
            setTimeout(function() {
                btn.innerHTML = originalHTML;
                btn.style.background = '#0f172a';
            }, 2000);
        }).catch(function() {
            // Fallback
            document.execCommand('copy');
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
            btn.style.background = '#16a34a';
            setTimeout(function() {
                btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy';
                btn.style.background = '#0f172a';
            }, 2000);
        });
    }
    </script>

</main>

</body>
</html>