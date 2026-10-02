<?php

namespace App\Services\Import;

use Illuminate\Database\Eloquent\Model;

/** Acumula estatísticas e avisos de uma execução da importação. */
final class ImportReport
{
    private const COLUMNS = ['created', 'updated', 'unchanged', 'skipped'];

    /** @var array<string, array<string, int>> */
    private array $stats = [];

    /** @var list<string> */
    private array $warnings = [];

    /** Registra o resultado de um upsert verificando o estado do model após o save. */
    public function track(string $entity, Model $model): void
    {
        $this->increment($entity, match (true) {
            $model->wasRecentlyCreated => 'created',
            $model->wasChanged() => 'updated',
            default => 'unchanged',
        });
    }

    public function skipped(string $entity, string $reason): void
    {
        $this->increment($entity, 'skipped');
        $this->warning("[{$entity}] {$reason}");
    }

    public function warning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function count(string $entity, string $column): int
    {
        return $this->stats[$entity][$column] ?? 0;
    }

    /** @return list<array{0: string, 1: int, 2: int, 3: int, 4: int}> */
    public function rows(): array
    {
        return array_map(
            fn (string $entity, array $s) => [$entity, ...array_map(fn ($c) => $s[$c], self::COLUMNS)],
            array_keys($this->stats),
            $this->stats,
        );
    }

    public function toArray(): array
    {
        return ['stats' => $this->stats, 'warnings' => $this->warnings];
    }

    private function increment(string $entity, string $column): void
    {
        $this->stats[$entity] ??= array_fill_keys(self::COLUMNS, 0);
        $this->stats[$entity][$column]++;
    }
}
