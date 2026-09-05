<?php

namespace App\Http\Controllers\Api\ShootGame;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\GameLog;
use App\Models\GameStats;

class PythonGameController extends Controller
{
     /**
     * Store game session log
     * 
     * Expected payload:
     * {
     *     "user_id": 5,
     *     "game_type": "balance_shooter",
     *     "score": 1500,
     *     "kills": 15,
     *     "hits_taken": 3,
     *     "final_balance": 45.5,
     *     "game_duration": 120.5,
     *     "game_result": "game_over",
     *     "timestamp": "2025-01-XX XX:XX:XX"
     * }
     */
    public function storeGameLog(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'game_type' => 'required|string|max:50',
            'score' => 'required|integer|min:0',
            'kills' => 'required|integer|min:0',
            'hits_taken' => 'required|integer|min:0',
            'final_balance' => 'required|numeric|min:0|max:100',
            'game_duration' => 'required|numeric|min:0',
            'game_result' => 'required|string|in:game_over,victory,quit',
            'timestamp' => 'required|date'
        ]);
        
        try {
            $gameLog = GameLog::create([
                'user_id' => $request->user_id,
                'game_type' => $request->game_type,
                'score' => $request->score,
                'kills' => $request->kills,
                'hits_taken' => $request->hits_taken,
                'final_balance' => $request->final_balance,
                'game_duration' => $request->game_duration,
                'game_result' => $request->game_result,
                'timestamp' => $request->timestamp,
                'created_at' => now()
            ]);
            
            return response()->json([
                'status' => 'Request was successful.',
                'message' => 'Game log stored successfully.',
                'data' => [
                    'log_id' => $gameLog->id,
                    'user_id' => $gameLog->user_id,
                    'score' => $gameLog->score
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error occurred.',
                'message' => 'Failed to store game log.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update player game statistics
     * 
     * Expected payload:
     * {
     *     "user_id": 5,
     *     "total_score": 5000,
     *     "total_kills": 100,
     *     "games_played": 10,
     *     "best_score": 1500,
     *     "average_score": 500
     * }
     */
    public function updateGameStats(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'total_score' => 'required|integer|min:0',
            'total_kills' => 'required|integer|min:0',
            'games_played' => 'required|integer|min:0',
            'best_score' => 'required|integer|min:0',
            'average_score' => 'required|numeric|min:0'
        ]);
        
        try {
            $gameStats = GameStats::updateOrCreate(
                ['user_id' => $request->user_id],
                [
                    'total_score' => $request->total_score,
                    'total_kills' => $request->total_kills,
                    'games_played' => $request->games_played,
                    'best_score' => $request->best_score,
                    'average_score' => $request->average_score,
                    'updated_at' => now()
                ]
            );
            
            return response()->json([
                'status' => 'Request was successful.',
                'message' => 'Game stats updated successfully.',
                'data' => [
                    'user_id' => $gameStats->user_id,
                    'total_score' => $gameStats->total_score,
                    'games_played' => $gameStats->games_played
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error occurred.',
                'message' => 'Failed to update game stats.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
