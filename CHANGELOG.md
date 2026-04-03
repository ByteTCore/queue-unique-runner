## [1.0.0] - 2026-04-03
Initial release

## [1.0.1] - 2026-04-03
- Merge remote-tracking branch 'origin/master' (252f4f1)
- make timestamp fields nullable in queue_unique_runner_locks_table (76f1bb9)
- chore: release 1.0.0 (2d5df12)

## [1.0.0] - 2026-04-03
Initial release

# Changelog

All notable changes to `bytetcore/queue-unique-runner` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-01-01

### Added
- Initial release
- Database lock driver with atomic locking via unique constraints
- Redis lock driver with SET NX EX and Lua scripts for atomic operations
- `UniqueRunner` job middleware for flexible per-job configuration
- `RunsOnUniqueRunner` trait for quick integration
- Heartbeat mechanism using `pcntl_alarm` to extend lock TTL for long-running jobs
- Automatic crash recovery via TTL expiration
- Per-class and per-instance lock scoping
- `queue-unique-runner:prune` Artisan command for cleaning expired database locks
- Configurable TTL, retry delay, and heartbeat interval
- Auto-detection of server identity via hostname:pid
- Support for PHP 8.0+ and Laravel 9.x through 12.x
- Comprehensive test suite
