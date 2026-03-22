# The A2 Protocol Family

## The Problem

LLMs are non-deterministic. Ask the same question twice, get different answers. That's useful for conversation — dangerous for execution.

When an AI agent generates code, SQL, HTML, or API calls directly, the result is:
- **Unpredictable**: different output each run
- **Insecure**: SQL injection, code injection, XSS are one hallucination away
- **Unreproducible**: can't replay, audit, or revert
- **Untestable**: no contract to verify against

## The Solution: Bounded Non-Determinism

The A2 protocols let the LLM make decisions, but only within a **fixed catalog of safe operations**. The LLM decides WHAT to do. The system decides HOW to do it.

```
LLM (non-deterministic)          System (deterministic)
┌──────────────────────┐         ┌──────────────────────┐
│ "I need to store     │         │ CREATE TABLE a2d_ticket
│  support tickets     │  ──→    │ (subject VARCHAR(255),
│  with priority"      │         │  priority ENUM(...))
│                      │         │ + validation rules
│ Chooses from catalog │         │ + REST API
│ Declares JSON        │         │ + search index
└──────────────────────┘         └──────────────────────┘
```

The LLM picks from a menu. The system cooks.

## The Protocols

### A2I — Agent-to-Integration

**Human equivalent**: Setting up a Zapier/n8n connection

The agent declares what external service to connect to. The system materializes credentials, endpoint tools, and connection tests.

```json
{
  "service": "stripe",
  "base_url": "https://api.stripe.com/v1",
  "auth": {"type": "bearer", "key": "sk_..."},
  "endpoints": [
    {"name": "list_charges", "method": "GET", "path": "/charges"},
    {"name": "create_charge", "method": "POST", "path": "/charges", "body": ["amount", "currency"]}
  ],
  "test": {"endpoint": "list_charges", "expect_status": 200}
}
```

**Catalog**: auth types (bearer, basic, header, query) + HTTP methods (GET, POST, PUT, DELETE)

**Produces**: stored credential + N tools (one per endpoint) + connection test

### A2D — Agent-to-Data

**Human equivalent**: Creating an Airtable base

The agent declares what data structure it needs. The system materializes the database table, CRUD operations, validation, API endpoints, and search index.

```json
{
  "entity": "ticket",
  "fields": [
    {"name": "subject", "type": "string", "required": true},
    {"name": "priority", "type": "enum", "values": ["low", "medium", "high"]},
    {"name": "status", "type": "enum", "values": ["open", "in_progress", "closed"]}
  ],
  "search": true
}
```

**Catalog**: field types (string, text, integer, number, boolean, date, datetime, enum, relation, json)

**Produces**: SQL table + insert/find/list/update/delete + validation rules + REST API + vector index

### A2E — Agent-to-Execution

**Human equivalent**: Building an n8n workflow

The agent declares what steps to execute in order. The system orchestrates execution with a data store that flows between steps.

```json
{
  "steps": [
    {"id": "tickets", "type": "ExecuteTool", "tool": "entity_list", "input": {"entity": "ticket"}},
    {"id": "urgent", "type": "FilterData", "data": "/tickets", "field": "priority", "operator": "eq", "value": "high"},
    {"id": "count", "type": "TransformData", "data": "/urgent", "operation": "count"},
    {"id": "notify", "type": "Conditional", "left": "/count", "operator": "gt", "right": 0,
      "then": [{"id": "alert", "type": "ExecuteTool", "tool": "slack_send", "input": {"text": "High priority tickets found"}}]}
  ]
}
```

**Catalog**: 8 operations (ExecuteTool, FilterData, TransformData, Conditional, Loop, StoreData, Wait, MergeData)

**Produces**: sequential execution with in-memory data store, `/step_id` path references between steps

### A2T — Agent-to-Test

**Human equivalent**: Writing a test suite in Jest/PHPUnit

The agent declares what to verify. The system executes assertions and reports pass/fail.

```json
{
  "suite": "ticket-system",
  "tests": [
    {"assert": "entity_exists", "entity": "ticket"},
    {"assert": "insert_succeeds", "entity": "ticket", "data": {"subject": "Test", "priority": "high"}},
    {"assert": "insert_fails", "entity": "ticket", "data": {}},
    {"assert": "enum_rejects", "entity": "ticket", "field": "priority", "value": "INVALID"},
    {"assert": "workflow_produces", "steps": [...], "expect": {"count": 5}}
  ]
}
```

**Catalog**: 14 assertion types (entity_exists, insert_succeeds, insert_fails, enum_rejects, find_returns, filter_count, update_changes, delete_removes, workflow_succeeds, workflow_produces, tool_returns, response_contains, search_finds, entity_not_exists)

**Produces**: test results with pass/fail per assertion, duration, auto-cleanup of test data

### A2P — Agent-to-Page

**Human equivalent**: Building with a page builder (Webflow, Wix)

The agent declares what pages to show using components from an atomic design catalog. The system renders the page structure with resolved data.

```json
{
  "page": "ticket-dashboard",
  "template": "dashboard",
  "layout": "admin-sidebar",
  "sections": [
    {"slot": "header", "component": "heading", "props": {"content": "Tickets", "level": 1}},
    {"slot": "stats", "component": "stat-cards", "props": {
      "cards": [
        {"label": "Open", "source": {"tool": "entity_list", "args": {"entity": "ticket", "filters": {"status": "open"}}}, "value": "length"}
      ]
    }},
    {"slot": "main", "component": "data-table", "props": {
      "source": {"tool": "entity_list", "args": {"entity": "ticket"}},
      "columns": ["subject", "priority", "status"]
    }}
  ],
  "auth": {"required": true}
}
```

**Catalog**: 41 components across 5 levels (atoms, molecules, organisms, templates, layouts)

**Produces**: render-ready JSON structure with resolved data sources, servable to any frontend

## The Pattern

All five protocols share the same architecture:

```
┌─────────────────────────┐     ┌─────────────────────────┐
│    LLM (non-deterministic)    │     │   System (deterministic)      │
│                         │     │                         │
│  1. Reads the catalog   │     │  1. Validates declaration│
│  2. Selects primitives  │     │  2. Materializes result  │
│  3. Composes them       │     │  3. Enforces constraints │
│  4. Outputs JSON        │     │  4. Returns confirmation │
│                         │     │                         │
│  Never generates code   │     │  Never improvises       │
│  Never writes SQL       │     │  Never hallucinates     │
│  Never creates HTML     │     │  Always deterministic   │
└─────────────────────────┘     └─────────────────────────┘
```

### Why Catalogs Work

| Property | Free-form generation | Catalog-based |
|----------|---------------------|---------------|
| Security | SQL/code injection possible | Only pre-approved operations |
| Reproducibility | Different each run | Same declaration = same result |
| Auditability | Opaque generated code | JSON declarations in version control |
| Testability | No contract to test against | Catalog defines the contract |
| Composability | Arbitrary code doesn't compose | Designed primitives always compose |
| Learnability | Infinite possibility space | Finite, documented options |

### The Analogy

The same principle exists everywhere:

| Domain | Free-form (dangerous) | Catalog-based (safe) |
|--------|--------------------|---------------------|
| Web | Arbitrary JavaScript | HTML elements |
| Data | Raw SQL strings | ORM/QueryBuilder |
| CI/CD | Shell scripts | GitHub Actions steps |
| Music | Infinite frequencies | 12 notes, scales, chords |
| Chemistry | Infinite reactions | Periodic table elements |
| LEGO | Sculpting from clay | Fixed bricks that snap together |

A2 protocols are **LEGO for AI agents**: a finite set of bricks that snap together to build anything.

## Building an Application

A single conversation with the agent:

```
"I need a support ticket system with Stripe billing and Slack notifications"

1. A2I → integrate_service("stripe", ...)      Connect payment API
2. A2I → integrate_service("slack", ...)        Connect notifications
3. A2D → define_entity("customer", ...)         Customer data model
4. A2D → define_entity("ticket", ...)           Ticket data model
5. A2D → define_entity("invoice", ...)          Invoice data model
6. A2P → define_page("login", ...)              Login page
7. A2P → define_page("dashboard", ...)          Main dashboard
8. A2P → define_page("tickets", ...)            Ticket list
9. A2P → define_page("ticket-new", ...)         New ticket form
10. A2E → schedule_workflow("check-overdue")     Auto-check overdue tickets
11. A2E → schedule_workflow("daily-summary")     Daily email summary
12. A2T → run_tests("ticket-system-suite")       Verify everything works
```

12 declarations. Zero code. Complete application.

## Implementation in NoWP

```
src/Agent/
├── Integration/          A2I — external service connections
│   ├── ServiceDefinition.php
│   └── IntegrationManager.php
├── Data/                 A2D — data model materialization
│   ├── EntitySchema.php
│   └── EntityMaterializer.php
├── Workflow/             A2E — workflow orchestration
│   ├── WorkflowEngine.php
│   ├── DataStore.php
│   └── Scheduler.php
├── Testing/              A2T — declarative test runner
│   └── TestRunner.php
├── Page/                 A2P — page builder
│   ├── ComponentCatalog.php
│   └── PageBuilder.php
├── Memory/               Persistent semantic memory
│   └── MemoryService.php
├── Provider/             AI model providers
│   ├── AIProviderInterface.php
│   ├── OllamaProvider.php
│   └── HttpProvider.php
├── Tools/                Tool definitions
│   └── Tool.php
├── MCP/                  Model Context Protocol
│   ├── MCPServer.php
│   └── MCPController.php
├── AgentService.php      Agentic loop
├── AgentController.php   REST API
└── AgentServiceProvider.php  Bootstrap & wiring
```

## License

The A2 protocol concepts are open. The NoWP implementation is MIT licensed.
