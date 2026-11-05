<?php
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Hàm gửi request và lấy headers
function fetchWithHeaders($url, $method = 'GET', $headers = [], $postData = null) {
    $ch = curl_init($url);
    $responseHeaders = [];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) return $len;
        $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
        return $len;
    });

    if ($postData) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $headers[] = 'Content-Type: application/json';
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'headers' => $responseHeaders
    ];
}

if ($action === 'check_gemini') {
    $data = json_decode(file_get_contents('php://input'), true);
    $apiKey = $data['api_key'] ?? '';
    
    if (empty($apiKey)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng cung cấp API Key.']);
        exit;
    }
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($apiKey);
    $res = fetchWithHeaders($url);
    
    if ($res['code'] === 200) {
        echo json_encode(['status' => 'success', 'data' => $res['body']['models'] ?? []]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $res['body']['error']['message'] ?? 'Lỗi Gemini API']);
    }
    exit;
}

if ($action === 'test_model_gemini') {
    $data = json_decode(file_get_contents('php://input'), true);
    $apiKey = $data['api_key'] ?? '';
    $model = $data['model'] ?? 'gemini-2.5-flash';
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . urlencode($apiKey);
    $payload = json_encode(["contents" => [["parts" => [["text" => "hi"]]]]]);
    
    $res = fetchWithHeaders($url, 'POST', [], $payload);
    
    // Trích xuất thông tin Rate Limit từ Headers
    $quota = [
        'remaining_requests' => $res['headers']['x-ratelimit-remaining-requests'] ?? 'N/A',
        'limit_requests' => $res['headers']['x-ratelimit-limit-requests'] ?? 'N/A',
        'reset_requests' => $res['headers']['x-ratelimit-reset-requests'] ?? 'N/A',
        'remaining_tokens' => $res['headers']['x-ratelimit-remaining-tokens'] ?? 'N/A',
        'limit_tokens' => $res['headers']['x-ratelimit-limit-tokens'] ?? 'N/A',
        'reset_tokens' => $res['headers']['x-ratelimit-reset-tokens'] ?? 'N/A',
    ];

    if ($res['code'] === 200) {
        echo json_encode(['status' => 'success', 'quota' => $quota, 'latency' => 'OK']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $res['body']['error']['message'] ?? 'Lỗi Test']);
    }
    exit;
}

if ($action === 'check_claude') {
    $data = json_decode(file_get_contents('php://input'), true);
    $apiKey = $data['api_key'] ?? '';
    
    $url = "https://api.anthropic.com/v1/models";
    $headers = ["x-api-key: $apiKey", "anthropic-version: 2023-06-01"];
    $res = fetchWithHeaders($url, 'GET', $headers);
    
    if ($res['code'] === 200) {
        echo json_encode(['status' => 'success', 'data' => $res['body']['data'] ?? []]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $res['body']['error']['message'] ?? 'Lỗi Anthropic API']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
