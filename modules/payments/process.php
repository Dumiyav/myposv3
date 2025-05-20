<?php
// Get active orders
$orders = readJsonFile('orders.json');
$tables = readJsonFile('tables.json');
$menuItemsAll = readJsonFile('menu.json'); // menu.json එක load කරගන්නවා items වල නම් ගන්න - Renamed for clarity

// Filter active orders
$activeOrders = [];
if (is_array($orders)) { // Ensure $orders is an array
    foreach ($orders as $order) {
        if (isset($order['status']) && $order['status'] === 'active') {
            $activeOrders[] = $order;
        }
    }
}


// Create table lookup
$tableLookup = [];
if (is_array($tables)) { // Ensure $tables is an array
    foreach ($tables as $table) {
        if (isset($table['id'])) { // Ensure 'id' key exists
            $tableLookup[$table['id']] = $table;
        }
    }
}


// Process payment form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    $orderId = $_POST['order_id'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? '';
    
    // Validate input
    $errors = [];
    
    if (empty($orderId)) {
        $errors[] = 'Order ID is required.';
    }
    
    if (empty($paymentMethod)) {
        $errors[] = 'Payment method is required.';
    }
    
    // If no errors, process the payment
    if (empty($errors)) {
        // Find the order
        $orderIndex = -1;
        if (is_array($orders)) {
            foreach ($orders as $index => $o) { 
                if (isset($o['id']) && $o['id'] === $orderId) {
                    $orderIndex = $index;
                    break;
                }
            }
        }
        
        if ($orderIndex !== -1) {
            // Update order
            $orders[$orderIndex]['status'] = 'completed';
            $orders[$orderIndex]['payment_method'] = $paymentMethod;
            $orders[$orderIndex]['payment_status'] = 'paid';
            $orders[$orderIndex]['updated_at'] = date(DATE_FORMAT);
            
            $tableIdToUpdate = $orders[$orderIndex]['table_id'] ?? null;
            $tablesSaved = true; // Assume true if no table update is needed

            if ($tableIdToUpdate && is_array($tables)) {
                foreach ($tables as &$table) { 
                    if (isset($table['id']) && $table['id'] === $tableIdToUpdate) {
                        $table['status'] = 'available';
                        break;
                    }
                }
                 $tablesSaved = writeJsonFile('tables.json', $tables);
            }
            
            $ordersSaved = writeJsonFile('orders.json', $orders);
            
            if ($ordersSaved && $tablesSaved) {
                setFlashMessage('success', 'Payment processed successfully.');
                header('Location: index.php?page=payments');
                exit;
            } else {
                $errors[] = 'Failed to process payment.';
                if (!$ordersSaved) $errors[] = 'Failed to save order data.';
                if (!$tablesSaved && $tableIdToUpdate) {
                    $errors[] = 'Failed to save table data.';
                }
            }
        } else {
            $errors[] = 'Order not found.';
        }
    }
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Process Payments</h1>
    
    <?php
    $flashMsg = getFlashMessage(); // Get flash message once
    if ($flashMsg): ?>
    <div class="bg-<?php echo $flashMsg['type'] === 'success' ? 'green' : 'red'; ?>-100 border-l-4 border-<?php echo $flashMsg['type'] === 'success' ? 'green' : 'red'; ?>-500 text-<?php echo $flashMsg['type'] === 'success' ? 'green' : 'red'; ?>-700 p-4 mb-6" role="alert">
        <p><?php echo $flashMsg['message']; ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (empty($activeOrders)): ?>
    <div class="bg-white rounded-lg shadow-md p-6 text-center">
        <p class="text-gray-500">No active orders to process payments for.</p>
        <a href="index.php?page=order_create" class="inline-block mt-4 bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
            Create New Order
        </a>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($activeOrders as $order): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6">
                <div class="flex justify-between items-start mb-2"> <h3 class="text-lg font-semibold">Order #<?php echo substr(htmlspecialchars($order['id'] ?? ''), -4); ?></h3>
                    <span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                        Active
                    </span>
                </div>
                
                <p class="text-gray-500 mb-2">
                    Table: <?php 
                        $tableName = 'Unknown';
                        if (isset($order['table_id']) && isset($tableLookup[$order['table_id']]['name'])) {
                            $tableName = $tableLookup[$order['table_id']]['name'];
                        }
                        echo htmlspecialchars($tableName); 
                    ?><br>
                    Date: <?php echo formatDate($order['created_at'] ?? '', 'M d, Y H:i'); ?>
                </p>
                <div class="text-sm text-gray-700 mb-4">
                    <strong>Items (<?php echo (isset($order['items']) && is_array($order['items'])) ? count($order['items']) : 0; ?>):</strong>
                    <?php if (isset($order['items']) && is_array($order['items']) && count($order['items']) > 0): ?>
                        <ul class="list-disc list-inside ml-1 text-xs"> <?php
                            $itemDisplayCount = 0;
                            $maxItemsToShow = 2; 
                            foreach ($order['items'] as $itemEntry):
                                $itemName = 'Unknown Item';
                                // MODIFICATION START: Handle custom items
                                if (isset($itemEntry['is_custom']) && $itemEntry['is_custom'] === true) {
                                    $itemName = $itemEntry['custom_name'] ?? 'Custom Item';
                                } else if (isset($itemEntry['menu_item_id']) && is_array($menuItemsAll)) {
                                    foreach ($menuItemsAll as $menuItem) { 
                                        if (isset($menuItem['id']) && $menuItem['id'] === $itemEntry['menu_item_id']) {
                                            $itemName = $menuItem['name'] ?? 'Unnamed Menu Item';
                                            break;
                                        }
                                    }
                                }
                                // MODIFICATION END
                                echo '<li>' . htmlspecialchars($itemName) . ' (Qty: ' . htmlspecialchars($itemEntry['quantity'] ?? 0) . ')</li>';
                                $itemDisplayCount++;
                                if ($itemDisplayCount >= $maxItemsToShow && count($order['items']) > $maxItemsToShow) { 
                                    echo '<li class="text-gray-500">...and more (View Details)</li>';
                                    break;
                                }
                            endforeach;
                            ?>
                        </ul>
                    <?php else: ?>
                        <p class="ml-4 text-xs">No items in this order.</p> 
                    <?php endif; ?>
                </div>
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <?php
                        // Calculate subtotal for display on this card if not directly stored or if preferred
                        $cardSubtotal = 0;
                        if (isset($order['items']) && is_array($order['items'])) {
                            foreach($order['items'] as $cardItem) {
                                $itemPrice = 0;
                                if (isset($cardItem['is_custom']) && $cardItem['is_custom'] === true) {
                                    $itemPrice = $cardItem['custom_price'] ?? 0;
                                } else if (isset($cardItem['menu_item_id']) && is_array($menuItemsAll)) {
                                    foreach($menuItemsAll as $mi) {
                                        if (isset($mi['id']) && $mi['id'] === $cardItem['menu_item_id']) {
                                            $itemPrice = $mi['price'] ?? 0;
                                            break;
                                        }
                                    }
                                }
                                $cardSubtotal += $itemPrice * ($cardItem['quantity'] ?? 0);
                            }
                        }
                        $orderDiscount = $order['discount'] ?? 0;
                        $orderTax = $order['tax'] ?? 0;
                        $orderTotal = $order['total'] ?? 0; // Use the authoritative stored total
                    ?>
                    <div class="flex justify-between mb-1">
                        <span>Subtotal:</span>
                        <span><?php echo formatCurrency($cardSubtotal); ?></span>
                    </div>
                    
                    <?php if ($orderDiscount > 0): ?>
                    <div class="flex justify-between mb-1">
                        <span>Discount:</span>
                        <span class="text-red-500">-<?php echo formatCurrency($orderDiscount); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between mb-1">
                        <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                        <span><?php echo formatCurrency($orderTax); ?></span>
                    </div>
                    
                    <div class="flex justify-between font-bold border-t pt-1">
                        <span>Total:</span>
                        <span><?php echo formatCurrency($orderTotal); ?></span>
                    </div>
                </div>
                
                <form method="POST" action="index.php?page=payments">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id'] ?? ''); ?>">
                    
                    <div class="mb-4">
                        <label for="payment_method_<?php echo htmlspecialchars($order['id'] ?? ''); ?>" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select id="payment_method_<?php echo htmlspecialchars($order['id'] ?? ''); ?>" name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Select payment method</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="mobile">Mobile Payment</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-between">
                        <a href="index.php?page=orders&id=<?php echo htmlspecialchars($order['id'] ?? ''); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded text-sm">
                            View Details
                        </a>
                        
                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded text-sm">
                            Process Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>