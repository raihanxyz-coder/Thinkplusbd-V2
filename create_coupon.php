<?php
header('Content-Type: application/json');

function generate_random_string($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $characters_length = strlen($characters);
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, $characters_length - 1)];
    }
    return $random_string;
}

$input = json_decode(file_get_contents('php://input'), true);

$code = isset($input['code']) ? trim($input['code']) : '';
if (empty($code)) {
    $code = generate_random_string();
}

$discount_type = isset($input['discount_type']) ? $input['discount_type'] : 'percentage';
$discount_value = isset($input['discount_value']) ? (float)$input['discount_value'] : 0;
$product_ids = isset($input['product_ids']) ? $input['product_ids'] : null;
$category = isset($input['category']) ? trim($input['category']) : null;

if (!empty($code) && $discount_value > 0 && !empty($category) && !empty($product_ids)) {
    $coupons_file_path = __DIR__ . '/coupons.json';
    $coupons = [];
    if (file_exists($coupons_file_path)) {
        $coupons_json = file_get_contents($coupons_file_path);
        $coupons = json_decode($coupons_json, true);
    }

    $new_coupon = [
        'code' => $code,
        'discount_type' => $discount_type,
        'discount_value' => $discount_value,
        'product_ids' => $product_ids,
        'category' => $category,
    ];

    $coupons[] = $new_coupon;
    $json_data = json_encode($coupons, JSON_PRETTY_PRINT);
    file_put_contents($coupons_file_path, $json_data);

    echo json_encode(['success' => true, 'coupon_code' => $code]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid input.']);
?>
