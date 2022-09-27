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

use QueryPath\DOMQuery;

use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;

/**
 * Class TikTokTransformPass
 * @package Lullabot\AMP\Pass
 */
class TikTokTransformPass extends BasePass
{
  function pass()
  {
    $all_tiktoks = $this->q->top()->find('blockquote.tiktok-embed');
    /** @var DOMQuery $el */
    foreach ($all_tiktoks as $el) {
      /** @var \DOMElement $dom_el */
      $dom_el = $el->get(0);
      $lineno = $this->getLineNo($dom_el);

      $tiktok_id = $this->getTikTokId($el);
      // Very important, if we didn't find a tiktok id then go to next tiktok
      // This could be just a simple blockquote
      if (empty($tiktok_id)) {
        continue;
      }

      $tiktok_script_tag = $this->getScriptTag($el, '&(*UTF8)tiktok.com/embed\.js&i');

      $context_string = $this->getContextString($dom_el);

      /** @var \DOMElement $new_dom_el */
      $el->after("<amp-tiktok width=\"325\" height=\"700\" layout=\"responsive\" data-src=\"$tiktok_id\"></amp-tiktok>");
      $new_dom_el = $el->get(0);

      // Remove the blockquote, its children.
      $el->removeChildren()->remove();
      if (!empty($tiktok_script_tag)) {
        $tiktok_script_tag->remove();
      }
      $this->addActionTaken(new ActionTakenLine('blockquote.tiktok-embed', ActionTakenType::TIKTOK_CONVERTED, $lineno, $context_string));

      $this->context->addLineAssociation($new_dom_el, $lineno);
    }

    return $this->transformations;
  }

  /**
   * Get TikTok status from the TikTok embed code
   */
  protected function getTikTokId(DOMQuery $el)
  {
    $tiktok_id = $el->attr('data-video-id');
    if (empty($tiktok_id)) {
      $tiktok_url = $el->attr('cite');
      if (preg_match('@tiktok.com/.*/video/([^/]+)@i', $tiktok_url, $matches)) {
        if (!empty($matches[1])) {
          $tiktok_id = $matches[1];
        }
      }
    }

    return $tiktok_id;
  }
}
