<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends login OTPs through SMSGatewayHub (SMS or WhatsApp).
 * Credentials come from config/services.php ('smsgatewayhub'), i.e. .env.
 */
class MessageService
{
    /**
     * Sends a login OTP. Returns true when the gateway accepted the message.
     */
    public static function sendOtpMessage($mobile_no, $otp, $channel = 'sms'): bool
    {
        if ($channel === 'sms') {
            return self::sendSmsOtp($mobile_no, $otp);
        }

        if ($channel === 'whatsapp') {
            return self::sendWhatsAppOtp($mobile_no, $otp);
        }

        Log::warning('Invalid OTP channel requested', ['channel' => $channel]);

        return false;
    }

    private static function sendWhatsAppOtp($mobile_no, $otp): bool
    {
        try {
            $body = [
                'to' => '91' . $mobile_no,
                'recipient_type' => 'individual',
                'type' => 'template',
                'template' => [
                    'language' => [
                        'policy' => 'deterministic',
                        'code' => 'en',
                    ],
                    'name' => config('services.smsgatewayhub.whatsapp_otp_template'),
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [['type' => 'text', 'text' => $otp]],
                        ],
                        [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => 0,
                            'parameters' => [['type' => 'text', 'text' => $otp]],
                        ],
                    ],
                ],
            ];

            $response = Http::withToken(config('services.smsgatewayhub.whatsapp_api_key'))
                ->post(config('services.smsgatewayhub.whatsapp_url'), $body);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Error sending OTP WhatsApp message: ' . $e->getMessage());

            return false;
        }
    }

    private static function sendSmsOtp($mobile_no, $otp): bool
    {
        try {
            // Must match the DLT-registered template exactly; $otp fills both {#var#} placeholders.
            $message = "Your Saathealth WebApp verification code is: {$otp}. Please enter the OTP to proceed. Thank you, Team Saathealth @saathealth.com #{$otp}";

            $response = Http::get(config('services.smsgatewayhub.sms_url'), [
                'APIKey' => config('services.smsgatewayhub.sms_api_key'),
                'senderid' => config('services.smsgatewayhub.sender_id'),
                'channel' => '2',
                'DCS' => '0',
                'flashsms' => '0',
                'number' => '91' . $mobile_no,
                'text' => $message,
                'route' => '1',
            ]);

            // Never log the request URL: it carries the API key as a query parameter.
            Log::info('SMS OTP API Response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // SMSGatewayHub returns ErrorCode "000" on success.
            $result = $response->json();

            return isset($result['ErrorCode']) && $result['ErrorCode'] === '000';
        } catch (\Exception $e) {
            Log::error('Error sending OTP SMS: ' . $e->getMessage());

            return false;
        }
    }
}
