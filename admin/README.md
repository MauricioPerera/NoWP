# NoWP Admin Panel

Modern, responsive admin panel for NoWP Framework built with vanilla JavaScript.

## Features

- 🔐 Secure authentication with JWT
- 📊 Dashboard with statistics
- 📝 Content management (create, edit, delete)
- 🖼️ Media library with upload
- 👥 User management
- 🔌 Plugin documentation
- 📱 Fully responsive design
- ⚡ Fast and lightweight (no framework dependencies)

## Development

```bash
# Install dependencies
npm install

# Start dev server (with API proxy)
npm run dev

# Build for production
npm run build
```

The dev server runs on `http://localhost:3000` and proxies API requests to `http://localhost:8000`.

## Production Build

```bash
npm run build
```

The built files will be in the `dist/` directory. Serve these files with any static file server or integrate with your NoWP backend.

## Project Structure

```
admin/
├── src/
│   ├── styles/
│   │   └── main.css          # Global styles
│   ├── pages/
│   │   ├── dashboard.js      # Dashboard page
│   │   ├── content.js        # Content management
│   │   ├── media.js          # Media library
│   │   ├── users.js          # User management
│   │   └── plugins.js        # Plugin docs
│   ├── api.js                # API client
│   ├── router.js             # Simple router
│   └── main.js               # App entry point
├── index.html                # Main HTML
├── package.json
└── vite.config.js
```

## Configuration

To change the API endpoint, edit `vite.config.js`:

```js
server: {
  proxy: {
    '/api': {
      target: 'http://your-api-url',
      changeOrigin: true,
    },
  },
}
```

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers

## License

MIT
