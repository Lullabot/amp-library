# Changelog
All notable changes (from version 2.0.1 onwards) to this project will be 
documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.1] - 2021-09-24

### Added 
- Support for data-locale attribute on Facebook posts

### Changed
- Replaced Travis with Github Actions for running tests.
- Allowed sebastian/diff 4.x
- Fixed tests 
- Removed Guzzle for HTTPlug, you will need to install a [HTTPlug adapter](https://docs.php-http.org/en/latest/clients.html)