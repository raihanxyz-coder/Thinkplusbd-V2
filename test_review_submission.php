<?php
$_POST = array(
    "product_id" => 1,
    "name" => "Test User",
    "rating" => 5,
    "comment" => "This is a test review."
);

$_SERVER['HTTP_REFERER'] = 'index.html#product/1/test-product';

include 'submit_review.php';
?>
