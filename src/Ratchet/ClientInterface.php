<?php

namespace Localhook\Localhook\Ratchet;

interface ClientInterface
{
    public function routeInputEvents($type, $msg, $comKey);
}
