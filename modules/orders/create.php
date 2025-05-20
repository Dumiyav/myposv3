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
    // $items from POST will now be an array of item details
    $submitted_items_data = isset($_POST['items']) ? $_POST['items'] : [];
    $discount = floatval($_POST['discount'] ?? 0);

    // Validate input
    $errors = [];

    if (empty($submitted_items_data)) {
        $errors[] = 'Please add at least one item to the order.';
    }
    
    $orderItems = [];
    if (empty($errors)) { // Only process items if basic validation passes
        foreach ($submitted_items_data as $item_data) {
            $quantity = isset($item_data['quantity']) ? intval($item_data['quantity']) : 0;
            if ($quantity <= 0) {
                // Skip item if quantity is not positive
                continue; 
            }

            $notes = isset($item_data['notes']) ? trim($item_data['notes']) : '';
            $is_custom = (isset($item_data['is_custom']) && $item_data['is_custom'] === 'true');

            if ($is_custom) {
                $custom_name = isset($item_data['name']) ? trim($item_data['name']) : '';
                $custom_price = isset($item_data['price']) ? floatval($item_data['price']) : 0;

                if (empty($custom_name) || $custom_price <= 0) {
                    $errors[] = 'Custom item name must be provided and price must be greater than zero. Please check your custom items.';
                    // Optional: break here or collect all errors
                    continue; 
                }
                $orderItems[] = [
                    'is_custom' => true,
                    'custom_name' => $custom_name,
                    'custom_price' => $custom_price,
                    'quantity' => $quantity,
                    'notes' => $notes,
                    'status' => 'pending' 
                ];
            } else if (isset($item_data['id'])) {
                // Regular menu item
                 $menuItemExists = false;
                 foreach($menuItems as $menuItem) {
                     if($menuItem['id'] === $item_data['id']) {
                         $menuItemExists = true;
                         break;
                     }
                 }
                 if (!$menuItemExists) {
                     $errors[] = "Invalid menu item ID found in order: " . htmlspecialchars($item_data['id']);
                     continue;
                 }
                $orderItems[] = [
                    'menu_item_id' => $item_data['id'],
                    'quantity' => $quantity,
                    'notes' => $notes,
                    'status' => 'pending'
                ];
            }
        }
         if (empty($orderItems) && empty($errors) && !empty($submitted_items_data) ) {
            // This case handles if all submitted items had quantity <= 0 or were invalid but no other errors were triggered.
            $errors[] = 'No valid items to add to the order. Please check quantities and item details.';
        }
    }


    // If no errors, create the order
    if (empty($errors)) {
        // Calculate order total using the already updated function in functions.php
        // $menuItems is passed as the second argument for price lookup of regular items.
        $totals = calculateOrderTotal($orderItems, $menuItems, $discount);
        
        // Create new order
        $newOrder = [
            'id' => generateId(), 
            'items' => $orderItems, // This now includes custom item structures
            'status' => 'active', 
            'discount' => $discount,
            'tax' => $totals['tax'],
            'total' => $totals['total'],
            'payment_method' => '',
            'payment_status' => 'pending',
            'created_at' => date(DATE_FORMAT),
            'updated_at' => date(DATE_FORMAT)
        ];
        
        $existingOrders = readJsonFile('orders.json');
        $existingOrders[] = $newOrder;
        
        if (writeJsonFile('orders.json', $existingOrders)) {
            setFlashMessage('success', 'Order created successfully. Order ID: ' . $newOrder['id']);
            
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            header('Location: index.php?page=orders&id=' . $newOrder['id']);
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
            <li><?php echo htmlspecialchars($error); ?></li>
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
    <div class="bg-white rounded-lg shadow-md p-6">
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                window.orderApp = {
                    orderItemsList: [], // Stores objects: { id, name, price, quantity, notes, is_custom }
                    discount: 0,
                    menuItemsData: <?php echo json_encode($menuItems); ?>,
                    searchTerm: '',
                    selectedCategory: '',

                    // For adding regular menu items
                    addMenuItem: function(itemId) {
                        const itemDetails = this.getMenuItemDetails(itemId);
                        if (!itemDetails) return;

                        const existingItemIndex = this.orderItemsList.findIndex(oi => oi.id === itemId && !oi.is_custom);
                        if (existingItemIndex > -1) {
                            this.orderItemsList[existingItemIndex].quantity++;
                        } else {
                            this.orderItemsList.push({
                                id: itemDetails.id,
                                name: itemDetails.name,
                                price: parseFloat(itemDetails.price),
                                quantity: 1,
                                notes: '',
                                is_custom: false
                            });
                        }
                        this.updateOrderDisplay();
                        this.closeCustomItemModal(); // Close modal if open
                    },

                    // For adding custom items from the modal
                    addCustomItemFromModal: function() {
                        const name = document.getElementById('customItemNameInput').value.trim();
                        const priceStr = document.getElementById('customItemPriceInput').value.trim();
                        const quantityStr = document.getElementById('customItemQuantityInput').value.trim();

                        if (!name) { alert('Custom item name is required.'); return; }
                        if (!priceStr) { alert('Custom item price is required.'); return; }
                        if (!quantityStr) { alert('Custom item quantity is required.'); return; }
                        
                        const price = parseFloat(priceStr);
                        const quantity = parseInt(quantityStr);

                        if (isNaN(price) || price <= 0) { alert('Invalid price. Must be a positive number.'); return; }
                        if (isNaN(quantity) || quantity <= 0) { alert('Invalid quantity. Must be a positive integer.'); return; }
                        
                        const customId = 'custom_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
                        this.orderItemsList.push({
                            id: customId, 
                            name: name,
                            price: price,
                            quantity: quantity,
                            notes: document.getElementById('customItemNotesInput').value.trim(),
                            is_custom: true
                        });
                        this.updateOrderDisplay();
                        this.closeCustomItemModal();
                        // Clear modal fields
                        document.getElementById('customItemNameInput').value = '';
                        document.getElementById('customItemPriceInput').value = '';
                        document.getElementById('customItemQuantityInput').value = '1';
                        document.getElementById('customItemNotesInput').value = '';
                    },
                    
                    removeItem: function(index) {
                        this.orderItemsList.splice(index, 1);
                        this.updateOrderDisplay();
                    },
                    
                    updateQuantity: function(index, newQuantity) {
                        const qty = parseInt(newQuantity);
                        if (qty > 0) {
                            this.orderItemsList[index].quantity = qty;
                        } else {
                            this.removeItem(index); 
                        }
                        this.updateOrderDisplay();
                    },

                    updateItemNote: function(index, note) {
                        this.orderItemsList[index].notes = note;
                         // The hidden input for notes will be updated by updateOrderDisplay
                    },
                    
                    getMenuItemDetails: function(itemId) { // For regular menu items
                        return this.menuItemsData.find(item => item.id === itemId);
                    },
                    
                    calculateSubtotal: function() {
                        let subtotal = 0;
                        this.orderItemsList.forEach(item => {
                            subtotal += item.price * item.quantity;
                        });
                        return subtotal;
                    },
                    
                    calculateTax: function() {
                        const subtotal = this.calculateSubtotal();
                        const currentDiscount = parseFloat(this.discount) || 0;
                        const taxableAmount = Math.max(0, subtotal - currentDiscount);
                        return taxableAmount * (<?php echo TAX_RATE; ?> / 100);
                    },
                    
                    calculateTotal: function() {
                        const subtotal = this.calculateSubtotal();
                        const currentDiscount = parseFloat(this.discount) || 0;
                        const tax = this.calculateTax();
                        return Math.max(0, subtotal - currentDiscount) + tax;
                    },
                    
                    formatCurrency: function(amount) {
                        return '<?php echo CURRENCY; ?>' + parseFloat(amount).toFixed(2);
                    },
                    
                    updateOrderDisplay: function() {
                        const orderItemsContainerEl = document.getElementById('orderItemsListContainer'); // Target for actual item list
                        const noItemsMessageEl = document.getElementById('noItemsMessage');
                        
                        if (this.orderItemsList.length === 0) {
                            noItemsMessageEl.style.display = 'block';
                            orderItemsContainerEl.innerHTML = ''; 
                        } else {
                            noItemsMessageEl.style.display = 'none';
                            orderItemsContainerEl.innerHTML = ''; 
                            
                            this.orderItemsList.forEach((item, index) => {
                                const itemElement = document.createElement('div');
                                itemElement.className = 'flex items-start justify-between mb-3 p-2 border-b';
                                
                                let hiddenInputs = '';
                                if (item.is_custom) {
                                    hiddenInputs = `
                                        <input type="hidden" name="items[${index}][is_custom]" value="true">
                                        <input type="hidden" name="items[${index}][name]" value="${this.escapeHtml(item.name)}">
                                        <input type="hidden" name="items[${index}][price]" value="${item.price}">
                                    `;
                                } else {
                                    hiddenInputs = `
                                        <input type="hidden" name="items[${index}][is_custom]" value="false">
                                        <input type="hidden" name="items[${index}][id]" value="${item.id}">
                                    `;
                                }
                                // Common hidden inputs
                                hiddenInputs += `<input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">`;
                                // Note: notes are handled by the textarea itself, no separate hidden input for notes needed here
                                // if it's directly part of the form. Or update it if needed.
                                // For simplicity, we'll ensure the textarea name is items[${index}][notes]

                                itemElement.innerHTML = `
                                    ${hiddenInputs}
                                    <div class="flex-grow">
                                        <div class="font-medium">${this.escapeHtml(item.name)} ${item.is_custom ? '<span class="text-xs text-blue-500">(Custom)</span>' : ''}</div>
                                        <div class="text-xs text-gray-500">${this.formatCurrency(item.price)} ea.</div>
                                        <textarea name="items[${index}][notes]" rows="1" class="mt-1 w-full text-xs border border-gray-200 rounded-md p-1 focus:ring-blue-500 focus:border-blue-500" placeholder="Item notes..." @input="orderApp.updateItemNote(${index}, event.target.value)">${this.escapeHtml(item.notes)}</textarea>
                                    </div>
                                    <div class="flex flex-col items-end ml-2">
                                        <input type="number" value="${item.quantity}" min="1" class="w-16 px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 mb-1 text-sm" @change="orderApp.updateQuantity(${index}, event.target.value)" @input="orderApp.updateQuantity(${index}, event.target.value)">
                                        <div class="text-sm font-semibold">${this.formatCurrency(item.price * item.quantity)}</div>
                                        <button type="button" class="text-red-500 hover:text-red-700 mt-1" @click="orderApp.removeItem(${index})">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                `;
                                orderItemsContainerEl.appendChild(itemElement);
                            });
                        }
                        
                        document.getElementById('subtotalDisplay').textContent = this.formatCurrency(this.calculateSubtotal());
                        document.getElementById('taxDisplay').textContent = this.formatCurrency(this.calculateTax());
                        document.getElementById('totalDisplay').textContent = this.formatCurrency(this.calculateTotal());
                        document.getElementById('submitOrderButton').disabled = this.orderItemsList.length === 0;
                    },

                    filterMenuItems: function() {
                        const menuSections = document.querySelectorAll('.menu-item-category-section');
                        let hasVisibleItemsOverall = false;

                        menuSections.forEach(section => {
                            const categoryName = section.getAttribute('data-category').toLowerCase();
                            let hasVisibleItemsInSection = false;

                            const itemsInSection = section.querySelectorAll('.menu-item-selectable');
                            itemsInSection.forEach(itemEl => {
                                const itemName = itemEl.getAttribute('data-name').toLowerCase();
                                const itemCode = (itemEl.getAttribute('data-code') || '').toLowerCase();
                                
                                const matchesCategory = !this.selectedCategory || categoryName === this.selectedCategory.toLowerCase();
                                const matchesSearch = !this.searchTerm || 
                                                      itemName.includes(this.searchTerm.toLowerCase()) || 
                                                      itemCode.includes(this.searchTerm.toLowerCase());

                                if (matchesCategory && matchesSearch) {
                                    itemEl.style.display = 'flex'; // Use flex for proper layout if items are flex containers
                                    hasVisibleItemsInSection = true;
                                    hasVisibleItemsOverall = true;
                                } else {
                                    itemEl.style.display = 'none';
                                }
                            });
                            section.style.display = hasVisibleItemsInSection ? 'block' : 'none';
                        });
                        document.getElementById('noMenuItemsMessageFilter').style.display = hasVisibleItemsOverall ? 'none' : 'block';
                    },
                    
                    openCustomItemModal: function() {
                        document.getElementById('customItemModal').style.display = 'flex';
                    },
                    closeCustomItemModal: function() {
                        document.getElementById('customItemModal').style.display = 'none';
                    },
                    escapeHtml: function(unsafe) {
                        if (unsafe === null || typeof unsafe === 'undefined') {
                            return '';
                        }
                        return unsafe
                             .toString()
                             .replace(/&/g, "&amp;")
                             .replace(/</g, "&lt;")
                             .replace(/>/g, "&gt;")
                             .replace(/"/g, "&quot;")
                             .replace(/'/g, "&#039;");
                    },

                    init: function() {
                        document.getElementById('discountInput').addEventListener('input', (e) => {
                            this.discount = e.target.value;
                            this.updateOrderDisplay();
                        });
                        document.getElementById('menuSearchInput').addEventListener('input', (e) => {
                            this.searchTerm = e.target.value.toLowerCase();
                            this.filterMenuItems();
                        });
                        document.getElementById('categoryFilterSelect').addEventListener('change', (e) => {
                            this.selectedCategory = e.target.value;
                            this.filterMenuItems();
                        });
                        
                        document.getElementById('addCustomItemBtn').addEventListener('click', () => this.openCustomItemModal());
                        document.getElementById('closeCustomItemModalBtn').addEventListener('click', () => this.closeCustomItemModal());
                        document.getElementById('saveCustomItemBtn').addEventListener('click', () => this.addCustomItemFromModal());
                         // Close modal if clicking outside of it
                        const modal = document.getElementById('customItemModal');
                        modal.addEventListener('click', (event) => {
                            if (event.target === modal) { // Check if the click is on the modal backdrop itself
                                this.closeCustomItemModal();
                            }
                        });


                        this.updateOrderDisplay(); 
                        this.filterMenuItems(); 
                    }
                };
                window.orderApp.init();
            });
        </script>
        
        <div id="customItemModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center" style="display: none; z-index: 100;">
            <div class="bg-white p-5 rounded-lg shadow-xl w-full max-w-md mx-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Add Custom Item</h3>
                    <button id="closeCustomItemModalBtn" type="button" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label for="customItemNameInput" class="block text-sm font-medium text-gray-700">Item Name*</label>
                        <input type="text" id="customItemNameInput" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="customItemPriceInput" class="block text-sm font-medium text-gray-700">Price (<?php echo CURRENCY; ?>)*</label>
                        <input type="number" id="customItemPriceInput" step="0.01" min="0.01" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="customItemQuantityInput" class="block text-sm font-medium text-gray-700">Quantity*</label>
                        <input type="number" id="customItemQuantityInput" min="1" value="1" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="customItemNotesInput" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea id="customItemNotesInput" rows="2" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button id="saveCustomItemBtn" type="button" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        Add to Order
                    </button>
                </div>
            </div>
        </div>


        <form method="POST" action="">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div> <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">1. Select Items</h2>
                        <button type="button" id="addCustomItemBtn" class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-3 rounded-md text-sm inline-flex items-center">
                            <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path></svg>
                            Custom Item
                        </button>
                    </div>
                    <div class="mb-4 space-y-2">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <input type="text" id="menuSearchInput" placeholder="Search menu items by name or code..." class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <select id="categoryFilterSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo ucfirst(htmlspecialchars($category)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="noMenuItemsMessageFilter" class="hidden text-center py-8 text-gray-500">
                        <p>No menu items found matching your criteria.</p>
                    </div>
                    
                    <div class="space-y-4 max-h-[calc(100vh-380px)] overflow-y-auto p-1 border rounded-md">
                        <?php foreach ($menuByCategory as $category => $itemsInCategory): ?>
                        <div class="menu-item-category-section" data-category="<?php echo htmlspecialchars($category); ?>">
                            <h3 class="text-md font-medium mb-2 capitalize sticky top-0 bg-gray-100 p-2 -mx-1 -mt-1 z-10 border-b"><?php echo ucfirst(htmlspecialchars($category)); ?></h3>
                            <div class="space-y-2 px-1">
                                <?php foreach ($itemsInCategory as $item): ?>
                                <div class="menu-item-selectable border rounded-lg p-3 hover:bg-gray-50 cursor-pointer flex justify-between items-center" 
                                     data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                     data-code="<?php echo htmlspecialchars($item['code'] ?? ''); ?>"
                                     @click="orderApp.addMenuItem('<?php echo htmlspecialchars($item['id']); ?>')">
                                    <div>
                                        <h4 class="font-medium text-sm"><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <?php if (isset($item['code']) && !empty($item['code'])): ?>
                                        <p class="text-xs text-gray-400">Code: <?php echo htmlspecialchars($item['code']); ?></p>
                                        <?php endif; ?>
                                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <?php if (isset($item['stock']) && $item['stock'] !== null && $item['stock'] <= 5): ?>
                                        <p class="text-xs font-semibold text-<?php echo $item['stock'] > 0 ? 'orange' : 'red'; ?>-500">
                                            <?php echo $item['stock'] > 0 ? 'Only ' . $item['stock'] . ' left' : 'Out of stock'; ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="font-semibold text-sm"><?php echo formatCurrency($item['price']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div> <h2 class="text-lg font-semibold mb-4">2. Order Summary</h2>
                    
                    <div class="border rounded-lg p-4 bg-gray-50 min-h-[300px] max-h-[calc(100vh-300px)] overflow-y-auto">
                        <div id="noItemsMessage" class="text-center py-8 text-gray-500" style="display: block;">
                            <p>No items added to the order.</p>
                            <p class="text-sm">Click on menu items or add a custom item.</p>
                        </div>
                        <div id="orderItemsListContainer" class="space-y-2">
                            </div>
                    </div>
                    
                    <div class="mt-6 space-y-3">
                        <div>
                            <label for="discountInput" class="block text-sm font-medium text-gray-700 mb-1">Discount</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm"><?php echo CURRENCY; ?></span>
                                </div>
                                <input type="number" id="discountInput" name="discount" value="0" min="0" step="0.01" class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div class="flex justify-between text-sm">
                            <span>Subtotal:</span>
                            <span id="subtotalDisplay"><?php echo CURRENCY; ?>0.00</span>
                        </div>
                        
                        <div class="flex justify-between text-sm">
                            <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                            <span id="taxDisplay"><?php echo CURRENCY; ?>0.00</span>
                        </div>
                        
                        <div class="border-t pt-2 flex justify-between font-bold text-md">
                            <span>Total:</span>
                            <span id="totalDisplay"><?php echo CURRENCY; ?>0.00</span>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <a href="index.php?page=orders" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                                Cancel
                            </a>
                            <button id="submitOrderButton" type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded" disabled>
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