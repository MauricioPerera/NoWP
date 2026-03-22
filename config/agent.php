<?php

/**
 * Agent Configuration
 *
 * Configure the AI agent: provider, tools, memory, and workflow engine.
 */

return [
    // Enable/disable the agent module
    'enabled' => env('AGENT_ENABLED', true),

    // Agent identifier (for memory scoping)
    'id' => env('AGENT_ID', 'nowp-agent'),

    // System prompt — defines the agent's personality and capabilities
    'system_prompt' => env('AGENT_SYSTEM_PROMPT',
        'You are a helpful assistant for this website. You can search content, ' .
        'manage posts, and remember information across conversations. ' .
        'Use your tools to help the user.'
    ),

    // AI Provider
    'provider' => env('AGENT_PROVIDER', 'ollama'),

    'providers' => [
        'ollama' => [
            'host'        => env('OLLAMA_HOST', 'http://localhost:11434'),
            'model'       => env('OLLAMA_MODEL', 'llama3.1'),
            'temperature' => env('OLLAMA_TEMPERATURE', 0.7),
        ],
        'openai' => [
            'url'         => 'https://api.openai.com/v1/chat/completions',
            'api_key'     => env('OPENAI_API_KEY', ''),
            'model'       => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => 0.7,
            'max_tokens'  => 4096,
        ],
        'openrouter' => [
            'url'         => 'https://openrouter.ai/api/v1/chat/completions',
            'api_key'     => env('OPENROUTER_API_KEY', ''),
            'model'       => env('OPENROUTER_MODEL', 'anthropic/claude-sonnet-4'),
            'temperature' => 0.7,
            'max_tokens'  => 4096,
        ],
        'cloudflare' => [
            'url'         => 'https://api.cloudflare.com/client/v4/accounts/' . env('CF_ACCOUNT_ID', '') . '/ai/v1/chat/completions',
            'api_key'     => env('CF_AI_API_KEY', ''),
            'model'       => env('CF_AI_MODEL', '@cf/meta/llama-3.3-70b-instruct-fp8-fast'),
            'temperature' => 0.7,
            'max_tokens'  => 4096,
        ],
        'custom' => [
            'url'         => env('AGENT_API_URL', ''),
            'api_key'     => env('AGENT_API_KEY', ''),
            'model'       => env('AGENT_MODEL', ''),
            'temperature' => env('AGENT_TEMPERATURE', 0.7),
            'max_tokens'  => env('AGENT_MAX_TOKENS', 4096),
        ],
    ],

    // Memory storage path
    'memory_path' => env('AGENT_MEMORY_PATH', 'storage/agent/memory'),

    // Enable persistent memory across sessions
    'memory_enabled' => env('AGENT_MEMORY_ENABLED', true),

    // Built-in tools to register automatically
    'builtin_tools' => [
        'search_content',  // Semantic search across site content
        'get_content',     // Get content by ID
        'create_content',  // Create new content
        'remember',        // Save a memory
        'recall',          // Recall memories
        'run_workflow',    // Execute a workflow
    ],
];
