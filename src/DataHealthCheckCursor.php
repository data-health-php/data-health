<?php

declare(strict_types=1);

namespace DataHealth;

use DataHealth\Models\DataHealthCursor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DataHealthCheckCursor
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<Finding>  $check
     * @return Collection<int, covariant Model>
     */
    public function next(Builder $query, string $check, int $limit): Collection
    {
        $cursor = DataHealthCursor::firstOrCreate(
            ['key' => $check],
            ['last_id' => 0],
        );

        $overallLastId = (clone $query)->max('id');

        $ids = (clone $query)
            ->where('id', '>', $cursor->last_id)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $cursor->update(['last_id' => 0]);

            return new Collection;
        }

        $lastId = $ids->last();

        $cursor->update([
            'last_id' => $lastId === $overallLastId ? 0 : $lastId,
        ]);

        return (clone $query)
            ->whereKey($ids)
            ->orderBy('id')
            ->get()
            ->values();
    }
}
