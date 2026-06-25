<?php
/**
 * Temporary Diagnostic Script to inspect Set-Cookie headers on login page
 * Delete immediately after use!
 */

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://127.0.0.1/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Host: erp.fstio.com",
    "X-Forwarded-Proto: https"
]);

$response = curl_exec($ch);
if ($response === false) {
    echo "Curl error: " . curl_error($ch);
} else {
    echo "<h3>Response Headers from /login:</h3><pre>";
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    echo htmlspecialchars($headers);
    echo "</pre>";
}
curl_close($ch);
