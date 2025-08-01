<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = Database::getInstance();
    
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $db);
            break;
        case 'POST':
            handlePostRequest($action, $db);
            break;
        case 'PUT':
            handlePutRequest($action, $db);
            break;
        case 'DELETE':
            handleDeleteRequest($action, $db);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    logError('Products API Error', ['error' => $e->getMessage(), 'action' => $action]);
    sendError('Internal server error', 500);
}

function handleGetRequest($action, $db) {
    switch ($action) {
        case 'list':
            getProducts($db);
            break;
        case 'categories':
            getCategories($db);
            break;
        case 'search':
            searchProducts($db);
            break;
        case 'details':
            getProductDetails($db);
            break;
        case 'featured':
            getFeaturedProducts($db);
            break;
        case 'low-stock':
            checkPermission('admin');
            getLowStockProducts($db);
            break;
        default:
            sendError('Invalid action', 400);
    }
}

function handlePostRequest($action, $db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            checkPermission('admin');
            createProduct($input, $db);
            break;
        case 'update-stock':
            checkPermission('admin');
            updateStock($input, $db);
            break;
        case 'bulk-update':
            checkPermission('admin');
            bulkUpdateProducts($input, $db);
            break;
        default:
            sendError('Invalid action', 400);
    }
}

function handlePutRequest($action, $db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update':
            checkPermission('admin');
            updateProduct($input, $db);
            break;
        default:
            sendError('Invalid action', 400);
    }
}

function handleDeleteRequest($action, $db) {
    switch ($action) {
        case 'delete':
            checkPermission('admin');
            deleteProduct($db);
            break;
        default:
            sendError('Invalid action', 400);
    }
}

function getProducts($db) {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    $category = $_GET['category'] ?? '';
    $status = $_GET['status'] ?? 'active';
    $offset = ($page - 1) * $limit;
    
    // Build query
    $whereConditions = ['status = ?'];
    $params = [$status];
    
    if (!empty($category)) {
        $whereConditions[] = 'category = ?';
        $params[] = $category;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count
    $totalQuery = "SELECT COUNT(*) as total FROM products WHERE {$whereClause}";
    $total = $db->fetch($totalQuery, $params)['total'];
    
    // Get products with relevance scoring
    $productsQuery = "
        SELECT id, name, description, price, unit, category, image_url, stock_quantity, 
               min_stock_level, status, created_at, updated_at,
               CASE 
                   WHEN name ILIKE ? THEN 3
                   WHEN description ILIKE ? THEN 2
                   ELSE 1
               END as relevance
        FROM products 
        WHERE {$whereClause}
        ORDER BY relevance DESC, created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    if (!empty($query)) {
        array_unshift($params, '%' . $query . '%', '%' . $query . '%');
    } else {
        array_unshift($params, '', '');
    }
    
    $params[] = $limit;
    $params[] = $offset;
    
    $products = $db->fetchAll($productsQuery, $params);
    
    // Add additional product data
    foreach ($products as &$product) {
        $product['in_stock'] = $product['stock_quantity'] > 0;
        $product['low_stock'] = $product['stock_quantity'] <= $product['min_stock_level'];
        $product['badge'] = getProductBadge($product);
        $product['formatted_price'] = '₱' . number_format($product['price'], 2);
        unset($product['relevance']); // Remove relevance from response
    }
    
    sendSuccess([
        'products' => $products,
        'search_query' => $query,
        'filters' => [
            'category' => $category,
            'min_price' => $minPrice,
            'max_price' => $maxPrice
        ],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => intval($total),
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getProductDetails($db) {
    $productId = intval($_GET['id'] ?? 0);
    
    if ($productId <= 0) {
        sendError('Valid product ID is required');
    }
    
    $product = $db->fetch("
        SELECT id, name, description, price, unit, category, image_url, stock_quantity, 
               min_stock_level, status, created_at, updated_at
        FROM products 
        WHERE id = ? AND status = 'active'
    ", [$productId]);
    
    if (!$product) {
        sendError('Product not found', 404);
    }
    
    // Add additional product data
    $product['in_stock'] = $product['stock_quantity'] > 0;
    $product['low_stock'] = $product['stock_quantity'] <= $product['min_stock_level'];
    $product['badge'] = getProductBadge($product);
    $product['formatted_price'] = '₱' . number_format($product['price'], 2);
    
    // Get related products
    $relatedProducts = $db->fetchAll("
        SELECT id, name, price, unit, image_url, stock_quantity
        FROM products 
        WHERE category = ? AND id != ? AND status = 'active'
        ORDER BY RANDOM()
        LIMIT 4
    ", [$product['category'], $productId]);
    
    foreach ($relatedProducts as &$related) {
        $related['formatted_price'] = '₱' . number_format($related['price'], 2);
        $related['in_stock'] = $related['stock_quantity'] > 0;
    }
    
    sendSuccess([
        'product' => $product,
        'related_products' => $relatedProducts
    ]);
}

function getFeaturedProducts($db) {
    $limit = intval($_GET['limit'] ?? 8);
    
    // Get products with high stock or recently added
    $products = $db->fetchAll("
        SELECT id, name, description, price, unit, category, image_url, stock_quantity, 
               min_stock_level, status, created_at
        FROM products 
        WHERE status = 'active' AND stock_quantity > min_stock_level
        ORDER BY created_at DESC, stock_quantity DESC
        LIMIT ?
    ", [$limit]);
    
    foreach ($products as &$product) {
        $product['in_stock'] = $product['stock_quantity'] > 0;
        $product['low_stock'] = $product['stock_quantity'] <= $product['min_stock_level'];
        $product['badge'] = getProductBadge($product);
        $product['formatted_price'] = '₱' . number_format($product['price'], 2);
    }
    
    sendSuccess(['products' => $products]);
}

function getLowStockProducts($db) {
    $products = $db->fetchAll("
        SELECT id, name, price, unit, category, stock_quantity, min_stock_level
        FROM products 
        WHERE status = 'active' AND stock_quantity <= min_stock_level
        ORDER BY (stock_quantity::float / min_stock_level::float) ASC
    ");
    
    foreach ($products as &$product) {
        $product['formatted_price'] = '₱' . number_format($product['price'], 2);
        $product['stock_ratio'] = $product['min_stock_level'] > 0 ? 
            round(($product['stock_quantity'] / $product['min_stock_level']) * 100, 1) : 0;
    }
    
    sendSuccess(['products' => $products]);
}

function createProduct($input, $db) {
    // Validate required fields
    $requiredFields = ['name', 'price', 'unit', 'category'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            sendError(ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
    }
    
    // Sanitize and validate inputs
    $name = sanitizeInput($input['name']);
    $description = sanitizeInput($input['description'] ?? '');
    $price = floatval($input['price']);
    $unit = sanitizeInput($input['unit']);
    $category = sanitizeInput($input['category']);
    $stockQuantity = intval($input['stock_quantity'] ?? 0);
    $minStockLevel = intval($input['min_stock_level'] ?? 5);
    $imageUrl = sanitizeInput($input['image_url'] ?? '');
    
    if ($price <= 0) {
        sendError('Price must be greater than zero');
    }
    
    if ($stockQuantity < 0) {
        sendError('Stock quantity cannot be negative');
    }
    
    // Check if product name already exists
    $existing = $db->fetch("SELECT id FROM products WHERE name = ?", [$name]);
    if ($existing) {
        sendError('Product with this name already exists');
    }
    
    try {
        $productId = $db->insert('products', [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'unit' => $unit,
            'category' => $category,
            'stock_quantity' => $stockQuantity,
            'min_stock_level' => $minStockLevel,
            'image_url' => $imageUrl,
            'status' => 'active'
        ]);
        
        logError('Product created', ['product_id' => $productId, 'name' => $name]);
        
        sendSuccess(['product_id' => $productId], 'Product created successfully');
        
    } catch (Exception $e) {
        throw $e;
    }
}

function updateProduct($input, $db) {
    $productId = intval($_GET['id'] ?? 0);
    
    if ($productId <= 0) {
        sendError('Valid product ID is required');
    }
    
    // Check if product exists
    $existingProduct = $db->fetch("SELECT id, name FROM products WHERE id = ?", [$productId]);
    if (!$existingProduct) {
        sendError('Product not found', 404);
    }
    
    // Build update data
    $updateData = [];
    $allowedFields = ['name', 'description', 'price', 'unit', 'category', 'stock_quantity', 'min_stock_level', 'image_url', 'status'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            if (in_array($field, ['name', 'description', 'unit', 'category', 'image_url', 'status'])) {
                $updateData[$field] = sanitizeInput($input[$field]);
            } elseif ($field === 'price') {
                $price = floatval($input[$field]);
                if ($price <= 0) {
                    sendError('Price must be greater than zero');
                }
                $updateData[$field] = $price;
            } elseif (in_array($field, ['stock_quantity', 'min_stock_level'])) {
                $value = intval($input[$field]);
                if ($value < 0) {
                    sendError(ucfirst(str_replace('_', ' ', $field)) . ' cannot be negative');
                }
                $updateData[$field] = $value;
            }
        }
    }
    
    if (empty($updateData)) {
        sendError('No valid fields to update');
    }
    
    // Check for duplicate name if name is being updated
    if (isset($updateData['name']) && $updateData['name'] !== $existingProduct['name']) {
        $duplicate = $db->fetch("SELECT id FROM products WHERE name = ? AND id != ?", [$updateData['name'], $productId]);
        if ($duplicate) {
            sendError('Product with this name already exists');
        }
    }
    
    $updateData['updated_at'] = date('Y-m-d H:i:s');
    
    try {
        $rowsAffected = $db->update('products', $updateData, 'id = ?', [$productId]);
        
        if ($rowsAffected > 0) {
            logError('Product updated', ['product_id' => $productId, 'updated_fields' => array_keys($updateData)]);
            sendSuccess([], 'Product updated successfully');
        } else {
            sendError('No changes made to product');
        }
        
    } catch (Exception $e) {
        throw $e;
    }
}

function updateStock($input, $db) {
    $productId = intval($input['product_id'] ?? 0);
    $quantity = intval($input['quantity'] ?? 0);
    $operation = $input['operation'] ?? 'set'; // 'set', 'add', 'subtract'
    
    if ($productId <= 0) {
        sendError('Valid product ID is required');
    }
    
    if (!in_array($operation, ['set', 'add', 'subtract'])) {
        sendError('Invalid operation. Use: set, add, or subtract');
    }
    
    // Get current stock
    $product = $db->fetch("SELECT id, name, stock_quantity FROM products WHERE id = ?", [$productId]);
    if (!$product) {
        sendError('Product not found', 404);
    }
    
    $currentStock = $product['stock_quantity'];
    $newStock = $currentStock;
    
    switch ($operation) {
        case 'set':
            if ($quantity < 0) {
                sendError('Stock quantity cannot be negative');
            }
            $newStock = $quantity;
            break;
        case 'add':
            $newStock = $currentStock + abs($quantity);
            break;
        case 'subtract':
            $newStock = $currentStock - abs($quantity);
            if ($newStock < 0) {
                sendError('Operation would result in negative stock');
            }
            break;
    }
    
    try {
        $db->update('products', [
            'stock_quantity' => $newStock,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$productId]);
        
        logError('Stock updated', [
            'product_id' => $productId,
            'product_name' => $product['name'],
            'operation' => $operation,
            'old_stock' => $currentStock,
            'new_stock' => $newStock,
            'quantity' => $quantity
        ]);
        
        sendSuccess([
            'product_id' => $productId,
            'old_stock' => $currentStock,
            'new_stock' => $newStock
        ], 'Stock updated successfully');
        
    } catch (Exception $e) {
        throw $e;
    }
}

function bulkUpdateProducts($input, $db) {
    if (empty($input['products']) || !is_array($input['products'])) {
        sendError('Products array is required');
    }
    
    $results = [];
    $errors = [];
    
    $db->beginTransaction();
    
    try {
        foreach ($input['products'] as $productData) {
            $productId = intval($productData['id'] ?? 0);
            
            if ($productId <= 0) {
                $errors[] = "Invalid product ID: {$productId}";
                continue;
            }
            
            // Build update data for this product
            $updateData = [];
            $allowedFields = ['price', 'stock_quantity', 'min_stock_level', 'status'];
            
            foreach ($allowedFields as $field) {
                if (isset($productData[$field])) {
                    if ($field === 'price') {
                        $price = floatval($productData[$field]);
                        if ($price > 0) {
                            $updateData[$field] = $price;
                        }
                    } elseif (in_array($field, ['stock_quantity', 'min_stock_level'])) {
                        $value = intval($productData[$field]);
                        if ($value >= 0) {
                            $updateData[$field] = $value;
                        }
                    } elseif ($field === 'status') {
                        if (in_array($productData[$field], ['active', 'inactive'])) {
                            $updateData[$field] = $productData[$field];
                        }
                    }
                }
            }
            
            if (!empty($updateData)) {
                $updateData['updated_at'] = date('Y-m-d H:i:s');
                $rowsAffected = $db->update('products', $updateData, 'id = ?', [$productId]);
                
                if ($rowsAffected > 0) {
                    $results[] = ['product_id' => $productId, 'status' => 'updated'];
                } else {
                    $results[] = ['product_id' => $productId, 'status' => 'no_changes'];
                }
            } else {
                $results[] = ['product_id' => $productId, 'status' => 'no_valid_fields'];
            }
        }
        
        $db->commit();
        
        logError('Bulk product update', [
            'total_products' => count($input['products']),
            'successful_updates' => count(array_filter($results, fn($r) => $r['status'] === 'updated')),
            'errors' => count($errors)
        ]);
        
        sendSuccess([
            'results' => $results,
            'errors' => $errors,
            'summary' => [
                'total' => count($input['products']),
                'updated' => count(array_filter($results, fn($r) => $r['status'] === 'updated')),
                'failed' => count($errors)
            ]
        ], 'Bulk update completed');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function deleteProduct($db) {
    $productId = intval($_GET['id'] ?? 0);
    
    if ($productId <= 0) {
        sendError('Valid product ID is required');
    }
    
    // Check if product exists and get its details
    $product = $db->fetch("SELECT id, name FROM products WHERE id = ?", [$productId]);
    if (!$product) {
        sendError('Product not found', 404);
    }
    
    // Check if product is used in any orders (soft delete if used)
    $orderCount = $db->fetch("SELECT COUNT(*) as count FROM order_items WHERE product_id = ?", [$productId])['count'];
    
    try {
        if ($orderCount > 0) {
            // Soft delete - just mark as inactive
            $db->update('products', [
                'status' => 'inactive',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$productId]);
            
            logError('Product soft deleted', ['product_id' => $productId, 'name' => $product['name']]);
            sendSuccess([], 'Product deactivated (soft delete due to existing orders)');
        } else {
            // Hard delete - remove completely
            $rowsAffected = $db->delete('products', 'id = ?', [$productId]);
            
            if ($rowsAffected > 0) {
                logError('Product deleted', ['product_id' => $productId, 'name' => $product['name']]);
                sendSuccess([], 'Product deleted successfully');
            } else {
                sendError('Failed to delete product');
            }
        }
        
    } catch (Exception $e) {
        throw $e;
    }
}

function getProductBadge($product) {
    if (!$product['in_stock']) {
        return 'Out of Stock';
    }
    
    if ($product['low_stock']) {
        return 'Low Stock';
    }
    
    // Check if product is new (created within last 7 days)
    $createdDate = new DateTime($product['created_at']);
    $now = new DateTime();
    $daysDiff = $now->diff($createdDate)->days;
    
    if ($daysDiff <= 7) {
        return 'New';
    }
    
    // Check if it's a premium product (high price)
    if ($product['price'] >= 400) {
        return 'Premium';
    }
    
    // Default badges based on category
    $categoryBadges = [
        'fresh' => 'Fresh',
        'ground' => 'Ground',
        'specialty' => 'Specialty'
    ];
    
    return $categoryBadges[$product['category']] ?? 'Available';
}
?>) as total FROM products WHERE {$whereClause}";
    $total = $db->fetch($totalQuery, $params)['total'];
    
    // Get products
    $productsQuery = "
        SELECT id, name, description, price, unit, category, image_url, stock_quantity, 
               min_stock_level, status, created_at, updated_at
        FROM products 
        WHERE {$whereClause}
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $products = $db->fetchAll($productsQuery, $params);
    
    // Add stock status and badges
    foreach ($products as &$product) {
        $product['in_stock'] = $product['stock_quantity'] > 0;
        $product['low_stock'] = $product['stock_quantity'] <= $product['min_stock_level'];
        $product['badge'] = getProductBadge($product);
        $product['formatted_price'] = '₱' . number_format($product['price'], 2);
    }
    
    sendSuccess([
        'products' => $products,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => intval($total),
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getCategories($db) {
    $categories = $db->fetchAll("
        SELECT category, COUNT(*) as product_count, 
               MIN(price) as min_price, MAX(price) as max_price
        FROM products 
        WHERE status = 'active'
        GROUP BY category
        ORDER BY category
    ");
    
    sendSuccess(['categories' => $categories]);
}

function searchProducts($db) {
    $query = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? '';
    $minPrice = floatval($_GET['min_price'] ?? 0);
    $maxPrice = floatval($_GET['max_price'] ?? 999999);
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    if (empty($query) && empty($category)) {
        sendError('Search query or category is required');
    }
    
    // Build search query
    $whereConditions = ['status = ?'];
    $params = ['active'];
    
    if (!empty($query)) {
        $whereConditions[] = '(name ILIKE ? OR description ILIKE ?)';
        $searchTerm = '%' . $query . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($category)) {
        $whereConditions[] = 'category = ?';
        $params[] = $category;
    }
    
    if ($minPrice > 0) {
        $whereConditions[] = 'price >= ?';
        $params[] = $minPrice;
    }
    
    if ($maxPrice < 999999) {
        $whereConditions[] = 'price <= ?';
        $params[] = $maxPrice;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count
    $totalQuery = "SELECT COUNT(*) as total FROM products WHERE {$whereClause}";
    $total = $db->fetch($totalQuery, $params)['total'];
    
    // Get products with relevance scoring
    $productsQuery = "
        SELECT id, name, description, price, unit, category, image_url, stock_quantity, 
               min_stock_level, status, created_at, updated_at,
               CASE 
                   WHEN name ILIKE ? THEN 3
                   WHEN description ILIKE ? THEN 2
                   ELSE 1
               END as relevance
        FROM products 
        WHERE {$whereClause}
        ORDER BY relevance DESC, created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    if (!empty($query)) {
        array_unshift($params, '%' . $query . '%', '%' . $query . '%');
    } else {
        array_unshift($params, '', '');
    }
    
    $params[] = $limit;
    $params[] = $offset;
    
    $products = $db->fetchAll($productsQuery, $params);
    
    // Add additional product data
    foreach ($products as &$product) {
        $product['in_stock'] = $product['stock_quantity'] > 0;
        $product['low_stock'] = $product['stock_quantity'] <= $product['min_stock_level'];
        $product['badge'] = getProductBadge($product);
        $product['formatted_price'] = '₱' . number_format($product['price'], 2);
        unset($product['relevance']); // Remove relevance from response
    }
    
    sendSuccess([
        'products' => $products,
        'search_query' => $query,
        'filters' => [
            'category' => $category,
            'min_price' => $minPrice,
            'max_price' => $maxPrice
        ],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => intval($total),
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getProductBadge($product) {
    if (!$product['in_stock']) {
        return 'Out of Stock';
    }
    
    if ($product['low_stock']) {
        return 'Low Stock';
    }
    
    // Check if product is new (created within last 7 days)
    $createdDate = new DateTime($product['created_at']);
    $now = new DateTime();
    $daysDiff = $now->diff($createdDate)->days;
    
    if ($daysDiff <= 7) {
        return 'New';
    }
    
    // Check if it's a premium product (high price)
    if ($product['price'] >= 400) {
        return 'Premium';
    }
    
    // Default badges based on category
    $categoryBadges = [
        'fresh' => 'Fresh',
        'ground' => 'Ground',
        'specialty' => 'Specialty'
    ];
    
    return $categoryBadges[$product['category']] ?? 'Available';
}