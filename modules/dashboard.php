<?php
// Get data for dashboard
$orders = readJsonFile('orders.json');
$tables = readJsonFile('tables.json');
$menuItems = readJsonFile('menu.json');

// Calculate statistics
$totalOrders = count($orders);
$activeOrders = 0;
$completedOrders = 0;
$cancelledOrders = 0;
$totalRevenue = 0;
$todayRevenue = 0;
$todayDate = date('Y-m-d');

foreach ($orders as $order) {
    switch ($order['status']) {
        case 'active':
            $activeOrders++;
            break;
        case 'completed':
            $completedOrders++;
            if ($order['payment_status'] === 'paid') {
                $totalRevenue += $order['total'];
                
                // Check if order is from today
                $orderDate = date('Y-m-d', strtotime($order['created_at']));
                if ($orderDate === $todayDate) {
                    $todayRevenue += $order['total'];
                }
            }
            break;
        case 'cancelled':
            $cancelledOrders++;
            break;
    }
}

// Get table statistics
$totalTables = count($tables);
$availableTables = 0;
$occupiedTables = 0;

foreach ($tables as $table) {
    if ($table['status'] === 'available') {
        $availableTables++;
    } else {
        $occupiedTables++;
    }
}

// Get popular menu items
$menuItemCounts = [];
foreach ($orders as $order) {
    if ($order['status'] === 'completed') {
        foreach ($order['items'] as $item) {
            $menuItemId = $item['menu_item_id'];
            $quantity = $item['quantity'];
            
            if (!isset($menuItemCounts[$menuItemId])) {
                $menuItemCounts[$menuItemId] = 0;
            }
            
            $menuItemCounts[$menuItemId] += $quantity;
        }
    }
}

// Sort menu items by popularity
arsort($menuItemCounts);

// Get top 5 popular items
$popularItems = [];
$count = 0;
foreach ($menuItemCounts as $menuItemId => $quantity) {
    foreach ($menuItems as $menuItem) {
        if ($menuItem['id'] === $menuItemId) {
            $popularItems[] = [
                'id' => $menuItemId,
                'name' => $menuItem['name'],
                'quantity' => $quantity
            ];
            break;
        }
    }
    
    $count++;
    if ($count >= 5) {
        break;
    }
}

// Get recent orders (last 5)
$recentOrders = [];
$sortedOrders = $orders;

// Sort orders by created_at (newest first)
usort($sortedOrders, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Get the 5 most recent orders
$recentOrders = array_slice($sortedOrders, 0, 5);
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Dashboard</h1>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Orders</p>
                    <p class="text-2xl font-bold"><?php echo $totalOrders; ?></p>
                </div>
            </div>
            <div class="mt-4 text-sm">
                <span class="text-green-500"><?php echo $completedOrders; ?> completed</span>
                <span class="mx-2">•</span>
                <span class="text-blue-500"><?php echo $activeOrders; ?> active</span>
                <span class="mx-2">•</span>
                <span class="text-red-500"><?php echo $cancelledOrders; ?> cancelled</span>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Today's Revenue</p>
                    <p class="text-2xl font-bold"><?php echo formatCurrency($todayRevenue); ?></p>
                </div>
            </div>
            <div class="mt-4 text-sm">
                <span class="text-gray-500">Total Revenue: <?php echo formatCurrency($totalRevenue); ?></span>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Tables</p>
                    <p class="text-2xl font-bold"><?php echo $totalTables; ?></p>
                </div>
            </div>
            <div class="mt-4 text-sm">
                <span class="text-green-500"><?php echo $availableTables; ?> available</span>
                <span class="mx-2">•</span>
                <span class="text-red-500"><?php echo $occupiedTables; ?> occupied</span>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Menu Items</p>
                    <p class="text-2xl font-bold"><?php echo count($menuItems); ?></p>
                </div>
            </div>
            <div class="mt-4 text-sm">
                <a href="index.php?page=menu" class="text-blue-500 hover:text-blue-700">View all menu items</a>
            </div>
        </div>
    </div>
    
    <!-- Recent Orders and Popular Items -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Orders -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold mb-4">Recent Orders</h2>
            
            <?php if (empty($recentOrders)): ?>
            <p class="text-gray-500">No orders found.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-2 text-left">Order ID</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2 text-center">Status</th>
                            <th class="px-4 py-2 text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <a href="index.php?page=orders&id=<?php echo $order['id']; ?>" class="text-blue-500 hover:text-blue-700 font-medium">
                                    #<?php echo $order['id']; ?>
                                </a>
                            </td>
                            
                            </td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($order['total']); ?></td>
                            <td class="px-4 py-2 text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo getStatusClass($order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <?php echo formatDate($order['created_at']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 text-right">
                <a href="index.php?page=orders" class="text-blue-500 hover:text-blue-700">View all orders</a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Popular Items -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold mb-4">Popular Items</h2>
            
            <?php if (empty($popularItems)): ?>
            <p class="text-gray-500">No data available.</p>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($popularItems as $item): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                            <span class="text-gray-600 font-medium"><?php echo $item['quantity']; ?></span>
                        </div>
                        <div>
                            <h3 class="font-medium"><?php echo $item['name']; ?></h3>
                            <p class="text-sm text-gray-500"><?php echo $item['quantity']; ?> orders</p>
                        </div>
                    </div>
                    <div class="w-24 bg-gray-200 rounded-full h-2.5">
                        <?php
                        // Calculate percentage based on highest quantity
                        $maxQuantity = $popularItems[0]['quantity'];
                        $percentage = ($item['quantity'] / $maxQuantity) * 100;
                        ?>
                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-4 text-right">
                <a href="index.php?page=reports" class="text-blue-500 hover:text-blue-700">View detailed reports</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>