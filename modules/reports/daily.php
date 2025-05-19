<?php
// Get date parameter or use today
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Get orders
$orders = readJsonFile('orders.json');
$menuItems = readJsonFile('menu.json');

// Filter orders by date
$dateOrders = [];
foreach ($orders as $order) {
    $orderDate = substr($order['created_at'], 0, 10); // Extract YYYY-MM-DD part
    if ($orderDate === $date) {
        $dateOrders[] = $order;
    }
}

// Calculate statistics
$totalOrders = count($dateOrders);
$totalRevenue = 0;
$totalTax = 0;
$totalDiscount = 0;
$completedOrders = 0;
$cancelledOrders = 0;
$activeOrders = 0;

// Item sales data
$itemSales = [];

foreach ($dateOrders as $order) {
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

foreach ($dateOrders as $order) {
    if ($order['status'] === 'completed') {
        $method = isset($order['payment_method']) && !empty($order['payment_method']) ? $order['payment_method'] : 'pending';
        if (isset($paymentMethods[$method])) {
            $paymentMethods[$method]++;
        }
    }
}

// Format date for display
$displayDate = date('F j, Y', strtotime($date));

// Previous and next day links
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$isToday = $date === date('Y-m-d');
?>

<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Daily Report</h1>
        
        <div class="flex space-x-2">
            <a href="index.php?page=reports" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                Daily
            </a>
            <a href="index.php?page=reports&view=summary" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
                Summary
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <a href="index.php?page=reports&date=<?php echo $prevDate; ?>" class="text-blue-500 hover:text-blue-700">
                <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Previous Day
            </a>
            
            <h2 class="text-xl font-semibold"><?php echo $displayDate; ?></h2>
            
            <a href="<?php echo $isToday ? 'javascript:void(0)' : 'index.php?page=reports&date=' . $nextDate; ?>" class="<?php echo $isToday ? 'text-gray-400 cursor-not-allowed' : 'text-blue-500 hover:text-blue-700'; ?>">
                Next Day
                <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
        
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
                    Before tax: <?php echo formatCurrency($totalRevenue - $totalTax); ?>
                </div>
            </div>
            
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Tax Collected</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalTax); ?></p>
                <div class="mt-2 text-sm text-gray-500">
                    Tax rate: <?php echo TAX_RATE; ?>%
                </div>
            </div>
            
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Discounts</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalDiscount); ?></p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Item Sales -->
            <div>
                <h3 class="text-lg font-semibold mb-3">Item Sales</h3>
                
                <?php if (empty($itemSales)): ?>
                <p class="text-gray-500">No sales data available for this day.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-4 py-2 text-left">Item</th>
                                <th class="px-4 py-2 text-right">Price</th>
                                <th class="px-4 py-2 text-right">Quantity</th>
                                <th class="px-4 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itemSales as $item): ?>
                            <tr class="border-b">
                                <td class="px-4 py-2"><?php echo $item['name']; ?></td>
                                <td class="px-4 py-2 text-right"><?php echo formatCurrency($item['price']); ?></td>
                                <td class="px-4 py-2 text-right"><?php echo $item['quantity']; ?></td>
                                <td class="px-4 py-2 text-right"><?php echo formatCurrency($item['total']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Payment Methods -->
            <div>
                <h3 class="text-lg font-semibold mb-3">Payment Methods</h3>
                
                <?php if ($completedOrders === 0): ?>
                <p class="text-gray-500">No payment data available for this day.</p>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($paymentMethods as $method => $count): ?>
                    <?php if ($count > 0 || $method === 'pending'): ?>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="capitalize"><?php echo $method; ?></span>
                            <span><?php echo $count; ?> orders</span>
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
                
                <!-- Orders by Hour -->
                <h3 class="text-lg font-semibold mt-6 mb-3">Orders by Hour</h3>
                
                <?php if (empty($dateOrders)): ?>
                <p class="text-gray-500">No order data available for this day.</p>
                <?php else: ?>
                <?php
                // Group orders by hour
                $hourlyOrders = [];
                for ($i = 0; $i < 24; $i++) {
                    $hourlyOrders[$i] = 0;
                }
                
                foreach ($dateOrders as $order) {
                    $hour = (int)date('G', strtotime($order['created_at']));
                    $hourlyOrders[$hour]++;
                }
                
                // Find max for scaling
                $maxOrders = max($hourlyOrders);
                ?>
                
                <div class="flex items-end h-40 space-x-1">
                    <?php for ($i = 0; $i < 24; $i++): ?>
                    <?php 
                    $height = $maxOrders > 0 ? ($hourlyOrders[$i] / $maxOrders) * 100 : 0;
                    $displayHour = $i % 12;
                    if ($displayHour === 0) $displayHour = 12;
                    $amPm = $i < 12 ? 'AM' : 'PM';
                    ?>
                    <div class="flex flex-col items-center flex-1">
                        <div class="w-full bg-blue-500 rounded-t" style="height: <?php echo $height; ?>%"></div>
                        <div class="text-xs mt-1 text-gray-500"><?php echo $displayHour; ?><?php echo $amPm; ?></div>
                    </div>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>