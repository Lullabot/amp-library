[![Build Status](https://travis-ci.org/Lullabot/amp-library.svg?branch=master)](https://travis-ci.org/Lullabot/amp-library)
# AMP PHP Library

An open source PHP library and console utility to convert HTML to [AMP HTML](https://www.ampproject.org/) and report HTML compliance with the AMP HTML specification.

### What is the AMP PHP Library?

The AMP PHP Library is an open source and pure PHP Library that:
- Works with whole or partial HTML documents (or strings). Specifically, the AMP PHP Library:
 - Reports compliance of a whole/partial HTML document with the [AMP HTML specification](https://www.ampproject.org/). We implement an AMP HTML validator in pure PHP to report compliance of an arbitrary HTML document / HTML fragment with the AMP HTML standard. This validator is a ported subset of the [canonical validator](https://github.com/ampproject/amphtml/tree/master/validator) that is implemented in JavaScript 
    - Specifically, the PHP validator supports tag specification validation, attribute specification validation, CDATA validation, CSS validation, layout validation, template validation and attribute property-value pair validation. It will report tags and attributes that are missing, illegal, mandatory according to spec but not present, unique according to spec but multiply present, having wrong parents or ancestors or children and so forth.
    - _Note_: while the AMP PHP library (already) supports many of the features and capabilities of the canonical validator, it is not intended to achieve parity in _every_ respect with the canonical validator. Even _within_ the features we support (e.g. CSS validation) there may be certain validation issues that we don't flag but the canonical validator does.   
 - Using the feedback given by the in-house PHP validator, the AMP PHP library tries to "correct" some issues found in the HTML to make it more AMP HTML compliant. This would, for example, involve:
    - Removing illegal attributes e.g. `style` attribute within `<body>` tag 
    - Removing all kinds of illegal tags e.g. `<script>` within `<body>` tag, a tag with a disallowed ancestor, a duplicate unique tag etc.
    - Removing illegal property value pairs e.g. removing `minimum-scale=hello` from `<meta name="viewport" content="width=device-width,minimum-scale=hello">`
    - Adding or correcting the tags necessary for a minimally valid AMP document:
      - `<head>`, `<body>`, `meta viewport`, `meta charset`, `<style>` and `<noscript>` tags
      - The `link rel=canonical` tag if you let the library know the canonical path of the document
      - Javascript `<script>` tags for the various AMP components and generic AMP Javascript `<script>` tag
      - Boilerplate CSS
    - If there are mutually exclusive attributes for a tag, removing all but one of them
    - Fixing issues with `amp-img` tags that have problems like inconsistent units, invalid attributes, missing mandatory attributes, invalid implied or specified layouts.
    - _Notes_: 
       - The library does a decent job of _removing_ bad things and in a few cases makes some corrections/additions to the HTML. As the library cannot understand the true _intention_ of the user, a lot the validation problems in the HTML may eventually need to be fixed manually by the human.
       - In general, the library will try to fix validation errors in `<head>` and if its not successful in doing so, _remove_ those tags from `<head>`. Within `<body>` the AMP PHP library is less aggressive and in most cases will _not_ remove the tag from the document if the tag does not validate after it attempts any fixes on it.
       - The library needs to be provided with well formed HTML / HTML5. Please don't give it faulty, incorrect html (e.g. non closed `<div>` tags etc). The correction it does is related to AMP HTML standard issues only. Use a HTML tidying library if you expect your HTML to be malformed.
 - Converts some non-amp elements to their AMP equivalents automatically
    - A `<img>` tag is converted to an `<amp-img>` tag
    - A `<iframe>` tag is converted to an `<amp-iframe>` tag
    - A [`<audio>`](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/audio-to-amp-audio-conversion-fragment.html) tag is converted to an `<amp-audio>` tag
    - A [`<video>`](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/video-fragment-and-placeholder-test.html) tag is converted to an `<amp-video>` tag
    - [Twitter embed code](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/twitter-fragment.html) for tweets is converted to an `<amp-twitter>` tag.
    - [Instagram embed code](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/instagram-fragment.html) for instagrams is converted to an `<amp-instagram>` tag.
    - [Youtube embed code](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/youtube-fragment.html) for videos is converted to an `<amp-youtube>` tag
    - [Dailymotion embed code](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/dailymotion-fragment.html) for videos is converted to an `<amp-dailymotion>` tag
    - [Pinterest embed code](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/pinterest-fragment.html) for pins is converted to an `<amp-pinterest>` tag
    - [Soundcloud embed code](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/soundcloud-fragment.html) for audio music is converted to an `<amp-soundcloud>` tag
    - [Vimeo embed code](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/vimeo-fragment.html) for videos is converted to an `<amp-vimeo>` tag
    - [Vine embed code](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/vine-fragment.html) for videos is converted to an `<amp-vine>` tag
    - Facebook [iframe](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/facebook-iframe-fragment.html) and [Javascript SDK](https://github.com/Lullabot/amp-library/blob/master/tests/test-data/fragment-html/facebook-non-iframe-fragment.html) embed code for posts and videos is converted to an `<amp-facebook>` tag
    - _Notes_: 
       - Some of these embed code conversions may not have the advanced features you may require. File an issue if you need enhancements to the functionality already provided or new embed code conversions
       - Some of the embed codes have an associated `<script>` tag. These conversions will work even if no `<script>` tag was added to your HTML document. The AMP library will add the appropriate AMP component `<script>` tag to the `<head>` if it is provided a full html document.
       - You may experiment with the command line utility `amp-console` on the above HTML fragments to see how the converted HTML looks
- Provides both a console and programmatic interface with which to call the library. It works like this: the developer first provides some HTML. After processing it, the library returns: 
    - The AMPized HTML
    - A list of validation errors in the HTML provided 
    - A description of fixes and embed code conversions made to the HTML  

### Use Cases

- Currently the AMP PHP Library is used by the [Drupal AMP Module](https://www.drupal.org/project/amp) to report issues with user entered, arbitrary HTML (originating from Rich Text Editors) and converting the HTML to AMPized HTML (as much as possible)
- The AMP PHP Library command line validator can be used for experimentation and to do HTML to AMP HTML conversion of HTML files. While the [canonical validator](https://github.com/ampproject/amphtml/tree/master/validator) only validates, our library tries to make corrections too. As noted above, our validator is a subset of the canonical validator but already covers a lot of cases
- The AMP PHP Library can be used in any other PHP project to "convert" HTML to AMP HTML and report validation issues. It does not have any non-PHP dependencies and will work in PHP 5.5 and higher. It will also work in recent versions of [HHVM](http://hhvm.com/).

### Setup

The project uses a [composer](https://getcomposer.org/) workflow. If you're not familiar with composer then please read up on it before trying to set this up.

Using this in Drupal requires some specific steps. Please refer to the [Drupal AMP Module](https://www.drupal.org/project/amp) documentation.

For all other scenarios, continue reading.

#### Setup for command line console

`git clone` this repo, `cd` into it and type in `$ composer install` at the command prompt to get all the dependencies of the library. Now you'll be able to use the command line AMP html converter `amp-console` (or equivalently `amp-console.php`

##### Running phpunit tests

After doing a `$ composer install` for setting up the command line console, you can run some [phpunit](https://phpunit.de/) tests

```bash
$ vendor/bin/phpunit tests
```

##### Looking at test coverage

To see test coverage data first ensure you have the xdebug extenstion installed in your PHP installation.

```bash
$ php -m | grep xdebug # should output xdebug
$ vendor/bin/phpunit tests --coverage-html=coverage-data
$ cd coverage-data
$ firefox index.html
```

#### Setup for your composer based PHP project

To use this in your composer based PHP project, refer to [composer docs here](https://getcomposer.org/doc/05-repositories.md#loading-a-package-from-a-vcs-repository) to make changes to your `composer.json`

Or you can simply do `$ composer require lullabot/amp:"^1.0.0"` to fetch the library from [here](https://packagist.org/packages/lullabot/amp) and automatically update your `composer.json`

##### Advanced
Should you wish to follow the bleeding edge you can do `$ composer require lullabot/amp:"dev-master"`. Note that this will create a `.git` folder in `vendor/lullabot/amp`. If you want to avoid that,  do `$ composer require lullabot/amp:"dev-master" --prefer-dist`

### Using the command line `amp-console`

```bash
$ cd <amp-php-library-repo-cloned-location>
# Do this if you haven't already
$ composer install
$ ./amp-console amp:convert --help
$ ./amp-console amp:convert <name-of-html-document> <options> 
```

Please note that the `--help` command line option is your friend. Use that when confused!

A few example HTML files are available in the test-html folder for you to test drive so that you can get a flavor of the AMP PHP library.

```bash
$ ./amp-console amp:convert sample-html/sample-html-fragment.html
$ ./amp-console amp:convert sample-html/several_errors.html --full-document
```
Note that you need to provide `--full-document` if you're providing a full html document file for conversion.

Lets see the output of the first example command above. The first few lines is the AMPized HTML provided by our library. The rest of the headings are self explanatory.

```html
$ cd <amp-php-library-repo-cloned-location>
$ ./amp-console amp:convert sample-html/sample-html-fragment.html 
Line  1: <p><a>Run</a></p>
Line  2: <p><a href="http://www.cnn.com">CNN</a></p>
Line  3: <amp-img src="http://i2.cdn.turner.com/cnnnext/dam/assets/160208081229-gaga-superbowl-exlarge-169.jpg" width="780" height="438" layout="responsive"></amp-img>
Line  4: <p><a href="http://www.bbcnews.com" target="_blank">BBC</a></p>
Line  5: <p></p>
Line  6: <p>This is a <!-- test comment -->sample </p><div>sample</div> paragraph
Line  7: <amp-iframe height="315" width="560" sandbox="allow-scripts allow-same-origin" layout="responsive" src="https://www.reddit.com"></amp-iframe>
Line  8: 
Line  9: 
Line 10: 


ORIGINAL HTML
---------------
Line  1: <p><a style="color: red;" href="javascript:run();">Run</a></p>
Line  2: <p><a style="margin: 2px;" href="http://www.cnn.com" target="_parent">CNN</a></p>
Line  3: <img src="http://i2.cdn.turner.com/cnnnext/dam/assets/160208081229-gaga-superbowl-exlarge-169.jpg">
Line  4: <p><a href="http://www.bbcnews.com" target="_blank">BBC</a></p>
Line  5: <p><INPUT type="submit" value="submit"></p>
Line  6: <p>This is a <!-- test comment -->sample <div onmouseover="hello();">sample</div> paragraph</p>
Line  7: <iframe src="https://www.reddit.com"></iframe>
Line  8: <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
Line  9: <style></style>
Line 10: 


Transformations made from HTML tags to AMP custom tags
-------------------------------------------------------

<img src="http://i2.cdn.turner.com/cnnnext/dam/assets/160208081229-gaga-superbowl-exlarge-169.jpg"> at line 3
 ACTION TAKEN: img tag was converted to the amp-img tag.

<iframe src="https://www.reddit.com"> at line 7
 ACTION TAKEN: iframe tag was converted to the amp-iframe tag.


AMP-HTML Validation Issues and Fixes
-------------------------------------
FAIL

<a style="color: red;" href="javascript:run();"> on line 1
- The attribute 'style' may not appear in tag 'a'.
   [code: DISALLOWED_ATTR  category: DISALLOWED_HTML]
   ACTION TAKEN: a.style attribute was removed due to validation issues.
- Invalid URL protocol 'javascript:' for attribute 'href' in tag 'a'.
   [code: INVALID_URL_PROTOCOL  category: DISALLOWED_HTML]
   ACTION TAKEN: a.href attribute was removed due to validation issues.

<a style="margin: 2px;" href="http://www.cnn.com" target="_parent"> on line 2
- The attribute 'style' may not appear in tag 'a'.
   [code: DISALLOWED_ATTR  category: DISALLOWED_HTML]
   ACTION TAKEN: a.style attribute was removed due to validation issues.
- The attribute 'target' in tag 'a' is set to the invalid value '_parent'.
   [code: INVALID_ATTR_VALUE  category: DISALLOWED_HTML]
   ACTION TAKEN: a.target attribute was removed due to validation issues.

<input type="submit" value="submit"> on line 5
- The tag 'input' is disallowed.
   [code: DISALLOWED_TAG  category: DISALLOWED_HTML]
   ACTION TAKEN: input tag was removed due to validation issues.

<div onmouseover="hello();"> on line 6
- The attribute 'onmouseover' may not appear in tag 'div'.
   [code: DISALLOWED_ATTR  category: DISALLOWED_HTML]
   ACTION TAKEN: div.onmouseover attribute was removed due to validation issues.

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js"> on line 8
- The tag 'script' is disallowed except in specific forms.
   [code: GENERAL_DISALLOWED_TAG  category: CUSTOM_JAVASCRIPT_DISALLOWED]
   ACTION TAKEN: script tag was removed due to validation issues.

<style> on line 9
- The parent tag of tag 'style' is 'body', but it can only be 'head'.
   [code: WRONG_PARENT_TAG  category: DISALLOWED_HTML see: https://www.ampproject.org/docs/reference/spec.html#required-markup]
   ACTION TAKEN: style tag was removed due to validation issues.
```

### Using the library in a composer based PHP project

First, follow the setup steps above if you're using this in a composer based project.

Sample code to get started:

```php
<?php

use Lullabot\AMP\AMP;
use Lullabot\AMP\Validate\Scope;

// Create an AMP object
$amp = new AMP();

// Notice this is a HTML fragment, i.e. anything that can appear below <body>
$html =
    '<p><a href="javascript:run();">Run</a></p>' . PHP_EOL .
    '<p><a style="margin: 2px;" href="http://www.cnn.com" target="_parent">CNN</a></p>' . PHP_EOL .
    '<p><a href="http://www.bbcnews.com" target="_blank">BBC</a></p>' . PHP_EOL .
    '<p><INPUT type="submit" value="submit"></p>' . PHP_EOL .
    '<p>This is a <div onmouseover="hello();">sample</div> paragraph</p>';

// Load up the HTML into the AMP object
// Note that we only support UTF-8 or ASCII string input and output. (UTF-8 is a superset of ASCII) 
$amp->loadHtml($html);

// If you're feeding it a complete document use the following line instead
// $amp->loadHtml($html, ['scope' => Scope::HTML_SCOPE]);

// If you want some performance statistics (see https://github.com/Lullabot/amp-library/issues/24)
// $amp->loadHtml($html, ['add_stats_html_comment' => true]);

// Convert to AMP HTML and store output in a variable
$amp_html = $amp->convertToAmpHtml();

// Print AMP HTML
print($amp_html);

// Print validation issues and fixes made to HTML provided in the $html string
print($amp->warningsHumanText());

// warnings that have been passed through htmlspecialchars() function
// print($amp->warningsHumanHtml());

// You can do the above steps all over again without having to create a fresh object
// $amp->loadHtml($another_string)
// ...
// ...

```

### Tips
- Its probably not a good idea to run the library on your HTML dynamically on _every_ page view. You should try caching the results of `$amp->convertToAmpHtml()` once the library has run. If you're using the library from a CMS then you should consider using the caching facilities provided by the CMS.

### Caveats and Known issues
- We only support UTF-8 string input and output from the library. If you're using ASCII, then you don't need to worry as UTF-8 is a superset of ASCII. If you're using another encoding like Latin-1 (etc.) you'll need to convert to UTF-8 strings before you use this library 
- If you have `<img>`s with `https` urls _and_ they don't have height/width attributes _and_ you are using PHP 5.6 or higher _and_ you have not listed any certificate authorities (`cafile`) in your `php.ini` file  _then_ the library may have problems converting these to `<amp-img>`. This is because of http://php.net/manual/en/migration56.openssl.php . That link also has a work around.
- If your `<amp-pinterest>` pins are appearing "chopped off" (after pinterest embed code conversion) try the workaround [here](https://github.com/Lullabot/amp-library/issues/46#issuecomment-230424580) 

### Useful Links
- [Composer homepage](https://packagist.org/packages/lullabot/amp) for the AMP PHP Library on [Packagist](https://packagist.org/), the PHP package repository
- AMP Project [Homepage](https://www.ampproject.org/)
- AMP Project [code repository](https://github.com/ampproject/amphtml) on Github
- [AMP HTML JavaScript validator subtree](https://github.com/ampproject/amphtml/tree/master/validator) on Github within the AMP Project code repository
- [Technical Specification](https://github.com/ampproject/amphtml/blob/master/validator/validator-main.protoascii) of AMP HTML in [Protocol Buffers](https://developers.google.com/protocol-buffers/) ASCII message format. See [here](https://github.com/ampproject/amphtml/blob/master/validator/validator.proto) for the Schema definition of the technical specification

### Useful Links for amp-library developers

- [Notes](https://github.com/Lullabot/amp-library/blob/master/src/Spec/README.md) on the contents of the `src/Spec` folder
- [Notes](https://github.com/Lullabot/amp-library/blob/master/src/Validate/README.md) on the contents of the `src/Validate` folder

You can ignore these links if you simply plan to _use_ this library and not develop for it

### Third-party libraries

- Symfony:
    - [takeit/amp-html-bundle](https://github.com/takeit/AmpHtmlBundle)

- Drupal:
    - [Drupal AMP Module](https://www.drupal.org/project/amp)

### Sponsored by

- Google for creating the AMP Project and sponsoring development
- Lullabot for development of the module, theme, and library to work with the specifications

