<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoolreSmsService
{
    private $apiUrl = 'https://api.moolre.com/open/sms/send';
    private $apiKey;
    private $senderId;

    public function __construct()
    {
        $this->apiKey = config('services.moolre.api_key');
        $this->senderId = 'PRODATAWLD';
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        Log::info('Attempting to send SMS', [
            'recipient' => $phoneNumber,
            'sender_id' => $this->senderId,
            'message_length' => strlen($message)
        ]);

        try {
            $response = Http::timeout(30)->withHeaders([
                'X-API-VASKEY' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'type' => 1,
                'senderid' => $this->senderId,
                'messages' => [
                    [
                        'recipient' => $phoneNumber,
                        'message' => $message
                    ]
                ]
            ]);

            $responseData = $response->json();
            $success = $response->successful() && isset($responseData['status']) && $responseData['status'] === 1;
            
            if ($success) {
                Log::info('SMS sent successfully', [
                    'recipient' => $phoneNumber,
                    'response_status' => $responseData['status'] ?? 'unknown',
                    'response_data' => $responseData
                ]);
            } else {
                Log::warning('SMS sending failed', [
                    'recipient' => $phoneNumber,
                    'http_status' => $response->status(),
                    'response_data' => $responseData
                ]);
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error('SMS sending failed with exception', [
                'recipient' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}