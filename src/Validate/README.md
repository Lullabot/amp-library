_This note is for developers of the AMP PHP Library only. You don't need to read it if you are a consumer of the library_

### A Note on the Source Files in this Directory

The source files in this directory represent a Javascript to PHP port of the AMP HTML [validator](https://github.com/ampproject/amphtml/tree/main/validator) (aka "canonical validator").

The main files ported over are:
- [validator.js](https://github.com/ampproject/amphtml/blob/main/validator/validator.js). (This is the main file of the validator that we have ported.)
- [validator-full.js](https://github.com/ampproject/amphtml/blob/main/validator/validator-full.js)


Please read the comments in each source file in the `src/Validate` directory to get a better understanding of their relationship with the canonical validator source code.

Of course, there are a few differences:
- The AMP HTML validator is a large project and we have not ported everything over. Our validator is a subset of the canonical validator.
- The AMP HTML project uses a SAX like HTML parser while we use a HTML DOM parser. Thats because we want to perform manipulations in the DOM tree.
- The output of our validator is used as an input to "correction" passes (see code in the [`src/Pass`](https://github.com/Lullabot/amp-library/tree/main/src/Pass) directory). In the correction passes we try to fix as many validation issues automatically, as possible. Here our emphasis is totally different from the canonical validator which only reports problems (but never corrects them). Because we need to accomodate this requirement, our validator codebase has a few differences and additions vis-a-vis the canonical validator.
- Our validator deals with both HTML fragments and full HTML documents while the canonical validator is designed to deal with whole HTML documents at a time. It is because of this feature of dealing with HTML fragments that our validator can be used to process inputs from rich text editors etc. Again, this causes code divergences between the ported and canonical validators.

The canonical validator has its own HTML5 parser ( [htmlparser.js](https://github.com/ampproject/amphtml/blob/main/validator/htmlparser.js), [htmlparser-interface.js](https://github.com/ampproject/amphtml/blob/main/validator/htmlparser-interface.js) etc.) and CSS parser ( [parse-css.js](https://github.com/ampproject/amphtml/blob/main/validator/parse-css.js), [tokenize-css.js](https://github.com/ampproject/amphtml/blob/main/validator/tokenize-css.js), [css-selectors.js](https://github.com/ampproject/amphtml/blob/main/validator/css-selectors.js)). Instead of porting these over, we use projects in the PHP packagist ecosystem to perform the tasks of parsing CSS and HTML:
- For HTML5 parsing we use the [masterminds/html5-php](https://github.com/Masterminds/html5-php) library
- For CSS parsing we use the [sabberworm/php-css-parser](https://github.com/sabberworm/PHP-CSS-Parser) library

If you're interested in learning about the `src/Spec` folder and its relationship to the [validator-main.protoascii](https://github.com/ampproject/amphtml/blob/main/validator/validator-main.protoascii), `validator_gen_js.py` etc. files in the canonical validator, please see the [README](https://github.com/Lullabot/amp-library/blob/main/src/Spec/README.md) in the `src/Spec` folder.

Consult the main [README](https://github.com/Lullabot/amp-library/blob/main/README.md) for more details on the validation capabilities of our validator.
