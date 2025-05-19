<?php
// Start output buffering as a backup
if (!ob_get_level()) {
    ob_start();
}

// Get menu items
$menuItems = readJsonFile('menu.json');

// Group menu items by category
$menuByCategory = [];
foreach ($menuItems as $item) {
    if ($item['available']) {
        if (!isset($menuByCategory[$item['category']])) {
            $menuByCategory[$item['category']] = [];
        }
        $menuByCategory[$item['category']][] = $item;
    }
}

// Get all categories for filter dropdown
$categories = array_keys($menuByCategory);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $items = isset($_POST['items']) ? $_POST['items'] : [];
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
    $discount = floatval($_POST['discount'] ?? 0);
    
    // Validate input
    $errors = [];
    
    if (empty($items)) {
        $errors[] = 'Please add at least one item to the order.';
    }
    
    // If no errors, create the order
    if (empty($errors)) {
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
        
        // Create new order
        $newOrder = [
            'id' => generateId(),
            'items' => $orderItems,
            'status' => 'active',
            'discount' => $discount,
            'tax' => $totals['tax'],
            'total' => $totals['total'],
            'payment_method' => '',
            'payment_status' => 'pending',
            'created_at' => date(DATE_FORMAT),
            'updated_at' => date(DATE_FORMAT)
        ];
        
        // Get existing orders
        $orders = readJsonFile('orders.json');
        
        // Add new order
        $orders[] = $newOrder;
        
        // Save changes
        $ordersSaved = writeJsonFile('orders.json', $orders);
        
        if ($ordersSaved) {
            setFlashMessage('success', 'Order created successfully.');
            
            // Clean any output buffers before redirect
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Redirect to orders page
            header('Location: index.php?page=orders');
            exit;
        } else {
            $errors[] = 'Failed to save order.';
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
        <h1 class="text-2xl font-bold">Create New Order</h1>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (empty($menuItems)): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
        <p>No menu items are available. Please add menu items before creating an order.</p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($menuItems)): ?>
    <!-- Order Creation Form -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <script>
            // Define the order management functionality
            document.addEventListener('DOMContentLoaded', function() {
                window.orderApp = {
                    items: [],
                    quantities: [],
                    discount: 0,
                    menuItems: <?php echo json_encode($menuItems); ?>,
                    searchTerm: '',
                    selectedCategory: '',
                    
                    addItem: function(itemId) {
                        const index = this.items.indexOf(itemId);
                        if (index === -1) {
                            this.items.push(itemId);
                            this.quantities.push(1);
                        } else {
                            this.quantities[index]++;
                        }
                        this.updateOrderItems();
                    },
                    
                    removeItem: function(index) {
                        this.items.splice(index, 1);
                        this.quantities.splice(index, 1);
                        this.updateOrderItems();
                    },
                    
                    updateQuantity: function(index, value) {
                        this.quantities[index] = parseInt(value);
                        if (this.quantities[index] <= 0) {
                            this.removeItem(index);
                        }
                        this.updateOrderItems();
                    },
                    
                    getItemName: function(itemId) {
                        for (const item of this.menuItems) {
                            if (item.id === itemId) {
                                return item.name;
                            }
                        }
                        return 'Unknown Item';
                    },
                    
                    getItemPrice: function(itemId) {
                        for (const item of this.menuItems) {
                            if (item.id === itemId) {
                                return parseFloat(item.price);
                            }
                        }
                        return 0;
                    },
                    
                    calculateSubtotal: function() {
                        let subtotal = 0;
                        for (let i = 0; i < this.items.length; i++) {
                            subtotal += this.getItemPrice(this.items[i]) * this.quantities[i];
                        }
                        return subtotal;
                    },
                    
                    calculateDiscount: function() {
                        return parseFloat(this.discount) || 0;
                    },
                    
                    calculateTax: function() {
                        const subtotal = this.calculateSubtotal();
                        const discount = this.calculateDiscount();
                        const discountedSubtotal = Math.max(0, subtotal - discount);
                        return discountedSubtotal * (<?php echo TAX_RATE; ?> / 100);
                    },
                    
                    calculateTotal: function() {
                        const subtotal = this.calculateSubtotal();
                        const discount = this.calculateDiscount();
                        const discountedSubtotal = Math.max(0, subtotal - discount);
                        const tax = this.calculateTax();
                        return discountedSubtotal + tax;
                    },
                    
                    formatCurrency: function(amount) {
                        return '<?php echo CURRENCY; ?>' + amount.toFixed(2);
                    },
                    
                    updateOrderItems: function() {
                        const orderItemsContainer = document.getElementById('orderItems');
                        const noItemsMessage = document.getElementById('noItemsMessage');
                        const orderItemsList = document.getElementById('orderItemsList');
                        const subtotalElement = document.getElementById('subtotal');
                        const taxElement = document.getElementById('tax');
                        const totalElement = document.getElementById('total');
                        const submitButton = document.getElementById('submitButton');
                        
                        // Update summary
                        subtotalElement.textContent = this.formatCurrency(this.calculateSubtotal());
                        taxElement.textContent = this.formatCurrency(this.calculateTax());
                        totalElement.textContent = this.formatCurrency(this.calculateTotal());
                        
                        // Update order items
                        if (this.items.length === 0) {
                            noItemsMessage.style.display = 'block';
                            orderItemsList.style.display = 'none';
                        } else {
                            noItemsMessage.style.display = 'none';
                            orderItemsList.style.display = 'block';
                            
                            // Clear existing items
                            orderItemsList.innerHTML = '';
                            
                            // Add each item
                            for (let i = 0; i < this.items.length; i++) {
                                const itemId = this.items[i];
                                const quantity = this.quantities[i];
                                const itemName = this.getItemName(itemId);
                                const itemPrice = this.getItemPrice(itemId);
                                const itemTotal = itemPrice * quantity;
                                
                                const itemElement = document.createElement('div');
                                itemElement.className = 'flex items-center justify-between mb-4';
                                itemElement.innerHTML = `
                                    <div>
                                        <div class="font-medium">${itemName}</div>
                                        <div class="text-sm text-gray-500">${this.formatCurrency(itemPrice)} × ${quantity} = ${this.formatCurrency(itemTotal)}</div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="hidden" name="items[${i}]" value="${itemId}">
                                        <input type="number" name="quantities[${i}]" value="${quantity}" min="1" class="w-16 px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" onchange="window.orderApp.updateQuantity(${i}, this.value)">
                                        <button type="button" class="text-red-500 hover:text-red-700" onclick="window.orderApp.removeItem(${i})">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                `;
                                
                                orderItemsList.appendChild(itemElement);
                            }
                        }
                        
                        // Enable/disable submit button
                        submitButton.disabled = this.items.length === 0;
                    },
                    
                    searchItems: function(term) {
                        this.searchTerm = term.toLowerCase();
                        this.filterMenuItems();
                    },
                    
                    filterByCategory: function(category) {
                        this.selectedCategory = category;
                        this.filterMenuItems();
                    },
                    
                    filterMenuItems: function() {
                        const menuSections = document.querySelectorAll('.menu-category');
                        const menuItems = document.querySelectorAll('.menu-item');
                        
                        // First filter by category if selected
                        if (this.selectedCategory) {
                            menuSections.forEach(section => {
                                if (section.getAttribute('data-category').toLowerCase() === this.selectedCategory.toLowerCase()) {
                                    section.style.display = 'block';
                                } else {
                                    section.style.display = 'none';
                                }
                            });
                        } else {
                            menuSections.forEach(section => {
                                section.style.display = 'block';
                            });
                        }
                        
                        // Then filter by search term
                        if (this.searchTerm) {
                            let hasVisibleItems = false;
                            
                            menuItems.forEach(item => {
                                const itemName = item.getAttribute('data-name').toLowerCase();
                                const itemCode = item.getAttribute('data-code') ? item.getAttribute('data-code').toLowerCase() : '';
                                const itemCategory = item.getAttribute('data-category').toLowerCase();
                                
                                if (itemName.includes(this.searchTerm) || 
                                    itemCode.includes(this.searchTerm) || 
                                    itemCategory.includes(this.searchTerm)) {
                                    item.style.display = 'block';
                                    hasVisibleItems = true;
                                    
                                    // Make sure parent category is visible
                                    const parentCategory = item.closest('.menu-category');
                                    if (parentCategory) {
                                        parentCategory.style.display = 'block';
                                    }
                                } else {
                                    item.style.display = 'none';
                                }
                            });
                            
                            // Show no results message if needed
                            const noResultsMessage = document.getElementById('noResultsMessage');
                            if (noResultsMessage) {
                                noResultsMessage.style.display = hasVisibleItems ? 'none' : 'block';
                            }
                        } else {
                            // If no search term, show all items
                            menuItems.forEach(item => {
                                item.style.display = 'block';
                            });
                            
                            // Hide no results message
                            const noResultsMessage = document.getElementById('noResultsMessage');
                            if (noResultsMessage) {
                                noResultsMessage.style.display = 'none';
                            }
                        }
                    },
                    
                    init: function() {
                        // Initialize discount input
                        const discountInput = document.getElementById('discount');
                        discountInput.addEventListener('input', (e) => {
                            this.discount = e.target.value;
                            this.updateOrderItems();
                        });
                        
                        // Initialize search input
                        const searchInput = document.getElementById('menuSearch');
                        searchInput.addEventListener('input', (e) => {
                            this.searchItems(e.target.value);
                        });
                        
                        // Initialize category filter
                        const categorySelect = document.getElementById('categoryFilter');
                        if (categorySelect) {
                            categorySelect.addEventListener('change', (e) => {
                                this.filterByCategory(e.target.value);
                            });
                        }
                        
                        // Initial update
                        this.updateOrderItems();
                    }
                };
                
                // Initialize the app
                window.orderApp.init();
            });
        </script>
        
        <form method="POST" action="">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column: Menu Items -->
                <div>
                    <h2 class="text-lg font-semibold mb-4">Menu Items</h2>
                    
                    <!-- Search and Filter -->
                    <div class="mb-4 space-y-2">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text" id="menuSearch" placeholder="Search menu items..." class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <select id="categoryFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category; ?>"><?php echo ucfirst($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="noResultsMessage" class="hidden text-center py-8 text-gray-500">
                        <p>No menu items found matching your search.</p>
                    </div>
                    
                    <div class="space-y-6 max-h-[500px] overflow-y-auto">
                        <?php foreach ($menuByCategory as $category => $items): ?>
                        <div class="menu-category" data-category="<?php echo $category; ?>">
                            <h3 class="text-md font-medium mb-2 capitalize"><?php echo $category; ?></h3>
                            
                            <div class="space-y-2">
                                <?php foreach ($items as $item): ?>
                                <div class="menu-item border rounded-lg p-3 hover:bg-gray-50 cursor-pointer" 
                                     data-name="<?php echo $item['name']; ?>"
                                     data-code="<?php echo $item['code'] ?? ''; ?>"
                                     data-category="<?php echo $category; ?>"
                                     onclick="window.orderApp.addItem('<?php echo $item['id']; ?>')">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-medium"><?php echo $item['name']; ?></h4>
                                            <?php if (isset($item['code']) && !empty($item['code'])): ?>
                                            <p class="text-xs text-gray-500"><?php echo $item['code']; ?></p>
                                            <?php endif; ?>
                                            <p class="text-sm text-gray-500"><?php echo $item['description']; ?></p>
                                            <?php if (isset($item['stock']) && $item['stock'] <= 5): ?>
                                            <p class="text-xs text-<?php echo $item['stock'] > 0 ? 'orange' : 'red'; ?>-500">
                                                <?php echo $item['stock'] > 0 ? 'Only ' . $item['stock'] . ' left' : 'Out of stock'; ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="font-medium"><?php echo formatCurrency($item['price']); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Right Column: Order Items -->
                <div>
                    <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                    
                    <div id="orderItems" class="border rounded-lg p-4 bg-gray-50 min-h-[300px]">
                        <div id="noItemsMessage" class="text-center py-8 text-gray-500">
                            <p>No items added to the order.</p>
                            <p class="text-sm">Click on menu items to add them to the order.</p>
                        </div>
                        
                        <div id="orderItemsList" class="space-y-4" style="display: none;">
                            <!-- Order items will be dynamically added here -->
                        </div>
                    </div>
                    
                    <div class="mt-6 space-y-4">
                        <div>
                            <label for="discount" class="block text-sm font-medium text-gray-700 mb-1">Discount</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm"><?php echo CURRENCY; ?></span>
                                </div>
                                <input type="number" id="discount" name="discount" value="0" min="0" step="0.01" class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span id="subtotal"><?php echo CURRENCY; ?>0.00</span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                            <span id="tax"><?php echo CURRENCY; ?>0.00</span>
                        </div>
                        
                        <div class="border-t pt-2 flex justify-between font-bold">
                            <span>Total:</span>
                            <span id="total"><?php echo CURRENCY; ?>0.00</span>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <a href="index.php?page=orders" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                                Cancel
                            </a>
                            <button id="submitButton" type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded" disabled>
                                Create Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>