<?php
// Get date parameter or use today
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Get orders
$orders = readJsonFile('orders.json');
$menuItemsAll = readJsonFile('menu.json');

// Filter orders by date
$dateOrders = [];
if(is_array($orders)) {
    foreach ($orders as $order) {
        if (isset($order['created_at'])) {
            $orderDate = substr($order['created_at'], 0, 10);
            if ($orderDate === $date) {
                $dateOrders[] = $order;
            }
        }
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
$itemSales = []; // For regular menu items
$totalRevenueFromCustomItems = 0;
$totalRevenueFromRegularItems = 0;
$totalQuantityCustomItems = 0;
$totalQuantityRegularItems = 0;


foreach ($dateOrders as $order) {
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
                            error_log("Daily Report: menu_item_id {$menuItemId} not found in menu.json for order ID {$order['id']}");
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
foreach ($dateOrders as $order) {
    if (isset($order['status']) && $order['status'] === 'completed') {
        $method = (isset($order['payment_method']) && !empty($order['payment_method'])) ? $order['payment_method'] : 'pending';
        if (isset($paymentMethods[$method])) {
            $paymentMethods[$method]++;
        }
    }
}

$displayDate = date('F j, Y', strtotime($date));
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$isToday = ($date === date('Y-m-d'));
?>

<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Daily Report</h1>
        <div class="flex space-x-2">
            <a href="index.php?page=reports&view=daily&date=<?php echo $date; ?>" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">Daily</a>
            <a href="index.php?page=reports&view=summary&start_date=<?php echo date('Y-m-d', strtotime($date . ' -6 days')); ?>&end_date=<?php echo $date; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">Summary</a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <a href="index.php?page=reports&view=daily&date=<?php echo $prevDate; ?>" class="text-blue-500 hover:text-blue-700">
                <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg> Prev
            </a>
            <form method="GET" action="index.php" class="inline-flex items-center">
                <input type="hidden" name="page" value="reports"><input type="hidden" name="view" value="daily">
                <input type="date" name="date" value="<?php echo $date; ?>" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 mx-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-3 rounded-md text-sm">Go</button>
            </form>
            <a href="<?php echo $isToday ? 'javascript:void(0)' : 'index.php?page=reports&view=daily&date=' . $nextDate; ?>" class="<?php echo $isToday ? 'text-gray-400 cursor-not-allowed' : 'text-blue-500 hover:text-blue-700'; ?>">
                Next <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>
        <p class="text-center text-xl font-semibold mb-6"><?php echo $displayDate; ?></p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Total Orders</h3>
                <p class="text-2xl font-bold"><?php echo $totalOrders; ?></p>
                <div class="mt-2 text-sm">
                    <span class="text-green-500"><?php echo $completedOrders; ?> completed</span><span class="mx-1">•</span>
                    <span class="text-red-500"><?php echo $cancelledOrders; ?> cancelled</span>
                </div>
            </div>
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Total Revenue (Completed Orders)</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalRevenue); ?></p>
                <div class="mt-2 text-sm text-gray-500">
                    Before tax & disc: <?php echo formatCurrency($totalRevenueFromRegularItems + $totalRevenueFromCustomItems); ?>
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
        </div>
         <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6"> <div class="lg:col-start-2 bg-gray-100 rounded-lg p-4"> <h3 class="text-sm text-gray-500 mb-1">Tax Collected</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalTax); ?></p>
            </div>
            <div class="bg-gray-100 rounded-lg p-4">
                <h3 class="text-sm text-gray-500 mb-1">Discounts Given</h3>
                <p class="text-2xl font-bold"><?php echo formatCurrency($totalDiscount); ?></p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <h3 class="text-lg font-semibold mb-3">Top Selling Regular Menu Items</h3>
                <?php if (empty($itemSales)): ?>
                <p class="text-gray-500">No regular menu item sales data for this day.</p>
                <?php else: ?>
                <div class="overflow-x-auto max-h-96">
                    <table class="w-full">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left">Item</th>
                                <th class="px-4 py-2 text-right">Qty</th>
                                <th class="px-4 py-2 text-right">Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $itemSalesToDisplay = array_slice($itemSales, 0, 10); 
                            foreach ($itemSalesToDisplay as $item_sale): ?>
                            <tr class="border-b">
                                <td class="px-4 py-2"><?php echo htmlspecialchars($item_sale['name'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-2 text-right"><?php echo htmlspecialchars($item_sale['quantity'] ?? 0); ?></td>
                                <td class="px-4 py-2 text-right"><?php echo formatCurrency($item_sale['total'] ?? 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <div> 
                <h3 class="text-lg font-semibold mb-3">Payment Methods</h3>
                <?php if ($completedOrders === 0): ?>
                <p class="text-gray-500">No completed orders with payment data for this day.</p>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($paymentMethods as $method => $count): ?>
                    <?php if ($count > 0 || ($method === 'pending' && $completedOrders > 0) ): ?>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="capitalize"><?php echo htmlspecialchars($method); ?></span>
                            <span><?php echo $count; ?> orders</span>
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
                
                <h3 class="text-lg font-semibold mt-6 mb-3">Orders by Hour</h3>
                <?php if (empty($dateOrders)): ?>
                <p class="text-gray-500">No order data to display hourly breakdown for this day.</p>
                <?php else: ?>
                <?php
                $hourlyOrders = array_fill(0, 24, 0);
                foreach ($dateOrders as $order) {
                    if (isset($order['created_at'])) {
                        $hour = (int)date('G', strtotime($order['created_at']));
                        if (isset($hourlyOrders[$hour])) { $hourlyOrders[$hour]++; }
                    }
                }
                $maxOrdersInHour = !empty($hourlyOrders) ? max($hourlyOrders) : 0;
                if ($maxOrdersInHour == 0 && count($dateOrders) > 0) {
                    $maxOrdersInHour = 1; 
                } else if ($maxOrdersInHour == 0 && empty($dateOrders)) {
                    $maxOrdersInHour = 1; 
                }
                ?>
                <div class="flex items-end h-48 space-x-px bg-gray-50 p-3 rounded relative">
                    <?php for ($i = 0; $i < 24; $i++): ?>
                    <?php 
                    $orderCountThisHour = $hourlyOrders[$i];
                    $heightPercentage = ($maxOrdersInHour > 0) ? ($orderCountThisHour / $maxOrdersInHour) * 100 : 0;
                    
                    $barHeightStyle = '0%'; 
                    if ($orderCountThisHour > 0) {
                        if ($heightPercentage < 3 && $heightPercentage > 0) {
                            $barHeightStyle = '3%'; 
                        } elseif ($heightPercentage >=3) {
                            $barHeightStyle = $heightPercentage . '%';
                        }
                    }

                    $displayHourText = date('ga', strtotime("$i:00"));
                    ?>
                    <div class="flex flex-col items-center flex-1 group relative" style="min-width: 15px;">
                        <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -mt-5 px-1.5 py-0.5 bg-gray-700 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
                            <?php echo $orderCountThisHour; ?> orders
                        </div>
                        <div class="w-3/4 md:w-2/3 bg-blue-400 hover:bg-blue-600 rounded-t transition-all duration-150" 
                             style="height: <?php echo $barHeightStyle; ?>;"
                             title="<?php echo $orderCountThisHour . ' orders at ' . date('g A', strtotime("$i:00"));?>">
                        </div>
                        <div class="text-xs mt-1 text-gray-500 text-center" style="font-size: 0.6rem; line-height: 1;">
                            <?php echo str_replace('m', '', $displayHourText); ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                <p class="text-xs text-gray-400 mt-1 text-center">Hover over bars for exact counts.</p>
                <?php endif; ?>
                </div>
        </div>
    </div>
</div>