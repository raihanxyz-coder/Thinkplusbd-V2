<?php
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$review_id = isset($input['id']) ? (int)$input['id'] : -1;

if ($review_id >= 0) {
    $reviews_file_path = __DIR__ . '/reviews.json';
    if (file_exists($reviews_file_path)) {
        $reviews_json = file_get_contents($reviews_file_path);
        $reviews = json_decode($reviews_json, true);
        if (isset($reviews[$review_id])) {
            array_splice($reviews, $review_id, 1);
            file_put_contents($reviews_file_path, json_encode($reviews, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
            exit();
        }
    }
}
echo json_encode(['success' => false]);
?>
