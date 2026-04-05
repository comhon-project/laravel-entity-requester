<?php

namespace Comhon\EntityRequester\EntityRequest;

use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\Interfaces\EntityRequestAuthorizerInterface;

class Validator
{
    public function __construct(
        private Importer $importer,
        private EntityRequestAuthorizerInterface $authorizer,
        private ConsistencyChecker $consistencyChecker,
    ) {}

    public function validate(array $data, ?string $modelClass = null): EntityRequest
    {
        $entityRequest = $this->importer->import($data, $modelClass);
        $this->authorizer->authorize($entityRequest);
        $this->consistencyChecker->validate($entityRequest);

        return $entityRequest;
    }
}
