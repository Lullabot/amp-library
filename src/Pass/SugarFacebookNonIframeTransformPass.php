<?php

namespace Lullabot\AMP\Pass;

class SugarFacebookNonIframeTransformPass extends FacebookNonIframeTransformPass
{
    /**
     * POPSUGAr version: return true, since url don't always comply with cpecs
     *
     * @param string $url
     * @return bool
     */
    protected function isValidPostUrl($url)
    {
        return TRUE;
    }

    protected function isValidVideoUrl($url)
    {
        return TRUE;
    }
}
