<?php

declare(strict_types=1);

namespace Zolinga\System;

use ArrayAccess;
use Zolinga\System\Events\{Event, StoppableInterface, ServiceInterface, ListenerInterface, AuthorizeEvent};
use Zolinga\System\Types\{StatusEnum, SeverityEnum};
use Zolinga\System\Config\Atom\{ListenAtom, EmitAtom, WebComponentAtom, AtomInterface};

/**
 * Main System API class.
 * 
 * @property Config\ManifestService $manifest
 * @property Loader\Autoloader $autoloader
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-01-31
 */
class Api
{
    /**
     * @var array<string, Object> list of lazily instantiated services accesible through $api->myService
     */
    private array $services = [];

    /**
     * All instantiated listeners. All listeners are persistent and are reused.
     * @var array<ListenerInterface>
     */
    private array $listeners = [];

    /**
     * Magic method to get a service object. If the service object has not been
     * instantiated yet, it will be instantiated and registered.
     * 
     * Example: $api->myService->doSomething();
     * 
     * @param string $name
     */
    public function __get(string $name): Object
    {
        if (!isset($this->services[$name])) {
            $this->loadService($name);
        }

        return $this->services[$name];
    }

    public function __set(string $name, Object $value): void
    {
        throw new \Exception("Cannot set property $name on Api object.");
    }

    /**
     * Register object as a service.
     * 
     * @param string $name
     * @param ServiceInterface $service
     * @return void
     */
    public function registerService(string $name, ServiceInterface $service): void
    {
        $this->services[$name] = $service;
    }

    /**
     * Find all listeners for the event and call their methods as defined in the zolinga.json manifest.
     * 
     * If the event is StoppableInterface and isPropagationStopped() returns true, the event is not 
     * dispatched to the rest of the listeners. To implement stoppable events, the event must implement
     * StoppableInterface. You may use StoppableTrait to add needed interface to your class.
     * 
     * E.g. 
     * 
     * class MyEvent extends \Zolinga\System\Events\Event implements \Zolinga\System\Events\StoppableInterface { 
     *      use \Zolinga\System\Events\StoppableTrait;
     *      ... 
     * }
     *
     * @param Event $event
     * @return Event
     */
    public function dispatchEvent(Event $event): Event
    {
        $subscriptions = $this->manifest->findByEvent($event, 'listen');


        foreach ($subscriptions as $subscription) {
            if ($event instanceof StoppableInterface && $event->isPropagationStopped()) break;

            // Authorization required
            if (!empty($subscription['right']) && !($event instanceof AuthorizeEvent)) {
                $authEvent = new AuthorizeEvent('system:authorize', AuthorizeEvent::ORIGIN_INTERNAL, [$subscription['right']]);
                $authEvent->dispatch();
                if (!$authEvent->isAuthorized($subscription['right'])) {
                    $event->setStatus(StatusEnum::UNAUTHORIZED, "Unauthorized");
                    continue;
                }
            }
            /** @var ListenAtom $subscription */
            $this->processEvent($event, $this->getSubscriberByClass($subscription['class']), $subscription['method']);
        }

        return $event;
    }

    private function processEvent(Event $event, ListenerInterface $listener, string $method): void
    {
        try {
            if (!is_callable([$listener, $method])) {
                throw new \Exception("Method $method not found in " . $listener::class);
            }
            $listener->$method($event);
        } catch (\Throwable $e) {
            $err = $e->getMessage();
            //trigger_error($err . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']', E_USER_WARNING);
            $event->setStatus(StatusEnum::ERROR, $err);
        }
    }

    /**
     * Instantiate and return a service object.
     * 
     * @param string $name
     * @return ServiceInterface
     */
    private function loadService(string $name): ServiceInterface
    {
        // Autoload the service object based on the $name
        // For example, if $name is "UserService", load the UserService class
        $event = new Event("system:service:$name", Event::ORIGIN_INTERNAL);

        /* @var ListenAtom $subscription */
        $subscription = $this->manifest->findByEvent($event, 'listen', 1);

        if (!$subscription) {
            throw new \Exception("Service '$name' (a listener for $event) not found.");
        }

        $listener = $this->getSubscriberByClass($subscription['class']);
        if ($listener instanceof ServiceInterface) {
            $this->registerService($name, $listener);
        } else {
            throw new \Exception("Service class {$subscription['class']} does not implement Zolinga\\System\\ServiceInterface.");
        }

        if (!empty($subscription['method'])) {
            $method = $subscription['method'];
            $listener->$method($event);
        }

        return $listener;
    }

    /**
     * Does the service exist? Is it declared in the zolinga.json manifest?
     * 
     * Example:
     * 
     * $api->serviceExists('user'); // true if zolinga-rms module is installed
     *
     * @param string $name
     * @return boolean
     */
    public function serviceExists(string $name): bool
    {
        return isset($this->services[$name]) || $this->getServiceSubscription($name);
    }

    /**
     * Find the service subscription in the manifest.
     *
     * @param string $service
     * @return ListenAtom|null
     */
    private function getServiceSubscription(string $service): ?ListenAtom {
        $event = new Event("system:service:$service", Event::ORIGIN_INTERNAL);
        $subscription = $this->manifest->findByEvent($event, 'listen', 1);
        return $subscription instanceof ListenAtom ? $subscription : null;
    }

    /**
     * Subscriber objects are persistent and are reused.
     * 
     * @param string $class Class name of the listener
     * @return ListenerInterface
     */
    private function getSubscriberByClass(string $class): ListenerInterface
    {
        if (!class_exists($class)) {
            throw new \Exception("Class $class not found. Make sure the zolinga.json's 'autoload' section is correct.");
        }

        $interfaces = class_implements($class);
        if (!isset($interfaces[ListenerInterface::class])) {
            throw new \Exception("Class $class does not implement Zolinga\\System\\ListenerInterface.");
        }

        if (!isset($this->listeners[$class])) {
            /** @var ListenerInterface $listener */
            $listener = new $class();
            $this->listeners[$class] = $listener;
        }

        return $this->listeners[$class];
    }
}
