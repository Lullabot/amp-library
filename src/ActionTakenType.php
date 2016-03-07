<?php
/*
 * Copyright 2016 Google
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Lullabot\AMP;

use MyCLabs\Enum\Enum;

class ActionTakenType extends Enum
{
    const TAG_REMOVED = 'tag was removed due to validation issues.';
    const ATTRIBUTE_REMOVED = 'attribute was removed due to validation issues.';
    const PROPERTY_REMOVED = 'property value pair was removed from attribute due to validation issues.';
    const IMG_CONVERTED = 'tag was converted to the amp-img tag.';
    const INSTAGRAM_CONVERTED = 'instagram embed code was converted to the amp-instagram tag.';
    const TWITTER_CONVERTED = 'twitter embed code was converted to the amp-twitter tag.';
    const IFRAME_CONVERTED = 'tag was converted to the amp-iframe tag.';
    const YOUTUBE_IFRAME_CONVERTED = 'tag was converted to the amp-youtube tag.';
}
