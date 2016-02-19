<?php
namespace Lullabot\AMP;

use MyCLabs\Enum\Enum;

class WarningType extends Enum
{
    const IMG_CONVERTED_AMP_IMG = 'The &lt;img&gt; tag was converted to an an &lt;amp-img&gt; tag with some standard attributes.';
    const TAG_NOT_ALLOWED = 'tag is not allowed or valid.';
    const ATTRIBUTE_NOT_ALLOWED = 'attribute is not allowed or valid.';
    const COMMENT_CONDITIONAL_NOT_ALLOWED = 'conditional comments are not allowed.';
}
