#!/usr/bin/env php
<?php
/**
 * Chimera NoWP — CLI Agent Gateway
 *
 * Interactive REPL for chatting with the agentic CMS.
 * Usage: php cli/chimera.php
 */

declare(strict_types=1);

// Bootstrap
require __DIR__ . '/../vendor/autoload.php';

// Load environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Load config
$appConfig = file_exists(__DIR__ . '/../config/app.php') ? require __DIR__ . '/../config/app.php' : [];
$agentConfig = file_exists(__DIR__ . '/../config/agent.php') ? require __DIR__ . '/../config/agent.php' : [];

// Boot application
$app = new ChimeraNoWP\Core\Application($appConfig);
$app->boot();

$container = $app->getContainer();

// Get agent facade
if (!$container->has(ChimeraNoWP\Agent\AgentFacade::class)) {
    echo "Error: Agent is not enabled. Check config/agent.php\n";
    exit(1);
}

$facade = $container->get(ChimeraNoWP\Agent\AgentFacade::class);

// Simple CLI loop (fallback if CliGateway not compatible)
echo "\n  Chimera NoWP — Agentic CMS\n";
echo "  Provider: {$facade->getProvider()->name()} | Model: {$facade->getProvider()->model()}\n";
echo "  Type /help for commands, /quit to exit\n\n";

// Event listeners for real-time feedback
$facade->events->on('thinking', fn($d) => fprintf(STDERR, "  [thinking...]\n"));
$facade->events->on('tool_call', fn($d) => fprintf(STDERR, "  [tool: {$d['name']}]\n"));
$facade->events->on('anti_loop', fn($d) => fprintf(STDERR, "  [anti-loop triggered]\n"));

while (true) {
    $input = readline('you> ');
    if ($input === false || $input === '/quit' || $input === '/exit') {
        echo "Bye!\n";
        break;
    }

    $input = trim($input);
    if ($input === '') continue;

    if ($input === '/help') {
        echo "  /tools    List available tools\n";
        echo "  /clear    Clear conversation history\n";
        echo "  /model    Show current LLM model\n";
        echo "  /quit     Exit\n\n";
        continue;
    }

    if ($input === '/tools') {
        $tools = $facade->listTools();
        echo "  Available tools (" . count($tools) . "):\n";
        foreach ($tools as $t) {
            $name = $t['function']['name'] ?? 'unknown';
            $desc = $t['function']['description'] ?? '';
            echo "    - {$name}: {$desc}\n";
        }
        echo "\n";
        continue;
    }

    if ($input === '/clear') {
        $facade->clear();
        echo "  History cleared.\n\n";
        continue;
    }

    if ($input === '/model') {
        echo "  Provider: {$facade->getProvider()->name()}\n";
        echo "  Model: {$facade->getProvider()->model()}\n\n";
        continue;
    }

    // Chat
    try {
        $result = $facade->chat($input);
        echo "\nagent> {$result['content']}\n";
        if (!empty($result['toolsUsed'])) {
            echo "  [tools: " . implode(', ', $result['toolsUsed']) . " | iterations: {$result['iterations']}]\n";
        }
        if (($result['learned']['memoriesExtracted'] ?? 0) > 0 || ($result['learned']['skillsExtracted'] ?? 0) > 0) {
            echo "  [learned: {$result['learned']['memoriesExtracted']} memories, {$result['learned']['skillsExtracted']} skills]\n";
        }
        echo "\n";
    } catch (\Throwable $e) {
        echo "  Error: {$e->getMessage()}\n\n";
    }
}
