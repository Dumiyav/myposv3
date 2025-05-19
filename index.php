<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

session_start();
require_once 'config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header('Location: modules/auth/login.php');
    exit;
}

// Default to dashboard
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Include header
include_once 'includes/header.php';
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include_once 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <main class="p-6">
            <?php
            // Load the requested page
            switch ($page) {
                case 'dashboard':
                    include 'modules/dashboard.php';
                    break;
                case 'menu':
                    include 'modules/menu/list.php';
                    break;
                case 'menu_add':
                    include 'modules/menu/add.php';
                    break;
                case 'menu_edit':
                    include 'modules/menu/edit.php';
                    break;
                case 'orders':
                    include 'modules/orders/view.php';
                    break;
                case 'order_create':
                    include 'modules/orders/create.php';
                    break;
                case 'order_update':
                    include 'modules/orders/update.php';
                    break;
                case 'tables':
                    include 'modules/tables/list.php';
                    break;
                case 'payments':
                    include 'modules/payments/process.php';
                    break;
                case 'reports':
                    // Check if a specific report view is requested
                    $view = isset($_GET['view']) ? $_GET['view'] : 'daily';
                    
                    if ($view === 'summary') {
                        include 'modules/reports/summary.php';
                    } else {
                        include 'modules/reports/daily.php';
                    }
                    break;
                case 'users':
                    // Check if user has admin role
                    if (getCurrentUser()['role'] !== 'admin') {
                        include 'modules/dashboard.php';
                        break;
                    }
                    
                    // Check if a specific user action is requested
                    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
                    
                    switch ($action) {
                        case 'add':
                            include 'modules/users/add.php';
                            break;
                        case 'edit':
                            include 'modules/users/edit.php';
                            break;
                        default:
                            include 'modules/users/list.php';
                            break;
                    }
                    break;
                case 'profile':
                    include 'modules/users/profile.php';
                    break;
                default:
                    include 'modules/dashboard.php';
                    break;
            }
            ?>
        </main>
    </div>
</div>

<?php 
include_once 'includes/footer.php';

// End output buffering and send output
ob_end_flush();
?>