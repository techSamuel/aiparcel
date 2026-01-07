<?php
$html = file_get_contents('bd_test.html');
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// NEW LOGIC: justify-between
$rows = $xpath->query("//div[contains(@class, 'border-b') and contains(@class, 'justify-between')]");
$courier_data = [];

foreach ($rows as $row) {
    // 1. Identify Courier
    $imgNode = $xpath->query(".//img", $row)->item(0);
    if (!$imgNode)
        continue;

    $src = $imgNode->getAttribute('src') . $imgNode->getAttribute('srcset');
    $courierName = null;

    if (stripos($src, 'pathao') !== false)
        $courierName = 'Pathao';
    elseif (stripos($src, 'redx') !== false)
        $courierName = 'RedX';
    elseif (stripos($src, 'steadfast') !== false)
        $courierName = 'Steadfast';
    elseif (stripos($src, 'paperfly') !== false)
        $courierName = 'Paperfly';
    elseif (stripos($src, 'ecourier') !== false)
        $courierName = 'eCourier';
    elseif (stripos($src, 'parceldex') !== false)
        $courierName = 'Parceldex';
    elseif (stripos($src, 'carrybee') !== false)
        $courierName = 'Carrybee';

    if (!$courierName)
        continue;

    // 2. Extract Stats
    $totalNode = $xpath->query(".//span[contains(@class, 'text-center')]", $row)->item(0);
    $cancelledNode = $xpath->query(".//span[contains(@class, 'text-danger')]", $row)->item(0);
    $deliveredNode = $xpath->query(".//span[contains(@class, 'text-secondary')]", $row)->item(0);

    // NEW LOGIC: Validation
    $rawOrders = $totalNode ? trim($totalNode->textContent) : '0';
    $rawCancelled = $cancelledNode ? trim($cancelledNode->textContent) : '0';
    $rawDelivered = $deliveredNode ? trim($deliveredNode->textContent) : '0';

    if (strlen($rawOrders) > 5 || strlen($rawCancelled) > 5) {
        // Skip junk
        continue;
    }

    $orders = (int) $rawOrders;
    $cancelled = (int) $rawCancelled;
    $delivered = (int) $rawDelivered;

    if ($orders > 0 || $delivered > 0 || $cancelled > 0) {
        $courier_data[] = [
            'courier' => $courierName,
            'orders' => $orders,
            'delivered' => $delivered,
            'cancelled' => $cancelled
        ];
    }
}
echo json_encode($courier_data, JSON_PRETTY_PRINT);
?>