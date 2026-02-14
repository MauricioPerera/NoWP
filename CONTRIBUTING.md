# Contributing to NoWP Framework

Thank you for your interest in contributing to NoWP Framework! This document provides guidelines and instructions for contributing.

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on constructive feedback
- Respect differing viewpoints and experiences

## How to Contribute

### Reporting Bugs

Before creating a bug report:
1. Check if the bug has already been reported
2. Verify you're using the latest version
3. Test with a clean installation if possible

When reporting a bug, include:
- PHP version and environment details
- Steps to reproduce the issue
- Expected vs actual behavior
- Error messages and stack traces
- Relevant configuration

### Suggesting Features

Feature suggestions are welcome! Please:
1. Check if the feature has already been suggested
2. Explain the use case and benefits
3. Consider if it fits the project's goals (lightweight, shared hosting compatible)
4. Provide examples of how it would work

### Pull Requests

1. **Fork the repository** and create a new branch
2. **Follow coding standards** (see below)
3. **Write tests** for new functionality
4. **Update documentation** as needed
5. **Keep commits focused** - one feature/fix per PR
6. **Write clear commit messages**

## Development Setup

### Prerequisites

- PHP 8.1+
- MySQL 5.7+
- Composer
- Node.js 18+ (for admin panel and client)

### Setup Steps

```bash
# Clone your fork
git clone https://github.com/your-username/nowp-framework.git
cd nowp-framework

# Install PHP dependencies
composer install

# Configure environment
cp .env.example .env
# Edit .env with your settings

# Run migrations
php cli/migrate.php

# Run tests
composer test
```

### Admin Panel Development

```bash
cd admin
npm install
npm run dev
```

### TypeScript Client Development

```bash
cd client
npm install
npm run dev
```

## Coding Standards

### PHP

- Follow PSR-12 coding style
- Use PHP 8.1+ features (enums, readonly properties, etc.)
- Type hint everything (strict types)
- Document public APIs with PHPDoc
- Keep methods focused and small
- Use meaningful variable names

Example:
```php
<?php

declare(strict_types=1);

namespace NoWP\Content;

class ContentService
{
    public function __construct(
        private readonly ContentRepository $repository,
        private readonly HookSystem $hooks
    ) {}

    public function createContent(array $data): Content
    {
        // Implementation
    }
}
```

### JavaScript/TypeScript

- Use TypeScript for type safety
- Follow ESLint configuration
- Use async/await over promises
- Document public APIs with JSDoc
- Use meaningful variable names
- Keep functions focused and small

Example:
```typescript
/**
 * Fetches content from the API
 */
export async function getContent(id: number): Promise<Content> {
  const response = await fetch(`/api/contents/${id}`);
  return response.json();
}
```

### CSS

- Use CSS custom properties for theming
- Follow BEM naming convention
- Keep selectors simple
- Mobile-first responsive design
- Avoid !important

## Testing

### PHP Tests

We use Pest PHP for testing:

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/pest tests/Unit/Content/ContentServiceTest.php

# Run with coverage
composer test:coverage
```

#### Writing Tests

```php
<?php

use NoWP\Content\ContentService;

it('creates content with valid data', function () {
    $service = new ContentService($repository, $hooks);
    
    $content = $service->createContent([
        'title' => 'Test Post',
        'content' => 'Test content',
        'type' => 'post',
    ]);
    
    expect($content->id)->toBeInt();
    expect($content->title)->toBe('Test Post');
});
```

### JavaScript Tests

We use Vitest for testing:

```bash
cd client
npm test
```

## Documentation

### Code Documentation

- Document all public APIs
- Include examples in documentation
- Explain complex logic with comments
- Keep documentation up to date

### User Documentation

When adding features, update:
- README.md (if it's a major feature)
- Relevant docs in `docs/` directory
- API examples in `examples/`
- CHANGELOG.md

## Performance Considerations

NoWP is designed for shared hosting environments. Keep in mind:

- Memory usage should stay under 256MB
- Response times should be under 100ms
- Disk usage should be minimal
- Avoid N+1 queries
- Use caching where appropriate
- Lazy load when possible

## Security Considerations

- Always use prepared statements for database queries
- Validate and sanitize all user input
- Use CSRF tokens for forms
- Implement rate limiting for sensitive endpoints
- Follow OWASP security guidelines
- Never store sensitive data in logs

## Commit Message Guidelines

Use clear, descriptive commit messages:

```
feat: add content versioning system
fix: resolve N+1 query in content list
docs: update authentication guide
test: add tests for media upload
refactor: simplify query builder interface
perf: optimize content loading with eager loading
```

Prefixes:
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `test:` - Test additions or changes
- `refactor:` - Code refactoring
- `perf:` - Performance improvements
- `chore:` - Maintenance tasks

## Pull Request Process

1. **Create a branch** from `main`
   ```bash
   git checkout -b feat/my-new-feature
   ```

2. **Make your changes** following the guidelines above

3. **Test thoroughly**
   ```bash
   composer test
   ```

4. **Update documentation** as needed

5. **Commit your changes**
   ```bash
   git commit -m "feat: add my new feature"
   ```

6. **Push to your fork**
   ```bash
   git push origin feat/my-new-feature
   ```

7. **Create a Pull Request** on GitHub
   - Describe what the PR does
   - Reference any related issues
   - Include screenshots for UI changes
   - List any breaking changes

8. **Respond to feedback** and make requested changes

9. **Wait for approval** from maintainers

## Areas for Contribution

### High Priority

- Property-based tests for core functionality
- Additional language translations
- More plugin examples
- Theme examples
- Performance optimizations

### Medium Priority

- Additional cache adapters
- More comprehensive error messages
- CLI command improvements
- Admin panel enhancements

### Low Priority

- Additional documentation
- Code refactoring
- Test coverage improvements

## Questions?

If you have questions about contributing:
- Open an issue for discussion
- Check existing documentation
- Review similar implementations in the codebase

## License

By contributing to NoWP Framework, you agree that your contributions will be licensed under the MIT License.

---

Thank you for contributing to NoWP Framework! 🚀
