<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['admin_logged_in_thinkplusbd']) || $_SESSION['admin_logged_in_thinkplusbd'] !== true) {
    header("Location: admin_login.php");
    exit();
}

function get_products() {
    $products_file_path = __DIR__ . '/products.json';
    if (!file_exists($products_file_path)) {
        return [];
    }
    $json_data = file_get_contents($products_file_path);
    return json_decode($json_data, true);
}

function save_products($products) {
    $products_file_path = __DIR__ . '/products.json';
    $json_data = json_encode(array_values($products), JSON_PRETTY_PRINT);
    file_put_contents($products_file_path, $json_data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    if ($product_id > 0) {
        $products = get_products();
        $product_index = -1;
        $category = '';

        foreach ($products as $index => $product) {
            if ($product['id'] === $product_id) {
                $product_index = $index;
                $category = $product['category'];
                break;
            }
        }

        if ($product_index !== -1) {
            unset($products[$product_index]);
            save_products($products);
            header("Location: edit_products.php?category=" . urlencode($category) . "&status=deleted");
            exit();
        }
    }
}

header("Location: edit_products.php?error=delete_failed");
exit();
?>
