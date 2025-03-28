<?php
session_start();
include '../data/connect.php';
include '../data/auth.php';

// Use the auth function to check admin access
checkAdminAccess();

$email = $_SESSION['email'];
// Get admin info for display
$sql = "SELECT role, firstName, lastName FROM users WHERE email=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Create a display name from firstName and lastName
$displayName = ($user) ? $user['firstName'] . ' ' . $user['lastName'] : $email;

// Initialize variables
$editUser = [];
$errors = [];
$success = "";

// Check if user ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $userId = $_GET['id'];
    
    // Get user details
    $sqlUser = "SELECT * FROM users WHERE id = ?";
    $stmtUser = $conn->prepare($sqlUser);
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();
    
    if ($resultUser->num_rows > 0) {
        $editUser = $resultUser->fetch_assoc();
    } else {
        // Redirect to manage users if user not found
        header("Location: manage_users.php?error=User not found");
        exit;
    }
} else {
    // Redirect to manage users if no ID provided
    header("Location: manage_users.php?error=No user specified");
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $updateEmail = trim($_POST['email']);
    $role = $_POST['role'];
    $updatePassword = trim($_POST['password']);
    
    // Validate required fields
    if (empty($firstName)) $errors[] = "First name is required";
    if (empty($lastName)) $errors[] = "Last name is required";
    if (empty($updateEmail)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($updateEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email exists for another user
    if ($updateEmail !== $editUser['email']) {
        $checkEmailSql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $checkEmailStmt = $conn->prepare($checkEmailSql);
        $checkEmailStmt->bind_param("si", $updateEmail, $userId);
        $checkEmailStmt->execute();
        $checkEmailResult = $checkEmailStmt->get_result();
        
        if ($checkEmailResult->num_rows > 0) {
            $errors[] = "Email already exists for another user";
        }
    }
    
    // If no errors, proceed with database update
    if (empty($errors)) {
        if (!empty($updatePassword)) {
            // Update with new password (MD5 hashed)
            $hashedPassword = md5($updatePassword);
            $sqlUpdate = "UPDATE users SET firstName=?, lastName=?, email=?, role=?, password=? WHERE id=?";
            $updateStmt = $conn->prepare($sqlUpdate);
            $updateStmt->bind_param("sssssi", $firstName, $lastName, $updateEmail, $role, $hashedPassword, $userId);
        } else {
            // Update without changing password
            $sqlUpdate = "UPDATE users SET firstName=?, lastName=?, email=?, role=? WHERE id=?";
            $updateStmt = $conn->prepare($sqlUpdate);
            $updateStmt->bind_param("ssssi", $firstName, $lastName, $updateEmail, $role, $userId);
        }
        
        if ($updateStmt->execute()) {
            $success = "User updated successfully!";
            
            // Refresh user data from database
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();
            $editUser = $resultUser->fetch_assoc();
        } else {
            $errors[] = "Error: " . $updateStmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin Dashboard</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/modern.css">
    
    <style>
        /* Using the same styles as manage_cars.php */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            width: 250px;
            background-color: #2d3748;
            color: #fff;
            transition: all 0.3s;
            position: fixed;
            height: 100%;
            z-index: 1000;
        }
        
        .admin-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .admin-logo {
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu-item {
            margin-bottom: 5px;
        }
        
        .sidebar-menu-link {
            padding: 10px 20px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }
        
        .sidebar-menu-link:hover, .sidebar-menu-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
        }
        
        .admin-main {
            flex: 1;
            margin-left: 250px;
            transition: all 0.3s;
        }
        
        .form-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-body {
            padding: 30px;
        }
        
        .field-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .required-asterisk {
            color: #e53e3e;
            margin-left: 3px;
        }
        
        .form-hint {
            font-size: 0.8rem;
            color: #718096;
            margin-top: 5px;
        }
        
        .user-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #a0aec0;
            margin: 0 auto 20px;
            overflow: hidden;
        }
        
        .role-badge {
            padding: 10px 15px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            margin-right: 10px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        
        .role-badge.admin {
            background-color: rgba(237, 100, 100, 0.1);
            color: #e53e3e;
        }
        
        .role-badge.user {
            background-color: rgba(72, 187, 120, 0.1);
            color: #48bb78;
        }
        
        .role-badge.selected {
            border-color: currentColor;
        }
        
        .password-toggle {
            cursor: pointer;
        }
        
        @media (max-width: 992px) {
            .admin-sidebar {
                left: -250px;
            }
            
            .admin-sidebar.show {
                left: 0;
            }
            
            .admin-main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper" id="adminWrapper">
        <!-- Admin Sidebar -->
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="admin-sidebar-header">
                <a href="admin.php" class="admin-logo">
                    <i class="fas fa-car"></i>
                    <span>CarRental</span> Admin
                </a>
            </div>
            
            <nav class="admin-sidebar-menu">
                <div class="sidebar-menu-item">
                    <a href="admin.php" class="sidebar-menu-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <div class="sidebar-menu-item">
                    <a href="manage_cars.php" class="sidebar-menu-link">
                        <i class="fas fa-car"></i>
                        <span>Manage Cars</span>
                    </a>
                </div>
                
                <div class="sidebar-menu-item">
                    <a href="manage_users.php" class="sidebar-menu-link active">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </a>
                </div>
                
                <div class="sidebar-menu-item">
                    <a href="manage_payments.php" class="sidebar-menu-link">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Manage Payments</span>
                    </a>
                </div>
                
                <div class="sidebar-menu-item">
                    <a href="#" class="sidebar-menu-link has-submenu" id="bookingsMenu">
                        <i class="fas fa-calendar-check"></i>
                        <span>Bookings</span>
                    </a>
                </div>
                
                <div class="sidebar-menu-item">
                    <a href="#" class="sidebar-menu-link has-submenu" id="reportsMenu">
                        <i class="fas fa-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="sidebar-menu-item">
                    <a href="#" class="sidebar-menu-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                
                <div class="sidebar-menu-item mt-4">
                    <a href="../data/logout.php" class="sidebar-menu-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content Area -->
        <main class="admin-main">
            <!-- Top Bar -->
            <div class="admin-topbar">
                <button class="toggle-sidebar" id="toggleSidebar">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="admin-user">
                    <div class="admin-user-info">
                        <div class="admin-user-name"><?php echo htmlspecialchars($displayName); ?></div>
                        <div class="admin-user-role"><?php echo htmlspecialchars($user['role']); ?></div>
                    </div>
                    <div class="admin-user-avatar">
                        <img src="../images/profile-pic.png" alt="Admin">
                    </div>
                </div>
            </div>
            
            <div class="admin-content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0"><i class="fas fa-user-edit me-2"></i>Edit User</h1>
                        <p class="text-muted">Update user account information</p>
                    </div>
                    <div>
                        <a href="manage_users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Users
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Edit User Form -->
                <div class="form-card">
                    <div class="form-header">
                        <h5 class="mb-0">User Information</h5>
                    </div>
                    <div class="form-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $userId); ?>" method="post">
                            <div class="row">
                                <!-- Left Column -->
                                <div class="col-md-4 mb-4 mb-md-0 text-center">
                                    <div class="user-avatar">
                                        <?php 
                                        // Display initials as avatar placeholder
                                        $initials = mb_substr($editUser['firstName'], 0, 1) . mb_substr($editUser['lastName'], 0, 1);
                                        echo htmlspecialchars(strtoupper($initials));
                                        ?>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5><?php echo htmlspecialchars($editUser['firstName'] . ' ' . $editUser['lastName']); ?></h5>
                                        <p class="text-muted"><?php echo htmlspecialchars($editUser['email']); ?></p>
                                        <span class="badge bg-<?php echo $editUser['role'] === 'admin' ? 'danger' : 'success'; ?>">
                                            <?php echo ucfirst(htmlspecialchars($editUser['role'])); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="text-start mb-4">
                                        <div class="field-group">
                                            <label class="form-label">User Role</label>
                                            <div>
                                                <label class="role-badge admin <?php echo $editUser['role'] === 'admin' ? 'selected' : ''; ?>">
                                                    <input type="radio" name="role" value="admin" <?php echo $editUser['role'] === 'admin' ? 'checked' : ''; ?> 
                                                           class="d-none">
                                                    <i class="fas fa-user-shield"></i> Admin
                                                </label>
                                                <label class="role-badge user <?php echo $editUser['role'] === 'user' ? 'selected' : ''; ?>">
                                                    <input type="radio" name="role" value="user" <?php echo $editUser['role'] === 'user' ? 'checked' : ''; ?> 
                                                           class="d-none">
                                                    <i class="fas fa-user"></i> User
                                                </label>
                                            </div>
                                            <div class="form-hint">Select the user's role and permissions level</div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-start">
                                        <p class="mb-1 text-muted small">Account created on:</p>
                                        <p class="mb-0 fw-bold"><?php echo isset($editUser['created_at']) ? date('M d, Y', strtotime($editUser['created_at'])) : 'N/A'; ?></p>
                                    </div>
                                </div>
                                
                                <!-- Right Column -->
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="field-group">
                                                <label for="firstName" class="form-label">First Name <span class="required-asterisk">*</span></label>
                                                <input type="text" class="form-control" id="firstName" name="firstName" 
                                                       value="<?php echo htmlspecialchars($editUser['firstName']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="field-group">
                                                <label for="lastName" class="form-label">Last Name <span class="required-asterisk">*</span></label>
                                                <input type="text" class="form-control" id="lastName" name="lastName" 
                                                       value="<?php echo htmlspecialchars($editUser['lastName']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="field-group">
                                        <label for="email" class="form-label">Email Address <span class="required-asterisk">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($editUser['email']); ?>" required>
                                    </div>
                                    
                                    <div class="field-group">
                                        <label for="password" class="form-label">Reset Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Leave blank to keep current password">
                                            <button class="btn btn-outline-secondary password-toggle" type="button">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-hint">Only fill this out if you want to change the user's password</div>
                                    </div>
                                    
                                    <div class="alert alert-info" role="alert">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <i class="fas fa-info-circle fa-2x"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1">Important Note</h5>
                                                <p class="mb-0 small">
                                                    Changing a user's role will affect their permissions and access to certain features.
                                                    If you change a user from admin to regular user, they will lose access to the admin dashboard.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="d-flex justify-content-between">
                                <a href="delete_user.php?id=<?php echo $userId; ?>" class="btn btn-outline-danger" 
                                   onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                    <i class="fas fa-trash me-2"></i>Delete User
                                </a>
                                
                                <div class="d-flex gap-2">
                                    <a href="manage_users.php" class="btn btn-light px-4">Cancel</a>
                                    <button type="submit" class="btn btn-primary px-5">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Toggle sidebar functionality
        const toggleSidebar = document.getElementById('toggleSidebar');
        const adminWrapper = document.getElementById('adminWrapper');
        const adminSidebar = document.getElementById('adminSidebar');
        
        toggleSidebar.addEventListener('click', function() {
            adminWrapper.classList.toggle('sidebar-collapsed');
            if (window.innerWidth < 992) {
                adminSidebar.classList.toggle('show');
            }
        });
        
        // Password visibility toggle
        const passwordToggle = document.querySelector('.password-toggle');
        const passwordField = document.getElementById('password');
        
        passwordToggle.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            // Toggle icon
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
        
        // Role badge selection
        const roleBadges = document.querySelectorAll('.role-badge');
        
        roleBadges.forEach(badge => {
            badge.addEventListener('click', function() {
                roleBadges.forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
    </script>
</body>
</html>