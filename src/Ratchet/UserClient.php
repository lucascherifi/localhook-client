<?php

namespace Localhook\Localhook\Ratchet;

use Exception;

class UserClient extends AbstractClient implements ClientInterface
{
    /** @var integer */
    private $max;

    /** @var integer */
    private $counter;

    /** @var callable */
    private $forwardRequestCallback;

    /** @var callable */
    private $maxReachedCallback;

    public function routeInputEvents($type, $msg, $comKey)
    {
        switch ($type) {
            case '_retrieveConfigurationFromSecret':
                $this->defaultReceive($msg, $comKey);
                break;
            case '_subscribeWebHook':
                $this->defaultReceive($msg, $comKey);
                break;
            case '_unsubscribeWebHook':
                $this->defaultReceive($msg, $comKey);
                break;
            case '_forwardRequest':
                $this->receiveForwardRequest($msg, $comKey);
                break;
            default:
                throw new Exception('Type "' . $type . '" not managed');
        }
    }

    public function executeRetrieveConfigurationFromSecret($secret, callable $onSuccess)
    {
        $this->defaultExecute('retrieveConfigurationFromSecret', [
            'secret' => $secret,
        ], $onSuccess);
    }

    public function executeSubscribeWebHook(
        callable $onSuccess,
        callable $onForward,
        callable $onMaxReached,
        $secret,
        $max
    ) {
        $this->forwardRequestCallback = $onForward;
        $this->maxReachedCallback = $onMaxReached;
        $this->max = $max;
        $this->counter = 0;
        $this->defaultExecute('subscribeWebHook', [
            'secret' => $secret,
        ], $onSuccess);
    }

    private function receiveForwardRequest($msg, $comKey)
    {
        $requestData = $msg['request'];
        $forwardRequestCallback = $this->forwardRequestCallback;
        $forwardRequestCallback($requestData, $comKey);
        $this->counter++;

        if (!is_null($this->max) && $this->counter >= $this->max) {
            $maxReachedCallback = $this->maxReachedCallback;
            $maxReachedCallback($comKey);
        }
    }
}
