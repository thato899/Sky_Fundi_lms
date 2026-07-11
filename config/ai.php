<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AI Gateway Configuration
|--------------------------------------------------------------------------
|
| See docs/ai/ai-gateway.md. No module or Core service may talk to a
| provider SDK directly — everything resolves through
| Core\AIGateway\Application\AIManager, which uses this configuration to
| pick a provider per request (explicit request preference, tenant
| default, then platform default, in that order).
|
*/

return [
    // Platform-wide fallback provider when no tenant/request preference
    // is set. Kept intentionally provider-agnostic — this is Core\Settings
    // -overridable per docs/ai/ai-gateway.md, this is just the bootstrap
    // default before Settings has been seeded.
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'ollama'),

    'providers' => [
        'ollama' => [
            'driver' => Core\AIGateway\Infrastructure\Providers\OllamaProvider::class,
            'base_url' => env('AI_OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('AI_OLLAMA_MODEL', 'llama3'),
            'timeout' => (int) env('AI_OLLAMA_TIMEOUT', 60),
            'enabled' => (bool) env('AI_OLLAMA_ENABLED', true),
        ],

        'deepseek' => [
            'driver' => Core\AIGateway\Infrastructure\Providers\DeepSeekProvider::class,
            'base_url' => env('AI_DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'api_key' => env('AI_DEEPSEEK_API_KEY'),
            'model' => env('AI_DEEPSEEK_MODEL', 'deepseek-chat'),
            'timeout' => (int) env('AI_DEEPSEEK_TIMEOUT', 60),
            'enabled' => (bool) env('AI_DEEPSEEK_ENABLED', false),
        ],

        // Future providers — see docs/ai/ai-gateway.md#supportedplanned-providers.
        // Each ships a real class implementing AIProviderInterface that
        // throws ProviderNotAvailableException until wired up, rather
        // than being silently absent from the registry.
        'openai' => [
            'driver' => Core\AIGateway\Infrastructure\Providers\OpenAIProvider::class,
            'api_key' => env('AI_OPENAI_API_KEY'),
            'model' => env('AI_OPENAI_MODEL', 'gpt-4o'),
            'enabled' => (bool) env('AI_OPENAI_ENABLED', false),
        ],

        'claude' => [
            'driver' => Core\AIGateway\Infrastructure\Providers\ClaudeProvider::class,
            'api_key' => env('AI_CLAUDE_API_KEY'),
            'model' => env('AI_CLAUDE_MODEL', 'claude-sonnet-4-6'),
            'enabled' => (bool) env('AI_CLAUDE_ENABLED', false),
        ],

        'gemini' => [
            'driver' => Core\AIGateway\Infrastructure\Providers\GeminiProvider::class,
            'api_key' => env('AI_GEMINI_API_KEY'),
            'model' => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
            'enabled' => (bool) env('AI_GEMINI_ENABLED', false),
        ],
    ],

    // Retry/fallback behaviour when the resolved provider is unavailable.
    // See docs/ai/ai-gateway.md#failure-handling.
    'fallback_provider' => env('AI_FALLBACK_PROVIDER'),
    'max_retries' => (int) env('AI_MAX_RETRIES', 1),
];
