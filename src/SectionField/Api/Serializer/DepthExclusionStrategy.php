<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Serializer;

use JMS\Serializer\Exclusion\ExclusionStrategyInterface;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Context;

class DepthExclusionStrategy implements ExclusionStrategyInterface
{
    private $depth;

    public function __construct(int $depth = 20)
    {
        $this->depth = $depth;
    }

    public function shouldSkipClass(ClassMetadata $metadata, Context $navigatorContext): bool
    {
        return $this->isTooDeep($navigatorContext);
    }

    public function shouldSkipProperty(PropertyMetadata $property, Context $navigatorContext): bool
    {
        return $this->isTooDeep($navigatorContext);
    }

    private function isTooDeep(Context $navigatorContext): bool
    {
        return $navigatorContext->getDepth() > $this->depth;
    }
}
