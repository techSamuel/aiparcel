<?php
// Facebook Conversions API (CAPI) Helper

function sendFacebookCAPIEvent($eventName, $userData, $customData = [], $eventId = null)
{
    if (!defined('FACEBOOK_ACCESS_TOKEN') || FACEBOOK_ACCESS_TOKEN === 'YOUR_ACCESS_TOKEN_HERE' || !defined('FACEBOOK_PIXEL_ID')) {
        return ['status' => 'skipped', 'message' => 'CAPI Not Configured'];
    }

    $pixelId = FACEBOOK_PIXEL_ID;
    $accessToken = FACEBOOK_ACCESS_TOKEN;

    // Normalize User Data (Hash PII)
    $normalizedUserData = [];

    if (!empty($userData['em'])) {
        $normalizedUserData['em'] = hash('sha256', strtolower(trim($userData['em'])));
    }
    if (!empty($userData['ph'])) {
        $normalizedUserData['ph'] = hash('sha256', trim($userData['ph'])); // Ensure phone includes country code before calling
    }
    if (!empty($userData['fn'])) {
        $normalizedUserData['fn'] = hash('sha256', strtolower(trim($userData['fn'])));
    }
    // Add Client IP and User Agent if available (Required for high match quality)
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $normalizedUserData['client_ip_address'] = $_SERVER['REMOTE_ADDR'];
    }
    if (!empty($_SERVER['HTTP_USER_AGENT'])) {
        $normalizedUserData['client_user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    }

    // Event Payload
    $data = [
        'data' => [
            [
                'event_name' => $eventName,
                'event_time' => time(),
                'action_source' => 'website',
                'user_data' => $normalizedUserData,
                'custom_data' => (object) $customData,
            ]
        ],
        // 'test_event_code' => 'TEST62030', // Uncomment for testing in Events Manager
    ];

    // Add Event ID for Deduplication if provided
    if ($eventId) {
        $data['data'][0]['event_id'] = $eventId;
    }
    // Add Event Source URL
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $data['data'][0]['event_source_url'] = $_SERVER['HTTP_REFERER'];
    } else {
        $data['data'][0]['event_source_url'] = APP_URL;
    }

    // Send Request
    $url = "https://graph.facebook.com/v19.0/{$pixelId}/events?access_token={$accessToken}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['status' => 'success', 'response' => json_decode($response, true)];
    } else {
        return ['status' => 'error', 'code' => $httpCode, 'message' => $response, 'curl_error' => $error];
    }
}
