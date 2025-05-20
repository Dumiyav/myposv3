<?php
// Start output buffering
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
$currentUser = getCurrentUser(); // Get current user for header part
$currentPage = $page; // To pass to sidebar for active state

// If the current user is a cashier AND they are trying to access the 'dashboard' page
// (either by default or by URL) AND they no longer have permission for 'dashboard',
// redirect them to a more suitable page.
if ($currentUser && isset($currentUser['role']) && $currentUser['role'] === 'cashier' && !hasPermission($currentUser['role'], 'dashboard')) {
    // If the cashier is trying to access 'dashboard' (or it's the default landing page)
    if ($page === 'dashboard') {
        // Redirect to 'order_create' or 'orders' page
        $redirectTo = 'order_create'; // Or 'orders'

        // If the URL explicitly requested ?page=dashboard, or if no page was specified (defaulting to dashboard)
        // perform a header redirect to change the URL in the browser.
        if ((isset($_GET['page']) && $_GET['page'] === 'dashboard') || !isset($_GET['page'])) {
            // Ensure no output has been sent before header()
            if (!headers_sent()) {
                header('Location: index.php?page=' . $redirectTo);
                exit;
            } else {
                // Fallback if headers already sent (less ideal, but avoids errors)
                // This might happen if there was an echo or HTML before this block.
                // Best practice is to ensure no output before this logic.
                error_log("Headers already sent, cannot redirect cashier from dashboard. Current page variable set to: $redirectTo");
                $page = $redirectTo;
                $currentPage = $redirectTo;
            }
        } else {
             // If ?page= was something else (not dashboard), but they still landed here due to $page logic
             // This case is less likely given the outer if condition, but for completeness:
            $page = $redirectTo;
            $currentPage = $redirectTo; // Update for sidebar active state
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="{ sidebarOpen: false }" class="bg-gray-100">

    <div class="flex h-screen">
        <?php include_once 'includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white shadow-sm">
                <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                    <div class="md:hidden"> <button @click.stop="sidebarOpen = !sidebarOpen" class="text-gray-600 focus:outline-none">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        </button>
                    </div>

                    <h1 class="text-xl font-bold hidden md:block"><?php echo APP_NAME; ?></h1>
                    <div class="text-xl font-bold md:hidden flex-1 text-center">
                        <?php echo APP_NAME; ?>
                    </div>


                    <?php if ($currentUser): ?>
                    <div x-data="{ userDropdownOpen: false }" class="relative">
                        <button @click="userDropdownOpen = !userDropdownOpen" class="flex items-center space-x-2 focus:outline-none">
                            <span class="font-medium hidden sm:inline"><?php echo ucfirst($currentUser['role']); ?></span>
                            <span class="text-gray-700"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div x-cloak x-show="userDropdownOpen" @click.away="userDropdownOpen = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-20"> <div class="py-1">
                                <a href="index.php?page=profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Profile</a>
                                <a href="modules/auth/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign out</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto p-6">
                <?php
                // Load the requested page
                switch ($page) {
                    case 'dashboard': include 'modules/dashboard.php'; break;
                    case 'menu': include 'modules/menu/list.php'; break;
                    case 'menu_add': include 'modules/menu/add.php'; break;
                    case 'menu_edit': include 'modules/menu/edit.php'; break;
                    case 'orders': include 'modules/orders/view.php'; break;
                    case 'order_create': include 'modules/orders/create.php'; break;
                    case 'order_update': include 'modules/orders/update.php'; break;
                    // case 'tables': include 'modules/tables/list.php'; break; // Assuming you have this file
                    case 'payments': include 'modules/payments/process.php'; break;
                    case 'reports':
                        $view = isset($_GET['view']) ? $_GET['view'] : 'daily';
                        if ($view === 'summary') {
                            include 'modules/reports/summary.php';
                        } else {
                            include 'modules/reports/daily.php';
                        }
                        break;
                    case 'users':
                        if ($currentUser && hasPermission($currentUser['role'], 'users')) { // Ensure permission check and currentUser exists
                            $action = isset($_GET['action']) ? $_GET['action'] : 'list';
                            switch ($action) {
                                case 'add': include 'modules/users/add.php'; break;
                                case 'edit': include 'modules/users/edit.php'; break;
                                default: include 'modules/users/list.php'; break;
                            }
                        } else {
                             // Redirect to a default page if no permission, or show an error
                            if ($currentUser) { // if logged in but no permission
                                setFlashMessage('error', 'You do not have permission to access the users page.');
                                if (!headers_sent()) {
                                     header('Location: index.php?page=dashboard'); // Or their allowed default
                                     exit;
                                } else {
                                    include 'modules/dashboard.php'; // Fallback
                                }
                            } else { // Not logged in (should have been caught earlier)
                                 if (!headers_sent()) {
                                    header('Location: modules/auth/login.php');
                                    exit;
                                 }
                            }
                        }
                        break;
                    case 'profile': include 'modules/users/profile.php'; break;
                    default:
                        // Before defaulting to dashboard, check if the user (e.g. cashier) should be redirected
                        if ($currentUser && isset($currentUser['role']) && $currentUser['role'] === 'cashier' && !hasPermission($currentUser['role'], 'dashboard')) {
                            include 'modules/orders/create.php'; // Default for cashier if dashboard is the "default" case
                        } else {
                            include 'modules/dashboard.php';
                        }
                        break;
                }
                ?>
            </main>
        </div>
    </div>

<?php
include_once 'includes/footer.php';
if (ob_get_level() > 0) { // Ensure output buffer exists before flushing
    ob_end_flush();
}
?>
</body>
</html>