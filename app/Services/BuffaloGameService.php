<?php

namespace App\Services;

use App\Models\User;

class BuffaloGameService
{
    /**
     * Site configuration - Updated for Production
     */
    private const SITE_NAME = 'africanbuffalo.vip';
    private const SITE_PREFIX = 'gam';
    // No secret key needed - API provider uses UUID + token format

    /**
     * Generate UUID (32 characters) for Buffalo API
     */
    public static function generateUid(string $userName): string
    {
        // Generate a proper 32-character UUID format
        // Format: gam + 28 characters from username hash
        $hash = md5($userName . self::SITE_NAME);
        return self::SITE_PREFIX . substr($hash, 0, 28);
    }

    /**
     * Generate token (64 characters) for Buffalo API
     */
    public static function generateToken(string $uid): string
    {
        // Generate a 64-character token using SHA256
        return hash('sha256', $uid . self::SITE_NAME . time());
    }

    /**
     * Generate persistent token for user (stored in database)
     */
    public static function generatePersistentToken(User $user): string
    {
        // Generate a persistent token that doesn't change with time
        return hash('sha256', $user->user_name . self::SITE_NAME . $user->id);
    }

    /**
     * Generate complete Buffalo authentication data for a user
     */
    public static function generateBuffaloAuth(User $user): array
    {
        $uid = self::generateUid($user->user_name);
        $token = self::generatePersistentToken($user); // Use persistent token

        return [
            'uid' => $uid,
            'token' => $token,
        ];
    }

    /**
     * Extract user_name from Buffalo UID (32 characters)
     */
    public static function extractUserNameFromUid(string $uid): string
    {
        // Since we generate UUID based on username, we need to find the user by matching the generated UUID
        if (str_starts_with($uid, self::SITE_PREFIX)) {
            // Find user by matching the generated UUID
            $users = \App\Models\User::all();
            foreach ($users as $user) {
                $generatedUid = self::generateUid($user->user_name);
                if ($generatedUid === $uid) {
                    return $user->user_name;
                }
            }
        }
        
        // Fallback: if no match found, return as is
        return $uid;
    }

    /**
     * Verify token for Buffalo API (no secret key verification)
     */
    public static function verifyToken(string $uid, string $token): bool
    {
        // Find the user first
        $userName = self::extractUserNameFromUid($uid);
        $user = \App\Models\User::where('user_name', $userName)->first();
        
        if (!$user) {
            return false;
        }
        
        // Generate the expected token and compare
        $expectedToken = self::generatePersistentToken($user);
        return hash_equals($expectedToken, $token);
    }

    /**
     * Get site information
     */
    public static function getSiteInfo(): array
    {
        return [
            'site_name' => self::SITE_NAME,
            'site_prefix' => self::SITE_PREFIX,
        ];
    }

    /**
     * Build a launch URL for the locally hosted Buffalo Cocos build.
     *
     * The legacy Cocos build reads apiUrl from the query string so its requests reach providerupdate even when assets use another host.
     * Real-money play must remain disabled until staging tests pass.
     */
    public static function generateGameUrl(User $user, int $roomId = 1, string $lobbyUrl = ''): string
    {
        $baseUrl = trim((string) config('services.buffalo.game_url', ''));

        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Buffalo local game URL is not configured or is invalid.');
        }

        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        if ($scheme !== 'https' && ! (app()->environment(['local', 'testing']) && $scheme === 'http')) {
            throw new \RuntimeException('Buffalo game URL must use HTTPS outside local/testing environments.');
        }

        $auth = self::generateBuffaloAuth($user);
        $appUrl = (string) config('app.url', '');
        $domain = (string) parse_url($appUrl, PHP_URL_HOST);
        $finalLobbyUrl = $lobbyUrl !== '' ? $lobbyUrl : $appUrl;
        $apiUrl = trim((string) config('services.buffalo.api_url', ''));
        if ($apiUrl === '') {
            $apiUrl = rtrim($appUrl, '/') . '/api/';
        }
        if (! filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Buffalo API URL is not configured as a valid absolute URL.');
        }
        $apiUrl = rtrim($apiUrl, '/') . '/';
        $apiScheme = strtolower((string) parse_url($apiUrl, PHP_URL_SCHEME));
        if ($apiScheme !== 'https' && ! (app()->environment(['local', 'testing']) && $apiScheme === 'http')) {
            throw new \RuntimeException('Buffalo API URL must use HTTPS outside local/testing environments.');
        }

        $query = http_build_query([
            'gameId' => 23,
            'roomId' => $roomId,
            'lobbyUrl' => $finalLobbyUrl,
            'apiUrl' => $apiUrl,
            'id' => $auth['uid'],
            'passcode' => $auth['token'],
            'game' => (string) config('services.buffalo.game_code', 'Buffalo'),
            'domain' => $domain,
        ]);

        return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . $query;
    }
    

    /**
     * Get room configuration
     */
    public static function getRoomConfig(): array
    {
        return [
            1 => ['min_bet' => 50, 'name' => '50 အခန်း', 'level' => 'Low'],
            2 => ['min_bet' => 500, 'name' => '500 အခန်း', 'level' => 'Medium'],
            3 => ['min_bet' => 5000, 'name' => '5000 အခန်း', 'level' => 'High'],
            4 => ['min_bet' => 10000, 'name' => '10000 အခန်း', 'level' => 'VIP'],
        ];
    }

    /**
     * Get available rooms for user based on balance
     */
    public static function getAvailableRooms(User $user): array
    {
        $userBalance = $user->balance; // No cents conversion needed
        $rooms = self::getRoomConfig();
        $availableRooms = [];

        foreach ($rooms as $roomId => $config) {
            if ($userBalance >= $config['min_bet']) {
                $availableRooms[$roomId] = $config;
            }
        }

        return $availableRooms;
    }

}
