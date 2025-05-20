<?php
// --- prune_old_orders.php ---

// Ensure this script is not easily accessible via a web browser if not intended,
// or add authentication if you plan to trigger it via a web interface.
// For cron jobs, direct web access is usually not an issue.

// If this script is in the root 'mypos v04' folder:
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// If you place it, for example, in 'mypos v04/scripts/prune_old_orders.php',
// then the paths would be:
// require_once __DIR__ . '/../config.php';
// require_once __DIR__ . '/../includes/functions.php';

$ordersFile = DATA_PATH . '/orders.json'; // DATA_PATH comes from config.php
$orders = readJsonFile('orders.json');    // readJsonFile uses DATA_PATH internally
$retainedOrders = [];

$cutoffTimestamp = strtotime('-3 months'); // Get timestamp for 3 months ago

$prunedCount = 0;
$keptCount = 0;

if (is_array($orders)) {
    foreach ($orders as $order) {
        if (isset($order['created_at'])) {
            $orderTimestamp = strtotime($order['created_at']);
            if ($orderTimestamp >= $cutoffTimestamp) {
                $retainedOrders[] = $order;
                $keptCount++;
            } else {
                $prunedCount++;
            }
        } else {
            // Handle orders without a 'created_at' date if necessary.
            // For safety, you might keep them or log an error.
            $retainedOrders[] = $order; // Example: Keep them if no date
            $keptCount++;
            error_log("Order (ID: " . ($order['id'] ?? 'N/A') . ") kept due to missing 'created_at' date during pruning.");
        }
    }

    if (writeJsonFile('orders.json', $retainedOrders)) {
        $logMessage = sprintf(
            "Order pruning successful on %s. Pruned: %d orders. Kept: %d orders. Cutoff date: %s.\n",
            date('Y-m-d H:i:s'),
            $prunedCount,
            $keptCount,
            date('Y-m-d H:i:s', $cutoffTimestamp)
        );
        error_log($logMessage); // Logs to your PHP error log
        echo $logMessage; // Output for cron job logs
    } else {
        $logMessage = sprintf(
            "Failed to write updated orders to %s during pruning on %s.\n",
            $ordersFile,
            date('Y-m-d H:i:s')
        );
        error_log($logMessage);
        echo $logMessage;
    }
} else {
    $logMessage = sprintf(
        "Could not read or decode %s for pruning on %s. No orders processed.\n",
        $ordersFile,
        date('Y-m-d H:i:s')
    );
    error_log($logMessage);
    echo $logMessage;
}
?>