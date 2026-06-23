<?php

namespace App\Explorer;

use JeroenG\Explorer\Domain\Syntax\SyntaxInterface;

class CustomMultiMatch implements SyntaxInterface
{
    private string $value;

    private ?array $fields;

    public function __construct(string $value, ?array $fields = null)
    {
        $this->value = $value;
        $this->fields = $fields;
    }

    public function build(): array
    {
        $query = [
            'query' => $this->value,
            'type' => 'bool_prefix',
            'operator' => 'and',
        ];

        if ($this->fields !== null) {
            $query['fields'] = $this->fields;
        }

        return [
            'multi_match' => $query,
        ];
    }
}
