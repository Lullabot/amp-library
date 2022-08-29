##  Some HTML test documents have been taken from the AMP Project. They are listed here.

Note: An explicit commit is noted as these files can change.

* `test-data/full-html/amp_layouts.html` https://github.com/ampproject/amphtml/blob/a26e8789435e15c570645be72f325e85ac3980e3/validator/testdata/feature_tests/amp_layouts.html
* `test-data/full-html/aria.html` https://github.com/ampproject/amphtml/blob/59a990001071daae6d7ee901ec742b16d700bec2/validator/testdata/feature_tests/aria.html
* `test-data/full-html/bad_viewport.html` from https://github.com/ampproject/amphtml/blob/96335e0540532264b3b38d498070f17473692b21/validator/testdata/feature_tests/bad_viewport.html
* `test-data/full-html/duplicate_unique_tags_and_wrong_parent.html` https://github.com/ampproject/amphtml/blob/96335e0540532264b3b38d498070f17473692b21/validator/testdata/feature_tests/duplicate_unique_tags_and_wrong_parents.html
* `test-data/full-html/incorrect_custom_style.html` https://github.com/ampproject/amphtml/blob/8843c4a8fb77631d00455ef08f77fcfbe31ac5d2/validator/testdata/feature_tests/incorrect_custom_style.html
  There is line that is commented out by us as the css parser seems to have problems with parsing the CSS otherwise.
* `test-data/full-html/incorrect_mandatory_style.html` https://github.com/ampproject/amphtml/blob/96335e0540532264b3b38d498070f17473692b21/validator/testdata/feature_tests/incorrect_mandatory_style.html
* `test-data/full-html/javascript_xss.html` https://github.com/ampproject/amphtml/blob/96335e0540532264b3b38d498070f17473692b21/validator/testdata/feature_tests/javascript_xss.html
* `test-data/full-html/link_meta_values.html` https://github.com/ampproject/amphtml/blob/96335e0540532264b3b38d498070f17473692b21/validator/testdata/feature_tests/link_meta_values.html
* `test-data/full-html/mandatory-dimensions.html` https://github.com/ampproject/amphtml/blob/e1aa24df8432963423ee6cec1ce4d57529767e6e/validator/testdata/feature_tests/mandatory_dimensions.html
  There is a trivial modification: `</amp-anum>` is changed to `</amp-anim>`. See https://github.com/ampproject/amphtml/issues/3609
  Also all `<amp-img>` tags are closed. See https://github.com/ampproject/amphtml/issues/3713
* `test-data/full-html/minimum_valid_amp.html` https://github.com/ampproject/amphtml/blob/96335e0540532264b3b38d498070f17473692b21/validator/testdata/feature_tests/minimum_valid_amp.html
* `test-data/full-html/new_and_old_boilerplate_mixed.html` https://github.com/ampproject/amphtml/blob/3bd74c3915f9e29824eebedbf6f14f6c531a3449/validator/testdata/feature_tests/new_and_old_boilerplate_mixed.html
* `test-data/full-html/new_and_old_boilerplate_mixed2.html` https://github.com/ampproject/amphtml/blob/d17719548ca786ec8c3778743da7f2020c7d0460/validator/testdata/feature_tests/new_and_old_boilerplate_mixed2.html
* `test-data/full-html/no_custom_js.html` https://github.com/ampproject/amphtml/blob/96335e0540532264b3b38d498070f17473692b21/validator/testdata/feature_tests/no_custom_js.html
* `test-data/full-html/noscript.html` https://github.com/ampproject/amphtml/blob/e1aa24df8432963423ee6cec1ce4d57529767e6e/validator/testdata/feature_tests/noscript.html
* `test-data/full-html/old-boilerplate.amp.html` https://github.com/ampproject/amphtml/blob/0a056ca50ac8cb9ba8e5a6489baeecb5ed958556/examples/old-boilerplate.amp.html
* `test-data/full-html/regexps.html` https://github.com/ampproject/amphtml/blob/de471567c924ce51e401248ef69c001cee599cfc/validator/testdata/feature_tests/regexps.html
  There is a slight custom modification made by us to this file (see our repo's 36c67aace ) and that is to close the `<amp-audio>` tags in the file that are not closed. This causes problems for PHP dom.
* `test-data/full-html/several_errors.html` from https://github.com/ampproject/amphtml/blob/96335e0540532264b3b38d498070f17473692b21/validator/testdata/feature_tests/several_errors.html
* `test-data/full-html/spec_example.html` https://github.com/ampproject/amphtml/blob/96335e0540532264b3b38d498070f17473692b21/validator/testdata/feature_tests/spec_example.html
* `test-data/full-html/svg.html` https://github.com/ampproject/amphtml/raw/8ab5d550fae93b9a1bb8a06d9fb82ffc08569b44/validator/testdata/feature_tests/svg.html
* `test-data/full-html/track_tag.html` https://github.com/ampproject/amphtml/blob/27ee29ffc3d809fcc8143044d22df9d176ad8169/validator/testdata/feature_tests/track_tag.html
* `test-data/full-html/urls.html` from https://github.com/ampproject/amphtml/blob/eddc6fd2224559cb7ccc6a1e27484e52de3d9301/validator/testdata/feature_tests/urls.html
* `test-data/full-html/validator-amp-accordion.html` https://github.com/ampproject/amphtml/blob/27ee29ffc3d809fcc8143044d22df9d176ad8169/extensions/amp-accordion/0.1/test/validator-amp-accordion.html
* `test-data/full-html/validator-amp-carousel.html` https://github.com/ampproject/amphtml/blob/27ee29ffc3d809fcc8143044d22df9d176ad8169/extensions/amp-carousel/0.1/test/validator-amp-carousel.html
* `test-data/full-html/validator-amp-facebook.html` https://github.com/ampproject/amphtml/blob/27ee29ffc3d809fcc8143044d22df9d176ad8169/extensions/amp-facebook/0.1/test/validator-amp-facebook.html
* `test-data/full-html/validator-amp-jwplayer.html` https://github.com/ampproject/amphtml/blob/27ee29ffc3d809fcc8143044d22df9d176ad8169/extensions/amp-jwplayer/0.1/test/validator-amp-jwplayer.html
* `test-data/full-html/validator-amp-mustache.html` https://github.com/ampproject/amphtml/blob/68e287b5269ed956e8e7694b1e8da5ab478458df/extensions/amp-mustache/0.1/test/validator-amp-mustache.html
* `test-data/full-html/validator-amp-pinterest.html` https://github.com/ampproject/amphtml/blob/27ee29ffc3d809fcc8143044d22df9d176ad8169/extensions/amp-pinterest/0.1/test/validator-amp-pinterest.html
* `test-data/full-html/validator-amp-sidebar.html` https://github.com/ampproject/amphtml/tree/68e287b5269ed956e8e7694b1e8da5ab478458df/extensions/amp-sidebar/0.1/test/validator-amp-sidebar.html
* `test-data/full-html/validator-amp-soundcloud.html` https://github.com/ampproject/amphtml/blob/27ee29ffc3d809fcc8143044d22df9d176ad8169/extensions/amp-soundcloud/0.1/test/validator-amp-soundcloud.html
* `test-data/full-html/validator-amp-springboard-player.html` https://github.com/ampproject/amphtml/blob/27ee29ffc3d809fcc8143044d22df9d176ad8169/extensions/amp-springboard-player/0.1/test/validator-amp-springboard-player.html
* `test-data/full-html/validator-amp-vimeo.html` https://github.com/ampproject/amphtml/raw/27ee29ffc3d809fcc8143044d22df9d176ad8169/extensions/amp-vimeo/0.1/test/validator-amp-vimeo.html
* `test-data/full-html/validator-amp-youtube.html` https://github.com/ampproject/amphtml/blob/27ee29ffc3d809fcc8143044d22df9d176ad8169/extensions/amp-youtube/0.1/test/validator-amp-youtube.html

AMP Project github URL https://github.com/ampproject/amphtml/tree/main/validator

Thanks to the AMP Project for these test files. Please see the individual HTML documents for usage licenses.
