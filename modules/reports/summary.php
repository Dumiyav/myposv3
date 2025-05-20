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
$menuItemsAll = readJsonFile('menu.json'); // Renamed for clarity

// Filter orders by date range
$rangeOrders = [];
if (is_array($orders)) {
    foreach ($orders as $order) {
        if (isset($order['created_at'])) {
            $orderDate = substr($order['created_at'], 0, 10); // Extract YYYY-MM-DD part
            if ($orderDate >= $startDate && $orderDate <= $endDate) {
                $rangeOrders[] = $order;
            }
        }
    }
}


// Calculate statistics
$totalOrdersInRange = count($rangeOrders); // Renamed for clarity
$totalRevenue = 0; // Final total revenue from completed orders (after discount, with tax)
$totalTax = 0;
$totalDiscount = 0;
$completedOrders = 0;
$cancelledOrders = 0;
$activeOrders = 0;

// Item sales data
$itemSales = []; // For regular menu items
$totalRevenueFromCustomItems = 0;  // Pre-tax, pre-order-discount revenue from custom items
$totalRevenueFromRegularItems = 0; // Pre-tax, pre-order-discount revenue from regular items
$totalQuantityCustomItems = 0;
$totalQuantityRegularItems = 0;

// Daily revenue data for chart
$dailyRevenue = [];
$dailyOrders = [];

// Initialize daily data arrays
$currentDateIterator = new DateTime($startDate); // Renamed for clarity
$endDateTime = new DateTime($endDate);
$endDateTime->modify('+1 day'); // Include end date in loop

while ($currentDateIterator < $endDateTime) {
    $dateStr = $currentDateIterator->format('Y-m-d');
    $dailyRevenue[$dateStr] = 0;
    $dailyOrders[$dateStr] = 0;
    $currentDateIterator->modify('+1 day');
}

foreach ($rangeOrders as $order) {
    if (isset($order['status'])) {
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
    }
    
    if (isset($order['status']) && $order['status'] === 'completed') {
        $currentOrderRevenue = $order['total'] ?? 0;
        $currentOrderTax = $order['tax'] ?? 0;
        $currentOrderDiscount = $order['discount'] ?? 0;

        $totalRevenue += $currentOrderRevenue;
        $totalTax += $currentOrderTax;
        $totalDiscount += $currentOrderDiscount;
        
        $orderDateForChart = isset($order['created_at']) ? substr($order['created_at'], 0, 10) : null;
        if ($orderDateForChart && isset($dailyRevenue[$orderDateForChart])) {
            $dailyRevenue[$orderDateForChart] += $currentOrderRevenue;
            $dailyOrders[$orderDateForChart]++;
        }
        
        if (isset($order['items']) && is_array($order['items'])) {
            foreach ($order['items'] as $item_in_order) {
                $quantity = $item_in_order['quantity'] ?? 0;
                if ($quantity <= 0) continue;

                if (isset($item_in_order['is_custom']) && $item_in_order['is_custom'] === true) {
                    $customItemPrice = $item_in_order['custom_price'] ?? 0;
                    $totalRevenueFromCustomItems += $customItemPrice * $quantity;
                    $totalQuantityCustomItems += $quantity;
                } else if (isset($item_in_order['menu_item_id'])) {
                    $menuItemId = $item_in_order['menu_item_id'];
                    
                    if (is_array($menuItemsAll)) {
                        $menuItemDetails = null;
                        foreach ($menuItemsAll as $mi) {
                            if (isset($mi['id']) && $mi['id'] === $menuItemId) {
                                $menuItemDetails = $mi;
                                break;
                            }
                        }
                        
                        if ($menuItemDetails) {
                            $itemName = $menuItemDetails['name'] ?? 'Unnamed Item';
                            $itemPrice = $menuItemDetails['price'] ?? 0;
                            
                            $totalRevenueFromRegularItems += $itemPrice * $quantity;
                            $totalQuantityRegularItems += $quantity;

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
                            $itemSales[$menuItemId]['total'] += $itemPrice * $quantity;
                        } else {
                             error_log("Summary Report: menu_item_id {$menuItemId} not found in menu.json for order ID {$order['id']}");
                        }
                    }
                }
            }
        }
    }
}

if (!empty($itemSales)) {
    usort($itemSales, function($a, $b) {
        return ($b['quantity'] ?? 0) - ($a['quantity'] ?? 0);
    });
}

$paymentMethods = ['cash' => 0, 'card' => 0, 'mobile' => 0, 'pending' => 0];
foreach ($rangeOrders as $order) {
    if (isset($order['status']) && $order['status'] === 'completed') {
        $method = (isset($order['payment_method']) && !empty($order['payment_method'])) ? $order['payment_method'] : 'pending';
        if (isset($paymentMethods[$method])) {
            $paymentMethods[$method]++;
        }
    }
}

$displayStartDate = date('F j, Y', strtotime($startDate));
$displayEndDate = date('F j, Y', strtotime($endDate));

$dayCount = count($dailyRevenue); // Number of days with orders in the range for averaging
$avgDailyRevenue = $completedOrders > 0 ? $totalRevenue / $completedOrders : 0; // Avg revenue per completed order over the period
$avgOrderValue = $completedOrders > 0 ? $totalRevenue / $completedOrders : 0;

// More accurate average daily revenue:
$numberOfDaysInPeriod = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1;
$avgRevenuePerDayInPeriod = $numberOfDaysInPeriod > 0 ? $totalRevenue / $numberOfDaysInPeriod : 0;


?>

<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Summary Report</h1>
        <div class="flex space-x-2">
            <a href="index.php?page=reports&view=daily&date=<?php echo $endDate; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">Daily</a>
            <a href="index.php?page=reports&view=summary&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">Summary</a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="index.php" class="mb-6">
            <input type="hidden" name="page" value="reports"><input type="hidden" name="view" value="summary">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div><button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">Apply</button></div>
                <div class="ml-auto flex space-x-2">
                    <a href="index.php?page=reports&view=summary&start_date=<?php echo date('Y-m-d', strtotime('-6 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="text-blue-500 hover:text-blue-700 text-sm">Last 7 Days</a>
                    <a href="index.php?page=reports&view=summary&start_date=<?php echo date('Y-m-d', strtotime('-29 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="text-blue-500 hover:text-blue-700 text-sm">Last 30 Days</a>
                    <a href="index.php?page=reports&view=summary&start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>" class="text-blue-500 hover:text-blue-700 text-sm">This Month</a>
                    <a href="index.php?page=reports&view=summary&start_date=<?php echo date('Y-m-01', strtotime('first day of last month')); ?>&end_date=<?php echo date('Y-m-t', strtotime('last day of last month')); ?>" class="text-blue-500 hover:text-blue-700 text-sm">Last Month</a>
                </div>
            </div>
        </form>
        
        <h2 class="text-xl font-semibold mb-4 text-center"><?php echo $displayStartDate; ?> - <?php echo $displayEndDate; ?></h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Total Orders in Range</h3>
                <p class="text-2xl font-bold"><?php echo $totalOrdersInRange; ?></p>
                <div class="mt-2 text-xs">
                    <span class="text-green-500"><?php echo $completedOrders; ?> completed</span><span class="mx-1">•</span>
                    <span class="text-red-500"><?php echo $cancelledOrders; ?> cancelled</span>
                </div>
            </div>
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Total Revenue (Completed)</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalRevenue); ?></p>
                 <div class="mt-2 text-xs text-gray-500">
                    Avg daily (period): <?php echo formatCurrency($avgRevenuePerDayInPeriod); ?>
                </div>
            </div>
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Revenue from Regular Items</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalRevenueFromRegularItems); ?></p>
                <p class="text-xs text-gray-500 mt-1"><?php echo $totalQuantityRegularItems; ?> items sold</p>
            </div>
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Revenue from Custom Items</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalRevenueFromCustomItems); ?></p>
                <p class="text-xs text-gray-500 mt-1"><?php echo $totalQuantityCustomItems; ?> items sold</p>
            </div>
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Avg. Order Value (Completed)</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($avgOrderValue); ?></p>
            </div>
        </div>
         <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6"> <div class="xl:col-start-2 bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Tax Collected</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalTax); ?></p>
            </div>
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Discounts Given</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalDiscount); ?></p>
            </div>
        </div>


        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <h3 class="text-lg font-semibold mb-3">Daily Revenue Over Period</h3>
                <?php if (empty($rangeOrders) || $completedOrders === 0): ?>
                <p class="text-gray-500">No revenue data for this period.</p>
                <?php else: ?>
                <div class="h-64 bg-gray-50 p-2 rounded">
                    <div class="flex items-end h-56 space-x-1">
                        <?php 
                        $maxDailyRevenueForChart = 0;
                        if (!empty($dailyRevenue)) {
                            $maxDailyRevenueForChart = max($dailyRevenue);
                        }
                        if ($maxDailyRevenueForChart == 0) $maxDailyRevenueForChart = 1; // Avoid division by zero
                        
                        foreach ($dailyRevenue as $dateForChart => $revenueForChart): 
                            $height = ($revenueForChart / $maxDailyRevenueForChart) * 100;
                            $displayDateForChart = date('M j', strtotime($dateForChart));
                        ?>
                        <div class="flex flex-col items-center flex-1 group relative" title="<?php echo $displayDateForChart . ': ' . formatCurrency($revenueForChart) . ' (' . ($dailyOrders[$dateForChart] ?? 0) . ' orders)';?>">
                            <div class="w-full bg-blue-400 hover:bg-blue-600 rounded-t transition-all duration-150" style="height: <?php echo max(1,$height); ?>%"></div>
                             <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-1 px-1.5 py-0.5 bg-gray-700 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                <?php echo formatCurrency($revenueForChart); ?>
                            </div>
                            <div class="text-xs mt-1 text-gray-500" style="font-size: 0.6rem; writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap;"><?php echo $displayDateForChart; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div>
                <h3 class="text-lg font-semibold mb-3">Payment Methods (Completed Orders)</h3>
                <?php if ($completedOrders === 0): ?>
                <p class="text-gray-500">No payment data for completed orders in this period.</p>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($paymentMethods as $method => $count): ?>
                    <?php if ($count > 0 || ($method === 'pending' && $completedOrders > 0)): ?>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="capitalize"><?php echo htmlspecialchars($method); ?></span>
                            <span><?php echo $count; ?> orders (<?php echo $completedOrders > 0 ? round(($count / $completedOrders) * 100) : 0; ?>%)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <?php $percentage = $completedOrders > 0 ? ($count / $completedOrders) * 100 : 0; ?>
                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-3">Top Selling Regular Menu Items</h3>
            <?php if (empty($itemSales)): ?>
            <p class="text-gray-500">No regular item sales data for this period.</p>
            <?php else: ?>
            <div class="overflow-x-auto max-h-96">
                <table class="w-full">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left">Item</th>
                            <th class="px-4 py-2 text-right">Price</th>
                            <th class="px-4 py-2 text-right">Quantity Sold</th>
                            <th class="px-4 py-2 text-right">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $topItems = array_slice($itemSales, 0, 15); // Show top 15 regular items
                        foreach ($topItems as $item_sale): 
                        ?>
                        <tr class="border-b">
                            <td class="px-4 py-2"><?php echo htmlspecialchars($item_sale['name'] ?? 'N/A'); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($item_sale['price'] ?? 0); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo htmlspecialchars($item_sale['quantity'] ?? 0); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($item_sale['total'] ?? 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>