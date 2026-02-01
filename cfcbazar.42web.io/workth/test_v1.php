<?php
// test.php

// API credentials
$api_id  = "f196acadb20fc8d89d502a49bf41191e82291fc4a602e3b6414b59db221e86d3";
$api_key = "eafc42e3628bc072cb643e855d24b9206598000f91088bdb980f49810d2a1132";

// Set the required headers
$headers = [
    "accept: application/json",
    "X-API-ID: $api_id",
    "X-API-KEY: $api_key"
];

// Initialize cURL and make the request to the WorkTH currency endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.mintme.com/dev/api/v2/auth/currencies/workth");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    // If there's an error, display it
    echo "Error: " . curl_error($ch);
} else {
    // Set header type to JSON and output the response
    header("Content-Type: application/json");
    echo $response;
}

curl_close($ch);
?>