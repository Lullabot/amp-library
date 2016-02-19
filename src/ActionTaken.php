<?php

namespace Lullabot\AMP;

use MyCLabs\Enum\Enum;

class ActionTaken extends Enum
{
    const TAG_REMOVED = 'tag was removed.';
    const ATTRIBUTE_REMOVED = 'attribute was removed.';
    const IMG_CONVERTED = 'tag was converted to the amp-img tag.';
    const IFRAME_CONVERTED = 'tag was converted to the amp-iframe tag.';
}
