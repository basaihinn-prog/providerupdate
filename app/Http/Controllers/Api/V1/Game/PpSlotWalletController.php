<?php

namespace App\Http\Controllers\Api\V1\Game;

use App\Http\Controllers\Controller;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PpSlotWalletController extends Controller
{
    public function balance(Request $request)
    {
        $data = $request->validate([
            'UserID' => ['required', 'string', 'max:255'],
            'AppID' => ['required', 'string', 'max:255'],
            'AppSecret' => ['required', 'string', 'max:255'],
        ]);

        if (! $this->validCredentials($data['AppID'], $data['AppSecret'])) {
            return $this->error('Access denied', 6010, 401);
        }

        $user = User::where('user_name', $data['UserID'])->first();

        if (! $user) {
            return $this->error('Player account does not exist', 6007, 404);
        }

        if ((int) $user->status !== 1) {
            return $this->error('Player status is close', 6008, 403);
        }

        return response()->json([
            'code' => 0,
            'error' => '',
            'data' => [
                'Balance' => (float) number_format((float) $user->balance, 2, '.', ''),
            ],
        ]);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'UserID' => ['required', 'string', 'max:255'],
            'AppID' => ['required', 'string', 'max:255'],
            'AppSecret' => ['required', 'string', 'max:255'],
            'TransactionID' => ['required', 'string', 'max:191'],
            'Amount' => ['required', 'numeric'],
            'RoundID' => ['nullable', 'string', 'max:255'],
            'GameID' => ['nullable', 'string', 'max:255'],
            'ReqTime' => ['nullable'],
            'Reason' => ['nullable', 'string', 'max:255'],
            'IsEnd' => ['nullable', 'boolean'],
            'RealAmount' => ['nullable', 'numeric'],
        ]);

        if (! $this->validCredentials($data['AppID'], $data['AppSecret'])) {
            return $this->error('Access denied', 6010, 401);
        }

        $provider = 'ppslot';

        $existing = ProviderWalletTransaction::where('provider', $provider)
            ->where('transaction_id', $data['TransactionID'])
            ->first();

        if ($existing) {
            return $this->successTransfer($existing->after_balance, $data['RealAmount'] ?? $data['Amount']);
        }

        try {
            $result = DB::transaction(function () use ($data, $provider) {
                $user = User::where('user_name', $data['UserID'])->lockForUpdate()->first();

                if (! $user) {
                    return ['error' => ['Player account does not exist', 6007, 404]];
                }

                if ((int) $user->status !== 1) {
                    return ['error' => ['Player status is close', 6008, 403]];
                }

                $existing = ProviderWalletTransaction::where('provider', $provider)
                    ->where('transaction_id', $data['TransactionID'])
                    ->first();

                if ($existing) {
                    return ['balance' => $existing->after_balance];
                }

                $beforeCents = $this->toMinor($user->balance);
                $changeCents = $this->toMinor($data['Amount']);
                $afterCents = $beforeCents + $changeCents;

                if ($afterCents < 0) {
                    return ['error' => ['Insufficient balance', 6006, 409]];
                }

                $before = $this->fromMinor($beforeCents);
                $amount = $this->fromMinor($changeCents);
                $after = $this->fromMinor($afterCents);

                $user->balance = $after;
                $user->save();

                ProviderWalletTransaction::create([
                    'provider' => $provider,
                    'transaction_id' => $data['TransactionID'],
                    'user_id' => $user->id,
                    'game_id' => $data['GameID'] ?? null,
                    'round_id' => $data['RoundID'] ?? null,
                    'amount' => $amount,
                    'before_balance' => $before,
                    'after_balance' => $after,
                    'reason' => $data['Reason'] ?? null,
                    'is_end' => (bool) ($data['IsEnd'] ?? false),
                    'raw_request' => $data,
                ]);

                return ['balance' => $after];
            }, 3);
        } catch (QueryException $e) {
            $existing = ProviderWalletTransaction::where('provider', $provider)
                ->where('transaction_id', $data['TransactionID'])
                ->first();

            if ($existing) {
                return $this->successTransfer($existing->after_balance, $data['RealAmount'] ?? $data['Amount']);
            }

            throw $e;
        } catch (Throwable $e) {
            Log::error('PPSLOT wallet transfer failed', [
                'transaction_id' => $data['TransactionID'],
                'user_id' => $data['UserID'],
                'exception' => $e->getMessage(),
            ]);

            return $this->error('Internal wallet error', 5000, 500);
        }

        if (isset($result['error'])) {
            [$message, $code, $status] = $result['error'];

            return $this->error($message, $code, $status);
        }

        return $this->successTransfer($result['balance'], $data['RealAmount'] ?? $data['Amount']);
    }

    private function validCredentials(string $appId, string $appSecret): bool
    {
        $expectedId = (string) config('game_providers.drivers.ppslot.app_id');
        $expectedSecret = (string) config('game_providers.drivers.ppslot.app_secret');

        return $expectedId !== ''
            && $expectedSecret !== ''
            && hash_equals($expectedId, $appId)
            && hash_equals($expectedSecret, $appSecret);
    }

    private function successTransfer($balance, $realAmount)
    {
        return response()->json([
            'code' => 0,
            'error' => '',
            'data' => [
                'Balance' => (float) number_format((float) $balance, 2, '.', ''),
                'RealAmount' => (float) number_format((float) $realAmount, 2, '.', ''),
            ],
        ]);
    }

    private function error(string $message, int $code, int $httpStatus = 400)
    {
        return response()->json([
            'code' => $code,
            'error' => $message,
            'data' => null,
        ], $httpStatus);
    }

    private function toMinor($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function fromMinor(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
