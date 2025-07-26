<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    if ($product_id > 0 && !empty($name) && $rating > 0 && !empty($comment)) {
        $reviews_file_path = __DIR__ . '/reviews.json';
        $reviews = [];
        if (file_exists($reviews_file_path)) {
            $reviews_json = file_get_contents($reviews_file_path);
            $reviews = json_decode($reviews_json, true);
        }

        $new_review = [
            'id' => uniqid(),
            'product_id' => $product_id,
            'name' => $name,
            'rating' => $rating,
            'comment' => $comment,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'pending' // Add status for admin approval
        ];

        $reviews[] = $new_review;
        $json_data = json_encode($reviews, JSON_PRETTY_PRINT);
        file_put_contents($reviews_file_path, $json_data);

        $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.html';
        header("Location: " . $redirect_url . "&status=review_submitted");
        exit();
    }
}

$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.html';
header("Location: " . $redirect_url . "&status=review_failed");
exit();
?>
