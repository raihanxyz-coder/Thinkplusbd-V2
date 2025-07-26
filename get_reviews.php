<?php
header('Content-Type: application/json');

$reviews_file_path = __DIR__ . '/reviews.json';
$products_file_path = __DIR__ . '/products.json';

if (file_exists($reviews_file_path) && file_exists($products_file_path)) {
    $reviews_json = file_get_contents($reviews_file_path);
    $reviews = json_decode($reviews_json, true);

    $products_json = file_get_contents($products_file_path);
    $products = json_decode($products_json, true);

    // Create a lookup table for products
    $product_lookup = [];
    foreach ($products as $product) {
        $product_lookup[$product['id']] = $product;
    }

    // Add product name and category to each review
    foreach ($reviews as &$review) {
        if (isset($product_lookup[$review['product_id']])) {
            $product = $product_lookup[$review['product_id']];
            $review['product_name'] = $product['name'];
            $review['category'] = $product['category'];
        } else {
            $review['product_name'] = 'Unknown Product';
            $review['category'] = 'Unknown Category';
        }
    }

    echo json_encode($reviews);
} else {
    echo json_encode([]);
}
?>
