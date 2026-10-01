<?php

declare(strict_types=1);

namespace DataHealth;

final class ContextHasher
{
    /**
     * @param  array<array-key, mixed>  $context
     */
    public function hash(array $context): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($context),
            JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $context): array
    {
        if (! array_is_list($context)) {
            ksort($context, SORT_STRING);
        }

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->canonicalize($value);
            }
        }

        return $context;
    }
}
