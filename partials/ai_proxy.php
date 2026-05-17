<?php
// Secure PHP proxy for Gemini AI API
require_once '../database/db.php';
startSession();

// Set JSON headers
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Receive the JSON post data
$input = json_decode(file_get_contents('php://input'), true);
$query = isset($input['query']) ? trim($input['query']) : '';

if (empty($query)) {
    echo json_encode(['success' => false, 'error' => 'Empty query']);
    exit;
}

// =========================================================================
// API CONFIGURATION
// Paste your free Gemini API Key from Google AI Studio here:
// =========================================================================
$apiKey = 'AIzaSyYourNewFreeAPIKeyFromGoogleAIStudio';

if (empty($apiKey) || $apiKey === 'AIzaSyYourNewFreeAPIKeyFromGoogleAIStudio' || strpos($apiKey, 'YourNewFreeAPIKey') !== false) {
    echo json_encode([
        'success' => true,
        'use_fallback' => true
    ]);
    exit;
}

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

$payload = [
    'contents' => [
        [
            'parts' => [
                [
                    'text' => "You are 'CCS AI Recommender', a specialized programming language and AI coding tool recommender for students in the College of Computer Studies. The student is asking: '" . $query . "'. Provide a detailed recommendation of which AI models (Gemini, Claude, GPT, Copilot, etc.) are best for the programming language or task they mentioned, why, and recommend 2-3 specific websites to learn it. Keep the tone friendly, helpful, and encourage them in their lab work. Format your response beautifully with HTML tags (like <strong>, <br>, <li>, etc.) so it displays cleanly in our chat bubble. Do not use markdown like asterisks or hashtags, use standard simple HTML tags only."
                ]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode([
        'success' => true,
        'use_fallback' => true // Fallback on curl error
    ]);
    curl_close($ch);
    exit;
}

curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode([
        'success' => true,
        'use_fallback' => true // Fallback on API server error
    ]);
    exit;
}

$resData = json_decode($response, true);
$text = isset($resData['candidates'][0]['content']['parts'][0]['text'])
    ? $resData['candidates'][0]['content']['parts'][0]['text']
    : '';

if (empty($text)) {
    echo json_encode([
        'success' => true,
        'use_fallback' => true
    ]);
    exit;
}

// Convert any accidental markdown formatting to clean inline line breaks
$cleanedText = str_replace(["\r\n", "\r", "\n"], "<br>", $text);

echo json_encode([
    'success' => true,
    'use_fallback' => false,
    'response' => $cleanedText
]);
