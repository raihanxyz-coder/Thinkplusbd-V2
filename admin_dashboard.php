<?php
session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

if (!isset($_SESSION['admin_logged_in_thinkplusbd']) || $_SESSION['admin_logged_in_thinkplusbd'] !== true) {
    header("Location: admin_login.php");
    exit();
}

if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    session_destroy();
    header("Location: admin_login.php");
    exit();
}

// Functions for stats
function getStatsForPeriod($orders, $startDate, $endDate) {
    $stats = [
        'total_orders' => 0,
        'confirmed_orders' => 0,
        'cancelled_orders' => 0,
        'pending_orders_in_period' => 0,
        'total_revenue' => 0.0
    ];
    if (!is_array($orders)) $orders = [];
    foreach ($orders as $order) {
        $orderTimestamp = isset($order['timestamp']) ? strtotime($order['timestamp']) : 0;
        $orderStatus = strtolower($order['status'] ?? 'unknown');
        $orderTotalAmount = floatval($order['totalAmount'] ?? 0);
        
        if ($orderTimestamp >= $startDate && $orderTimestamp <= $endDate) {
            $stats['total_orders']++;
            if ($orderStatus === 'confirmed') {
                $stats['confirmed_orders']++;
                if (!isset($order['is_deleted']) || $order['is_deleted'] !== true || (isset($order['confirmed_at']) && (!isset($order['deleted_at']) || strtotime($order['deleted_at']) > strtotime($order['confirmed_at'])) ) ) {
                    $stats['total_revenue'] += $orderTotalAmount;
                }
            } elseif ($orderStatus === 'cancelled') {
                $stats['cancelled_orders']++;
            } elseif ($orderStatus === 'pending') {
                $stats['pending_orders_in_period']++;
            }
        }
    }
    return $stats;
}

function getCurrentTotalPendingOrders($orders) {
    $count = 0;
    if (!is_array($orders)) $orders = [];
    foreach ($orders as $order) {
        if (strtolower($order['status'] ?? 'unknown') === 'pending' && (!isset($order['is_deleted']) || $order['is_deleted'] !== true)) {
            $count++;
        }
    }
    return $count;
}

// Functions for products and categories
function get_categories() {
    $categories_file_path = __DIR__ . '/categories.json';
    if (!file_exists($categories_file_path)) {
        return [];
    }
    $json_data = file_get_contents($categories_file_path);
    return json_decode($json_data, true);
}

function save_categories($categories) {
    $categories_file_path = __DIR__ . '/categories.json';
    $json_data = json_encode($categories, JSON_PRETTY_PRINT);
    file_put_contents($categories_file_path, $json_data);
}

function get_products() {
    $products_file_path = __DIR__ . '/products.json';
    if (!file_exists($products_file_path)) {
        return [];
    }
    $json_data = file_get_contents($products_file_path);
    return json_decode($json_data, true);
}

// Handle form submissions for categories
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $new_category_name = trim($_POST['category_name']);
        $new_category_icon = trim($_POST['category_icon']);
        $new_category_subtitle = trim($_POST['category_subtitle']);

        if (!empty($new_category_name) && !empty($new_category_icon) && !empty($new_category_subtitle)) {
            $categories = get_categories();
            $new_category = [
                'name' => $new_category_name,
                'icon' => $new_category_icon,
                'subtitle' => $new_category_subtitle
            ];
            $categories[] = $new_category;
            save_categories($categories);
            header("Location: admin_dashboard.php?page=categories&status=added");
            exit();
        } else {
            header("Location: admin_dashboard.php?page=categories&error=empty_fields");
            exit();
        }
    }

    if (isset($_POST['delete_category'])) {
        $category_name_to_delete = $_POST['category_name'];
        $categories = get_categories();
        $categories = array_filter($categories, function($category) use ($category_name_to_delete) {
            return $category['name'] !== $category_name_to_delete;
        });
        save_categories(array_values($categories));
        header("Location: admin_dashboard.php?page=categories&status=deleted");
        exit();
    }

    if (isset($_POST['create_product'])) {
        $products = get_products();
        $new_product_id = count($products) > 0 ? max(array_column($products, 'id')) + 1 : 1;

        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'product_images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = basename($_FILES['image']['name']);
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
            }
        }

        $new_product = [
            'id' => $new_product_id,
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'longDescription' => $_POST['longDescription'],
            'category' => $_POST['category'],
            'price' => (float)$_POST['price'],
            'image' => $image_path,
            'isFeatured' => isset($_POST['isFeatured']) && $_POST['isFeatured'] === 'true',
            'durations' => []
        ];

        if (isset($_POST['discount_toggle']) && $_POST['discount_toggle'] === 'on') {
            $new_product['discount'] = [
                'type' => $_POST['discount_type'],
                'value' => (float)$_POST['discount_value']
            ];
        }

        $products[] = $new_product;
        save_products($products);

        header("Location: admin_dashboard.php?page=products&category=" . urlencode($_POST['category']) . "&status=added");
        exit();
    }

    if (isset($_POST['update_product'])) {
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

        if ($product_id > 0) {
            $products = get_products();
            $product_index = -1;

            foreach ($products as $index => $product) {
                if ($product['id'] === $product_id) {
                    $product_index = $index;
                    break;
                }
            }

            if ($product_index !== -1) {
                $products[$product_index]['name'] = $_POST['name'];
                $products[$product_index]['description'] = $_POST['description'];
                $products[$product_index]['longDescription'] = $_POST['longDescription'];
                $products[$product_index]['price'] = (float)$_POST['price'];

                $durations = [];
                if (isset($_POST['durations']) && is_array($_POST['durations'])) {
                    foreach ($_POST['durations'] as $duration) {
                        if (!empty($duration['label']) && !empty($duration['price'])) {
                            $durations[] = [
                                'label' => $duration['label'],
                                'price' => (float)$duration['price']
                            ];
                        }
                    }
                }
                $products[$product_index]['durations'] = $durations;

                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'product_images/';
                    $file_name = basename($_FILES['image']['name']);
                    $target_file = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $products[$product_index]['image'] = $target_file;
                    }
                }

                save_products($products);
                header("Location: admin_dashboard.php?page=edit_products&category=" . urlencode($products[$product_index]['category']) . "&status=updated");
                exit();
            }
        }
    }
}


$orders_file_path = __DIR__ . '/orders.json';
$all_site_orders_for_stats = []; 
$orders_for_display = [];      
$json_load_error = null;

if (file_exists($orders_file_path)) {
    $json_order_data = file_get_contents($orders_file_path);
    if ($json_order_data === false) {
        $json_load_error = "Could not read orders.json file.";
    } elseif (!empty($json_order_data)) {
        $decoded_orders = json_decode($json_order_data, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_orders)) {
            $all_site_orders_for_stats = $decoded_orders; 
            foreach ($all_site_orders_for_stats as $order) {
                if (!isset($order['is_deleted']) || $order['is_deleted'] !== true) {
                    $orders_for_display[] = $order;
                }
            }
            usort($orders_for_display, function($a, $b) { 
                $timeA = isset($a['timestamp']) ? strtotime($a['timestamp']) : 0;
                $timeB = isset($b['timestamp']) ? strtotime($b['timestamp']) : 0;
                return $timeB - $timeA;
            });
        } else {
            $json_load_error = "Critical Error: Could not decode orders.json. Error: " . json_last_error_msg();
        }
    }
}

date_default_timezone_set('Asia/Dhaka'); 
$today_start = strtotime('today midnight');
$today_end = strtotime('tomorrow midnight') - 1;
$week_start = strtotime('-6 days midnight', $today_start);
$month_start = strtotime('-29 days midnight', $today_start);
$ninety_days_start = strtotime('-89 days midnight', $today_start);
$year_start = strtotime('-364 days midnight', $today_start);

$stats_today = getStatsForPeriod($all_site_orders_for_stats, $today_start, $today_end);
$stats_week = getStatsForPeriod($all_site_orders_for_stats, $week_start, $today_end);
$stats_month = getStatsForPeriod($all_site_orders_for_stats, $month_start, $today_end);
$stats_90_days = getStatsForPeriod($all_site_orders_for_stats, $ninety_days_start, $today_end);
$stats_year = getStatsForPeriod($all_site_orders_for_stats, $year_start, $today_end);
$current_total_pending_all_time = getCurrentTotalPendingOrders($all_site_orders_for_stats);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - THINK PLUS BD</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_dashboard.css">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="logo-admin">
                <img src="https://i.postimg.cc/4NtztqPt/IMG-20250603-130207-removebg-preview-1.png" alt="THINK PLUS BD Logo">
            </div>
            <nav class="admin-nav">
                <ul>
                    <li><a href="admin_dashboard.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'admin_dashboard.php') !== false && empty($_GET['page']) && strpos($_SERVER['REQUEST_URI'], 'product_code_generator.html') === false) ? 'active' : ''; ?>"><i class="fas fa-chart-pie"></i> <span>Dashboard</span></a></li>
                    <li><a href="admin_dashboard.php?page=categories" class="<?php echo (isset($_GET['page']) && $_GET['page'] === 'categories') ? 'active' : ''; ?>"><i class="fas fa-tags"></i> <span>Manage Categories</span></a></li>
                    <li><a href="admin_dashboard.php?page=edit_products" class="<?php echo (isset($_GET['page']) && $_GET['page'] === 'edit_products') ? 'active' : ''; ?>"><i class="fas fa-edit"></i> <span>Edit Products</span></a></li>
                    <li><a href="admin_dashboard.php?page=reviews" class="<?php echo (isset($_GET['page']) && $_GET['page'] === 'reviews') ? 'active' : ''; ?>"><i class="fas fa-star"></i> <span>Manage Reviews</span></a></li>
                    <li><a href="admin_dashboard.php?page=coupons" class="<?php echo (isset($_GET['page']) && $_GET['page'] === 'coupons') ? 'active' : ''; ?>"><i class="fas fa-tags"></i> <span>Manage Coupons</span></a></li>
                    <li><a href="product_code_generator.html" target="_blank"><i class="fas fa-plus-circle"></i> <span>Add Product Helper</span></a></li>
                    <li><a href="admin_dashboard.php?logout=1"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </nav>
        </aside>
        <main class="admin-main-content" id="adminMainContent">
            <header class="admin-topbar">
                <div style="display:flex; align-items:center;">
                    <i class="fas fa-bars sidebar-toggle" id="sidebarToggle"></i>
                    <h1>Admin Panel</h1>
                </div>
                <a href="admin_dashboard.php?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </header>
            <div class="admin-page-content">
            <?php if (isset($_GET['page']) && $_GET['page'] === 'reviews'): ?>
                <?php include 'admin_reviews.php'; ?>
            <?php elseif (isset($_GET['page']) && $_GET['page'] === 'coupons'): ?>
                <?php include 'admin_coupons.php'; ?>
            <?php elseif (isset($_GET['page']) && $_GET['page'] === 'categories'): ?>
                <div class="content-card">
                    <h2 class="card-title">Manage Categories</h2>
                    <?php
                    if (isset($_GET['status'])) {
                        if ($_GET['status'] == 'added') {
                            echo '<div class="alert-message alert-success">Category successfully added!</div>';
                        } elseif ($_GET['status'] == 'deleted') {
                            echo '<div class="alert-message alert-success">Category successfully deleted!</div>';
                        }
                    }
                    if (isset($_GET['error'])) {
                        if ($_GET['error'] == 'empty_fields') {
                            echo '<div class="alert-message alert-danger">Error: All fields are required.</div>';
                        }
                    }
                    ?>
                    <form method="POST" action="admin_dashboard.php?page=categories" style="margin-bottom: 2rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                            <div>
                                <label for="category_name" style="display:block; margin-bottom: .5rem; font-weight: 500;">Category Name</label>
                                <input type="text" id="category_name" name="category_name" placeholder="e.g., Course" required style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                            </div>
                            <div>
                                <label for="category_icon" style="display:block; margin-bottom: .5rem; font-weight: 500;">Font Awesome Icon</label>
                                <input type="text" id="category_icon" name="category_icon" placeholder="e.g., fas fa-graduation-cap" required style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                            </div>
                            <div>
                                <label for="category_subtitle" style="display:block; margin-bottom: .5rem; font-weight: 500;">Subtitle</label>
                                <input type="text" id="category_subtitle" name="category_subtitle" placeholder="e.g., Premium Courses" required style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                            </div>
                            <button type="submit" name="add_category" style="padding: 0.5rem 1rem; border: none; background-color: var(--primary-color); color: white; border-radius: var(--border-radius); cursor: pointer; height: fit-content;">Add Category</button>
                        </div>
                    </form>

                    <div class="category-cards-container">
                        <?php
                        $categories_file_path = __DIR__ . '/categories.json';
                        if (file_exists($categories_file_path)) {
                            $categories_json = file_get_contents($categories_file_path);
                            $categories = json_decode($categories_json, true);
                            if (is_array($categories)) {
                                foreach ($categories as $category) {
                                    echo '<div class="category-card">';
                                    echo '<div class="category-card-icon"><i class="' . htmlspecialchars($category['icon']) . '"></i></div>';
                                    echo '<div class="category-card-body">';
                                    echo '<h4 class="category-card-name">' . htmlspecialchars($category['name']) . '</h4>';
                                    echo '<p class="category-card-subtitle">' . htmlspecialchars($category['subtitle']) . '</p>';
                                    echo '</div>';
                                    echo '<div class="category-card-footer">';
                                    echo '<form method="POST" action="admin_dashboard.php?page=categories" onsubmit="return confirm(\'Are you sure you want to delete this category?\');">';
                                    echo '<input type="hidden" name="category_name" value="' . htmlspecialchars($category['name']) . '">';
                                    echo '<button type="submit" name="delete_category" class="action-btn action-btn-delete" style="color: #dc3545 !important; border-color: #dc3545;">Delete</button>';
                                    echo '</form>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            <?php elseif (isset($_GET['page']) && $_GET['page'] === 'edit_products'): ?>
                <?php
                $categories = get_categories();
                $selected_category = isset($_GET['category']) ? $_GET['category'] : null;
                $products = get_products();
                ?>
                <div class="content-card">
                    <h2 class="card-title">Select a Category to Edit Products</h2>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <form method="GET" action="admin_dashboard.php" style="margin-bottom: 0;">
                            <input type="hidden" name="page" value="edit_products">
                            <select name="category" onchange="this.form.submit()">
                                <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>" <?php if ($selected_category === $category['name']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php if ($selected_category): ?>
                        <a href="admin_dashboard.php?page=add_product&category=<?php echo urlencode($selected_category); ?>" class="action-btn" style="text-decoration: none;">Add New Product</a>
                    <?php endif; ?>
                    </div>
                </div>

                <?php if ($selected_category): ?>
                <div class="content-card">
                    <h2 class="card-title">Editing Products in "<?php echo htmlspecialchars($selected_category); ?>"</h2>
                    <div class="orders-table-container">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($products) {
                                    foreach ($products as $product) {
                                        if (strtolower($product['category']) === strtolower($selected_category)) {
                                            echo '<tr>';
                                            echo '<td data-label="Product Name">' . htmlspecialchars($product['name']) . '</td>';
                                            echo '<td data-label="Actions">
                                                    <a href="admin_dashboard.php?page=edit_product&id=' . $product['id'] . '" class="action-btn">Edit</a>
                                                    <form method="POST" action="delete_product.php" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this product?\');">
                                                        <input type="hidden" name="product_id" value="' . $product['id'] . '">
                                                        <button type="submit" class="action-btn action-btn-delete" style="color: #dc3545 !important; border-color: #dc3545;">Delete</button>
                                                    </form>
                                                  </td>';
                                            echo '</tr>';
                                        }
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            <?php elseif (isset($_GET['page']) && $_GET['page'] === 'add_product'): ?>
                <div class="content-card">
                    <h2 class="card-title">Add a New Product</h2>
                    <form action="admin_dashboard.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="create_product" value="1">
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="name">Product Title</label>
                            <input type="text" name="name" id="name" class="form-control" required style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="description">Short Description</label>
                            <textarea name="description" id="description" rows="3" class="form-control" required style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);"></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="longDescription">Long Description</label>
                            <textarea name="longDescription" id="longDescription" rows="10" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);"></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="price">Price</label>
                            <input type="number" step="0.01" name="price" id="price" class="form-control" required style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="category">Category</label>
                            <select name="category" id="category" class="form-control" required style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                                <?php
                                $categories = get_categories();
                                $selected_category = isset($_GET['category']) ? $_GET['category'] : '';
                                foreach ($categories as $category): ?>
                                    <option value="<?php echo strtolower(htmlspecialchars($category['name'])); ?>" <?php if (strtolower($selected_category) === strtolower($category['name'])) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="image">Product Image</label>
                            <input type="file" name="image" id="image" class="form-control-file" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="isFeatured">Featured Product?</label>
                            <input type="checkbox" name="isFeatured" id="isFeatured" value="true">
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="discount-toggle">Apply Discount?</label>
                            <input type="checkbox" name="discount_toggle" id="discount-toggle">
                        </div>

                        <div id="discount-fields" style="display: none;">
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label for="discount-type">Discount Type</label>
                                <select name="discount_type" id="discount-type" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                                    <option value="percentage">Percentage</option>
                                    <option value="fixed">Fixed Amount</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label for="discount-value">Discount Value</label>
                                <input type="number" step="0.01" name="discount_value" id="discount-value" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; border: none; background-color: var(--primary-color); color: white; border-radius: var(--border-radius); cursor: pointer;">Add Product</button>
                    </form>
                </div>
            <?php elseif (isset($_GET['page']) && $_GET['page'] === 'edit_product' && isset($_GET['id'])): ?>
                <?php
                $product_id = (int)$_GET['id'];
                $products = get_products();
                $product_to_edit = null;
                foreach ($products as $product) {
                    if ($product['id'] === $product_id) {
                        $product_to_edit = $product;
                        break;
                    }
                }
                ?>
                <?php if ($product_to_edit): ?>
                    <div class="content-card">
                        <h2 class="card-title">Editing "<?php echo htmlspecialchars($product_to_edit['name']); ?>"</h2>
                        <form action="admin_dashboard.php?page=edit_product&id=<?php echo $product_to_edit['id']; ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="update_product" value="1">
                            <input type="hidden" name="product_id" value="<?php echo $product_to_edit['id']; ?>">

                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label for="name">Product Title</label>
                                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($product_to_edit['name']); ?>" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                            </div>

                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label for="description">Short Description</label>
                                <textarea name="description" id="description" rows="3" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);"><?php echo htmlspecialchars($product_to_edit['description']); ?></textarea>
                            </div>

                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label for="longDescription">Long Description</label>
                                <textarea name="longDescription" id="longDescription" rows="10" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);"><?php echo htmlspecialchars($product_to_edit['longDescription']); ?></textarea>
                            </div>

                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label for="price">Price (for products without duration)</label>
                                <input type="number" step="0.01" name="price" id="price" value="<?php echo htmlspecialchars($product_to_edit['price']); ?>" class="form-control" style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                            </div>

                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label>Durations and Prices</label>
                                <div id="durations-container">
                                    <?php if (!empty($product_to_edit['durations'])): ?>
                                        <?php foreach ($product_to_edit['durations'] as $index => $duration): ?>
                                            <div class="duration-item" style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">
                                                <input type="text" name="durations[<?php echo $index; ?>][label]" placeholder="Label (e.g., 1 Month)" value="<?php echo htmlspecialchars($duration['label']); ?>" style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                                                <input type="number" step="0.01" name="durations[<?php echo $index; ?>][price]" placeholder="Price" value="<?php echo htmlspecialchars($duration['price']); ?>" style="width: 100%; padding: 0.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                                                <button type="button" class="remove-duration-btn" style="padding: 0.5rem 1rem; border: none; background-color: #dc3545; color: white; border-radius: var(--border-radius); cursor: pointer;">Remove</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="add-duration-btn" style="padding: 0.5rem 1rem; border: none; background-color: var(--primary-color); color: white; border-radius: var(--border-radius); cursor: pointer; margin-top: 0.5rem;">Add Duration</button>
                            </div>

                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label>Current Image</label>
                                <div>
                                    <img src="<?php echo htmlspecialchars($product_to_edit['image']); ?>" alt="Current Image" style="max-width: 200px; max-height: 200px;">
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label for="image">Upload New Image (optional)</label>
                                <input type="file" name="image" id="image" class="form-control-file">
                            </div>

                            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; border: none; background-color: var(--primary-color); color: white; border-radius: var(--border-radius); cursor: pointer;">Update Product</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="content-card">
                        <p>Product not found.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="content-card">
                    <h2 class="card-title">Performance Overview</h2>
                    <div class="stats-period-selector">
                        <label for="period_selector">Showing stats for:</label>
                        <select id="period_selector" onchange="updateStatsDisplay(this.value)">
                            <option value="today" selected>Today</option>
                            <option value="week">Last 7 Days</option>
                            <option value="month">Last 30 Days</option>
                            <option value="ninetydays">Last 90 Days</option>
                            <option value="year">Last 365 Days</option>
                        </select>
                        <p>
                            <strong>Pending (All Time):</strong> 
                            <span id="currentTotalPendingAllTime"><?php echo $current_total_pending_all_time; ?></span>
                        </p>
                    </div>
                    <div id="stats-display-area">
                        <div class="stat-card"><h4>Total Orders</h4><p id="stat_total_orders">0</p></div>
                        <div class="stat-card"><h4>Confirmed</h4><p id="stat_confirmed_orders">0</p></div>
                        <div class="stat-card"><h4>Cancelled</h4><p id="stat_cancelled_orders">0</p></div>
                        <div class="stat-card"><h4>Pending (Period)</h4><p id="stat_pending_orders_in_period">0</p></div>
                        <div class="stat-card" id="stat_total_revenue_card"><h4>Total Revenue</h4><p id="stat_total_revenue">৳0.00</p></div>
                    </div>
                </div>
                <div class="content-card">
                    <h2 class="card-title">Manage Orders</h2>
                    <?php if ($json_load_error): ?>
                        <div class="alert-message alert-danger"><?php echo htmlspecialchars($json_load_error); ?></div>
                    <?php endif; ?>
                    <?php
                        // Display success/error messages from GET parameters
                        if (isset($_GET['status_change'])) {
                            $changed_order_id = isset($_GET['orderid']) ? htmlspecialchars($_GET['orderid']) : '';
                            if ($_GET['status_change'] == 'success') {
                                $new_status = isset($_GET['new_status']) ? htmlspecialchars($_GET['new_status']) : 'updated';
                                echo '<div class="alert-message alert-success">Order ' . $changed_order_id . ' successfully marked as ' . $new_status . '!</div>';
                            } elseif ($_GET['status_change'] == 'marked_as_deleted') {
                                echo '<div class="alert-message alert-success">Order ' . $changed_order_id . ' successfully hidden from active list.</div>';
                            }
                        }
                        if (isset($_GET['error'])) {
                             echo '<div class="alert-message alert-danger">Error: ' . htmlspecialchars(str_replace('_', ' ', $_GET['error'])) . '</div>';
                        }
                    ?>
                    <div class="orders-table-container">
                        <?php if (empty($orders_for_display) && !$json_load_error): ?>
                            <p class='no-orders-message'>No active orders to display.</p>
                        <?php elseif (!empty($orders_for_display)): ?>
                            <table class='orders-table'>
                            <thead><tr><th>Order ID</th><th>Date</th><th>Customer</th><th>Contact</th><th>TrxID</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($orders_for_display as $single_order): ?>
                                <tr>
                                <td data-label='Order ID' style="font-weight:500;"><?php echo htmlspecialchars($single_order['id']); ?></td>
                                <td data-label='Date'><?php echo htmlspecialchars(date('d M Y, H:i', (isset($single_order['timestamp']) ? strtotime($single_order['timestamp']) : time()))); ?></td>
                                <td data-label='Customer'><strong><?php echo htmlspecialchars($single_order['customer']['name'] ?? 'N/A'); ?></strong><small><?php echo htmlspecialchars($single_order['customer']['email'] ?? 'N/A'); ?></small></td>
                                <td data-label='Contact'><?php echo htmlspecialchars($single_order['customer']['phone'] ?? 'N/A'); ?></td>
                                <td data-label='TrxID'><?php echo htmlspecialchars($single_order['transactionId'] ?? 'N/A'); ?></td>
                                <td data-label='Items'><ul class='order-items-list-admin'>
                                <?php if (isset($single_order['items']) && is_array($single_order['items'])): foreach ($single_order['items'] as $item):
                                    $item_name = htmlspecialchars($item['name'] ?? 'Unknown');
                                    $item_quantity = htmlspecialchars($item['quantity'] ?? 1);
                                    $item_price = htmlspecialchars(number_format(floatval($item['price'] ?? 0), 0)); // Price without decimals for cleaner look
                                    $item_duration = isset($item['selectedDurationLabel']) && !empty($item['selectedDurationLabel']) ? ' (' . htmlspecialchars($item['selectedDurationLabel']) . ')' : '';
                                ?>
                                    <li><?php echo $item_name . $item_duration; ?> (x<?php echo $item_quantity; ?>)</li>
                                <?php endforeach; endif; ?>
                                </ul></td>
                                <td data-label='Total' style="font-weight:600; color:var(--text-color);">৳<?php echo htmlspecialchars(number_format(floatval($single_order['totalAmount'] ?? 0), 0)); ?></td>
                                <td data-label='Payment'><?php echo htmlspecialchars(ucfirst($single_order['paymentMethod'] ?? 'N/A')); ?></td>
                                <?php
                                    $order_status_val = strtolower($single_order['status'] ?? 'unknown');
                                    $status_class_name = 'status-' . str_replace(' ', '-', $order_status_val);
                                    if (!in_array($status_class_name, ['status-pending', 'status-confirmed', 'status-cancelled'])) {
                                        $status_class_name = 'status-unknown';
                                    }
                                ?>
                                <td data-label='Status'><span class='status-badge <?php echo $status_class_name; ?>'><?php echo htmlspecialchars($order_status_val); ?></span></td>
                                <td data-label='Actions'>
                                <div class="action-buttons-group">
                                <?php if ($order_status_val === 'pending'): ?>
                                    <form method='POST' action='confirm_order.php' style='display:inline;'>
                                        <input type='hidden' name='order_id_to_change' value='<?php echo htmlspecialchars($single_order['id']); ?>'>
                                        <input type='hidden' name='new_status' value='Confirmed'><button type='submit' class='action-btn action-btn-confirm'>Confirm</button>
                                    </form>
                                    <form method='POST' action='confirm_order.php' style='display:inline;'>
                                        <input type='hidden' name='order_id_to_change' value='<?php echo htmlspecialchars($single_order['id']); ?>'>
                                        <input type='hidden' name='new_status' value='Cancelled'><button type='submit' class='action-btn action-btn-cancel'>Cancel</button>
                                    </form>
                                <?php elseif ($order_status_val === 'confirmed'): ?>
                                    <span class='action-btn-text confirmed'>Confirmed <small><?php if(isset($single_order['confirmed_at'])) echo htmlspecialchars(date('d M, H:i', strtotime($single_order['confirmed_at']))); ?></small></span>
                                <?php elseif ($order_status_val === 'cancelled'): ?>
                                    <span class='action-btn-text cancelled'>Cancelled <small><?php if(isset($single_order['cancelled_at'])) echo htmlspecialchars(date('d M, H:i', strtotime($single_order['cancelled_at']))); ?></small></span>
                                <?php endif; ?>
                                <?php // Hide button is always available for processed orders if needed, or only for pending if preferred ?>
                                <form method='POST' action='delete_order.php' style='display:inline;' onsubmit="return confirm('Are you sure you want to hide Order ID: <?php echo htmlspecialchars($single_order['id']); ?>?');">
                                    <input type='hidden' name='order_id_to_delete' value='<?php echo htmlspecialchars($single_order['id']); ?>'>
                                    <button type='submit' class='action-btn action-btn-delete' title='Hide this order from the active list'>Hide</button>
                                </form>
                                </div>
                                </td></tr>
                            <?php endforeach; ?>
                            </tbody></table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        const allStatsDataFromPHP = {
            today: <?php echo json_encode($stats_today); ?>,
            week: <?php echo json_encode($stats_week); ?>,
            month: <?php echo json_encode($stats_month); ?>,
            ninetydays: <?php echo json_encode($stats_90_days); ?>,
            year: <?php echo json_encode($stats_year); ?>
        };

        function updateStatsDisplay(period) {
            const selectedStats = allStatsDataFromPHP[period];
            if (selectedStats) {
                document.getElementById('stat_total_orders').textContent = selectedStats.total_orders || 0;
                document.getElementById('stat_confirmed_orders').textContent = selectedStats.confirmed_orders || 0;
                document.getElementById('stat_cancelled_orders').textContent = selectedStats.cancelled_orders || 0;
                document.getElementById('stat_pending_orders_in_period').textContent = selectedStats.pending_orders_in_period || 0;
                document.getElementById('stat_total_revenue').textContent = '৳' + (parseFloat(selectedStats.total_revenue) || 0).toFixed(2);
            } else { 
                document.getElementById('stat_total_orders').textContent = '0';
                document.getElementById('stat_confirmed_orders').textContent = '0';
                document.getElementById('stat_cancelled_orders').textContent = '0';
                document.getElementById('stat_pending_orders_in_period').textContent = '0';
                document.getElementById('stat_total_revenue').textContent = '৳0.00';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            updateStatsDisplay(document.getElementById('period_selector').value);
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const discountToggle = document.getElementById('discount-toggle');
            const discountFields = document.getElementById('discount-fields');

            if (discountToggle && discountFields) {
                discountToggle.addEventListener('change', function() {
                    if (this.checked) {
                        discountFields.style.display = 'block';
                    } else {
                        discountFields.style.display = 'none';
                    }
                });
            }
        });
    </script>
    <script src="admin_dashboard.js"></script>
</body>
</html>