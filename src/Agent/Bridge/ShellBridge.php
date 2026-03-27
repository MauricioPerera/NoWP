<?php
declare(strict_types=1);
namespace ChimeraNoWP\Agent\Bridge;

use ChimeraNoWP\Agent\Core\ToolDefinition;

/** Expose php-agent-shell as 2 tools (Agent Shell pattern). */
final class ShellBridge
{
    /** @return ToolDefinition[] */
    public static function tools(object $shell): array
    {
        return [
            new ToolDefinition('cli_help', 'Get the Agent Shell interaction protocol — call first to learn available commands', [
                'type' => 'object', 'properties' => [], 'required' => [],
            ], fn() => $shell->help(), safe: true, category: 'shell'),

            new ToolDefinition('cli_exec', 'Execute a shell command. Use "search <query>" to discover, "describe <cmd>" for details, then execute.', [
                'type' => 'object', 'properties' => ['command' => ['type' => 'string', 'description' => 'Command to execute (e.g. "search deploy", "file:list --path .")']], 'required' => ['command'],
            ], function (array $args) use ($shell) {
                return json_encode($shell->exec($args['command'] ?? ''));
            }, category: 'shell'),
        ];
    }

    /** Fallback tools if php-agent-shell not available. */
    public static function fallbackTools(): array
    {
        return [
            new ToolDefinition('shell_exec', 'Execute a shell command', [
                'type' => 'object', 'properties' => ['cmd' => ['type' => 'string', 'description' => 'Command']], 'required' => ['cmd'],
            ], function (array $args) {
                $cmd = $args['cmd'] ?? '';
                $parts = preg_split('/\s+/', trim($cmd));
                $binary = basename(array_shift($parts) ?? '');
                $allowedCommands = ['ls', 'cat', 'head', 'tail', 'grep', 'find', 'php', 'composer'];
                if (!in_array($binary, $allowedCommands, true)) {
                    return json_encode(['code' => 1, 'output' => "Command not allowed: " . substr($binary, 0, 32) . ". Allowed: " . implode(', ', $allowedCommands)]);
                }
                // Use proc_open with argument array to avoid shell interpretation
                $cmdArray = array_merge([$binary], array_map('escapeshellarg', $parts));
                $safeCmd = implode(' ', $cmdArray);
                $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                $process = proc_open($safeCmd, $descriptors, $pipes, defined('BASE_PATH') ? BASE_PATH : null);
                if (!is_resource($process)) {
                    return json_encode(['code' => 1, 'output' => 'Failed to start process']);
                }
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($process);
                return json_encode(['code' => $code, 'output' => $stdout]);
            }, category: 'shell'),

            new ToolDefinition('read_file', 'Read file contents', [
                'type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path'],
            ], function (array $args) {
                $path = $args['path'] ?? '';
                $basePath = defined('BASE_PATH') ? realpath(BASE_PATH) : realpath(__DIR__ . '/../../..');
                $realPath = realpath($path);
                if ($realPath === false || !str_starts_with($realPath, $basePath)) {
                    return json_encode(['error' => 'Access denied: path is outside project directory']);
                }
                return file_exists($realPath) ? file_get_contents($realPath) : json_encode(['error' => 'Not found']);
            }, safe: true, category: 'shell'),

            new ToolDefinition('list_dir', 'List directory contents', [
                'type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path'],
            ], function (array $args) {
                $path = $args['path'] ?? '.';
                $files = is_dir($path) ? scandir($path) : [];
                return json_encode(array_values(array_diff($files, ['.', '..'])));
            }, safe: true, category: 'shell'),
        ];
    }
}
