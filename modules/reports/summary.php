<?php
// Get date range parameters or use default (last 7 days)
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime($endDate . ' -6 days'));

// Validate date formats
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-d', strtotime($endDate . ' -6 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-d');
}

// Ensure start date is before end date
if (strtotime($startDate) > strtotime($endDate)) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// Get orders
$orders = readJsonFile('orders.json');
$menuItems = readJsonFile('menu.json');

// Filter orders by date range
$rangeOrders = [];
foreach ($orders as $order) {
    $orderDate = substr($order['created_at'], 0, 10); // Extract YYYY-MM-DD part
    if ($orderDate >= $startDate && $orderDate <= $endDate) {
        $rangeOrders[] = $order;
    }
}

// Calculate statistics
$totalOrders = count($rangeOrders);
$totalRevenue = 0;
$totalTax = 0;
$totalDiscount = 0;
$completedOrders = 0;
$cancelledOrders = 0;
$activeOrders = 0;

// Item sales data
$itemSales = [];

// Daily revenue data
$dailyRevenue = [];
$dailyOrders = [];

// Initialize daily data arrays
$currentDate = new DateTime($startDate);
$endDateTime = new DateTime($endDate);
$endDateTime->modify('+1 day'); // Include end date

while ($currentDate < $endDateTime) {
    $dateStr = $currentDate->format('Y-m-d');
    $dailyRevenue[$dateStr] = 0;
    $dailyOrders[$dateStr] = 0;
    $currentDate->modify('+1 day');
}

foreach ($rangeOrders as $order) {
    // Count by status
    switch ($order['status']) {
        case 'completed':
            $completedOrders++;
            break;
        case 'cancelled':
            $cancelledOrders++;
            break;
        case 'active':
            $activeOrders++;
            break;
    }
    
    // Add to totals if completed
    if ($order['status'] === 'completed') {
        $totalRevenue += $order['total'];
        $totalTax += $order['tax'];
        $totalDiscount += isset($order['discount']) ? $order['discount'] : 0;
        
        // Add to daily revenue
        $orderDate = substr($order['created_at'], 0, 10);
        if (isset($dailyRevenue[$orderDate])) {
            $dailyRevenue[$orderDate] += $order['total'];
            $dailyOrders[$orderDate]++;
        }
        
        // Process items
        foreach ($order['items'] as $item) {
            $menuItemId = $item['menu_item_id'];
            $quantity = $item['quantity'];
            
            // Find menu item
            $menuItem = null;
            foreach ($menuItems as $mi) {
                if ($mi['id'] === $menuItemId) {
                    $menuItem = $mi;
                    break;
                }
            }
            
            if ($menuItem) {
                $itemName = $menuItem['name'];
                $itemPrice = $menuItem['price'];
                $itemTotal = $itemPrice * $quantity;
                
                if (!isset($itemSales[$menuItemId])) {
                    $itemSales[$menuItemId] = [
                        'id' => $menuItemId,
                        'name' => $itemName,
                        'price' => $itemPrice,
                        'quantity' => 0,
                        'total' => 0
                    ];
                }
                
                $itemSales[$menuItemId]['quantity'] += $quantity;
                $itemSales[$menuItemId]['total'] += $itemTotal;
            }
        }
    }
}

// Sort item sales by quantity (highest first)
usort($itemSales, function($a, $b) {
    return $b['quantity'] - $a['quantity'];
});

// Calculate payment methods
$paymentMethods = [
    'cash' => 0,
    'card' => 0,
    'mobile' => 0,
    'pending' => 0
];

foreach ($rangeOrders as $order) {
    if ($order['status'] === 'completed') {
        $method = isset($order['payment_method']) && !empty($order['payment_method']) ? $order['payment_method'] : 'pending';
        if (isset($paymentMethods[$method])) {
            $paymentMethods[$method]++;
        }
    }
}

// Format dates for display
$displayStartDate = date('F j, Y', strtotime($startDate));
$displayEndDate = date('F j, Y', strtotime($endDate));

// Calculate average daily revenue
$dayCount = count($dailyRevenue);
$avgDailyRevenue = $dayCount > 0 ? $totalRevenue / $dayCount : 0;

// Calculate average order value
$avgOrderValue = $completedOrders > 0 ? $totalRevenue / $completedOrders : 0;
?>

<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Summary Report</h1>
        
        <div class="flex space-x-2">
            <a href="index.php?page=reports" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
                Daily
            </a>
            <a href="index.php?page=reports&view=summary" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                Summary
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <!-- Date Range Selector -->
        <form method="GET" action="index.php" class="mb-6">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="view" value="summary">
            
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        Apply
                    </button>
                </div>
                
                <!-- Quick date range selectors -->
                <div class="ml-auto flex space-x-2">
                    <a href="index.php?page=reports&view=summary&start_date=<?php echo date('Y-m-d', strtotime('-6 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="text-blue-500 hover:text-blue-700">
                        Last 7 Days
                    </a>
                    <a href="index.php?page=reports&view=summary&start_date=<?php echo date('Y-m-d', strtotime('-29 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="text-blue-500 hover:text-blue-700">
                        Last 30 Days
                    </a>
                    <a href="index.php?page=reports&view=summary&start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="text-blue-500 hover:text-blue-700">
                        This Month
                    </a>
                </div>
            </div>
        </form>
        
        <h2 class="text-xl font-semibold mb-4"><?php echo $displayStartDate; ?> - <?php echo $displayEndDate; ?></h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Total Orders</h3>
                <p class="text-2xl font-bold"><?php echo $totalOrders; ?></p>
                <div class="mt-2 text-sm">
                    <span class="text-green-500"><?php echo $completedOrders; ?> completed</span>
                    <span class="mx-1">•</span>
                    <span class="text-red-500"><?php echo $cancelledOrders; ?> cancelled</span>
                </div>
            </div>
            
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Total Revenue</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalRevenue); ?></p>
                <div class="mt-2 text-sm text-gray-500">
                    Avg. Daily: <?php echo formatCurrency($avgDailyRevenue); ?>
                </div>
            </div>
            
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Avg. Order Value</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($avgOrderValue); ?></p>
                <div class="mt-2 text-sm text-gray-500">
                    Based on <?php echo $completedOrders; ?> completed orders
                </div>
            </div>
            
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Tax Collected</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalTax); ?></p>
                <div class="mt-2 text-sm text-gray-500">
                    Discounts: <?php echo formatCurrency($totalDiscount); ?>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Daily Revenue Chart -->
            <div>
                <h3 class="text-lg font-semibold mb-3">Daily Revenue</h3>
                
                <?php if (empty($rangeOrders)): ?>
                <p class="text-gray-500">No revenue data available for this period.</p>
                <?php else: ?>
                <div class="h-64">
                    <div class="flex items-end h-56 space-x-1">
                        <?php 
                        // Find max for scaling
                        $maxRevenue = max($dailyRevenue);
                        
                        foreach ($dailyRevenue as $date => $revenue): 
                            $height = $maxRevenue > 0 ? ($revenue / $maxRevenue) * 100 : 0;
                            $displayDate = date('M j', strtotime($date));
                            $isWeekend = in_array(date('N', strtotime($date)), [6, 7]); // 6 = Saturday, 7 = Sunday
                        ?>
                        <div class="flex flex-col items-center flex-1">
                            <div class="relative w-full group">
                                <div class="w-full <?php echo $isWeekend ? 'bg-blue-400' : 'bg-blue-500'; ?> rounded-t" style="height: <?php echo $height; ?>%"></div>
                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs rounded py-1 px-2 mb-1 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                    <?php echo formatCurrency($revenue); ?> (<?php echo $dailyOrders[$date]; ?> orders)
                                </div>
                            </div>
                            <div class="text-xs mt-1 text-gray-500"><?php echo $displayDate; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Payment Methods -->
            <div>
                <h3 class="text-lg font-semibold mb-3">Payment Methods</h3>
                
                <?php if ($completedOrders === 0): ?>
                <p class="text-gray-500">No payment data available for this period.</p>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($paymentMethods as $method => $count): ?>
                    <?php if ($count > 0 || $method === 'pending'): ?>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="capitalize"><?php echo $method; ?></span>
                            <span><?php echo $count; ?> orders (<?php echo round(($count / $completedOrders) * 100); ?>%)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <?php $percentage = ($count / $completedOrders) * 100; ?>
                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Top Selling Items -->
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-3">Top Selling Items</h3>
            
            <?php if (empty($itemSales)): ?>
            <p class="text-gray-500">No sales data available for this period.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-2 text-left">Item</th>
                            <th class="px-4 py-2 text-right">Price</th>
                            <th class="px-4 py-2 text-right">Quantity</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2 text-right">% of Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalQuantity = 0;
                        foreach ($itemSales as $item) {
                            $totalQuantity += $item['quantity'];
                        }
                        
                        // Show top 10 items
                        $topItems = array_slice($itemSales, 0, 10);
                        
                        foreach ($topItems as $item): 
                            $percentOfSales = $totalQuantity > 0 ? ($item['quantity'] / $totalQuantity) * 100 : 0;
                        ?>
                        <tr class="border-b">
                            <td class="px-4 py-2"><?php echo $item['name']; ?></td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($item['price']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo $item['quantity']; ?></td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($item['total']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo number_format($percentOfSales, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>