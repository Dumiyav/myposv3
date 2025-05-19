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

// Get table details
$tables = readJsonFile('tables.json');
$table = null;

foreach ($tables as $t) {
    if ($t['id'] === $order['table_id']) {
        $table = $t;
        break;
    }
}

// Get menu items
$menuItems = readJsonFile('menu.json');
$orderItemsDetails = [];

foreach ($order['items'] as $item) {
    $menuItem = null;
    foreach ($menuItems as $mi) {
        if ($mi['id'] === $item['menu_item_id']) {
            $menuItem = $mi;
            break;
        }
    }
    
    if ($menuItem) {
        $orderItemsDetails[] = [
            'name' => $menuItem['name'],
            'price' => $menuItem['price'],
            'quantity' => $item['quantity'],
            'total' => $menuItem['price'] * $item['quantity']
        ];
    }
}

// Calculate totals
$subtotal = 0;
foreach ($orderItemsDetails as $item) {
    $subtotal += $item['total'];
}

$discount = $order['discount'] ?? 0;
$tax = $order['tax'] ?? ($subtotal * TAX_RATE / 100);
$total = $subtotal - $discount + $tax;

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
    <title>Receipt #<?php echo $orderId; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            line-height: 1.4;
        }
        .receipt {
            max-width: 400px;
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
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
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
        }
        .total-row.final {
            font-weight: bold;
            border-top: 1px solid #ddd;
            padding-top: 5px;
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
            }
            .receipt {
                border: none;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1><?php echo APP_NAME; ?></h1>
            <p>Receipt</p>
        </div>
        
        <div class="info">
            <div class="info-row">
                <span>Order #:</span>
                <span><?php echo $orderId; ?></span>
            </div>
            <div class="info-row">
                <span>Date:</span>
                <span><?php echo $order['created_at']; ?></span>
            </div>
            <div class="info-row">
                <span>Table:</span>
                <span><?php echo $table ? $table['name'] : 'Unknown'; ?></span>
            </div>
            <div class="info-row">
                <span>Status:</span>
                <span><?php echo ucfirst($order['status']); ?></span>
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
                <?php foreach ($orderItemsDetails as $item): ?>
                <tr>
                    <td><?php echo $item['name']; ?></td>
                    <td class="text-right"><?php echo formatCurrency($item['price']); ?></td>
                    <td class="text-right"><?php echo $item['quantity']; ?></td>
                    <td class="text-right"><?php echo formatCurrency($item['total']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span><?php echo formatCurrency($subtotal); ?></span>
            </div>
            
            <?php if ($discount > 0): ?>
            <div class="total-row">
                <span>Discount:</span>
                <span><?php echo formatCurrency($discount); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-row">
                <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                <span><?php echo formatCurrency($tax); ?></span>
            </div>
            
            <div class="total-row final">
                <span>Total:</span>
                <span><?php echo formatCurrency($total); ?></span>
            </div>
        </div>
        
        <div class="payment-info">
            <div class="info-row">
                <span>Payment Method:</span>
                <span><?php echo $order['payment_method'] ? ucfirst($order['payment_method']) : 'Pending'; ?></span>
            </div>
            <div class="info-row">
                <span>Payment Status:</span>
                <span><?php echo ucfirst($order['payment_status']); ?></span>
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
        // Auto-print when the page loads
        window.onload = function() {
            // Uncomment the line below to automatically open the print dialog
            // window.print();
        }
    </script>
</body>
</html>