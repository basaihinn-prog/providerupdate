<?php

namespace App\Http\Controllers\Api\V1\Game;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BuffaloGameService;
use App\Services\SlotGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuffaloLegacyGameController extends Controller
{
    public function __construct(private SlotGameService $slotGameService)
    {
    }

    /**
     * Bootstrap a server-owned Buffalo slot session for the legacy Cocos client.
     */
    public function startInfo(Request $request): JsonResponse
    {
        $user = $this->resolvePlayer($request);

        if (! $user) {
            return response()->json(['status' => 'incorrect credential'], 401);
        }

        $payload = $this->slotGameService->legacyStartInfo(
            $user,
            $request->input('game'),
            [
                'domain' => $request->input('domain'),
                'client_seed' => $request->input('client_seed'),
                'legacy_uid' => $request->input('id'),
            ]
        );

        return response()->json(array_merge($payload, ['gameplayEnabled' => true]));
    }

    public function ping(Request $request): JsonResponse
    {
        if (! $this->resolvePlayer($request)) {
            return response()->json(['status' => 'incorrect credential'], 401);
        }

        return response()->json(['status' => 'ok', 'gameplayEnabled' => true]);
    }

    public function exit(Request $request): JsonResponse
    {
        if (! $this->resolvePlayer($request)) {
            return response()->json(['status' => 'incorrect credential'], 401);
        }

        // The legacy client sends no session token on exit. Keep the server session active so pending free spins survive relaunch.
        return response()->json(['status' => 'ok', 'sessionClosed' => false, 'gameplayEnabled' => true]);
    }

    public function bet(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|string|max:100',
            'passcode' => 'required|string',
            'game' => 'required|string|max:100',
            'domain' => 'required|string|max:255',
            'bet' => 'required|numeric|min:1',
            'request_id' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $user = $this->resolvePlayer($request);
        if (! $user) {
            return response()->json(['status' => 'incorrect credential'], 401);
        }

        try {
            $outcome = $this->slotGameService->legacySpin(
                $user,
                (float) $request->input('bet'),
                $request->input('game'),
                [
                    'domain' => $request->input('domain'),
                    'legacy_uid' => $request->input('id'),
                    'request_id' => $request->input('request_id'),
                    'client_seed' => $request->input('client_seed'),
                ]
            );

            return response()->json(array_merge([
                'status' => 'ok',
                'message' => 'Spin completed',
            ], $outcome));
        } catch (\RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 400);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('Buffalo authoritative spin failed', [
                'user_id' => $user->id,
                'request_id' => $request->input('request_id'),
                'error' => $exception->getMessage(),
                'payload' => $request->except(['passcode', 'token', 'api_key', 'secret_key']),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to process spin.',
            ], 500);
        }
    }

    private function resolvePlayer(Request $request): ?User
    {
        $request->validate([
            'id' => 'required|string|max:100',
            'passcode' => 'required|string|max:255',
        ]);

        $uid = (string) $request->input('id');
        $passcode = (string) $request->input('passcode');

        if (! BuffaloGameService::verifyToken($uid, $passcode)) {
            return null;
        }

        $userName = BuffaloGameService::extractUserNameFromUid($uid);

        return User::where('user_name', $userName)->first();
    }
}
