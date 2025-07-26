<?php
$_POST = json_encode(array(
    "code" => "IMPROVED",
    "discount_type" => "percentage",
    "discount_value" => 20,
    "category" => "Subscription",
    "product_ids" => array("4", "6")
));

include 'create_coupon.php';
?>
