<?php

declare(strict_types=1);

namespace LaminasBench\EventManager;

use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManager;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

use function array_filter;

/**
 * @Revs(1000)
 * @Iterations(10)
 * @Warmup(2)
 */
class MultipleEventIndividualSharedListenerBench
{
    use BenchTrait;

    /** @var EventManager */
    private $events;

    /** @var array */
    private $eventsToTrigger;

    public function __construct()
    {
        $identifiers  = $this->getIdentifierList();
        $sharedEvents = new SharedEventManager();
        foreach ($this->getEventList() as $event) {
            $sharedEvents->attach($identifiers[0], $event, $this->generateCallback());
        }
        $this->events = new EventManager($sharedEvents, [$identifiers[0]]);

        $this->eventsToTrigger = array_filter($this->getEventList(), function ($value) {
            return $value !== '*';
        });
    }

    public function benchTrigger()
    {
        foreach ($this->eventsToTrigger as $event) {
            $this->events->trigger($event);
        }
    }
}
