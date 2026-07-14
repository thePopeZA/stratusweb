<?php
// Security: Ensure this file only runs when included by your other scripts, not accessed directly
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    die("Direct access not permitted.");
}

require __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

function send_stratus_email($subject, $body_content) {
    // --- STRATUS NET MICROSOFT GRAPH CREDENTIALS ---
    $clientId     = '53a9eb71-c9ba-40e0-b3bd-c5e9af57884a';
    $tenantId     = 'c36c3c5a-93c5-4965-97c1-21b0f7065050';
    $clientSecret = getenv('AZURE_CLIENT_SECRET');

    try {
        $guzzle = new Client();

        // 1. Get Access Token from Microsoft Entra
        $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        $tokenResponse = $guzzle->post($tokenUrl, [
            'form_params' => [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ],
        ]);
        
        $tokenData = json_decode($tokenResponse->getBody()->getContents());
        $token = $tokenData->access_token;

        // 2. Prepare the Message Payload
        $messagePayload = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'Text',
                    'content' => $body_content
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => 'info@stratusnet.co.za'
                        ]
                    ]
                ],
            ],
        ];

        // 3. Send the Email via Graph API
        $apiUrl = 'https://graph.microsoft.com/v1.0/users/jurgen@stratusnet.co.za/sendMail';
        $guzzle->post($apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            ],
            'json' => $messagePayload
        ]);

    } catch (\Exception $e) {
        // Log the error silently on the server if it fails (prevents crashing the PayFast webhook or contact form)
        error_log("Email Engine Error: " . $e->getMessage());
    }
}
?>