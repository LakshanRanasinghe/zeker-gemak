<?php

namespace App\Explorer;

use JeroenG\Explorer\Domain\Syntax\SyntaxInterface;

class CustomMultiMatch implements SyntaxInterface
{
    public function __construct(
        private string $value,
        private ?array $fields = null,
    ) {}

    public function build(): array
    {
        return [
            'bool' => [
                'should' => [
                    [
                        'multi_match' => [
                            'query' => $this->value,
                            'fields' => [
                                'sku^50',
                                'article_number^50',
                                'title^20',
                                'title_locales^20',
                                'name^20',
                                'name_locales^20',
                            ],
                            'type' => 'phrase',
                        ],
                    ],
                    [
                        'multi_match' => array_filter([
                            'query' => $this->value,
                            'fields' => $this->fields,
                            'type' => 'bool_prefix',
                            'operator' => 'and',
                        ], static fn (mixed $value): bool => $value !== null),
                    ],
                    [
                        'multi_match' => array_filter([
                            'query' => $this->value,
                            'fields' => $this->fields,
                            'type' => 'best_fields',
                            'operator' => 'and',
                            'fuzziness' => 'AUTO:4,7',
                            'prefix_length' => 1,
                            'max_expansions' => 25,
                            'boost' => 0.35,
                        ], static fn (mixed $value): bool => $value !== null),
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }
}
