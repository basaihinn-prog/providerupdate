<?php

namespace App\Http\Controllers\Api\V1\Game;

use App\Contracts\Games\GameEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FishEngineController extends Controller
{
    public function start(Request $request, GameEngine $engine)
    {
        $player = $request->user();
        if (! $player || (int) $player->type !== \App\Enums\UserType::Player->value) {
            return response()->json(['message' => 'Player authentication required.'], 403);
        }
        try {
            $state = $engine->start((int) $player->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
        $sessionId = (string) Str::uuid();
        DB::table('fish_engine_sessions')->insert([
            'player_id' => $player->id, 'game_key' => $engine->key(),
            'engine_version' => $engine->version(), 'status' => 'active',
            'sequence' => 0, 'simulation_state' => json_encode($state, JSON_THROW_ON_ERROR),
            'expires_at' => now()->addSeconds((int) config('fish_engine.session_ttl_seconds', 1800)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['session_id' => $sessionId, 'game_key' => $engine->key(),
            'engine_version' => $engine->version(), 'tick_ms' => config('fish_engine.tick_ms'),
            'state' => $this->publicState($state)], 201)->header('Cache-Control', 'no-store');
    }

    public function command(Request $request, string $sessionId, GameEngine $engine)
    {
        $data = $request->validate([
            'command_id' => ['required', 'string', 'max:100'],
            'sequence' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:fire'],
            'target_fish_id' => ['required', 'uuid'],
            'weapon' => ['required', 'in:1,2,3,4'],
            'bet_minor' => ['required', 'integer', 'min:1', 'max:' . (int) config('fish_engine.max_bet_minor', 100000000)],
        ]);
        try {
            $result = DB::transaction(function () use ($request, $sessionId, $engine, $data) {
                $session = DB::table('fish_engine_sessions')->where('player_id', $request->user()->id)
                    ->where('game_key', $engine->key())->where('status', 'active')
                    ->where('expires_at', '>', now())->where('id', $request->header('X-Engine-Session-Row'))
                    ->lockForUpdate()->first();
                if (!$session || !hash_equals((string)$session->id, $sessionId)) {
                    return ['status'=>404,'body'=>['message'=>'Active session not found.']];
                }
                $prior = DB::table('fish_engine_commands')->where('session_id',$session->id)->where('command_id',$data['command_id'])->first();
                if ($prior) return ['status'=>200,'body'=>json_decode($prior->response_payload,true)+['idempotent_replay'=>true]];
                if ((int)$data['sequence'] !== (int)$session->sequence+1) {
                    return ['status'=>409,'body'=>['message'=>'Out-of-order command.','expected_sequence'=>(int)$session->sequence+1]];
                }
                $state = json_decode($session->simulation_state,true) ?: [];
                $state = $engine->tick($state,(int)config('fish_engine.tick_ms',100));
                $resolved = $engine->command($state,(int)$request->user()->id,$data);
                $sequence=(int)$session->sequence+1;
                $body=['sequence'=>$sequence,'event'=>$resolved['event'],'state'=>$this->publicState($resolved['state']),
                    'bet_minor'=>$resolved['bet_minor'],'payout_minor'=>$resolved['payout_minor'],
                    'settlement_status'=>'not_settled'];
                DB::table('fish_engine_sessions')->where('id',$session->id)->update([
                    'sequence'=>$sequence,'simulation_state'=>json_encode($resolved['state'],JSON_THROW_ON_ERROR),
                    'last_command_at'=>now(),'updated_at'=>now()]);
                DB::table('fish_engine_commands')->insert([
                    'session_id'=>$session->id,'command_id'=>$data['command_id'],'command_type'=>$data['type'],
                    'sequence'=>$sequence,'request_payload'=>json_encode($data,JSON_THROW_ON_ERROR),
                    'response_payload'=>json_encode($body,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
                return ['status'=>200,'body'=>$body];
            },3);
            return response()->json($result['body'],$result['status'])->header('Cache-Control','no-store');
        } catch (InvalidArgumentException $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    private function publicState(array $state): array
    {
        unset($state['rng_commitment'],$state['rounds'],$state['player_id']);
        return $state;
    }
}
