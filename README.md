# Gwack Framework

Gwack is a modern PHP framework designed for full-stack development with seamless Vue.js integration. Built with developer experience in mind, it offers file-based routing, powerful dependency injection, and fast development workflow.

## 🚀 Features

- **File-Based Routing**: Zero configuration routing - just create PHP files in your `server/` directory
- **Vue.js Integration**: Native Vue.js support with hot module reloading
- **High Performance**: Optimized router with static route lookup and intelligent regex grouping
- **PSR Compliant**: Built on Symfony components with PSR standards compliance
- **Dependency Injection**: Container system with automatic resolution and caching
- **Developer Experience**: CLI tools for project scaffolding, development server, and production builds
- **API-First Design**: RESTful API server with built-in serialization and validation

## 📦 Installation

### Using the CLI (Recommended)

```bash
# Install the CLI globally
npm install -g @gwack/cli

# Create a new project
gwack create my-app

# Navigate to your project
cd my-app

# Install PHP dependencies
composer install

# Start development server
gwack dev
```

### Manual Installation

```bash
# Install via Composer
composer require gwack/core

# Install frontend tooling
npm install @gwack/cli
```

## 🏗 Example Project Structure

```
my-app/
├── pages/                 # Vue.js pages (frontend)
│   ├── index.vue         # Home page
│   └── about.vue         # About page
├── server/               # PHP API routes (backend)
│   ├── posts/
│   │   └── index.php     # GET/POST /api/posts
│   └── users/
│       └── [id].php      # GET/POST /api/users/{id}
├── assets/               # Static assets
├── gwack.config.js       # Framework configuration
├── composer.json         # PHP dependencies
├── package.json          # Node.js dependencies
└── index.html           # Entry point
```

## 🎯 Quick Start

### 1. Backend API Routes

Create API endpoints by adding PHP files to the `server/` directory:

```php
<?php
// server/posts/index.php

function getPosts() {
    return [
        ['id' => 1, 'title' => 'Hello World'],
        ['id' => 2, 'title' => 'Getting Started']
    ];
}

// Return a JSON response
return fn() => json(getPosts());
```

Available at: `GET /api/posts`

### 2. Dynamic Routes

Use bracket notation for dynamic parameters:

```php
<?php
// server/posts/[id].php

return function() {
    $request = request();
    $id = $request->get('id');

    return json(['id' => $id, 'title' => "Post #{$id}"]);
};
```

Available at: `GET /api/posts/123`

### 3. Frontend Pages

Create Vue.js pages in the `pages/` directory:

```vue
<!-- pages/index.vue -->
<template>
  <div>
    <h1>Welcome to Gwack</h1>
    <div v-for="post in posts" :key="post.id">
      <h2>{{ post.title }}</h2>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const posts = ref([])

onMounted(async () => {
  const response = await fetch('/api/posts')
  posts.value = await response.json()
})
</script>
```

## 🛠 Development Commands

```bash
# Start development server with hot reloading
gwack dev

# Build for production
gwack build

# Create new project
gwack create <project-name>

# Development server options
gwack dev --port 3000 --php-port 8080 --host localhost
```

## ⚙️ Configuration

### gwack.config.js

```javascript
export default {
  php: {
    port: 8080,
  },
  frontend: {
    port: 3000,
  },
  build: {
    target: 'es2020',
  },
}
```

### Application Bootstrap

```php
<?php
// index.php

use Gwack\Core\Application;

require_once 'vendor/autoload.php';

$app = new Application(__DIR__);

$app->configure([
    'env' => 'development',
    'debug' => true,
    'api_prefix' => '/api'
]);

$app->boot()->run();
```

## 🏛 Architecture

### Core Components

- **Application**: Main application class that bootstraps the framework
- **Router**: High-performance HTTP router with route compilation and caching
- **Container**: Dependency injection container with automatic resolution
- **ApiServer**: RESTful API server with middleware support
- **FileBasedRouter**: Automatic route discovery from filesystem

### Container Functions

The framework provides pre-registered functions available in all route handlers:

```php
// Available functions in route handlers
json($data, $status = 200)    // Create JSON response
request()                     // Get current request
context()                     // Get application context
config($key)                  // Get configuration value
logger()                      // Get logger instance
session()                     // Get session manager
validate($rules)              // Validate request data
```

### Middleware Support

```php
// Add middleware to API server
$app->getApiServer()->addMiddleware(new CorsMiddleware());
$app->getApiServer()->addMiddleware(new AuthMiddleware());
```

## 🔧 Advanced Usage

### Manual Route Registration

```php
// Register routes programmatically
$app->addRoute('GET', '/custom', function() {
    return json(['message' => 'Custom route']);
});
```

### Container Bindings

```php
// Bind custom services
$app->getContainer()->bind('myService', function() {
    return new MyService();
});

// Access in route handlers
return function() {
    $service = container()->get('myService');
    return json($service->getData());
};
```

## 📝 Requirements

- **PHP**: 8.3 or higher
- **Node.js**: 18.0 or higher
- **Composer**: For PHP dependency management
- **npm/yarn**: For frontend dependencies

## 🧪 Testing

```bash
# Run PHP tests
composer test

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📚 Learn More

- [CLI Documentation](../cli/README.md)
- [Example Application](../example-app/)
- [API Reference](docs/api.md)

---

Built with ❤️ by the Gwack Framework Team
