<?php

declare(strict_types=1);

namespace LaminasBench\EventManager;

use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManager;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * @Revs(1000)
 * @Iterations(10)
 * @Warmup(2)
 */
class SingleEventSingleSharedListenerBench
{
    use BenchTrait;

    /** @var EventManager */
    private $events;

    public function __construct()
    {
        $identifiers  = $this->getIdentifierList();
        $sharedEvents = new SharedEventManager();
        $sharedEvents->attach($identifiers[0], 'dispatch', $this->generateCallback());
        $this->events = new EventManager($sharedEvents, [$identifiers[0]]);
    }

    public function benchTrigger()
    {
        $this->events->trigger('dispatch');
    }
}
