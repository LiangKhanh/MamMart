<?php
declare(strict_types=1);

namespace MamMart\Marketing;

/** Local, synthetic data only. Never reads or writes WordPress/Woo tables. */
final class FakeRepository implements Repository
{
    private string $directory;
    private string $path;
    private array $seed;

    public function __construct(string $directory, array $seed)
    {
        $this->directory = $directory;
        $this->path = $directory . '/state.json';
        $this->seed = $seed;
        if (!is_dir($directory)) {
            // Another worker can create this directory after the existence check.
            // Suppress that expected mkdir warning, then verify the actual outcome.
            $created = @mkdir($directory, 0700, true);
            clearstatcache(true, $directory);
            if (!$created && !is_dir($directory)) {
                throw new \RuntimeException('Cannot create sandbox storage.');
            }
        }
    }

    public function transaction(callable $operation): array
    {
        $lock = fopen($this->directory . '/state.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Cannot lock sandbox state.');
        }
        $temporary = null;
        try {
            $state = is_file($this->path)
                ? json_decode((string) file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR)
                : $this->seed;
            $result = $operation($state);
            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $temporary = tempnam($this->directory, 'write-');
            if (!$temporary || file_put_contents($temporary, $json) === false || !rename($temporary, $this->path)) {
                throw new \RuntimeException('Cannot persist sandbox state.');
            }
            return $result;
        } finally {
            if ($temporary && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function snapshot(): array
    {
        return $this->transaction(static function (array &$state): array { return $state; });
    }
}
