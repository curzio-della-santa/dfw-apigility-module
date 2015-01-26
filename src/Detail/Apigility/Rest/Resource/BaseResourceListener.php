<?php

namespace Detail\Apigility\Rest\Resource;

use Zend\Stdlib\Parameters;

use ZF\ApiProblem\ApiProblem;
use ZF\Rest\AbstractResourceListener;
use ZF\Rest\ResourceEvent;

use Detail\Commanding\Command\CommandInterface;
use Detail\Commanding\CommandDispatcherInterface;
use Detail\Commanding\Service\CommandDispatcherAwareInterface;
use Detail\Commanding\Service\CommandDispatcherAwareTrait;
use Detail\Normalization\Normalizer\NormalizerInterface;
use Detail\Normalization\Normalizer\Service\NormalizerAwareInterface;
use Detail\Normalization\Normalizer\Service\NormalizerAwareTrait;

use Detail\Apigility\Exception;

class BaseResourceListener extends AbstractResourceListener implements
    CommandDispatcherAwareInterface,
    NormalizerAwareInterface
{
    use CommandDispatcherAwareTrait;
    use NormalizerAwareTrait;

    protected $requestCommandMap = array();

    /**
     * @var CommandInterface
     */
    protected $command;

    /**
     * @param NormalizerInterface $normalizer
     * @param CommandDispatcherInterface $commands
     */
    public function __construct(NormalizerInterface $normalizer, CommandDispatcherInterface $commands = null)
    {
        $this->setNormalizer($normalizer);

        if ($commands !== null) {
            $this->setCommands($commands);
        }
    }

    /**
     * @param string $eventName
     * @return array|string
     */
    public function getRequestCommandMapping($eventName)
    {
        $map = $this->getRequestCommandMap();

        return isset($map[$eventName]) ? $map[$eventName] : null;
    }

    /**
     * @return array|string
     */
    public function getRequestCommandMap()
    {
        return $this->requestCommandMap;
    }

    /**
     * @param array $requestCommandMap
     */
    public function setRequestCommandMap(array $requestCommandMap)
    {
        $this->requestCommandMap = $requestCommandMap;
    }

    /**
     * @param boolean $failOnNull
     * @return CommandInterface
     */
    public function getDispatchedCommand($failOnNull = true)
    {
        if ($this->command === null && $failOnNull !== false) {
            throw new Exception\RuntimeException('No command was created during dispatch');
        }

        return $this->command;
    }

    /**
     * @inheritdoc
     */
    public function dispatch(ResourceEvent $event)
    {
        $this->event = $event;

        switch ($event->getName()) {
            case 'fetchAll':
                // Always transform paging related params
                $event->setQueryParams(
                    new Parameters($this->getQueryParams($event, true))
                );
                break;
            default:
                // Do nothing
                break;
        }

        $commandMapping = $this->getRequestCommandMapping($event->getName());

        if ($commandMapping !== null) {
            $this->command = $this->createCommand($event);
        }

        /** @todo Use eventing... */
        $result = $this->onBeforeDispatch($event, $this->command);

        // No need to continue if we already encountered a problem...
        if ($result instanceof ApiProblem) {
            return $result;
        }

        return parent::dispatch($event);
    }

    /**
     * @param ResourceEvent $event
     * @param CommandInterface $command
     * @return mixed
     */
    protected function onBeforeDispatch(ResourceEvent $event, CommandInterface $command = null)
    {
    }

    /**
     * @param ResourceEvent $event
     * @return array
     */
    protected function getBodyParams(ResourceEvent $event)
    {
        $params = (array) $event->getParam('data', array());

        return $params;
    }

    /**
     * @param ResourceEvent $event
     * @param bool $translatePaging
     * @return array
     */
    protected function getQueryParams(ResourceEvent $event, $translatePaging = false)
    {
        $params = (array) ($event->getQueryParams() ?: array());

        if ($translatePaging === true) {
            if (!isset($params['page'])) {
                $params['page'] = 1;
            }

            if (!isset($params['page_size'])) {
                /** @todo Get from settings (subscribe to getList.pre?) */
                $params['page_size'] = 10;
            }

            $params['limit'] = $params['page_size'];
            $params['offset'] = ($params['page'] - 1) * $params['page_size'];

            unset($params['page'], $params['page_size']);
        }

        return $params;
    }

    /**
     * @param ResourceEvent $event
     * @return array
     */
    protected function getRouteParams(ResourceEvent $event)
    {
        $params = $event->getRouteMatch()->getParams();

        unset($params['controller']);

        return $params;
    }

    protected function createCommand(ResourceEvent $event)
    {
        $normalizer = $this->getNormalizer();

        if ($normalizer === null) {
            throw new Exception\ConfigException(
                'Cannot use request to command mapping; no Normalizer provided'
            );
        }

        if (!isset($commandMapping['command_class'])) {
            throw new Exception\ConfigException(
                sprintf(
                    'Invalid request to command mapping configuration for event "%s"',
                    $event->getName()
                )
            );
        }

        $commandClass = $commandMapping['command_class'];
        $data = array();

        switch ($event->getName()) {
            case 'create':
            case 'deleteList':
            case 'patch':
            case 'patchList':
            case 'replaceList':
            case 'update':
                $data = $this->getBodyParams($event);
                break;
            case 'fetchAll':
                /// Note that the paging related params are already transformed...
                $data = $this->getQueryParams($event, false);
                break;
            default:
                // Do nothing
                break;
        }

        /** @todo The normalizer should know from which version to denormalize from */
        return $normalizer->denormalize($data, $commandClass);
    }
}
