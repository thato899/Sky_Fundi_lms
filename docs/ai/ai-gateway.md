# AI Gateway

## Principle

> No module communicates directly with an AI provider. Everything goes through the AI Gateway.

The AI Gateway (`core/AIGateway`) is a Core service that abstracts away *which* AI provider actually serves a request. Modules ask the Gateway for a capability ("summarize this text," "generate practice questions for this topic," "transcribe this audio") and never instantiate a provider SDK client themselves.

## Why This Exists

- **Provider flexibility**: institutions may want on-premise/offline AI (Ollama) for cost or data-sovereignty reasons, or a hosted provider (OpenAI, Claude, Gemini, DeepSeek) for capability. Switching providers must not require touching module code.
- **Cost and usage control**: rate limiting, budget caps, and usage tracking are enforced once, centrally, instead of duplicated (or missed) per module.
- **Safety and policy enforcement**: content filtering, prompt-injection defenses, and data redaction (e.g. not sending a learner's full name/PII to a third-party provider unless explicitly permitted) are enforced at one chokepoint.
- **Auditability**: every AI request/response can be logged (subject to privacy policy) for debugging and compliance, without every module reimplementing logging.

## Supported/Planned Providers

| Provider | Notes |
|---|---|
| Ollama | Self-hosted/offline models; relevant for the "Offline AI" roadmap item (v2.0) |
| DeepSeek | Hosted provider |
| OpenAI | Hosted provider |
| Claude (Anthropic) | Hosted provider |
| Gemini | Hosted provider |
| Future providers | Added via a new adapter implementing the Gateway's provider interface |

## Gateway Interface (conceptual)

Modules depend on an interface, not a provider SDK:

```php
interface AIGatewayInterface
{
    public function complete(AIRequest $request): AIResponse;
    public function stream(AIRequest $request): Generator;
}
```

`AIRequest` carries the capability being requested, the input, tenant/module context (for usage attribution and policy enforcement), and any provider-preference hints (e.g. "prefer offline provider"). The concrete provider used to fulfill a given request is a Gateway/Core routing decision, configurable per tenant and per capability, not something module code decides.

## Tenant and Module Attribution

Every AI request carries tenant and module identifiers so that usage/cost can be attributed and, where relevant, billed (`core/Billing`) or capped (`core/Licensing`) per tenant.

## Data Handling

- The Gateway is the enforcement point for what data may be sent to a third-party provider versus what must stay on an on-premise/offline provider, per tenant configuration and applicable data protection requirements.
- PII redaction/minimization rules are configured centrally in the Gateway, not left to each module's judgment.

## Failure Handling

If a configured provider is unavailable, the Gateway is responsible for fallback behavior (retry, fallback provider, or a clear `503`-style error per [API error handling](../api/error-handling.md)) — module code should not need provider-specific error handling.

## Status

This document defines the contract. The concrete `core/AIGateway` implementation, provider adapters, and routing/policy configuration are future work — see [Roadmap](../roadmap.md), where AI Gateway is a v1.0 Core deliverable and Offline AI (Ollama-first) is a v2.0 deliverable.
