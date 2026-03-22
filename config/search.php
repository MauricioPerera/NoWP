<?php

/**
 * Semantic Search Configuration
 *
 * NoWP uses php-vector-store for semantic search with Matryoshka embeddings.
 *
 * Embedding providers:
 *   - 'ollama'     : Local inference (free, offline, private)
 *   - 'cloudflare' : Cloudflare Workers AI (fast, cheap)
 *   - 'openai'     : OpenAI Embeddings API
 *   - 'custom'     : Any OpenAI-compatible HTTP endpoint
 */

return [
    // Enable/disable semantic search
    'enabled' => env('SEARCH_ENABLED', true),

    // Vector dimensions (384 recommended — Matryoshka sweet spot)
    'dimensions' => env('SEARCH_DIMENSIONS', 384),

    // Use Int8 quantization (4x smaller, <0.001 accuracy loss)
    'quantized' => env('SEARCH_QUANTIZED', true),

    // Storage path for vector files
    'storage_path' => env('SEARCH_STORAGE_PATH', 'storage/vectors'),

    // Embedding provider
    'provider' => env('SEARCH_PROVIDER', 'ollama'),

    // Provider-specific settings
    'providers' => [
        'ollama' => [
            'host'       => env('OLLAMA_HOST', 'http://localhost:11434'),
            'model'      => env('OLLAMA_EMBED_MODEL', 'embeddinggemma'),
            'dimensions' => env('OLLAMA_EMBED_DIMS', 768),
        ],
        'cloudflare' => [
            'account_id' => env('CLOUDFLARE_ACCOUNT_ID', ''),
            'api_key'    => env('CLOUDFLARE_AI_API_KEY', ''),
            'model'      => env('CLOUDFLARE_EMBED_MODEL', '@cf/google/embeddinggemma-300m'),
            'dimensions' => 768,
        ],
        'openai' => [
            'api_key'    => env('OPENAI_API_KEY', ''),
            'model'      => env('OPENAI_EMBED_MODEL', 'text-embedding-3-small'),
            'dimensions' => 1536,
        ],
        'custom' => [
            'url'         => env('EMBED_API_URL', ''),
            'api_key'     => env('EMBED_API_KEY', ''),
            'model'       => env('EMBED_MODEL', ''),
            'dimensions'  => env('EMBED_DIMENSIONS', 768),
            'input_field' => env('EMBED_INPUT_FIELD', 'input'),
            'output_path' => env('EMBED_OUTPUT_PATH', 'data.0.embedding'),
        ],
    ],

    // Content types to auto-index on save
    'auto_index_types' => ['post', 'page'],

    // Auto-index on content save (disable for manual control)
    'auto_index' => env('SEARCH_AUTO_INDEX', true),
];
