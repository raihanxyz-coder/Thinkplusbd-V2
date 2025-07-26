<?php
// This script will simulate the form submission to add a new category

$url = 'https://test-website.great-site.net/admin_dashboard.php?page=categories';

// The data to be sent in the POST request
$data = [
    'add_category' => '1',
    'category_name' => 'Test Category',
    'category_icon' => 'fas fa-star',
    'category_subtitle' => 'This is a test category',
];

// cURL initialization
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true); // Get headers

// Execute cURL session
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch);
}

// Extract cookie from headers
$cookies = [];
if (preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches)) {
    foreach ($matches[1] as $match) {
        list($key, $value) = explode('=', $match, 2);
        $cookies[] = "$key=$value";
    }
}
$cookie_string = implode('; ', $cookies);

// Now make the actual request with the cookie
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false); // Don't get headers this time
curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));


// Execute cURL session
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch);
}

// Close cURL session
curl_close($ch);

// Print the response from the server
echo $response;
?>
