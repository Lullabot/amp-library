# AMP PHP Library

### What is the AMP PHP Library?

The AMP PHP Library is an open source and pure PHP Library that:
- Works with whole or partial HTML documents (or strings). Specifically, the AMP PHP Library:
 - Reports compliance of the whole/partial HTML document with the [AMP HTML standard](https://www.ampproject.org/)
 - Implements an AMP HTML validator in pure PHP to report compliance of an arbitrary HTML document / HTML fragment with the AMP HTML standard. This validator is a ported subset of the [canonical validator](https://github.com/ampproject/amphtml/tree/master/validator) that is implemented in javascript. In particular our PHP validator does not (yet) support template, cdata, css and layout validation. Otherwise, it supports tag specification validation, attribute specification validation and will report missing, illegal, mandatory, unique (etc) tags and attributes.
 - Using the feedback given by the validator, tries to "correct" some issues found in the HTML to make it more AMP HTML compliant. This would involve removing illegal attribtes (e.g. `style`) or illegal tags (e.g. `<script>`) in the body portion of the HTML document. This correction is currently basic.
 - Converts some non-amp elements to their AMP equivalents automatically. So an `<img>` tag is automatically converted to an `<amp-img>` tag and `<iframe>` is converted to an `<amp-iframe>`. More such automatic conversions will be available in the future.
- Provides both a console and programmatic interface with which to call the library. It works like this: the programmer/user provides some HTML and we return (1) The AMPized HTML (2) A list of warnings reported by the Validator (3) A list of fixes/tag conversions made by the library

### Use Cases

- Currently the AMP PHP Library is used by the [Drupal AMP Module](https://www.drupal.org/project/amp) to report issues with user entered, arbitrary HTML (originating from Rich Text Editors) and converts the HTML to AMPized HTML (as much as possible)
- The AMP PHP Library command line validator can be used for experimentation and to do HTML to AMP HTML conversion of HTML files. While the [canonical validator](https://github.com/ampproject/amphtml/tree/master/validator) only validates, our library tries to make corrections too. As noted above, our validator is a subset of the canononical validator but already covers a lot of cases
- The AMP PHP Library can be used in any other PHP project to "convert" HTML to AMP HTML and report validation issues. It does not have any non-PHP dependencies and will work in PHP 5.5+

### Setup

If you're familiar with [composer](https://getcomposer.org/) then you should not have any problems in getting this to work for your PHP project. If you're not familiar with composer then please read up on it before trying to set this up!

Using this in Drupal requires some specific steps. Please refer to the [Drupal AMP Module](https://www.drupal.org/project/amp) documentation.

For all other scenarios, briefly, the setup steps are:
- `git clone` this repo, `cd` into it and type in `$ composer install` at the command prompt to get all the dependencies of the library. Now you'll be able to use the command line AMP html converter `amp-console` (or equivalently `amp-console.php`
- To use this in your composer based PHP project, refer to [composer docs here](https://getcomposer.org/doc/05-repositories.md#loading-a-package-from-a-vcs-repository)

But essentially, you need to add the following snippet in your `composer.json`:

```json
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Lullabot/amp-library"
        }
    ],
    "require": {
        "lullabot/amp": "dev-master"
    }
```

After doing this issue `$ composer update`

### Using the command line `amp-console`

```bash
$ cd <amp-php-library-repo-cloned-location>
$ composer install <----- Do this if you haven't already
$ ./amp-console amp:convert --help
$ ./amp-console amp:convert <name-of-html-document> <options> 
```

Please note that the `--help` command line option is your friend. Use that when confused!

A few example HTML files are available in the test-html folder for you to test drive so that you can get a flavor of the AMP PHP library.

```bash
$ ./amp-console amp:convert test-html/sample-html-fragment.html
$ ./amp-console amp:convert test-html/regexps.html --full-document
```
Note that you need to provide `--full-document` if you're providing a full html document file for conversion.

Lets see the output output of the first example command above. The first few lines is the AMPized HTML provided by our library. The rest of the headings are self explanatory.

```
$ cd <amp-php-library-repo-cloned-location>
$ ./amp-console amp:convert test-html/sample-html-fragment.html 
Line 1: <p><a>Run</a></p>
Line 2: <p><a href="http://www.cnn.com">CNN</a></p>
Line 3: <amp-img src="http://i2.cdn.turner.com/cnnnext/dam/assets/160208081229-gaga-superbowl-exlarge-169.jpg" width="780" height="438" layout="responsive"></amp-img><p><a href="http://www.bbcnews.com" target="_blank">BBC</a></p>
Line 4: <p></p>
Line 5: <p>This is a <!-- test comment -->  sample </p><div>sample</div> paragraph
Line 6: <amp-iframe src="https://www.reddit.com" layout="responsive" sandbox="allow-scripts allow-same-origin"></amp-iframe>
Line 7: 


ORIGINAL HTML
---------------
Line  1: <p><a href="javascript:run();">Run</a></p>
Line  2: <p><a style="margin: 2px;" href="http://www.cnn.com" target="_parent">CNN</a></p>
Line  3: <img src="http://i2.cdn.turner.com/cnnnext/dam/assets/160208081229-gaga-superbowl-exlarge-169.jpg">
Line  4: <p><a href="http://www.bbcnews.com" target="_blank">BBC</a></p>
Line  5: <p><INPUT type="submit" value="submit"></p>
Line  6: <p>This is a <!-- test comment --> <!-- [if IE9] --> sample <div onmouseover="hello();">sample</div> paragraph</p>
Line  7: <iframe src="https://www.reddit.com"></iframe>
Line  8: <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
Line  9: <style></style>
Line 10: 


AMP-HTML Validation Issues
--------------------------
FAIL
- Line  1: Invalid URL protocol 'javascript:' for attribute 'href' in tag 'a'. [category: DISALLOWED_HTML] [code: INVALID_URL_PROTOCOL]
- Line  2: The attribute 'style' may not appear in tag 'a'. [category: DISALLOWED_HTML] [code: DISALLOWED_ATTR]
- Line  2: The attribute 'target' in tag 'a' is set to the invalid value '_parent'. [category: DISALLOWED_HTML] [code: INVALID_ATTR_VALUE]
- Line  5: The tag 'input' is disallowed. [category: DISALLOWED_HTML] [code: DISALLOWED_TAG]
- Line  6: The attribute 'onmouseover' may not appear in tag 'div'. [category: DISALLOWED_HTML] [code: DISALLOWED_ATTR]
- Line  8: The attribute 'src' in tag 'amphtml engine v0.js script' is set to the invalid value 'https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js'. (see https://github.com/ampproject/amphtml/blob/master/spec/amp-html-format.md#scrpt) [category: CUSTOM_JAVASCRIPT_DISALLOWED] [code: INVALID_ATTR_VALUE]
- Line  8: The mandatory attribute 'async' is missing in tag 'amphtml engine v0.js script'. (see https://github.com/ampproject/amphtml/blob/master/spec/amp-html-format.md#scrpt) [category: DISALLOWED_HTML] [code: MANDATORY_ATTR_MISSING]
- Line  8: The parent tag of tag 'script' is 'body', but it can only be 'head'. (see https://github.com/ampproject/amphtml/blob/master/spec/amp-html-format.md#scrpt) [category: DISALLOWED_HTML] [code: WRONG_PARENT_TAG]
- Line  9: The mandatory attribute 'amp-custom' is missing in tag 'author stylesheet'. (see https://github.com/ampproject/amphtml/blob/master/spec/amp-html-format.md#stylesheets) [category: DISALLOWED_HTML] [code: MANDATORY_ATTR_MISSING]
- Line  9: The parent tag of tag 'style' is 'body', but it can only be 'head'. (see https://github.com/ampproject/amphtml/blob/master/spec/amp-html-format.md#stylesheets) [category: DISALLOWED_HTML] [code: WRONG_PARENT_TAG]

Fixes made based on validation issues discovered (see above)
------------------------------------------------------------
- Line 1: a.href attribute was removed due to validation issues. [context: <a href="javascript:run();">] 
- Line 2: a.style attribute was removed due to validation issues. [context: <a style="margin: 2px;" href="http://www.cnn.com" target="_parent">] 
- Line 2: a.target attribute was removed due to validation issues. [context: <a href="http://www.cnn.com" target="_parent">] 
- Line 3: img tag was converted to the amp-img tag. [context: <img src="http://i2.cdn.turner.com/cnnnext/dam/assets/160208081229-gaga-superbowl-exlarge-169.jpg">] 
- Line 5: input tag was removed due to validation issues. [context: <input type="submit" value="submit">] 
- Line 6: div.onmouseover attribute was removed due to validation issues. [context: <div onmouseover="hello();">] 
- Line 6: HTML conditional comments not allowed. tag was removed due to validation issues.
- Line 7: iframe tag was converted to the amp-iframe tag. [context: <iframe src="https://www.reddit.com">] 
- Line 8: script.src attribute was removed due to validation issues. [context: <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js">] 
- Line 8: script tag was removed due to validation issues. [context: <script>] 
- Line 9: style tag was removed due to validation issues. [context: <style>] 

```

### Caveats

- This is beta quality code. You are likely to encounter bugs and errors, both fatal and harmless. Please help us improve this library by using the GitHub issue tracker on this repository to report errors.
- The library is currently not hosted on [Packagist](https://packagist.org) but we plan to do that in the near future

### Sponsored by

- Google for creating the AMP Project and sponsoring development
- Lullabot for development of the module, theme, and library to work with the specifications

