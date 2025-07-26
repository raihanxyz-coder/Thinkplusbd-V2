<?php
header('Content-Type: application/json');
$categories_file_path = __DIR__ . '/categories.json';

if (file_exists($categories_file_path)) {
    $json_data = file_get_contents($categories_file_path);
    echo $json_data;
} else {
    echo json_encode([]);
}
?>
