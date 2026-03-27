<?php

/**
 * Agent Configuration — Chimera NoWP
 *
 * Unified config for the Chimera agent engine + NoWP CMS integration.
 * Supports 4 LLM providers: Ollama, Cloudflare Workers AI, OpenRouter, OpenAI.
 */

return [
    // Enable/disable the agent module
    'enabled' => env('AGENT_ENABLED', true),

    // Agent identifier (for memory scoping)
    'id' => env('AGENT_ID', 'chimera'),

    // System prompt
    'system_prompt' => env('AGENT_SYSTEM_PROMPT', ''),

    // AI Provider: ollama | cloudflare | openrouter | openai
    'provider' => env('AGENT_PROVIDER', 'ollama'),

    // Max agent loop iterations (anti-runaway protection)
    'max_iterations' => (int) env('AGENT_MAX_ITERATIONS', 25),

    // Data directory for agent storage (sessions, etc)
    'data_dir' => env('AGENT_DATA_DIR', 'storage/agent'),

    'providers' => [
        'ollama' => [
            'host'        => env('OLLAMA_HOST', 'http://localhost:11434'),
            'model'       => env('OLLAMA_MODEL', 'qwen2.5:7b'),
            'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.7),
        ],
        'cloudflare' => [
            'account_id'  => env('CF_ACCOUNT_ID', ''),
            'api_token'   => env('CF_API_TOKEN', ''),
            'model'       => env('CF_AI_MODEL', '@cf/ibm-granite/granite-4.0-h-micro'),
        ],
        'openrouter' => [
            'api_key'     => env('OPENROUTER_API_KEY', ''),
            'model'       => env('OPENROUTER_MODEL', 'nousresearch/hermes-4-scout'),
        ],
        'openai' => [
            'api_key'     => env('OPENAI_API_KEY', ''),
            'model'       => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => (float) env('OPENAI_TEMPERATURE', 0.7),
        ],
    ],

    // Memory storage path (5 collections: memories, skills, knowledge, sessions, profiles)
    'memory_path' => env('AGENT_MEMORY_PATH', 'storage/agent/memory'),
    'memory_enabled' => env('AGENT_MEMORY_ENABLED', true),

    // Paths for A2 subsystems
    'integration_path' => env('AGENT_INTEGRATION_PATH', 'storage/agent/integrations'),
    'scheduler_path'   => env('AGENT_SCHEDULER_PATH', 'storage/agent/schedules'),
    'pages_path'       => env('AGENT_PAGES_PATH', 'storage/agent/pages'),
    'projects_path'    => env('AGENT_PROJECTS_PATH', 'storage/projects'),
    'scaffolding_path' => env('AGENT_SCAFFOLDING_PATH', 'storage/agent/scaffolding'),
];
