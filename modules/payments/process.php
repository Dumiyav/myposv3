<?php
// Get active orders
$orders = readJsonFile('orders.json');
$tables = readJsonFile('tables.json');
$menuItems = readJsonFile('menu.json');

// Filter active orders
$activeOrders = [];
foreach ($orders as $order) {
    if ($order['status'] === 'active') {
        $activeOrders[] = $order;
    }
}

// Create table lookup
$tableLookup = [];
foreach ($tables as $table) {
    $tableLookup[$table['id']] = $table;
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
        foreach ($orders as $index => $order) {
            if ($order['id'] === $orderId) {
                $orderIndex = $index;
                break;
            }
        }
        
        if ($orderIndex !== -1) {
            // Update order
            $orders[$orderIndex]['status'] = 'completed';
            $orders[$orderIndex]['payment_method'] = $paymentMethod;
            $orders[$orderIndex]['payment_status'] = 'paid';
            $orders[$orderIndex]['updated_at'] = date(DATE_FORMAT);
            
            // Update table status
            foreach ($tables as &$table) {
                if ($table['id'] === $orders[$orderIndex]['table_id']) {
                    $table['status'] = 'available';
                    break;
                }
            }
            
            // Save changes
            $ordersSaved = writeJsonFile('orders.json', $orders);
            $tablesSaved = writeJsonFile('tables.json', $tables);
            
            if ($ordersSaved && $tablesSaved) {
                setFlashMessage('success', 'Payment processed successfully.');
                header('Location: index.php?page=payments');
                exit;
            } else {
                $errors[] = 'Failed to process payment.';
            }
        } else {
            $errors[] = 'Order not found.';
        }
    }
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Process Payments</h1>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
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
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-semibold">Order #<?php echo substr($order['id'], -4); ?></h3>
                    <span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                        Active
                    </span>
                </div>
                
                <p class="text-gray-500 mb-4">
                    Table: <?php echo isset($tableLookup[$order['table_id']]) ? $tableLookup[$order['table_id']]['name'] : 'Unknown'; ?><br>
                    Items: <?php echo count($order['items']); ?><br>
                    Date: <?php echo formatDate($order['created_at'], 'M d, Y H:i'); ?>
                </p>
                
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <div class="flex justify-between mb-1">
                        <span>Subtotal:</span>
                        <span><?php echo formatCurrency($order['total'] - $order['tax']); ?></span>
                    </div>
                    
                    <?php if ($order['discount'] > 0): ?>
                    <div class="flex justify-between mb-1">
                        <span>Discount:</span>
                        <span>-<?php echo formatCurrency($order['discount']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between mb-1">
                        <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                        <span><?php echo formatCurrency($order['tax']); ?></span>
                    </div>
                    
                    <div class="flex justify-between font-bold border-t pt-1">
                        <span>Total:</span>
                        <span><?php echo formatCurrency($order['total']); ?></span>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    
                    <div class="mb-4">
                        <label for="payment_method_<?php echo $order['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select id="payment_method_<?php echo $order['id']; ?>" name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Select payment method</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-between">
                        <a href="index.php?page=order_update&id=<?php echo $order['id']; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded text-sm">
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