<?php

namespace App\Services\Slot;

class ProvablyFairService
{
    public function createRng(?string $clientSeed, int $nonce, ?string $testSeed = null): CryptoRng
    {
        if ($testSeed !== null && $testSeed !== '') {
            $serverSeed = hash('sha256', 'server|' . $testSeed . '|' . $nonce);
            $resolvedClientSeed = $clientSeed ?: hash('sha256', 'client|' . $testSeed . '|' . $nonce);

            return new CryptoRng($resolvedClientSeed, $serverSeed, $nonce);
        }

        return new CryptoRng($clientSeed, null, $nonce);
    }

    public function exportProof(CryptoRng $rng): array
    {
        return [
            'server_seed_hash' => $rng->getServerSeedHash(),
            'server_seed' => $rng->getServerSeed(),
            'client_seed' => $rng->getClientSeed(),
            'nonce' => $rng->getNonce(),
        ];
    }
}
