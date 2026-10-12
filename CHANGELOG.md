# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0]

### Added
- `ProxyMiddleware`: client ip, scheme and port behind trusted proxies (ips or ranges, IPv4 and IPv6), from `X-Forwarded-For`, `X-Forwarded-Proto` and the `Host` header.

### Fixed
- `ErrorHandler` sends the error response without the headers of a custom `HttpException` if they are invalid, instead of throwing, and logs them.
- `RequestBodyMiddleware` responds with `400` to form bodies it parses (e.g. `PUT`) above `max_input_vars` or `max_input_nesting_level`, instead of truncating them.
- `UrlGenerator::url()` removes the user info of the base uri.

### Security
- `TrailingSlashMiddleware` could redirect to another host, e.g. for `/\evil.com/`, with PSR-7 implementations that keep invalid characters in the path. The location is percent-encoded now.

## [1.0.0] - 2026-10-10

Initial release.

[Unreleased]: https://github.com/semperton/framework/compare/1.1.0...HEAD
[1.1.0]: https://github.com/semperton/framework/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/semperton/framework/releases/tag/1.0.0
