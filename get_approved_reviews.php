<?php
header('Content-Type: application/json');
$reviews_file_path = __DIR__ . '/reviews.json';
if (file_exists($reviews_file_path)) {
    $reviews_json = file_get_contents($reviews_file_path);
    $reviews = json_decode($reviews_json, true);
    $approved_reviews = array_filter($reviews, function($review) {
        return isset($review['status']) && $review['status'] === 'approved';
    });
    // Add an id to each review for easier handling on the frontend
    foreach ($approved_reviews as $i => &$review) {
        $review['id'] = $i;
    }
    echo json_encode(array_values($approved_reviews));
} else {
    echo json_encode([]);
}
?>
