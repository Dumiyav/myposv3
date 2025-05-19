<?php
// Check if ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'Order ID is required.');
    header('Location: index.php?page=orders');
    exit;
}

$id = $_GET['id'];

// Get order data
$orders = readJsonFile('orders.json');
$tables = readJsonFile('tables.json');
$menuItems = readJsonFile('menu.json');

// Find the order
$order = null;
foreach ($orders as $o) {
    if ($o['id'] === $id) {
        $order = $o;
        break;
    }
}

// If order not found
if (!$order) {
    setFlashMessage('error', 'Order not found.');
    header('Location: index.php?page=orders');
    exit;
}

// Find table
$table = null;
foreach ($tables as $t) {
    if ($t['id'] === $order['table_id']) {
        $table = $t;
        break;
    }
}

// Create menu lookup
$menuLookup = [];
foreach ($menuItems as $item) {
    $menuLookup[$item['id']] = $item;
}

// Process form submission for updating order items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_items') {
    // Get form data
    $items = isset($_POST['items']) ? $_POST['items'] : [];
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
    $discount = floatval($_POST['discount'] ?? 0);
    
    // Prepare order items
    $orderItems = [];
    for ($i = 0; $i < count($items); $i++) {
        if (isset($items[$i]) && isset($quantities[$i]) && $quantities[$i] > 0) {
            $orderItems[] = [
                'menu_item_id' => $items[$i],
                'quantity' => intval($quantities[$i]),
                'notes' => '',
                'status' => 'pending'
            ];
        }
    }
    
    // Calculate order total
    $totals = calculateOrderTotal($orderItems, $menuItems, $discount);
    
    // Update order
    foreach ($orders as &$o) {
        if ($o['id'] === $id) {
            $o['items'] = $orderItems;
            $o['discount'] = $discount;
            $o['tax'] = $totals['tax'];
            $o['total'] = $totals['total'];
            $o['updated_at'] = date(DATE_FORMAT);
            break;
        }
    }
    
    // Save changes
    if (writeJsonFile('orders.json', $orders)) {
        setFlashMessage('success', 'Order updated successfully.');
        header('Location: index.php?page=order_update&id=' . $id);
        exit;
    } else {
        setFlashMessage('error', 'Failed to update order.');
    }
}

// Process form submission for completing order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_order') {
    // Get form data
    $paymentMethod = $_POST['payment_method'] ?? '';
    
    // Validate input
    $errors = [];
    
    if (empty($paymentMethod)) {
        $errors[] = 'Payment method is required.';
    }
    
    // If no errors, complete the order
    if (empty($errors)) {
        // Update order
        foreach ($orders as &$o) {
            if ($o['id'] === $id) {
                $o['status'] = 'completed';
                $o['payment_method'] = $paymentMethod;
                $o['payment_status'] = 'paid';
                $o['updated_at'] = date(DATE_FORMAT);
                break;
            }
        }
        
        // Update table status
        foreach ($tables as &$t) {
            if ($t['id'] === $order['table_id']) {
                $t['status'] = 'available';
                break;
            }
        }
        
        // Save changes
        $ordersSaved = writeJsonFile('orders.json', $orders);
        $tablesSaved = writeJsonFile('tables.json', $tables);
        
        if ($ordersSaved && $tablesSaved) {
            setFlashMessage('success', 'Order completed successfully.');
            header('Location: index.php?page=orders');
            exit;
        } else {
            setFlashMessage('error', 'Failed to complete order.');
        }
    }
}
?>

<div class="container mx-auto">
    <div class="flex items-center mb-6">
        <a href="index.php?page=orders" class="mr-4">
            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <h1 class="text-2xl font-bold">Order #<?php echo substr($order['id'], -4); ?></h1>
        
        <div class="ml-4">
            <?php
            $statusClass = '';
            switch ($order['status']) {
                case 'active':
                    $statusClass = 'bg-blue-100 text-blue-800';
                    break;
                case 'completed':
                    $statusClass = 'bg-green-100 text-green-800';
                    break;
                case 'cancelled':
                    $statusClass = 'bg-red-100 text-red-800';
                    break;
            }
            ?>
            <span class="px-2 py-1 rounded-full text-xs <?php echo $statusClass; ?>">
                <?php echo ucfirst($order['status']); ?>
            </span>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Order Details -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Order Items</h2>
                    
                    <?php if ($order['status'] === 'active'): ?>
                    <button type="button" class="text-blue-500 hover:text-blue-700" onclick="toggleEditMode()">
                        <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Items
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- View Mode -->
                <div id="viewMode" class="<?php echo isset($_POST['action']) && $_POST['action'] === 'update_items' ? 'hidden' : ''; ?>">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($order['items'] as $item): ?>
                                <?php
                                $menuItem = isset($menuLookup[$item['menu_item_id']]) ? $menuLookup[$item['menu_item_id']] : null;
                                if (!$menuItem) continue;
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $menuItem['name']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo formatCurrency($menuItem['price']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo $item['quantity']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo formatCurrency($menuItem['price'] * $item['quantity']); ?></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($order['items'])): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                        No items in this order.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-6 space-y-2">
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span><?php echo formatCurrency($order['total'] - $order['tax']); ?></span>
                        </div>
                        
                        <?php if ($order['discount'] > 0): ?>
                        <div class="flex justify-between">
                            <span>Discount:</span>
                            <span>-<?php echo formatCurrency($order['discount']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-between">
                            <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                            <span><?php echo formatCurrency($order['tax']); ?></span>
                        </div>
                        
                        <div class="border-t pt-2 flex justify-between font-bold">
                            <span>Total:</span>
                            <span><?php echo formatCurrency($order['total']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Mode -->
                <div id="editMode" class="<?php echo isset($_POST['action']) && $_POST['action'] === 'update_items' ? '' : 'hidden'; ?>">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_items">
                        
                        <div class="space-y-4">
                            <?php foreach ($order['items'] as $index => $item): ?>
                            <?php
                            $menuItem = isset($menuLookup[$item['menu_item_id']]) ? $menuLookup[$item['menu_item_id']] : null;
                            if (!$menuItem) continue;
                            ?>
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-medium"><?php echo $menuItem['name']; ?></div>
                                    <div class="text-sm text-gray-500"><?php echo formatCurrency($menuItem['price']); ?> each</div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <input type="hidden" name="items[<?php echo $index; ?>]" value="<?php echo $item['menu_item_id']; ?>">
                                    <input type="number" name="quantities[<?php echo $index; ?>]" value="<?php echo $item['quantity']; ?>" min="1" class="w-16 px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <button type="button" onclick="removeItem(this)" class="text-red-500 hover:text-red-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-6">
                            <label for="discount" class="block text-sm font-medium text-gray-700 mb-1">Discount</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm"><?php echo CURRENCY; ?></span>
                                </div>
                                <input type="number" id="discount" name="discount" value="<?php echo $order['discount']; ?>" min="0" step="0.01" class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="button" onclick="toggleEditMode()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                                Cancel
                            </button>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                                Update Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                
                <div class="space-y-4">
                    <div>
                        <span class="text-gray-500">Order ID:</span>
                        <span class="float-right">#<?php echo substr($order['id'], -4); ?></span>
                    </div>
                    
                    <div>
                        <span class="text-gray-500">Table:</span>
                        <span class="float-right"><?php echo $table ? $table['name'] : 'Unknown'; ?></span>
                    </div>
                    
                    <div>
                        <span class="text-gray-500">Date:</span>
                        <span class="float-right"><?php echo formatDate($order['created_at'], 'M d, Y H:i'); ?></span>
                    </div>
                    
                    <div>
                        <span class="text-gray-500">Status:</span>
                        <span class="float-right">
                            <span class="px-2 py-1 rounded-full text-xs <?php echo $statusClass; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </span>
                    </div>
                    
                    <?php if ($order['status'] === 'completed'): ?>
                    <div>
                        <span class="text-gray-500">Payment Method:</span>
                        <span class="float-right"><?php echo ucfirst($order['payment_method']); ?></span>
                    </div>
                    
                    <div>
                        <span class="text-gray-500">Payment Status:</span>
                        <span class="float-right">
                            <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($order['status'] === 'active'): ?>
                <div class="mt-6">
                    <h3 class="text-md font-medium mb-2">Complete Order</h3>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="complete_order">
                        
                        <div class="mb-4">
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select id="payment_method" name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                                <option value="">Select payment method</option>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                            Complete Order
                        </button>
                    </form>
                </div>
                
                <div class="mt-4">
                    <a href="index.php?page=orders&action=cancel&id=<?php echo $order['id']; ?>" class="block text-center w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded" onclick="return confirm('Are you sure you want to cancel this order?')">
                        Cancel Order
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($order['status'] === 'completed'): ?>
                <div class="mt-6">
                    <button type="button" onclick="printReceipt()" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        Print Receipt
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Receipt Template (Hidden) -->
    <div id="receipt" class="hidden bg-white p-8 max-w-md mx-auto">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold"><?php echo APP_NAME; ?></h1>
            <p>123 Restaurant Street, City</p>
            <p>Phone: (123) 456-7890</p>
        </div>
        
        <div class="mb-6">
            <div class="flex justify-between">
                <span>Order #:</span>
                <span>#<?php echo substr($order['id'], -4); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Date:</span>
                <span><?php echo formatDate($order['created_at'], 'M d, Y H:i'); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Table:</span>
                <span><?php echo $table ? $table['name'] : 'Unknown'; ?></span>
            </div>
        </div>
        
        <div class="border-t border-b py-4 mb-6">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2">Item</th>
                        <th class="text-right py-2">Qty</th>
                        <th class="text-right py-2">Price</th>
                        <th class="text-right py-2">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                    <?php
                    $menuItem = isset($menuLookup[$item['menu_item_id']]) ? $menuLookup[$item['menu_item_id']] : null;
                    if (!$menuItem) continue;
                    ?>
                    <tr>
                        <td class="py-2"><?php echo $menuItem['name']; ?></td>
                        <td class="text-right py-2"><?php echo $item['quantity']; ?></td>
                        <td class="text-right py-2"><?php echo formatCurrency($menuItem['price']); ?></td>
                        <td class="text-right py-2"><?php echo formatCurrency($menuItem['price'] * $item['quantity']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mb-6">
            <div class="flex justify-between">
                <span>Subtotal:</span>
                <span><?php echo formatCurrency($order['total'] - $order['tax']); ?></span>
            </div>
            
            <?php if ($order['discount'] > 0): ?>
            <div class="flex justify-between">
                <span>Discount:</span>
                <span>-<?php echo formatCurrency($order['discount']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="flex justify-between">
                <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                <span><?php echo formatCurrency($order['tax']); ?></span>
            </div>
            
            <div class="flex justify-between font-bold border-t pt-2">
                <span>Total:</span>
                <span><?php echo formatCurrency($order['total']); ?></span>
            </div>
        </div>
        
        <div class="text-center">
            <p>Payment Method: <?php echo ucfirst($order['payment_method']); ?></p>
            <p class="mt-4">Thank you for dining with us!</p>
        </div>
    </div>
</div>

<script>
function toggleEditMode() {
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    
    if (viewMode.classList.contains('hidden')) {
        viewMode.classList.remove('hidden');
        editMode.classList.add('hidden');
    } else {
        viewMode.classList.add('hidden');
        editMode.classList.remove('hidden');
    }
}

function removeItem(button) {
    const itemDiv = button.closest('div').parentNode.parentNode;
    itemDiv.remove();
}

function printReceipt() {
    const receipt = document.getElementById('receipt');
    
    // Generate PDF
    html2pdf()
        .from(receipt)
        .save('receipt-<?php echo substr($order['id'], -4); ?>.pdf');
}
</script>