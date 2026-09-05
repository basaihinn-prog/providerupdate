<?php

namespace App\Http\Controllers\Api\ShootGame;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class UserController extends Controller
{
    public function updateBalance(Request $request): JsonResponse
    {
        $request->validate([
            'balance' => 'required|numeric|min:0',
            'game_balance' => 'required|numeric|min:0|max:100'
        ]);
        
        try {
            $user = auth()->user();
            
            // Update user's game balance (you might want to add this field to users table)
            $user->update([
                'balance' => $request->balance,
                'game_balance' => $request->game_balance,
                'updated_at' => now()
            ]);
            
            return response()->json([
                'status' => 'Request was successful.',
                'message' => 'Game balance updated successfully.',
                'data' => [
                    'user_id' => $user->id,
                    'balance' => $user->balance,
                    'game_balance' => $user->game_balance
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error occurred.',
                'message' => 'Failed to update game balance.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
