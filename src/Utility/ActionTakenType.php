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

namespace Lullabot\AMP\Utility;

class ActionTakenType
{
    const TAG_REMOVED = 'tag was removed due to validation issues.';
    const ATTRIBUTE_REMOVED = 'attribute was removed due to validation issues.';
    const PROPERTY_REMOVED = 'property value pair was removed from attribute due to validation issues.';
    const PROPERTY_REMOVED_ATTRIBUTE_REMOVED = 'property value pair was removed from attribute due to validation issues. The resulting attribute was empty and was also removed.';
    const IMG_CONVERTED = 'tag was converted to the amp-img tag.';
    const INSTAGRAM_CONVERTED = 'instagram embed code was converted to the amp-instagram tag.';
    const PINTEREST_CONVERTED = 'pinterest embed code was converted to the amp-pinterest tag.';
    const VINE_CONVERTED = 'vine embed code was converted to the amp-vine tag.';
    const FACEBOOK_IFRAME_CONVERTED = 'facebook embed code was converted to the amp-facebook tag.';
    const FACEBOOK_JSDK_CONVERTED = 'facebook javascript sdk embed code was converted to the amp-facebook tag.';
    const FACEBOOK_SCRIPT_REMOVED = 'facebook script tag was removed.';
    const VIMEO_CONVERTED = 'vimeo embed code was converted to the amp-vimeo tag.';
    const DAILYMOTION_CONVERTED = 'dailymotion embed code was converted to the amp-dailymotion tag.';
    const TWITTER_CONVERTED = 'twitter embed code was converted to the amp-twitter tag.';
    const IFRAME_CONVERTED = 'tag was converted to the amp-iframe tag.';
    const YOUTUBE_IFRAME_CONVERTED = 'tag was converted to the amp-youtube tag.';
    const SOUNDCLOUD_IFRAME_CONVERTED = 'tag was converted to the amp-soundcloud tag.';
    const COMPONENT_SCRIPT_TAG_ADDED = 'custom component script tag added to head';
    const AUDIO_CONVERTED = 'tag was converted to the amp-audio tag.';
    const VIDEO_CONVERTED = 'tag was converted to the amp-video tag.';
    const BLACKLISTED_TAG_REMOVED = 'and was removed as it matched a user submitted CSS selector blacklist.';
    const BAD_BLACKLIST_CSS_SELECTOR = 'is a bad CSS selector for tag blacklisting. Ignoring.';
}
