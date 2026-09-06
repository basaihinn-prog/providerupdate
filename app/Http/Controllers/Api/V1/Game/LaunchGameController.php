<?php

namespace App\Http\Controllers\Api\V1\Game;

use App\Http\Controllers\Controller;
use App\Models\GameList;
use App\Services\GameProviders\ProviderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class LaunchGameController extends Controller
{
    public function __construct(private readonly ProviderManager $providers) {}

    public function launchGame(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'code' => 401,
                'message' => 'Authentication required.',
            ], 401);
        }

        try {
            $data = $request->validate([
                'game_id' => ['nullable', 'integer'],
                'game_code' => ['required_without:game_id', 'string', 'max:255'],
                'product_code' => ['nullable'],
                'product_id' => ['nullable', 'integer'],
                'provider' => ['nullable', 'string', 'max:100'],
                'game_type' => ['nullable', 'string', 'max:100'],
                'language' => ['nullable', 'string', 'max:10'],
                'platform' => ['nullable', 'in:mobile,desktop'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $query = GameList::query()->with('product')->where('is_active', 1);

        if (! empty($data['game_id'])) {
            $query->whereKey($data['game_id']);
        } else {
            $query->where('game_code', $data['game_code']);

            if (! empty($data['product_id'])) {
                $query->where('product_id', $data['product_id']);
            } elseif (isset($data['product_code']) && $data['product_code'] !== '') {
                $query->where('product_code', $data['product_code']);
            }

            if (! empty($data['provider'])) {
                $query->where(function ($providerQuery) use ($data) {
                    $providerQuery->where('provider', $data['provider'])
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('provider', $data['provider']));
                });
            }
        }

        $matches = $query->limit(2)->get();

        if ($matches->isEmpty()) {
            return response()->json([
                'code' => 404,
                'message' => 'Game not found or inactive.',
            ], 404);
        }

        if ($matches->count() > 1) {
            return response()->json([
                'code' => 409,
                'message' => 'Game identifier is ambiguous. Send game_id, product_id, product_code, or provider.',
            ], 409);
        }

        $game = $matches->first();

        try {
            $driver = $this->providers->forGame($game);
            $result = $driver->launch($user, $game, [
                'language' => $data['language'] ?? null,
                'platform' => $data['platform'] ?? 'mobile',
            ]);

            Log::info('Game launched through provider adapter', [
                'user_id' => $user->id,
                'game_id' => $game->id,
                'game_code' => $game->game_code,
                'provider' => $game->product?->provider ?: $game->provider,
            ]);

            return response()->json([
                'code' => 200,
                'message' => 'Game launched successfully.',
                'url' => $result['url'] ?? null,
                'content' => $result['content'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('Game provider launch failed', [
                'user_id' => $user->id,
                'game_id' => $game->id,
                'game_code' => $game->game_code,
                'provider' => $game->product?->provider ?: $game->provider,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 502,
                'message' => 'Game provider launch failed.',
            ], 502);
        }
    }
}
