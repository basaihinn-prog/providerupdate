<?php

namespace App\Http\Controllers\Api\V1\Game;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BuffaloGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuffaloLegacyGameController extends Controller
{
    /**
     * Read-only compatibility endpoint for the legacy Cocos client.
     * This deliberately does not create a gameplay session: the authoritative
     * slot engine/session adapter has not yet been integrated into providerupdate.
     */
    public function startInfo(Request $request): JsonResponse
    {
        $user = $this->resolvePlayer($request);

        if (! $user) {
            return response()->json(['status' => 'incorrect credential'], 401);
        }

        return response()->json([
            'status' => 'ok',
            'gameCode' => (string) $request->input('game', config('services.buffalo.game_code', 'Buffalo')),
            'balance' => (float) $user->balance,
            'bet' => 0,
            'freeSpinCount' => 0,
            'freeSpinTotal' => 0,
            'freeSpinStatus' => '',
            'freeSpinWinCount' => 0,
            'freeSpinMultiply' => 1,
            'gameplayEnabled' => false,
            'message' => 'Read-only launch check only; gameplay session adapter is not configured.',
        ]);
    }

    public function ping(Request $request): JsonResponse
    {
        if (! $this->resolvePlayer($request)) {
            return response()->json(['status' => 'incorrect credential'], 401);
        }

        return response()->json(['status' => 'ok', 'gameplayEnabled' => false]);
    }

    public function exit(Request $request): JsonResponse
    {
        if (! $this->resolvePlayer($request)) {
            return response()->json(['status' => 'incorrect credential'], 401);
        }

        // No server-owned game session exists yet, so there is nothing to settle or close.
        return response()->json(['status' => 'ok', 'sessionClosed' => false]);
    }

    public function bet(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|string|max:100',
            'passcode' => 'required|string',
            'game' => 'required|string|max:100',
            'domain' => 'required|string|max:255',
            'bet' => 'required|numeric|min:1',
        ]);

        if (! $this->resolvePlayer($request)) {
            return response()->json(['status' => 'incorrect credential'], 401);
        }

        // Fail closed. Do not mutate balances until a server-owned game session,
        // idempotency key, and atomic bet+win settlement implementation are present.
        return response()->json([
            'status' => 'betting_disabled',
            'message' => 'Buffalo gameplay settlement is not enabled on this backend.',
        ], 503);
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
