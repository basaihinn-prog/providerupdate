<?php

namespace App\Http\Controllers\Api\V1\Game;

use App\Http\Controllers\Controller;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PpSlotWalletController extends Controller
{
    public function balance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'UserID' => ['required', 'string', 'max:255'],
            'AppID' => ['required', 'string', 'max:255'],
            'AppSecret' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->error('Invalid request', 6001);
        }

        $data = $validator->validated();

        if (! $this->validCredentials($data['AppID'], $data['AppSecret'])) {
            return $this->error('Access denied', 6010);
        }

        $user = User::where('user_name', $data['UserID'])->first();

        if (! $user) {
            return $this->error('Player account does not exist', 6007);
        }

        if ((int) $user->status !== 1) {
            return $this->error('Player status is close', 6008);
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
        $validator = Validator::make($request->all(), [
            'UserID' => ['required', 'string', 'max:255'],
            'AppID' => ['required', 'string', 'max:255'],
            'AppSecret' => ['required', 'string', 'max:255'],
            'TransactionID' => ['required', 'string', 'max:128'],
            'Amount' => ['required', 'numeric'],
            'RoundID' => ['nullable', 'string', 'max:255'],
            'GameID' => ['nullable', 'string', 'max:255'],
            'ReqTime' => ['nullable'],
            'Reason' => ['nullable', 'string', 'in:bet,win,refund'],
            'IsEnd' => ['nullable', 'boolean'],
            'RealAmount' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return $this->error('Invalid request', 6001);
        }

        $data = $validator->validated();

        if (! $this->validCredentials($data['AppID'], $data['AppSecret'])) {
            return $this->error('Access denied', 6010);
        }

        if (! $this->validReasonAmount($data['Reason'] ?? null, (float) $data['Amount'])) {
            return $this->error('Invalid transaction amount for reason', 6001);
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
                    return ['error' => ['Player account does not exist', 6007]];
                }

                if ((int) $user->status !== 1) {
                    return ['error' => ['Player status is close', 6008]];
                }

                $existing = ProviderWalletTransaction::where('provider', $provider)
                    ->where('transaction_id', $data['TransactionID'])
                    ->first();

                if ($existing) {
                    return ['balance' => $existing->after_balance];
                }

                $beforeMinor = $this->toMinor($user->balance);
                $changeMinor = $this->toMinor($data['Amount']);
                $afterMinor = $beforeMinor + $changeMinor;

                if ($afterMinor < 0) {
                    return ['error' => ['Insufficient balance', 6006]];
                }

                $before = $this->fromMinor($beforeMinor);
                $amount = $this->fromMinor($changeMinor);
                $after = $this->fromMinor($afterMinor);
                $rawRequest = $data;
                unset($rawRequest['AppSecret']);

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
                    'raw_request' => $rawRequest,
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

            Log::error('PPSLOT wallet database error', [
                'transaction_id' => $data['TransactionID'],
                'user_id' => $data['UserID'],
                'exception' => $e->getMessage(),
            ]);

            return $this->error('Internal wallet error', 5000);
        } catch (Throwable $e) {
            Log::error('PPSLOT wallet transfer failed', [
                'transaction_id' => $data['TransactionID'],
                'user_id' => $data['UserID'],
                'exception' => $e->getMessage(),
            ]);

            return $this->error('Internal wallet error', 5000);
        }

        if (isset($result['error'])) {
            [$message, $code] = $result['error'];

            return $this->error($message, $code);
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

    private function validReasonAmount(?string $reason, float $amount): bool
    {
        return match ($reason) {
            'bet' => $amount <= 0,
            'win', 'refund' => $amount >= 0,
            default => true,
        };
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

    private function error(string $message, int $code)
    {
        return response()->json([
            'code' => $code,
            'error' => $message,
            'data' => null,
        ]);
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
