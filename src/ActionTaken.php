<?php

namespace Lullabot\AMP;
use MyCLabs\Enum\Enum;

class ActionTaken extends Enum {
    const TAG_REMOVED = 'tag removed';
    const ATTRIBUTE_REMOVED = 'attribute removed';
    const IMG_CONVERTED = 'img converted to amp-img';
    const IFRAME_CONVERTED = 'iframe converted to amp-iframe';
}
