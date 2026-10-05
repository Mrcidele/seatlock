<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Faz a documentação refletir que todos os erros da API são
 * application/problem+json (RFC 9457).
 */
final class ProblemDetailsResponses
{
    public function __invoke(OpenApi $openApi): void
    {
        $problem = (new ObjectType)
            ->addProperty('type', (new StringType)->format('uri')->setDescription('URI que identifica o tipo do problema.'))
            ->addProperty('title', (new StringType)->setDescription('Resumo legível do problema.'))
            ->addProperty('status', (new IntegerType)->setDescription('Status HTTP.'))
            ->addProperty('detail', (new StringType)->setDescription('Explicação desta ocorrência.'))
            ->addProperty('instance', (new StringType)->setDescription('URI da requisição que falhou.'))
            ->addProperty('errors', (new ObjectType)->additionalProperties((new ArrayType)->setItems(new StringType))->setDescription('Erros de validação por campo (422).'))
            ->addProperty('conflicting_seat_ids', (new ArrayType)->setItems(new StringType)->setDescription('Assentos indisponíveis (409 seat-unavailable).'))
            ->addProperty('alternatives', (new ArrayType)->setItems(
                (new ObjectType)
                    ->addProperty('id', new StringType)
                    ->addProperty('number', new StringType)
                    ->addProperty('type', new StringType)
                    ->addProperty('deck', new IntegerType),
            )->setDescription('Sugestões de assentos livres parecidos (409 seat-unavailable).'))
            ->setRequired(['type', 'title', 'status']);

        $reference = $openApi->components->addSchema('ProblemDetails', Schema::fromType($problem));

        foreach ($openApi->components->responses as $response) {
            if ($response instanceof \Dedoc\Scramble\Support\Generator\Response) {
                $response->content = ['application/problem+json' => $reference];
            }
        }
    }
}
