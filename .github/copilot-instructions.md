# GitHub Copilot Instructions

## Commit Messages

- **Always write commit messages in English**, regardless of the language used in the conversation or code comments.
- Use the imperative mood (e.g., "Add feature" not "Added feature" or "Adds feature").
- Keep the subject line under 72 characters.
- Provide a short, descriptive summary of *what* changed and *why*.

### Examples

Good:
```
Fix unescaped DB parameter warning in uninstall.php
Add sitemap generation for custom post types
Refactor meta tag rendering to use filter hooks
```

Avoid:
```
修復 uninstall.php 的警告
update stuff
fixed bug
```

## Code Style

- Follow WordPress coding standards for PHP.
- Match the existing style of the surrounding code.
- Keep changes minimal and focused on the task at hand.
