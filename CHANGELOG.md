# CHANGELOG

Changelog for Laravel Smokescreen

## 2.0.2

- Update to laravel 8.0

## 2.0.1

- Update to laravel 7.0
- Switch from deprecated methods

## 2.0.0

- Correct advertised versions (`loadMissing` added in laravel 5.6)
- Officially dropping support for laravel 5.5 (was already not working)
- Drop support for php 7.0 (new requirement 7.1 from laravel 5.6)
- Bumping major version just to be safe
- [Resolve transformers from class name using app](https://github.com/rexlabsio/smokescreen-laravel-php/issues/16)

## 1.3.2

- Add support for laravel 6.0

## 1.3.1

- Add support for laravel 5.8

## 1.3.0

- Relationships now load via loadMissing rather than load to prevent accidentally reloading relationships

## Unreleased

- Add better type inference in `transform()`
