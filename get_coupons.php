<?php
header('Content-Type: application/json');
$coupons_file_path = __DIR__ . '/coupons.json';
if (file_exists($coupons_file_path)) {
    $coupons_json = file_get_contents($coupons_file_path);
    $coupons = json_decode($coupons_json, true);
    echo json_encode($coupons);
} else {
    echo json_encode([]);
}
?>
