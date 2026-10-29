<?php

namespace App\Services\Slot;

class CryptoRng
{
    private string $serverSeed;
    private string $clientSeed;
    private int $nonce;
    private int $cursor = 0;

    public function __construct(?string $clientSeed = null, ?string $serverSeed = null, int $nonce = 0)
    {
        $this->clientSeed = $clientSeed ?: bin2hex(random_bytes(8));
        $this->serverSeed = $serverSeed ?: bin2hex(random_bytes(32));
        $this->nonce = $nonce;
    }

    public function int(int $min, int $max): int
    {
        if ($max < $min) {
            throw new \InvalidArgumentException('Invalid RNG bounds.');
        }

        $range = $max - $min + 1;
        $value = $this->nextUInt64();

        return $min + (int) ($value % $range);
    }

    public function weightedIndex(array $weightedItems): int
    {
        $totalWeight = 0;

        foreach ($weightedItems as $item) {
            $totalWeight += (int) ($item['weight'] ?? 0);
        }

        if ($totalWeight <= 0) {
            throw new \InvalidArgumentException('Weighted RNG requires a positive total weight.');
        }

        $cursor = $this->int(1, $totalWeight);

        foreach ($weightedItems as $index => $item) {
            $cursor -= (int) ($item['weight'] ?? 0);

            if ($cursor <= 0) {
                return $index;
            }
        }

        return array_key_last($weightedItems);
    }

    public function getServerSeed(): string
    {
        return $this->serverSeed;
    }

    public function getServerSeedHash(): string
    {
        return hash('sha256', $this->serverSeed);
    }

    public function getClientSeed(): string
    {
        return $this->clientSeed;
    }

    public function getNonce(): int
    {
        return $this->nonce;
    }

    private function nextUInt64(): int
    {
        $bytes = hash_hmac(
            'sha256',
            sprintf('%d:%d', $this->nonce, $this->cursor),
            $this->serverSeed . '|' . $this->clientSeed,
            true
        );

        $this->cursor++;
        $parts = unpack('Nvalue', substr($bytes, 0, 4));

        return (int) $parts['value'];
    }
}
