<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

/**
 * Faz a documentação refletir que todos os erros da API são
 * application/problem+json (RFC 9457).
 */
final class ProblemDetailsResponses
{
    public function __invoke(OpenApi $openApi): void
    {
        $problem = new ObjectType;
        $problem->addProperty('type', self::described((new StringType)->format('uri'), 'URI que identifica o tipo do problema.'));
        $problem->addProperty('title', self::described(new StringType, 'Resumo legível do problema.'));
        $problem->addProperty('status', self::described(new IntegerType, 'Status HTTP.'));
        $problem->addProperty('detail', self::described(new StringType, 'Explicação desta ocorrência.'));
        $problem->addProperty('instance', self::described(new StringType, 'URI da requisição que falhou.'));

        $fieldErrors = new ArrayType;
        $fieldErrors->setItems(new StringType);
        $errors = new ObjectType;
        $errors->additionalProperties($fieldErrors);
        $problem->addProperty('errors', self::described($errors, 'Erros de validação por campo (422).'));

        $seatIds = new ArrayType;
        $seatIds->setItems(new StringType);
        $problem->addProperty('conflicting_seat_ids', self::described($seatIds, 'Assentos indisponíveis (409 seat-unavailable).'));

        $alternative = new ObjectType;
        $alternative->addProperty('id', new StringType);
        $alternative->addProperty('number', new StringType);
        $alternative->addProperty('type', new StringType);
        $alternative->addProperty('deck', new IntegerType);
        $alternatives = new ArrayType;
        $alternatives->setItems($alternative);
        $problem->addProperty('alternatives', self::described($alternatives, 'Sugestões de assentos livres parecidos (409 seat-unavailable).'));

        $problem->setRequired(['type', 'title', 'status']);

        $schema = Schema::fromType($problem);
        assert($schema instanceof Schema);
        $reference = $openApi->components->addSchema('ProblemDetails', $schema);

        foreach ($openApi->components->responses as $response) {
            $response->content = ['application/problem+json' => $reference];
        }
    }

    private static function described(Type $type, string $description): Type
    {
        $type->setDescription($description);

        return $type;
    }
}
