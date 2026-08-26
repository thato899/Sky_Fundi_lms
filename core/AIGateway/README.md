# core/AIGateway

**Purpose**: the single AI abstraction layer — no module or Core service is permitted to talk to an AI provider directly. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md) and [AI Gateway](../../docs/ai/ai-gateway.md).

**Responsibilities**:
- `Contracts/AIProviderInterface` — `complete()/stream()/isAvailable()/name()`. Every provider adapter implements this and nothing else is exposed to callers.
- `Application/DTOs/{AIRequest,AIResponse}` — the provider-agnostic request/response shape callers build and receive.
- `Application/ProviderFactory` — the Configuration Loader: instantiates a provider adapter from its `config('ai.providers.<name>')` entry.
- `Application/ProviderRegistry` — lists every configured provider and which are currently available (used by the admin AI settings screen).
- `Application/AIManager` — the entry point every caller depends on. Resolves the provider (explicit request preference -> platform default), calls it, and on `ProviderNotAvailableException`/`AIGatewayException` retries once against `config('ai.fallback_provider')` if configured, logging via `Core\Logging`'s `ai` channel throughout.
- `Infrastructure/Providers/OllamaProvider` — fully implemented, self-hosted/offline provider via Ollama's HTTP API (`/api/generate`), including streaming.
- `Infrastructure/Providers/DeepSeekProvider` — fully implemented, OpenAI-compatible hosted provider (`/chat/completions`), including SSE streaming.
- `Infrastructure/Providers/OpenAIProvider` — fully implemented against the OpenAI Responses API (`/responses`), including native JSON-schema structured output.
- `Infrastructure/Providers/GeminiProvider` — fully implemented against Google's Generative Language API (`/models/{model}:generateContent`, `:streamGenerateContent?alt=sse`), authenticated via the `x-goog-api-key` header. Structured output uses `responseMimeType: application/json` plus a schema-describing system instruction (the same strategy as `DeepSeekProvider`) rather than Gemini's native `responseSchema`, which uses its own upper-case type vocabulary that every caller's plain JSON Schema would need translating into first.
- `Infrastructure/Providers/ClaudeProvider` — real, registered, plug-and-play implementation of `AIProviderInterface` (via `AbstractPlaceholderProvider`) that reports `isAvailable(): false` and throws a clear `ProviderNotAvailableException::notImplemented()` if ever selected, rather than being silently absent from the provider registry. Implementing it fully is future work — the contract and registration are already in place.

**Allowed dependencies**: `Core\Logging`. Never a module.

**Routes**: `GET /api/v1/ai/providers` (list + availability), `POST /api/v1/ai/providers/test` (send a test prompt to a named provider) — both gated by `core.ai.manage`.

**Future usage**: implementing Claude for real means writing its HTTP call inside the existing class (see its docblock) and nothing else — `AIManager`, the registry, and every caller are already provider-agnostic.
