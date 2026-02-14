# Default Theme

A simple, responsive theme for the WordPress Alternative Framework.

## Features

- Responsive design (mobile-first)
- Clean, modern layout
- Blog post listing
- Single post view
- Page templates
- Navigation menu
- Footer widgets

## Installation

This theme is included by default. To activate:

1. Navigate to Admin Panel → Themes
2. Click "Activate" on Default Theme

## Templates

### Available Templates

- `index.php` - Homepage and blog listing
- `single.php` - Single post view
- `page.php` - Static page view
- `header.php` - Site header
- `footer.php` - Site footer
- `sidebar.php` - Sidebar widgets

### Template Hierarchy

The theme follows this template hierarchy:

1. **Homepage**: `index.php`
2. **Single Post**: `single.php` → `index.php`
3. **Page**: `page.php` → `index.php`
4. **404**: `404.php` → `index.php`

## Customization

### Colors

Edit `assets/css/style.css`:

```css
:root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --text-color: #333;
    --background-color: #fff;
}
```

### Layout

The theme uses a simple grid layout:
- Header: Full width
- Content: 70% width
- Sidebar: 30% width
- Footer: Full width

### Fonts

Default fonts:
- Headings: 'Helvetica Neue', sans-serif
- Body: 'Arial', sans-serif

## Theme Functions

### Available Functions

```php
// Get site title
theme_title();

// Get site URL
theme_url();

// Get post content
the_content();

// Get post title
the_title();

// Get post date
the_date();

// Get post author
the_author();
```

## Child Theme

To create a child theme:

1. Create new directory: `themes/my-child-theme/`
2. Create `theme.json`:

```json
{
    "name": "My Child Theme",
    "parent": "default",
    "version": "1.0.0"
}
```

3. Override templates by creating files with same names

## Support

For theme issues:
- Documentation: https://docs.your-domain.com/themes
- GitHub: https://github.com/your-repo/framework/issues
