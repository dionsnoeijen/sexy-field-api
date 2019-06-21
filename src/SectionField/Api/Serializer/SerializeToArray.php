<?php

/*
 * This file is part of the SexyField package.
 *
 * (c) Dion Snoeijen <hallo@dionsnoeijen.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tardigrades\SectionField\Api\Serializer;

use JMS\Serializer\Handler\HandlerRegistry;
use JMS\Serializer\Naming\IdenticalPropertyNamingStrategy;
use JMS\Serializer\Naming\SerializedNameAnnotationStrategy;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Tardigrades\SectionField\Api\Handler\DateTimeTimezoneHandler;
use Tardigrades\SectionField\Generator\CommonSectionInterface;

class SerializeToArray implements SerializeToArrayInterface {

    /** @var string */
    private $cacheDir;

    /** @var ContainerInterface */
    private $container;

    public function __construct(
        string $cacheDir,
        ContainerInterface $container
    ) {
        $this->cacheDir = $cacheDir;
        $this->container = $container;
    }

    /**
     * This will serialize entities into a associative array, using keys as they are
     * in the entity. (By default jms will use snake cased naming, it's reverted to
     * camel case)
     *
     * @param Request $request
     * @param CommonSectionInterface $entry
     * @return array
     */
    public function toArray(Request $request, CommonSectionInterface $entry): array
    {
        $serializer = SerializerBuilder::create()
            ->setPropertyNamingStrategy(
                new SerializedNameAnnotationStrategy(
                    new IdenticalPropertyNamingStrategy()
                )
            )
            ->addDefaultHandlers()
            ->configureHandlers(function(HandlerRegistry $registry) {
                $registry->registerSubscribingHandler(new DateTimeTimezoneHandler());

            })
            ->setCacheDir($this->cacheDir . '/serializer')
            ->build();

        return $serializer->toArray($entry, $this->getContext($request));
    }

    /**
     * This method will get the desired fields and depth from the request
     *
     * @param Request $request
     * @return SerializationContext
     */
    private function getContext(Request $request): SerializationContext
    {
        $fields = $request->get('fields', ['id']);
        $depth = $request->get('depth', 20);
        $depth = is_numeric($depth) ? (int)$depth : 20;

        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        $context = new SerializationContext();
        $context->addExclusionStrategy(new FieldsExclusionStrategy($fields));
        $context->addExclusionStrategy(new DepthExclusionStrategy($depth));

        return $context;
    }
}
