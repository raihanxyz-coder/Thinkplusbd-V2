<?php
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$coupon_code = isset($input['code']) ? trim($input['code']) : '';

if (!empty($coupon_code)) {
    $coupons_file_path = __DIR__ . '/coupons.json';
    if (file_exists($coupons_file_path)) {
        $coupons_json = file_get_contents($coupons_file_path);
        $coupons = json_decode($coupons_json, true);
        $updated_coupons = array_filter($coupons, function($coupon) use ($coupon_code) {
            return $coupon['code'] !== $coupon_code;
        });
        file_put_contents($coupons_file_path, json_encode(array_values($updated_coupons), JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit();
    }
}
echo json_encode(['success' => false]);
?>
