<?php

/**
 * Scaffolding Engine — conversational system builder.
 *
 * Guides the user through building a complete system via conversation:
 *   DISCOVER → PROPOSE → REFINE → EXECUTE → REVIEW
 *
 * The agent asks questions, generates a PRD, lets the user refine it,
 * then executes A2D/A2I/A2E/A2P/A2T to materialize everything.
 */

declare(strict_types=1);

namespace Framework\Agent\Scaffolding;

use Framework\Agent\AgentService;

class ScaffoldingEngine
{
    private AgentService $agent;
    private string $state = 'idle';
    private array $context = [];
    private array $plan = [];
    private array $history = [];
    private string $storagePath;

    private const STATES = ['idle', 'discover', 'propose', 'refine', 'execute', 'review', 'done'];

    private const DISCOVER_PROMPT = <<<'PROMPT'
You are a system architect helping the user build an application. You are in DISCOVERY phase.

Your job is to ask questions to understand what the user needs. Ask about:
1. What is the purpose of the system? (1-2 sentences)
2. Who are the users? (roles: admin, user, public, etc.)
3. What are the main entities/data types? (tickets, products, users, etc.)
4. What workflows exist? (when X happens, do Y)
5. What external services are needed? (email, payment, notifications)
6. Any specific UI requirements?

Ask ONE question at a time. Be concise. When you have enough information (at least entities, users, and purpose), respond with:
[READY_TO_PROPOSE]

Previous context gathered:
{{context}}
PROMPT;

    private const PROPOSE_PROMPT = <<<'PROMPT'
You are a system architect. Based on the discovery context below, generate a complete system plan as JSON.

The plan MUST be a valid JSON object with this exact structure:
{
  "name": "System Name",
  "description": "What it does",
  "entities": [
    {"entity": "name", "label": "Label", "fields": [{"name": "field", "type": "string|text|integer|number|boolean|date|enum|relation", "required": true, "values": ["a","b"] }], "search": false}
  ],
  "workflows": [
    {"name": "Workflow Name", "description": "What it does", "trigger": "manual|schedule", "interval": "hourly", "steps": [{"id": "step1", "type": "ExecuteTool", "tool": "entity_list", "input": {"entity": "name"}}]}
  ],
  "pages": [
    {"page": "slug", "title": "Title", "template": "dashboard|list-page|detail-page|form-page|login-page|settings-page", "layout": "admin-sidebar|public-centered", "sections": [{"slot": "main", "component": "data-table|form|detail-view|stat-cards|heading|button-group|card-grid", "props": {}}], "auth": {"required": true}}
  ],
  "services": [
    {"service": "name", "label": "Label", "base_url": "https://...", "auth": {"type": "bearer"}, "endpoints": [{"name": "ep", "method": "GET", "path": "/path"}]}
  ]
}

Respond with ONLY the JSON plan, no explanation. The user will review and modify it.

Discovery context:
{{context}}
PROMPT;

    private const REFINE_PROMPT = <<<'PROMPT'
You are a system architect. The user wants to modify the system plan below.

Apply their requested changes and return the COMPLETE updated plan as JSON (same structure as before).
If the change is unclear, ask ONE clarifying question instead.

Respond with ONLY the updated JSON plan, or a clarifying question.

Current plan:
{{plan}}
PROMPT;

    public function __construct(AgentService $agent, string $storagePath = '')
    {
        $this->agent = $agent;
        $this->storagePath = $storagePath ?: (defined('BASE_PATH') ? BASE_PATH . '/storage/agent/scaffolding' : sys_get_temp_dir() . '/nowp-scaffolding');

        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }

        $this->loadState();
    }

    /**
     * Process a user message in the scaffolding flow.
     * Returns the agent's response + current state.
     */
    public function process(string $message): array
    {
        $this->history[] = ['role' => 'user', 'content' => $message, 'state' => $this->state];

        // Handle state transitions based on user input
        if ($this->state === 'idle') {
            $this->state = 'discover';
            $this->context = [];
            $this->plan = [];
        }

        $response = match ($this->state) {
            'discover' => $this->handleDiscover($message),
            'propose'  => $this->handlePropose($message),
            'refine'   => $this->handleRefine($message),
            'execute'  => $this->handleExecute($message),
            'review'   => $this->handleReview($message),
            default    => $this->handleDiscover($message),
        };

        $this->history[] = ['role' => 'assistant', 'content' => $response['message'], 'state' => $this->state];
        $this->saveState();

        return [
            'state'   => $this->state,
            'message' => $response['message'],
            'plan'    => $this->plan,
            'context' => $this->context,
        ];
    }

    /**
     * Get current state info.
     */
    public function status(): array
    {
        return [
            'state'   => $this->state,
            'plan'    => $this->plan,
            'context' => $this->context,
            'history' => count($this->history),
        ];
    }

    /**
     * Reset the scaffolding session.
     */
    public function reset(): array
    {
        $this->state = 'idle';
        $this->context = [];
        $this->plan = [];
        $this->history = [];
        $this->saveState();

        return ['state' => 'idle', 'message' => 'Session reset. Tell me what you want to build.'];
    }

    // ── State Handlers ──────────────────────────────────────────

    private function handleDiscover(string $message): array
    {
        // Add message to context
        $this->context[] = $message;

        // Build prompt with context
        $prompt = str_replace('{{context}}', implode("\n", $this->context), self::DISCOVER_PROMPT);

        // Ask the AI to continue discovery or signal readiness
        $response = $this->agent->chat($message);

        // Check if AI signals it has enough info
        if (str_contains($response, '[READY_TO_PROPOSE]') || str_contains(strtolower($message), 'that\'s all') || str_contains(strtolower($message), 'eso es todo') || str_contains(strtolower($message), 'generate') || str_contains(strtolower($message), 'genera')) {
            $this->state = 'propose';
            return $this->handlePropose('Generate the plan based on our conversation.');
        }

        return ['message' => $response];
    }

    private function handlePropose(string $message): array
    {
        // Build context summary for plan generation
        $contextStr = implode("\n", $this->context);
        $prompt = str_replace('{{context}}', $contextStr, self::PROPOSE_PROMPT);

        // Reset chat and ask for plan with special system prompt
        $this->agent->reset();
        $response = $this->agent->chat($prompt . "\n\nUser request: " . $message);

        // Try to extract JSON plan from response
        $plan = $this->extractJson($response);

        if ($plan && isset($plan['entities'])) {
            $this->plan = $plan;
            $this->state = 'refine';

            // Format plan summary for user
            $summary = $this->formatPlanSummary($plan);

            return ['message' => $summary . "\n\nDo you want to modify anything? If it looks good, say 'execute' or 'adelante'."];
        }

        // AI didn't return valid JSON, show raw response
        return ['message' => $response];
    }

    private function handleRefine(string $message): array
    {
        $lower = strtolower(trim($message));

        // Check for approval
        if (in_array($lower, ['ok', 'yes', 'si', 'sí', 'execute', 'ejecuta', 'adelante', 'go', 'lgtm', 'approved', 'aprobado', 'dale'])) {
            $this->state = 'execute';
            return $this->handleExecute('');
        }

        // User wants changes — ask AI to refine
        $planStr = json_encode($this->plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $prompt = str_replace('{{plan}}', $planStr, self::REFINE_PROMPT);

        $this->agent->reset();
        $response = $this->agent->chat($prompt . "\n\nUser change request: " . $message);

        // Try to extract updated plan
        $updated = $this->extractJson($response);

        if ($updated && isset($updated['entities'])) {
            $this->plan = $updated;
            $summary = $this->formatPlanSummary($updated);
            return ['message' => "Updated plan:\n\n" . $summary . "\n\nAnything else to change? Say 'execute' when ready."];
        }

        // AI asked a clarifying question
        return ['message' => $response];
    }

    private function handleExecute(string $message): array
    {
        if (empty($this->plan)) {
            $this->state = 'discover';
            return ['message' => 'No plan to execute. Let\'s start over. What do you want to build?'];
        }

        $results = [];
        $errors = [];

        // 1. A2D: Create entities
        foreach ($this->plan['entities'] ?? [] as $entityDef) {
            try {
                $result = $this->agent->invokeToolByName('define_entity', $entityDef);
                $results[] = "A2D: Entity '{$entityDef['entity']}' → " . ($result['status'] ?? 'ok');
            } catch (\Throwable $e) {
                $errors[] = "A2D: Entity '{$entityDef['entity']}' failed: " . $e->getMessage();
            }
        }

        // 2. A2I: Create integrations
        foreach ($this->plan['services'] ?? [] as $serviceDef) {
            try {
                $result = $this->agent->invokeToolByName('integrate_service', $serviceDef);
                $results[] = "A2I: Service '{$serviceDef['service']}' → " . ($result['status'] ?? 'ok');
            } catch (\Throwable $e) {
                $errors[] = "A2I: Service '{$serviceDef['service']}' failed: " . $e->getMessage();
            }
        }

        // 3. A2E: Create workflows
        foreach ($this->plan['workflows'] ?? [] as $wfDef) {
            try {
                if (($wfDef['trigger'] ?? 'manual') === 'schedule') {
                    $result = $this->agent->invokeToolByName('schedule_workflow', [
                        'name'     => $wfDef['name'],
                        'steps'    => $wfDef['steps'],
                        'interval' => $wfDef['interval'] ?? 'daily',
                    ]);
                    $results[] = "A2E: Scheduled '{$wfDef['name']}' → " . ($result['status'] ?? 'ok');
                } else {
                    $results[] = "A2E: Workflow '{$wfDef['name']}' → registered (manual trigger)";
                }
            } catch (\Throwable $e) {
                $errors[] = "A2E: Workflow '{$wfDef['name']}' failed: " . $e->getMessage();
            }
        }

        // 4. A2P: Create pages
        foreach ($this->plan['pages'] ?? [] as $pageDef) {
            try {
                $result = $this->agent->invokeToolByName('define_page', $pageDef);
                $results[] = "A2P: Page '{$pageDef['page']}' → " . ($result['status'] ?? 'ok');
            } catch (\Throwable $e) {
                $errors[] = "A2P: Page '{$pageDef['page']}' failed: " . $e->getMessage();
            }
        }

        // 5. A2T: Auto-generate and run basic tests
        $tests = $this->generateTests();
        $testResult = null;
        if (!empty($tests)) {
            try {
                $testResult = $this->agent->invokeToolByName('run_tests', [
                    'suite' => 'scaffolding-verification',
                    'tests' => $tests,
                ]);
                $passed = $testResult['passed'] ?? 0;
                $total = $testResult['total'] ?? 0;
                $results[] = "A2T: {$passed}/{$total} tests passed";
            } catch (\Throwable $e) {
                $errors[] = "A2T: Tests failed: " . $e->getMessage();
            }
        }

        $this->state = 'review';

        // Format execution report
        $report = "## Execution Complete\n\n";
        foreach ($results as $r) {
            $report .= "  {$r}\n";
        }
        if (!empty($errors)) {
            $report .= "\n### Errors:\n";
            foreach ($errors as $e) {
                $report .= "  {$e}\n";
            }
        }

        $report .= "\nWant to adjust anything, or is the system ready?";

        return ['message' => $report];
    }

    private function handleReview(string $message): array
    {
        $lower = strtolower(trim($message));

        if (in_array($lower, ['done', 'listo', 'ready', 'ok', 'yes', 'si', 'sí', 'finished', 'terminado'])) {
            $this->state = 'done';
            $appUrl = defined('BASE_PATH') ? (env('APP_URL', 'http://localhost:8888') . '/app.html') : 'http://localhost:8888/app.html';
            return ['message' => "System ready. Open {$appUrl} to use it.\n\nSay 'new' to build something else."];
        }

        if (in_array($lower, ['new', 'nuevo', 'start over', 'reset'])) {
            return $this->reset();
        }

        // User wants more changes — go back to refine
        $this->state = 'refine';
        return $this->handleRefine($message);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function extractJson(string $text): ?array
    {
        // Try to find JSON in the response (might be wrapped in markdown code block)
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if ($decoded) return $decoded;
        }

        // Try raw JSON
        if (preg_match('/(\{[\s\S]*\})/', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if ($decoded) return $decoded;
        }

        return null;
    }

    private function formatPlanSummary(array $plan): string
    {
        $s = "## {$plan['name']}\n{$plan['description']}\n\n";

        if (!empty($plan['entities'])) {
            $s .= "### Entities\n";
            foreach ($plan['entities'] as $e) {
                $fields = array_column($e['fields'] ?? [], 'name');
                $s .= "- **{$e['entity']}** ({$e['label']}): " . implode(', ', $fields) . "\n";
            }
        }

        if (!empty($plan['workflows'])) {
            $s .= "\n### Workflows\n";
            foreach ($plan['workflows'] as $w) {
                $trigger = ($w['trigger'] ?? 'manual') === 'schedule' ? "every {$w['interval']}" : 'manual';
                $s .= "- **{$w['name']}**: {$w['description']} ({$trigger})\n";
            }
        }

        if (!empty($plan['pages'])) {
            $s .= "\n### Pages\n";
            foreach ($plan['pages'] as $p) {
                $s .= "- **{$p['page']}** ({$p['template']}): {$p['title']}\n";
            }
        }

        if (!empty($plan['services'])) {
            $s .= "\n### Integrations\n";
            foreach ($plan['services'] as $sv) {
                $s .= "- **{$sv['service']}**: {$sv['label']} ({$sv['base_url']})\n";
            }
        }

        return $s;
    }

    private function generateTests(): array
    {
        $tests = [];

        // Test each entity exists
        foreach ($this->plan['entities'] ?? [] as $e) {
            $tests[] = ['assert' => 'entity_exists', 'entity' => $e['entity']];
        }

        // Test each page exists and renders
        foreach ($this->plan['pages'] ?? [] as $p) {
            $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($p['page']));
            $tests[] = ['assert' => 'page_exists', 'page' => $slug];
            $tests[] = ['assert' => 'page_renders', 'page' => $slug];
        }

        return $tests;
    }

    private function saveState(): void
    {
        $data = [
            'state'   => $this->state,
            'context' => $this->context,
            'plan'    => $this->plan,
            'history' => $this->history,
        ];

        @file_put_contents(
            $this->storagePath . '/session.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function loadState(): void
    {
        $file = $this->storagePath . '/session.json';
        if (!file_exists($file)) return;

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) return;

        $this->state   = $data['state'] ?? 'idle';
        $this->context = $data['context'] ?? [];
        $this->plan    = $data['plan'] ?? [];
        $this->history = $data['history'] ?? [];
    }
}
