# Example Plugin

A simple example plugin demonstrating the capabilities of the WordPress Alternative Framework plugin system.

## Features

This plugin demonstrates:

1. **Custom API Endpoints**: Registers two custom endpoints
   - `GET /api/example/hello` - Returns a greeting message
   - `POST /api/example/process` - Processes posted data

2. **Action Hooks**: Listens to content lifecycle events
   - `content.created` - Triggered when content is created
   - `content.updated` - Triggered when content is updated

3. **Filter Hooks**: Modifies data before it's returned
   - `content.data` - Adds custom fields to content responses

## Installation

1. Copy the `example-plugin` directory to the `plugins/` folder
2. Activate the plugin through the admin panel or programmatically

## Usage

### Testing Custom Endpoints

```bash
# Test the hello endpoint
curl http://your-site.com/api/example/hello

# Test the process endpoint
curl -X POST http://your-site.com/api/example/process \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

### Expected Responses

**GET /api/example/hello**
```json
{
  "message": "Hello from Example Plugin!",
  "version": "1.0.0",
  "timestamp": "2026-02-13 10:30:00"
}
```

**POST /api/example/process**
```json
{
  "success": true,
  "processed": true,
  "data": {"test": "data"},
  "message": "Data processed by Example Plugin"
}
```

## Plugin Structure

```
example-plugin/
├── example-plugin.php    # Main plugin file
└── README.md            # This file
```

## Creating Your Own Plugin

Use this plugin as a template for creating your own plugins:

1. **Copy the structure**: Create a new directory in `plugins/`
2. **Implement PluginInterface**: Your main class must implement `PluginInterface`
3. **Register services**: Use the `register()` method to register routes and services
4. **Add hooks**: Use the `boot()` method to add action and filter hooks
5. **Clean up**: Use the `deactivate()` method to clean up resources

## Key Methods

### register()
Called when the plugin is first loaded. Use this to:
- Register custom routes
- Bind services to the container
- Set up configuration

### boot()
Called after all plugins are registered. Use this to:
- Add action hooks
- Add filter hooks
- Initialize features that depend on other plugins

### deactivate()
Called when the plugin is deactivated. Use this to:
- Remove hooks
- Clean up resources
- Save state if needed

## Dependencies

This plugin has no dependencies, but you can specify dependencies by returning plugin names from the `getDependencies()` method:

```php
public function getDependencies(): array
{
    return ['another-plugin', 'required-plugin'];
}
```

## License

This example plugin is provided as-is for educational purposes.
