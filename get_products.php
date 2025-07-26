<?php
header('Content-Type: application/json');
$products_file_path = __DIR__ . '/products.json';

if (file_exists($products_file_path)) {
    $json_data = file_get_contents($products_file_path);
    echo $json_data;
} else {
    echo json_encode([]);
}
?>
