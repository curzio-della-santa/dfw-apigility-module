<?php

namespace Detail\Apigility\Hal;

use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

abstract class BaseRenderListener implements
    ListenerAggregateInterface
{
    /**
     * @var array
     */
    protected $listeners = array();

    /**
     * Attach events to the HAL plugin.
     *
     * This method attaches listeners to the renderEntity and renderCollection.entity
     * events of ZF\Hal\Plugin\Hal.
     *
     * @param EventManagerInterface $eventManager
     */
    public function attach(EventManagerInterface $eventManager)
    {
        $this->listeners[] = $eventManager->attach(
            'renderCollection',
            array($this, 'onRenderCollection')
        );

        $this->listeners[] = $eventManager->attach(
            'renderCollection.entity',
            array($this, 'onRenderCollectionEntity')
        );

        $this->listeners[] = $eventManager->attach(
            'renderEntity',
            array($this, 'onRenderEntity')
        );
    }

    /**
     * Detach events from the shared event manager.
     *
     * This method detaches listeners it has previously attached.
     *
     * @param EventManagerInterface $eventManager
     */
    public function detach(EventManagerInterface $eventManager)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($eventManager->detach($listener)) {
                unset($listener[$index]);
            }
        }
    }

    /**
     * Listener for the "renderCollection" event.
     *
     * @param EventInterface $event
     */
    abstract public function onRenderCollection(EventInterface $event);

    /**
     * Listener for the "renderCollection.entity" event
     *
     * @param EventInterface $event
     * @return void
     */
    abstract public function onRenderCollectionEntity(EventInterface $event);

    /**
     * Listener for the "renderEntity" event
     *
     * @param EventInterface $event
     */
    abstract public function onRenderEntity(EventInterface $event);
}
