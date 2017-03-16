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

namespace Lullabot\AMP\Pass;

abstract class BaseFacebookPass extends BasePass
{
    /**
     * Checks whether the given url is a valid facebook post url
     *
     * @param string $url
     * @return bool
     */
    protected function isValidPostUrl($url)
    {
        return
            // e.g. https://www.facebook.com/20531316728/posts/10154009990506729/
            preg_match('&(*UTF8)facebook\.com/.*/posts/\d+/?&i', $url)
            // e.g. https://www.facebook.com/photos/{photo-id}
            // or https://www.facebook.com/SanAntonioVAMC/photos/a.411451129506.185409.351086129506/10154231221264507/?type=3&amp;theater
            || preg_match('&(*UTF8)facebook\.com/.*photos.*&i', $url)
            // e.g. https://www.facebook.com/photo.php?fbid={photo-id}
            // or https://www.facebook.com/photo.php?v=10153655829445601
            || preg_match('&(*UTF8)facebook\.com/photo\.php.*&i', $url);
    }

    protected function isValidVideoUrl($url)
    {
        return
            // e.g https://www.facebook.com/facebook/videos/10153231379946729/
            // https://www.facebook.com/PopSugar/videos/vb.112402358796244/848573591845780/?type=2&theater&notif_t=live_video_explicit&notif_id=1484099141368371
            preg_match('&(*UTF8)facebook\.com/.*/videos&i', $url)
            // e.g https://www.facebook.com/video.php?v=10153231379946729
            || preg_match('&(*UTF8)facebook\.com/video\.php.*&i', $url);
    }
}