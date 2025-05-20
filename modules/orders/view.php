<?php
// Get orders
$orders = readJsonFile('orders.json');
$menuItems = readJsonFile('menu.json');

// Get order ID from URL if provided
$orderId = isset($_GET['id']) ? $_GET['id'] : null;
$order = null;

if ($orderId) {
    foreach ($orders as $o) {
        if ($o['id'] === $orderId) {
            $order = $o;
            break;
        }
    }
}

// Process order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['order_id'])) {
    $action = $_POST['action'];
    $orderIdForAction = $_POST['order_id']; // Renamed to avoid conflict if $orderId is null

    // Find the order
    $orderIndex = -1;
    foreach ($orders as $index => $o) {
        if ($o['id'] === $orderIdForAction) {
            $orderIndex = $index;
            break;
        }
    }

    if ($orderIndex >= 0) {
        switch ($action) {
            case 'complete':
                $orders[$orderIndex]['status'] = 'completed';
                $orders[$orderIndex]['updated_at'] = date(DATE_FORMAT);
                break;
            
            case 'cancel':
                $orders[$orderIndex]['status'] = 'cancelled';
                $orders[$orderIndex]['updated_at'] = date(DATE_FORMAT);
                break;
            
            case 'pay':
                $paymentMethod = $_POST['payment_method'] ?? '';
                
                if (!empty($paymentMethod)) {
                    $orders[$orderIndex]['payment_method'] = $paymentMethod;
                    $orders[$orderIndex]['payment_status'] = 'paid';
                    $orders[$orderIndex]['updated_at'] = date(DATE_FORMAT);
                } else {
                     setFlashMessage('error', 'Payment method is required to process payment.');
                }
                break;
        }
        
        // Save changes if no new error was set (e.g. for payment method)
        if (!isset($_SESSION['flash_message']) || $_SESSION['flash_message']['type'] !== 'error') {
            if (writeJsonFile('orders.json', $orders)) {
                setFlashMessage('success', 'Order updated successfully.');
            } else {
                setFlashMessage('error', 'Failed to update order.');
            }
        }
        
        // Redirect to refresh
        // If $orderId was initially set (viewing a specific order), redirect back to it. Otherwise to general orders page.
        header('Location: index.php?page=orders' . ($orderId ? '&id=' . $orderId : ''));
        exit;
    } else {
        setFlashMessage('error', 'Order not found for action.');
        header('Location: index.php?page=orders');
        exit;
    }
}

// If viewing a specific order
if ($order) {
    // Get order items details
    $orderItemsDisplay = []; // Use a different variable name for clarity
    $subtotal = 0;

    foreach ($order['items'] as $item_in_order) { // Renamed to avoid confusion
        $item_details_for_display = [
            'name' => 'Unknown Item',
            'price' => 0,
            'quantity' => $item_in_order['quantity'] ?? 0,
            'notes' => $item_in_order['notes'] ?? '',
            'status' => $item_in_order['status'] ?? 'pending',
            'total' => 0,
            'is_custom' => false // Default
        ];

        if (isset($item_in_order['is_custom']) && $item_in_order['is_custom'] === true) {
            // This is a custom item
            $item_details_for_display['name'] = $item_in_order['custom_name'] ?? 'Custom Item';
            $item_details_for_display['price'] = $item_in_order['custom_price'] ?? 0;
            $item_details_for_display['is_custom'] = true;
            // 'id' for custom items is not from menu.json, can be omitted or use a generated one if needed for display keys
        } else if (isset($item_in_order['menu_item_id'])) {
            // This is a regular menu item
            $menuItemFound = false;
            foreach ($menuItems as $mi) {
                if ($mi['id'] === $item_in_order['menu_item_id']) {
                    $item_details_for_display['id'] = $item_in_order['menu_item_id'];
                    $item_details_for_display['name'] = $mi['name'];
                    $item_details_for_display['price'] = $mi['price'];
                    $menuItemFound = true;
                    break;
                }
            }
            if (!$menuItemFound) {
                $item_details_for_display['name'] = 'Menu Item Not Found (' . htmlspecialchars($item_in_order['menu_item_id']) . ')';
                $item_details_for_display['price'] = 0; // Avoid errors with calculations
                 error_log("Order view: Menu item ID {$item_in_order['menu_item_id']} not found in menu.json for order ID {$order['id']}");
            }
        } else {
            // Invalid item structure in order
            error_log("Order view: Invalid item structure in order ID {$order['id']}: " . print_r($item_in_order, true));
            // Keep default 'Unknown Item'
        }
        
        // Calculate total for this item
        $item_details_for_display['total'] = $item_details_for_display['price'] * $item_details_for_display['quantity'];
        $subtotal += $item_details_for_display['total'];
        $orderItemsDisplay[] = $item_details_for_display;
    }

    // Calculate final totals based on the order record
    $discount = $order['discount'] ?? 0;
    $tax = $order['tax'] ?? 0; // Tax is already calculated and stored
    $total = $order['total'] ?? 0; // Total is already calculated and stored
    
    // As a sanity check, you could recalculate if needed, but typically you'd display stored values.
    // For display consistency, we use the totals from the $order array.
    // $calculatedSubtotal = $subtotal; // Subtotal from items sum
    // $calculatedTotalAfterDiscount = $calculatedSubtotal - $discount;
    // $calculatedTax = $calculatedTotalAfterDiscount * (TAX_RATE / 100); // Assuming TAX_RATE is defined
    // $finalCalculatedTotal = $calculatedTotalAfterDiscount + $calculatedTax;
    // It's usually best to display what was stored with the order.
    // The $subtotal here is just for the display of sum of line items.
?>

<div class="container mx-auto">
    <div class="flex items-center mb-6">
        <a href="index.php?page=orders" class="mr-4">
            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <h1 class="text-2xl font-bold">Order #<?php echo htmlspecialchars($order['id']); ?></h1>
        <span class="ml-4 px-3 py-1 rounded-full text-sm font-medium <?php echo getStatusClass($order['status']); ?>">
            <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">Order Items</h2>
                
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
                            <?php if (empty($orderItemsDisplay)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-gray-500">No items found in this order.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orderItemsDisplay as $item_display): ?>
                                <tr class="border-b">
                                    <td class="px-4 py-2">
                                        <div class="font-medium">
                                            <?php echo htmlspecialchars($item_display['name']); ?>
                                            <?php if ($item_display['is_custom']): ?>
                                                <span class="text-xs text-blue-500">(Custom)</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($item_display['notes'])): ?>
                                        <div class="text-sm text-gray-500"><?php echo nl2br(htmlspecialchars($item_display['notes'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-right"><?php echo formatCurrency($item_display['price']); ?></td>
                                    <td class="px-4 py-2 text-right"><?php echo $item_display['quantity']; ?></td>
                                    <td class="px-4 py-2 text-right"><?php echo formatCurrency($item_display['total']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-right font-medium">Subtotal (from items):</td>
                                <td class="px-4 py-2 text-right"><?php echo formatCurrency($subtotal); ?></td>
                            </tr>
                            <?php if ($discount > 0): ?>
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-right font-medium">Discount:</td>
                                <td class="px-4 py-2 text-right text-red-500">-<?php echo formatCurrency($discount); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-right font-medium">Tax (<?php echo TAX_RATE; ?>%):</td>
                                <td class="px-4 py-2 text-right"><?php echo formatCurrency($tax); ?></td>
                            </tr>
                            <tr class="bg-gray-50">
                                <td colspan="3" class="px-4 py-2 text-right font-bold">Total (Stored):</td>
                                <td class="px-4 py-2 text-right font-bold"><?php echo formatCurrency($total); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Order ID:</span>
                        <span class="font-medium">#<?php echo htmlspecialchars($order['id']); ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Date:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($order['created_at']); ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo getStatusClass($order['status']); ?>">
                            <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                        </span>
                    </div>
                    
                    <?php if ($order['status'] !== 'cancelled'): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Method:</span>
                        <span class="font-medium"><?php echo !empty($order['payment_method']) ? ucfirst(htmlspecialchars($order['payment_method'])) : 'Not set'; ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Status:</span>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo getPaymentStatusClass($order['payment_status']); ?>">
                            <?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="mt-6 space-y-3">
                    <?php if ($order['status'] === 'active'): ?>
                    <form method="POST" action="index.php?page=orders&id=<?php echo htmlspecialchars($order['id']); ?>">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                        <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                            Mark as Completed
                        </button>
                    </form>
                    
                    <form method="POST" action="index.php?page=orders&id=<?php echo htmlspecialchars($order['id']); ?>">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                        <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded" onclick="return confirm('Are you sure you want to cancel this order?');">
                            Cancel Order
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] !== 'cancelled'): ?>
                        <?php if ($order['payment_status'] === 'pending' && $order['status'] === 'completed'): // Payment can be processed for completed orders ?>
                        <form method="POST" action="index.php?page=orders&id=<?php echo htmlspecialchars($order['id']); ?>">
                            <input type="hidden" name="action" value="pay">
                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                            
                            <div class="mb-3">
                                <label for="payment_method_<?php echo htmlspecialchars($order['id']); ?>" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                <select id="payment_method_<?php echo htmlspecialchars($order['id']); ?>" name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                                    <option value="">Select payment method</option>
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="mobile">Mobile Payment</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                                Process Payment
                            </button>
                        </form>
                        <?php elseif ($order['payment_status'] === 'paid'): ?>
                             <p class="text-center text-green-600 font-semibold">Payment Processed</p>
                        <?php endif; ?>
                        
                        <a href="modules/payments/receipt.php?order_id=<?php echo htmlspecialchars($order['id']); ?>" target="_blank" class="mt-3 block w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded text-center">
                            Print Receipt
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
} else {
    // List all orders
    // Sort orders by created_at (newest first)
    usort($orders, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
?>

<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Orders</h1>
        
        <a href="index.php?page=order_create" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded inline-flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            New Order
        </a>
    </div>
     <?php
    $flashMessage = getFlashMessage();
    if ($flashMessage): ?>
    <div class="mb-4 p-4 rounded-md <?php echo $flashMessage['type'] === 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>" role="alert">
        <?php echo $flashMessage['message']; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
    <div class="bg-white rounded-lg shadow-md p-6 text-center">
        <p class="text-gray-500">No orders found.</p>
        <a href="index.php?page=order_create" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
            Create New Order
        </a>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-4 py-2 text-left">Order ID</th>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-right">Total</th>
                        <th class="px-4 py-2 text-center">Status</th>
                        <th class="px-4 py-2 text-center">Payment</th>
                        <th class="px-4 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <a href="index.php?page=orders&id=<?php echo htmlspecialchars($o['id']); ?>" class="text-blue-500 hover:text-blue-700 font-medium">
                                #<?php echo htmlspecialchars($o['id']); ?>
                            </a>
                        </td>
                        <td class="px-4 py-2"><?php echo htmlspecialchars($o['created_at']); ?></td>
                        <td class="px-4 py-2 text-right"><?php echo formatCurrency($o['total']); ?></td>
                        <td class="px-4 py-2 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo getStatusClass($o['status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($o['status'])); ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo getPaymentStatusClass($o['payment_status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($o['payment_status'])); ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <div class="flex justify-center space-x-2">
                                <a href="index.php?page=orders&id=<?php echo htmlspecialchars($o['id']); ?>" class="text-blue-500 hover:text-blue-700" title="View">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                                
                                <?php if ($o['status'] !== 'cancelled'): ?>
                                <a href="modules/payments/receipt.php?order_id=<?php echo htmlspecialchars($o['id']); ?>" target="_blank" class="text-gray-500 hover:text-gray-700" title="Print Receipt">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                    </svg>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
} // End of the 'else' for listing all orders
?>