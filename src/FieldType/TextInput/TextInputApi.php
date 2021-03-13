<?php
declare(strict_types=1);

namespace Tardigrades\FieldType\TextInput;

use Tardigrades\SectionField\Api\Serializer\SerializeToArrayInterface;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\ValueObject\FieldConfig;

class TextInputApi
{
    protected SerializeToArrayInterface $serialize;

    public function __construct(SerializeToArrayInterface $serialize)
    {
        $this->serialize = $serialize;
    }

    public function info(): array
    {

    }

    public function serialize(
        CommonSectionInterface $entry,
        FieldConfig $config
    ): array {

    }
}


