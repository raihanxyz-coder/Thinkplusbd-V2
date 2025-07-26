<?php
header('Content-Type: application/json');

$category = isset($_GET['category']) ? $_GET['category'] : '';

if (empty($category)) {
    echo json_encode(['error' => 'Category not specified.']);
    exit;
}

$products_file_path = __DIR__ . '/products.json';

if (file_exists($products_file_path)) {
    $json_data = file_get_contents($products_file_path);
    $products = json_decode($json_data, true);

    $filtered_products = [];
    foreach ($products as $product) {
        if (isset($product['category']) && $product['category'] === $category) {
            $filtered_products[] = $product;
        }
    }

    echo json_encode($filtered_products);
} else {
    echo json_encode([]);
}
?>
