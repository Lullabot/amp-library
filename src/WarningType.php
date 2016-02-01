<?php
namespace Lullabot\AMP;

use MyCLabs\Enum\Enum;

class WarningType extends Enum
{
    const A_HREF_NO_JAVASCRIPT = 'href attribute cannot have "javascript:" in an &lt;a&gt; tag';
    const A_TARGET_ONLY_BLANK = 'target attribute can only be "_blank" in an &lt;a&gt; tag';
}