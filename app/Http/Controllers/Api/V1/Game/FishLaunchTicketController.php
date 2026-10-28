<?php

namespace App\Http\Controllers\Api\V1\Game;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FishLaunchTicketController extends Controller
{
    public function issue(Request $request)
    {
        $config = config('fish_session');
        $secret = (string) ($config['ticket_secret'] ?? '');
        $shopId = (string) ($config['shop_id'] ?? '');
        $game = (string) ($config['game'] ?? '');

        if (strlen($secret) < 32 || $shopId === '' || $game === '') {
            return response()->json([
                'code' => 503,
                'message' => 'Fish session integration is not configured.',
            ], 503)->header('Cache-Control', 'no-store');
        }

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'code' => 401,
                'message' => 'Authentication required.',
            ], 401)->header('Cache-Control', 'no-store');
        }

        $now = now()->timestamp;
        $claims = [
            'iss' => (string) ($config['issuer'] ?? 'laravel-backend'),
            'aud' => (string) ($config['audience'] ?? 'fish1-session-service'),
            'sub' => (string) $user->getAuthIdentifier(),
            'shop_id' => $shopId,
            'game' => $game,
            'jti' => (string) Str::uuid(),
            'iat' => $now,
            'nbf' => $now - 1,
            'exp' => $now + max(60, min((int) ($config['ticket_ttl_seconds'] ?? 90), 120)),
        ];

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        ];
        $signingInput = implode('.', $segments);
        $segments[] = $this->base64UrlEncode(hash_hmac('sha256', $signingInput, $secret, true));
        $ticket = implode('.', $segments);

        return response()->json([
            'code' => 200,
            'message' => 'Fish launch ticket issued.',
            'launch_ticket' => $ticket,
            'expires_at' => gmdate(DATE_ATOM, $claims['exp']),
        ])->header('Cache-Control', 'no-store');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
