<?php

namespace Comhon\EntityRequester\EntityRequest;

use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\Interfaces\RequestGateInterface;

class EntityRequestValidator
{
    public function __construct(
        private EntityRequestImporter $importer,
        private RequestGateInterface $gate,
        private SchemaConsistencyValidator $consistencyValidator,
    ) {}

    public function validate(array $data, ?string $modelClass = null): EntityRequest
    {
        $entityRequest = $this->importer->import($data, $modelClass);
        $this->gate->authorize($entityRequest);
        $this->consistencyValidator->validate($entityRequest);

        return $entityRequest;
    }
}
