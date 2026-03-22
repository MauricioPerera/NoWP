# NoWP Framework

A modern, lightweight CMS/BaaS framework with built-in AI agent, semantic search, and workflow engine. Built with PHP 8.1+ and optimized for shared hosting ($3/month).

## What makes it different

NoWP is a CMS that's also an **agentic platform**. Out of the box:

- **Semantic Search** — find content by meaning, not keywords. Powered by [php-vector-store](https://github.com/MauricioPerera/php-vector-store).
- **AI Agent** — chat with your site. The agent can search, create content, execute workflows, and remember across sessions.
- **Workflow Engine** — chain operations (A2E pattern): filter → transform → conditional → loop, with data flowing between steps.
- **Persistent Memory** — the agent remembers user preferences, facts, and corrections across conversations.
- **Works offline** — Ollama for local AI + EmbeddingGemma for local embeddings. Zero cloud dependency.

## Quick Start

```bash
composer install
cp .env.example .env
php cli/migrate.php
php -S localhost:8000 -t public/
```

### Configure AI (choose one)

```env
# Option A: Local / Offline (free)
AGENT_PROVIDER=ollama
OLLAMA_HOST=http://localhost:11434
OLLAMA_MODEL=llama3.1
SEARCH_PROVIDER=ollama
OLLAMA_EMBED_MODEL=embeddinggemma

# Option B: Cloud
AGENT_PROVIDER=openrouter
OPENROUTER_API_KEY=sk-...
OPENROUTER_MODEL=anthropic/claude-sonnet-4
SEARCH_PROVIDER=cloudflare
CLOUDFLARE_ACCOUNT_ID=xxx
CLOUDFLARE_AI_API_KEY=xxx
```

## Architecture

```
src/
├── Agent/                  # AI Agent SDK
│   ├── AgentService.php       Agentic loop (chat → tools → respond)
│   ├── AgentController.php    REST API endpoints
│   ├── Provider/              AI providers (Ollama, OpenAI, OpenRouter, custom)
│   ├── Tools/                 Tool definitions with fluent builder
│   ├── Workflow/              A2E workflow engine with data store
│   └── Memory/                Persistent semantic memory
├── Search/                 # Semantic Search
│   ├── SearchService.php      Index, search, hybrid search
│   ├── SearchController.php   REST API endpoints
│   ├── EmbeddingProvider.php  Ollama, Cloudflare, OpenAI, custom
│   └── SearchServiceProvider  Auto-index on content save
├── Auth/                   # JWT + roles + permissions
├── Content/                # CRUD + versioning + custom fields
├── Core/                   # Router, DI Container, Middleware
├── Database/               # MySQL + SQLite, QueryBuilder
├── Plugin/                 # Hooks & filters (WordPress-style)
├── Cache/                  # APCu, Redis, Memcached, File
├── Storage/                # File uploads, image processing
└── Theme/                  # Template system with inheritance
```

## Agent API

### Chat

```bash
curl -X POST http://localhost:8000/api/agent/chat \
  -H 'Content-Type: application/json' \
  -d '{"message": "Find posts about PHP and summarize them"}'
```

The agent automatically:
1. Calls `search_content` tool to find relevant posts
2. Reads the results
3. Generates a summary
4. Remembers the interaction for next session

### Built-in Tools

| Tool | What it does |
|------|-------------|
| `search_content` | Semantic search across all site content |
| `get_content` | Get a content item by ID |
| `create_content` | Create a new post or page |
| `remember` | Save a memory for future sessions |
| `recall` | Retrieve relevant memories |
| `run_workflow` | Execute an A2E workflow |

### Workflows

```bash
curl -X POST http://localhost:8000/api/agent/workflow \
  -H 'Content-Type: application/json' \
  -d '{
    "steps": [
      {"id": "posts", "type": "ExecuteTool", "tool": "search_content", "input": {"query": "PHP"}},
      {"id": "titles", "type": "TransformData", "data": "/posts", "operation": "map", "field": "title"},
      {"id": "count", "type": "TransformData", "data": "/titles", "operation": "count"}
    ]
  }'
```

Operations: `ExecuteTool`, `FilterData`, `TransformData`, `Conditional`, `Loop`, `StoreData`, `Wait`, `MergeData`.

### Memory

```bash
# Save
curl -X POST http://localhost:8000/api/agent/memory \
  -H 'Content-Type: application/json' \
  -d '{"content": "User prefers concise answers", "type": "preference"}'

# Recall
curl "http://localhost:8000/api/agent/memory?q=communication+style"
```

## Semantic Search API

Auto-indexes content on create/update. No manual indexing needed.

```bash
# Search
curl "http://localhost:8000/api/search?q=how+to+deploy&type=post&limit=5"

# Stats
curl "http://localhost:8000/api/search/stats"

# Rebuild index
curl -X POST "http://localhost:8000/api/search/reindex"
```

### How it works

```
Content saved → Hook fires → Embedding generated → Vector stored (392 bytes)
                                                           ↓
Search query → Embedding generated → Matryoshka search (128→256→384d)
                                                           ↓
                                              Results ranked by semantic similarity
```

- **392 bytes per vector** (Int8 quantized, 384 dimensions)
- **Matryoshka search**: 3-5x faster than brute-force
- **100% recall** with EmbeddingGemma embeddings
- **Works offline** with Ollama

## Content API

```bash
# Create
curl -X POST http://localhost:8000/api/contents \
  -H 'Authorization: Bearer TOKEN' \
  -d '{"title": "My Post", "content": "Hello!", "type": "post", "status": "published"}'

# List
curl http://localhost:8000/api/contents?type=post&limit=10

# Get
curl http://localhost:8000/api/contents/1

# Update
curl -X PUT http://localhost:8000/api/contents/1 \
  -d '{"title": "Updated Title"}'

# Delete
curl -X DELETE http://localhost:8000/api/contents/1
```

## Plugin System

WordPress-style hooks and filters:

```php
// In your plugin
$hooks->addAction('content.created', function ($content) {
    // React to new content
});

$hooks->addFilter('content.before_create', function ($data) {
    $data['title'] = strtoupper($data['title']);
    return $data;
});
```

## Features

- **API-First**: RESTful APIs for all functionality
- **Modern PHP**: PHP 8.1+ (enums, readonly, named args)
- **Authentication**: JWT with role-based permissions
- **Content**: CRUD, versioning, custom fields, i18n
- **Media**: Upload, image processing, organization
- **Cache**: APCu, Redis, Memcached, File (auto-detect)
- **Themes**: Template inheritance, parent/child
- **Security**: CSRF, rate limiting, security headers, bcrypt
- **Testing**: 63 test files with Pest PHP
- **Admin Panel**: Responsive SPA (vanilla JS)
- **TypeScript Client**: Full-typed API client

## Requirements

- PHP 8.1+
- MySQL 5.7+ or SQLite
- Optional: Ollama (for local AI)
- Optional: APCu/Redis/Memcached (for caching)

## Configuration

| File | What it configures |
|------|-------------------|
| `config/app.php` | App name, env, JWT, security |
| `config/database.php` | MySQL/SQLite connection |
| `config/cache.php` | Cache driver auto-detection |
| `config/search.php` | Embedding provider, dimensions, auto-index |
| `config/agent.php` | AI provider, tools, memory, system prompt |

## Performance

- Memory: ~4MB typical
- Response: <100ms
- Disk: <100MB core
- Vectors: 392 bytes each (Int8 384d)
- Search: ~5ms per query
- Runs on $3/month shared hosting

## License

MIT License
