<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\Api\Serializer;

use Symfony\Component\HttpFoundation\Request;
use Tardigrades\SectionField\Generator\CommonSectionInterface;

interface SerializeToArrayInterface {
    public function toArray(Request $request, CommonSectionInterface $entry): array;
}
