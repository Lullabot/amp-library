<?php

namespace Lullabot\AMP\Pass;

use Psr\Http\Client\ClientInterface;

trait HttpClientAwareTrait
{
    /** @var ClientInterface */
    private $httpClient;

    /**
     * @return ClientInterface
     */
    protected function getHttpClient()
    {
        return $this->httpClient;
    }

    public function setHttpClient(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }
}