<?php
// Start output buffering to prevent headers already sent issues
if (!ob_get_level()) {
    ob_start();
}

require_once '../../config.php';
require_once '../../includes/functions.php';

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    die('Order ID is required');
}

$orderId = $_GET['order_id'];

// Get order details
$orders = readJsonFile('orders.json');
$order = null;

foreach ($orders as $o) {
    if ($o['id'] === $orderId) {
        $order = $o;
        break;
    }
}

if (!$order) {
    die('Order not found');
}

// Get table details (if applicable, check if table_id exists)
$table = null;
if (isset($order['table_id']) && !empty($order['table_id'])) {
    $tables = readJsonFile('tables.json');
    foreach ($tables as $t) {
        if ($t['id'] === $order['table_id']) {
            $table = $t;
            break;
        }
    }
}


// Get menu items for regular item lookup
$menuItemsAll = readJsonFile('menu.json'); // Renamed to avoid conflict
$orderItemsDetails = [];
$subtotal = 0; // Initialize subtotal for items listed on receipt

foreach ($order['items'] as $item_in_order) {
    $item_detail_for_receipt = [
        'name' => 'Unknown Item',
        'price' => 0,
        'quantity' => $item_in_order['quantity'] ?? 0,
        'total' => 0,
        'is_custom' => false
    ];

    if (isset($item_in_order['is_custom']) && $item_in_order['is_custom'] === true) {
        // This is a custom item
        $item_detail_for_receipt['name'] = $item_in_order['custom_name'] ?? 'Custom Item';
        $item_detail_for_receipt['price'] = $item_in_order['custom_price'] ?? 0;
        $item_detail_for_receipt['is_custom'] = true;
    } else if (isset($item_in_order['menu_item_id'])) {
        // This is a regular menu item
        $menuItemFound = false;
        foreach ($menuItemsAll as $mi) {
            if ($mi['id'] === $item_in_order['menu_item_id']) {
                $item_detail_for_receipt['name'] = $mi['name'];
                $item_detail_for_receipt['price'] = $mi['price'];
                $menuItemFound = true;
                break;
            }
        }
        if (!$menuItemFound) {
            $item_detail_for_receipt['name'] = 'Menu Item Not Found (' . htmlspecialchars($item_in_order['menu_item_id']) . ')';
            // Price remains 0 to avoid incorrect calculations if item is missing
            error_log("Receipt: Menu item ID {$item_in_order['menu_item_id']} not found in menu.json for order ID {$order['id']}");
        }
    } else {
        error_log("Receipt: Invalid item structure in order ID {$order['id']}: " . print_r($item_in_order, true));
    }
    
    $item_detail_for_receipt['total'] = $item_detail_for_receipt['price'] * $item_detail_for_receipt['quantity'];
    $subtotal += $item_detail_for_receipt['total'];
    $orderItemsDetails[] = $item_detail_for_receipt;
}

// Use stored totals from the order for discount, tax, and final total
$discount = $order['discount'] ?? 0;
$tax = $order['tax'] ?? 0;
$finalTotal = $order['total'] ?? 0; // This is the authoritative total

// Clear any previous output
while (ob_get_level()) {
    ob_end_clean();
}

// Generate HTML receipt
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo htmlspecialchars($orderId); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            line-height: 1.4;
        }
        .receipt {
            max-width: 400px; /* Standard receipt width */
            min-width: 300px; /* Minimum width */
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0;
        }
        .header p {
            margin: 5px 0;
        }
        .info {
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 6px 4px; /* Reduced padding */
            text-align: left;
            border-bottom: 1px solid #eee; /* Lighter border */
        }
        th {
            background-color: #f9f9f9; /* Lighter background */
            font-size: 11px; /* Smaller font for headers */
        }
        td {
            font-size: 11px; /* Smaller font for data */
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
             font-size: 11px;
        }
        .total-row.final {
            font-weight: bold;
            border-top: 1px solid #ccc; /* Slightly darker border for final total */
            padding-top: 5px;
            margin-top: 5px;
            font-size: 12px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 10px;
            color: #666;
        }
        .print-button {
            text-align: center;
            margin-top: 20px;
        }
        .print-button button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                padding: 0;
                font-size: 10pt; /* Adjust base font size for printing */
            }
            .receipt {
                border: none;
                max-width: 100%;
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
             th, td {
                padding: 4px 2px; /* Further reduce padding for print */
                font-size: 9pt;
            }
            .header h1 { font-size: 14pt; }
            .header p { font-size: 9pt; margin: 2px 0; }
            .info-row, .total-row { font-size: 9pt; margin-bottom: 3px; }
            .total-row.final { font-size: 10pt; }

        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1><?php echo htmlspecialchars(APP_NAME); ?></h1>
            <p>Receipt</p>
        </div>
        
        <div class="info">
            <div class="info-row">
                <span>Order #:</span>
                <span><?php echo htmlspecialchars($orderId); ?></span>
            </div>
            <div class="info-row">
                <span>Date:</span>
                <span><?php echo htmlspecialchars($order['created_at']); ?></span>
            </div>
            <?php if ($table): ?>
            <div class="info-row">
                <span>Table:</span>
                <span><?php echo htmlspecialchars($table['name']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span>Status:</span>
                <span><?php echo ucfirst(htmlspecialchars($order['status'])); ?></span>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItemsDetails as $item_display): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($item_display['name']); ?>
                        <?php if ($item_display['is_custom']): ?>
                            <span style="font-size: 0.8em; color: #555;">(Custom)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?php echo formatCurrency($item_display['price']); ?></td>
                    <td class="text-right"><?php echo $item_display['quantity']; ?></td>
                    <td class="text-right"><?php echo formatCurrency($item_display['total']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span><?php echo formatCurrency($subtotal); // Display sum of items ?></span>
            </div>
            
            <?php if ($discount > 0): ?>
            <div class="total-row">
                <span>Discount:</span>
                <span style="color: red;">-<?php echo formatCurrency($discount); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-row">
                <span>Tax (<?php echo htmlspecialchars(TAX_RATE); ?>%):</span>
                <span><?php echo formatCurrency($tax); ?></span>
            </div>
            
            <div class="total-row final">
                <span>Total:</span>
                <span><?php echo formatCurrency($finalTotal); // Display stored final total ?></span>
            </div>
        </div>
        
        <div class="payment-info" style="margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 10px;">
            <div class="info-row">
                <span>Payment Method:</span>
                <span><?php echo $order['payment_method'] ? ucfirst(htmlspecialchars($order['payment_method'])) : 'Pending'; ?></span>
            </div>
            <div class="info-row">
                <span>Payment Status:</span>
                <span><?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?></span>
            </div>
        </div>
        
        <div class="footer">
            <p>Thank you for your business!</p>
            <p>Generated on <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
    
    <div class="print-button">
        <button onclick="window.print()">Print Receipt</button>
    </div>
    
    <script>
        // Optional: Auto-print when the page loads, consider user experience.
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>