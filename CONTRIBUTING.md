# Contributing to QueueUniqueRunner

Thank you for considering contributing to QueueUniqueRunner! This document provides guidelines and instructions for contributing.

## Code of Conduct

Please be respectful and constructive in all interactions.

## How to Contribute

### Reporting Bugs

- Use the [GitHub Issues](../../issues) with the bug report template
- Include steps to reproduce, expected behavior, and actual behavior
- Include your PHP version, Laravel version, and driver (database/redis)

### Suggesting Features

- Use the [GitHub Issues](../../issues) with the feature request template
- Describe the use case and proposed solution

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add or update tests as needed
5. Ensure all tests pass (`composer test`)
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

## Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/queue-unique-runner.git
cd queue-unique-runner

# Install dependencies
composer install

# Run tests
composer test
# or
vendor/bin/phpunit
```

## Coding Standards

- Follow PSR-12 coding style
- Add type hints to all method parameters and return types
- Write tests for all new features and bug fixes
- Keep methods focused and small
- Use descriptive variable and method names

## Testing

- All new features must include tests
- All bug fixes should include a regression test
- Tests should cover both `database` and `redis` drivers where applicable
- Use the `TestCase` base class from `tests/TestCase.php`

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature

# Run with coverage
vendor/bin/phpunit --coverage-html coverage
```

## License

By contributing, you agree that your contributions will be licensed under the Apache License 2.0.
