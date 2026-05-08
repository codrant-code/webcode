<?php

$SUPABASE_URL = "https://nwldvgafmyaagmyezena.supabase.co";
$SUPABASE_KEY = "sb_publishable_gWMY1sQRn3fqip0JfAQPRQ_F79rlYyZ";

function supabase($method, $endpoint, $data = null) {
    global $SUPABASE_URL, $SUPABASE_KEY;

    $url = $SUPABASE_URL . "/rest/v1/" . $endpoint;

    $ch = curl_init($url);

    $headers = [
        "Content-Type: application/json",
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Prefer: return=representation"
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return ["error" => curl_error($ch)];
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        "status" => $status,
        "data" => json_decode($response, true),
        "raw" => $response
    ];
}