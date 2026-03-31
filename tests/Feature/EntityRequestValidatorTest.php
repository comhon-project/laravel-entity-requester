<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\Exceptions\InvalidOperatorForPropertyTypeException;
use Comhon\EntityRequester\Exceptions\MalformedValueException;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Facades\EntityRequestValidator;
use Tests\TestCase;

class EntityRequestValidatorTest extends TestCase
{
    public function test_validate_valid_request()
    {
        $entityRequest = EntityRequestValidator::validate([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'email',
                'value' => 'john@example.com',
            ],
        ]);

        $this->assertInstanceOf(EntityRequest::class, $entityRequest);
        $this->assertInstanceOf(Condition::class, $entityRequest->getFilter());
    }

    public function test_validate_catches_import_error()
    {
        $this->expectException(MalformedValueException::class);
        EntityRequestValidator::validate([
            'entity' => 123,
        ]);
    }

    public function test_validate_catches_gate_error()
    {
        $this->expectException(NotFiltrableException::class);
        EntityRequestValidator::validate([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'password',
                'value' => 'secret',
            ],
        ]);
    }

    public function test_validate_catches_consistency_error()
    {
        $this->expectException(InvalidOperatorForPropertyTypeException::class);

        $entityRequest = EntityRequestValidator::validate([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => 'contains',
                'property' => 'email',
                'value' => 'john',
            ],
        ]);
    }

    public function test_validate_with_model_class()
    {
        $entityRequest = EntityRequestValidator::validate([
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'email',
                'value' => 'john@example.com',
            ],
        ], User::class);

        $this->assertInstanceOf(EntityRequest::class, $entityRequest);
        $this->assertEquals(User::class, $entityRequest->getModelClass());
    }
}
