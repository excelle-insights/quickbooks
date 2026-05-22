<?php

namespace ExcelleInsights\QuickBooks\Auth;

use ExcelleInsights\QuickBooks\Repositories\TokenRepository;

class Authentication
{
    public function __construct(
        private TokenRepository $tokens,
        private string $app,
        private string $userId
    ) {}

    public function getAuthUrl(): string
    {
        $clientId = $_ENV['QBO_CLIENT_ID'] ?? '';
        $redirectUri = $_ENV['QBO_REDIRECT_URI'] ?? '';
        $scope = 'com.intuit.quickbooks.accounting';

        return "https://appcenter.intuit.com/connect/oauth2?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope={$scope}&state=state123";
    }

    public function accessToken(): string
    {
        $record = $this->tokens->getLatest($this->app, $this->userId);

        if (!$record) {
            throw new \RuntimeException('No access token found');
        }

        $token = json_decode($record->access_token, true);
        $updatedAt = strtotime($record->updated_at);

        // Add 3500 seconds buffer to ensure we refresh before actual expiration
        $refreshBuffer = 3500;
        $deadline = $updatedAt + $refreshBuffer;
        $currentTime = time();

        $token_age = time() - $updatedAt;
        // Token still valid
        $formatDeadline = date('Y-m-d H:i:s', $deadline);
        $formatCurrentTime = date('Y-m-d H:i:s', $currentTime);

        // if ($currentTime < $deadline) {
        //     error_log("Access token not expired. Age is $token_age. Current time is $formatCurrentTime, deadline is $formatDeadline");
        //     return $token['access_token'];
        // } else {
        //     error_log("Access token expired. Age is $token_age.  Current time is $formatCurrentTime, deadline is $formatDeadline");
        // }

        // Refresh
        $newToken = $this->refreshToken($token['refresh_token']);

        $payload = [
            'access_token'  => $newToken['access_token'],
            'refresh_token' => $newToken['refresh_token'],
            'realmId'       => $token['realmId'],
        ];

        $this->tokens->save($this->app, $this->userId, $payload);

        return $payload['access_token'];
    }

    private function refreshToken(string $refreshToken): array
    {
        $url = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';

        $postData = http_build_query([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode(
                    $_ENV['QBO_CLIENT_ID'] . ':' . $_ENV['QBO_CLIENT_SECRET']
                ),
            ],
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new \RuntimeException(curl_error($ch));
        }

        return json_decode($response, true);
    }

    public function exchangeAuthorizationCode(string $code, string $realmId): void
    {
        // Call QuickBooks token endpoint
        $url = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';

        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $_ENV['QBO_REDIRECT_URI'] ?? '',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode(
                    $_ENV['QBO_CLIENT_ID'] . ':' . $_ENV['QBO_CLIENT_SECRET']
                ),
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new \RuntimeException(curl_error($ch));
        }
        curl_close($ch);

        $token = json_decode($response, true);

        // Store in DB
        $this->tokens->save('quickbooks', 'quickbooks', $token);
    }
}
