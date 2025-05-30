<?php
// These variables should ideally be passed from index.php or defined globally if not already
$currentUserSidebar = getCurrentUser(); // Assuming getCurrentUser() is globally available
$currentPageSidebar = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<div
    x-show="sidebarOpen"
    x-cloak
    class="fixed inset-0 z-20 bg-black bg-opacity-50 transition-opacity md:hidden"
    @click="sidebarOpen = false"
></div>

<aside
    x-cloak
    class="fixed inset-y-0 left-0 z-30 w-64 bg-gray-800 text-white transform transition-transform duration-300 ease-in-out
           md:relative md:translate-x-0 md:flex-shrink-0"
    :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen && window.innerWidth < 768, 'md:translate-x-0': window.innerWidth >= 768}"
>
    <div class="h-full flex flex-col">
        <div class="p-4 flex justify-between items-center">
            <h2 class="text-xl font-semibold"><?php echo APP_NAME; ?></h2>
            <button @click="sidebarOpen = false" class="md:hidden text-gray-300 hover:text-white focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto">
            <ul class="px-2 py-4">
                <?php if (hasPermission($currentUserSidebar['role'], 'dashboard')): ?>
                    <li class="mb-2">
                        <a href="index.php?page=dashboard" @click="if (window.innerWidth < 768) sidebarOpen = false" class="flex items-center px-4 py-2 rounded-lg <?php echo $currentPageSidebar == 'dashboard' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            Dashboard
                        </a>
                    </li>
                <?php endif; ?>
                    
                <?php if (hasPermission($currentUserSidebar['role'], 'orders')): ?>
                <li class="mb-2">
                    <a href="index.php?page=orders" @click="if (window.innerWidth < 768) sidebarOpen = false" class="flex items-center px-4 py-2 rounded-lg <?php echo $currentPageSidebar == 'orders' || $currentPageSidebar == 'order_view' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        Orders
                    </a>
                </li>
                <li class="mb-2">
                    <a href="index.php?page=order_create" @click="if (window.innerWidth < 768) sidebarOpen = false" class="flex items-center px-4 py-2 rounded-lg <?php echo $currentPageSidebar == 'order_create' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        New Order
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasPermission($currentUserSidebar['role'], 'menu')): ?>
                <li class="mb-2">
                    <a href="index.php?page=menu" @click="if (window.innerWidth < 768) sidebarOpen = false" class="flex items-center px-4 py-2 rounded-lg <?php echo ($currentPageSidebar == 'menu' || $currentPageSidebar == 'menu_add' || $currentPageSidebar == 'menu_edit') ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        Menu
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasPermission($currentUserSidebar['role'], 'payments')): ?>
                <li class="mb-2">
                    <a href="index.php?page=payments" @click="if (window.innerWidth < 768) sidebarOpen = false" class="flex items-center px-4 py-2 rounded-lg <?php echo $currentPageSidebar == 'payments' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Payments
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasPermission($currentUserSidebar['role'], 'reports')): ?>
                <li class="mb-2">
                    <a href="index.php?page=reports" @click="if (window.innerWidth < 768) sidebarOpen = false" class="flex items-center px-4 py-2 rounded-lg <?php echo $currentPageSidebar == 'reports' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Reports
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($currentUserSidebar['role'] === 'admin'): ?>
                <li class="mb-2">
                    <a href="index.php?page=users" @click="if (window.innerWidth < 768) sidebarOpen = false" class="flex items-center px-4 py-2 rounded-lg <?php echo ($currentPageSidebar == 'users' || $currentPageSidebar == 'users_add' || $currentPageSidebar == 'users_edit') ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        Users
                    </a>
                </li>
                <?php endif; ?>

                <li class="mb-2">
                    <a href="index.php?page=profile" @click="if (window.innerWidth < 768) sidebarOpen = false" class="flex items-center px-4 py-2 rounded-lg <?php echo $currentPageSidebar == 'profile' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        My Profile
                    </a>
                </li>
            </ul>
        </nav>

        <div class="p-4 border-t border-gray-700">
            <a href="modules/auth/logout.php" class="flex items-center text-gray-300 hover:text-white">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Logout
            </a>
        </div>
    </div>
</aside>