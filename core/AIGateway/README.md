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
- `Infrastructure/Providers/{OpenAIProvider,ClaudeProvider,GeminiProvider}` — real, registered, plug-and-play implementations of `AIProviderInterface` (via `AbstractPlaceholderProvider`) that report `isAvailable(): false` and throw a clear `ProviderNotAvailableException::notImplemented()` if ever selected, rather than being silently absent from the provider registry. Implementing each fully is future work — the contract and registration are already in place.

**Allowed dependencies**: `Core\Logging`. Never a module.

**Routes**: `GET /api/v1/ai/providers` (list + availability), `POST /api/v1/ai/providers/test` (send a test prompt to a named provider) — both gated by `core.ai.manage`.

**Future usage**: implementing OpenAI/Claude/Gemini for real means writing their HTTP call inside the existing class (see the docblock on each) and nothing else — `AIManager`, the registry, and every caller are already provider-agnostic.
