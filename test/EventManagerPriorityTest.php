<?php

declare(strict_types=1);

namespace LaminasTest\EventManager;

use Laminas\EventManager\Event;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManager;
use PHPUnit\Framework\TestCase;
use SplQueue;

use function array_shift;
use function count;
use function iterator_to_array;
use function sprintf;
use function var_export;

class EventManagerPriorityTest extends TestCase
{
    /** @var string[] */
    private array $identifiers;
    private SharedEventManager $sharedEvents;
    private EventManager $events;

    protected function setUp(): void
    {
        $this->identifiers  = [self::class];
        $this->sharedEvents = new SharedEventManager();
        $this->events       = new EventManager($this->sharedEvents, $this->identifiers);
    }

    public function createEvent(): Event
    {
        $accumulator = new SplQueue();
        $event       = new Event();
        $event->setName('test');
        $event->setTarget($this);
        $event->setParams(['accumulator' => $accumulator]);
        return $event;
    }

    /** @param mixed $return */
    public function createListener($return): callable
    {
        return function ($event) use ($return) {
            $event->getParam('accumulator')->enqueue($return);
        };
    }

    public function testTriggersListenersOfDifferentPrioritiesInPriorityOrder(): void
    {
        for ($i = -1; $i < 5; $i += 1) {
            $this->events->attach('test', $this->createListener($i), $i);
        }

        $event = $this->createEvent();
        $this->events->triggerEvent($event);
        $values = iterator_to_array($event->getParam('accumulator'));
        self::assertEquals(
            [4, 3, 2, 1, 0, -1],
            $values,
            sprintf("Did not receive values in priority order: %s\n", var_export($values, true))
        );
    }

    public function testTriggersListenersOfSamePriorityInAttachmentOrder(): void
    {
        for ($i = -1; $i < 5; $i += 1) {
            $this->events->attach('test', $this->createListener($i));
        }

        $event = $this->createEvent();
        $this->events->triggerEvent($event);
        $values = iterator_to_array($event->getParam('accumulator'));
        self::assertEquals(
            [-1, 0, 1, 2, 3, 4],
            $values,
            sprintf("Did not receive values in attachment order: %s\n", var_export($values, true))
        );
    }

    public function testTriggersWildcardListenersAfterExplicitListenersOfSamePriority(): void
    {
        $this->events->attach('*', $this->createListener(2), 5);
        $this->events->attach('test', $this->createListener(1), 5);
        $this->events->attach('*', $this->createListener(3), 5);

        $event = $this->createEvent();
        $this->events->triggerEvent($event);
        $values = iterator_to_array($event->getParam('accumulator'));
        self::assertEquals(
            [1, 2, 3],
            $values,
            sprintf("Did not receive wildcard values after explicit listeners: %s\n", var_export($values, true))
        );
    }

    public function testTriggersSharedListenersAfterWildcardListenersOfSamePriority(): void
    {
        $this->sharedEvents->attach(self::class, 'test', $this->createListener(2), 5);
        $this->events->attach('*', $this->createListener(1), 5);
        $this->sharedEvents->attach(self::class, 'test', $this->createListener(3), 5);

        $event = $this->createEvent();
        $this->events->triggerEvent($event);
        $values = iterator_to_array($event->getParam('accumulator'));
        self::assertEquals(
            [1, 2, 3],
            $values,
            sprintf("Did not receive shared listener values after wildcard listeners: %s\n", var_export($values, true))
        );
    }

    public function testTriggersSharedWildcardListenersAfterSharedListenersOfSamePriority(): void
    {
        $this->sharedEvents->attach(self::class, '*', $this->createListener(2), 5);
        $this->sharedEvents->attach(self::class, 'test', $this->createListener(1), 5);
        $this->sharedEvents->attach(self::class, '*', $this->createListener(3), 5);

        $event = $this->createEvent();
        $this->events->triggerEvent($event);
        $values = iterator_to_array($event->getParam('accumulator'));
        self::assertEquals(
            [1, 2, 3],
            $values,
            sprintf(
                "Did not receive shared wildcard listener values after shared listeners: %s\n",
                var_export($values, true)
            )
        );
    }

    public function testTriggersSharedWildcardIdentifierListenersAfterWildcardSharedListenersOfSamePriority(): void
    {
        $this->sharedEvents->attach('*', 'test', $this->createListener(2), 5);
        $this->sharedEvents->attach(self::class, '*', $this->createListener(1), 5);
        $this->sharedEvents->attach('*', 'test', $this->createListener(3), 5);

        $event = $this->createEvent();
        $this->events->triggerEvent($event);
        $values = iterator_to_array($event->getParam('accumulator'));
        self::assertEquals(
            [1, 2, 3],
            $values,
            sprintf(
                "Did not receive wildcard identifier listener values after shared wildcard listeners: %s\n",
                var_export($values, true)
            )
        );
    }

    public function testTriggersFullyWildcardSharedListenersAfterWildcardIdentifierListenersOfSamePriority(): void
    {
        $this->sharedEvents->attach('*', '*', $this->createListener(2), 5);
        $this->sharedEvents->attach('*', 'test', $this->createListener(1), 5);
        $this->sharedEvents->attach('*', '*', $this->createListener(3), 5);

        $event = $this->createEvent();
        $this->events->triggerEvent($event);
        $values = iterator_to_array($event->getParam('accumulator'));
        self::assertEquals(
            [1, 2, 3],
            $values,
            sprintf(
                "Did not receive fully wildcard shared listener values after shared wildcard listeners: %s\n",
                var_export($values, true)
            )
        );
    }

    public function testTriggeringMixOfLocalAndSharedAndWildcardListenersWorksAsExpected(): void
    {
        $this->sharedEvents->attach('*', '*', $this->createListener(1024), 1024);
        $this->sharedEvents->attach('*', '*', $this->createListener(1023), 1024);
        $this->events->attach('*', $this->createListener(1025), 1024);
        $this->events->attach('test', $this->createListener(1026), 1024);

        $this->sharedEvents->attach('*', 'test', $this->createListener(512), 512);
        $this->sharedEvents->attach('*', '*', $this->createListener(510), 512);
        $this->sharedEvents->attach('*', 'test', $this->createListener(511), 512);
        $this->events->attach('*', $this->createListener(513), 512);
        $this->events->attach('test', $this->createListener(514), 512);

        $this->sharedEvents->attach(self::class, '*', $this->createListener(256), 256);
        $this->sharedEvents->attach('*', '*', $this->createListener(253), 256);
        $this->sharedEvents->attach('*', 'test', $this->createListener(254), 256);
        $this->sharedEvents->attach(self::class, '*', $this->createListener(255), 256);
        $this->events->attach('*', $this->createListener(257), 256);
        $this->events->attach('test', $this->createListener(258), 256);

        $this->sharedEvents->attach(self::class, 'test', $this->createListener(128), 128);
        $this->sharedEvents->attach(self::class, '*', $this->createListener(126), 128);
        $this->sharedEvents->attach('*', '*', $this->createListener(123), 128);
        $this->sharedEvents->attach('*', 'test', $this->createListener(124), 128);
        $this->sharedEvents->attach(self::class, '*', $this->createListener(125), 128);
        $this->sharedEvents->attach(self::class, 'test', $this->createListener(127), 128);
        $this->events->attach('*', $this->createListener(129), 128);
        $this->events->attach('test', $this->createListener(130), 128);

        $this->events->attach('*', $this->createListener(64), 64);
        $this->events->attach('*', $this->createListener(63), 64);
        $this->events->attach('test', $this->createListener(32), 32);
        $this->events->attach('*', $this->createListener(30), 32);
        $this->events->attach('test', $this->createListener(31), 32);

        $event = $this->createEvent();
        $this->events->triggerEvent($event);

        $values = $report = iterator_to_array($event->getParam('accumulator'));
        self::assertCount(28, $values);
        $original = array_shift($values);
        do {
            $compare = array_shift($values);
            self::assertLessThan(
                $original,
                $compare,
                sprintf("Did not receive values in expected order: %s\n", var_export($report, true))
            );
            $original = $compare;
        } while (count($values));
    }
}
