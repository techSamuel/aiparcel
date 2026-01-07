<?php
$url = "https://openapi.redx.com.bd/v1.0.0-beta/areas";
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: PHP\r\n"
    ],
    "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false
    ]
];
$context = stream_context_create($opts);
$data = file_get_contents($url, false, $context);

if ($data === false) {
    echo "Failed to fetch data.\n";
    exit;
}

$json = json_decode($data, true);
echo "Total Bytes: " . strlen($data) . "\n";
echo "Total Areas: " . count($json['areas']) . "\n";
echo "First Area: " . print_r($json['areas'][0], true) . "\n";
?>