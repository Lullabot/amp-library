<?php

namespace Lullabot\AMP\Pass;

use Psr\Http\Client\ClientInterface;

interface HttpClientAwarePass
{
    public function setHttpClient(ClientInterface $httpClient);
}