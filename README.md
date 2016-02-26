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

### Caveats

- This is beta quality code. You are likely to encounter bugs and errors, both fatal and harmless. Please help us improve this library by using the GitHub issue tracker on this repository to report errors.
- The library is currently not hosted on [Packagist](https://packagist.org) but we plan to do that in the near future

### Sponsored by

- Google for creating the AMP Project and sponsoring development
- Lullabot for development of the module, theme, and library to work with the specifications

