<?php

declare(strict_types=1);

namespace Framework\Search;

interface EmbeddingProviderInterface
{
    public function embed(string $text): array;
    public function dimensions(): int;
}
