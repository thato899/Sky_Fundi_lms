# Materials

The Materials module owns teacher/tutor-uploaded course material, its async chunking and embedding pipeline, retrieval-grounded generation, and the learner-facing AI tutor chat. See [docs/adr/011-materials-retrieval.md](../../docs/adr/011-materials-retrieval.md) for why this is a separate module rather than an Assessments extension, and for the deliberate design choices below.

## Scope

- **Ingestion is text-only in this pass.** `MaterialIngestionService::ingest()` takes already-extracted text (`min:50` characters) and creates a `Draft`-equivalent `pending` `Material` row scoped to the organization (and optionally a Subject). A PDF-extraction path is a documented follow-up — no PDF-parsing library is in `composer.json` yet, and adding one is a real dependency decision this module does not make unilaterally.
- **Chunking and embedding are async**, via `Jobs\ChunkAndEmbedMaterialJob` (`ShouldQueue`, `core.ai` queue). It paragraph-aware-splits the material's text into ~800-character chunks and embeds each one through `Core\AIGateway\Application\AIManager::embed()` — never a provider SDK directly. A Material moves `pending` → `chunking` → `ready`, or `failed` with `failure_message` set (never a raw exception) if embedding fails.
- **Retrieval is brute-force cosine similarity in PHP**, not a vector database — `Application\MaterialRetrievalService::topK()` embeds the query, loads an organization's chunks (`organization_id` filtered at the query level, always — never after the fact), and ranks by cosine similarity. This is a real, working design for the material volumes one organization's own uploads produce, not a claim it scales to a global corpus — see the ADR.
- **The AI tutor chat** (`Application\TutorChatService`, `POST /api/v1/materials/tutor-chat`) resolves the learner's organization from their own record — the same `user_id` + `portal_access_enabled` pattern `Modules\Learners\Http\Controllers\Web\LearnerPortalController` already uses — never from client input, so retrieval is always scoped to the querying learner's own organization. Answers are grounded in retrieved excerpts only; the AI is instructed to say so rather than guess when the excerpts don't contain the answer, and citations (material title + chunk index) come from the retrieval result, not from the model's own claims. See `tests/Feature/TutorChatIsolationTest.php` for the explicit cross-organization proof.
- **AI-drafted quiz generation lives in Assessments**, not here (see the ADR) — Materials only exposes retrieval; Assessments' `QuizDraftService` is the consumer and still requires the existing teacher review/approve/`publish()` gate before any learner sees AI-drafted questions.

## Data model

- `materials` — one row per upload; `content` is the raw extracted text; `status` is `pending|chunking|ready|failed`.
- `material_chunks` — one row per chunk; `embedding` is a JSON float array (no vector column type exists in MySQL 8.0 or SQLite here); unique per `(material_id, chunk_index)`.

## Routes

`GET/POST /api/v1/materials`, `GET/DELETE /api/v1/materials/{material}` — gated by `materials.view`/`materials.upload`/`materials.delete`, organization-scoped (`organization.context` + explicit `organization_id` match on show/destroy, 404 on a foreign material rather than leaking existence via 403). `POST /api/v1/materials/tutor-chat` — any authenticated learner with portal access in the current organization context; no `materials.*` permission required, matching every other learner-self-service surface in this platform.

## Allowed dependencies

`Core\AIGateway` (embeddings + completions), `Core\AuditLogs`, `Modules\Academics` (optional Subject scoping), `Modules\Learners` (`LearnerProfile` for the tutor chat's own-organization resolution). Never depends on Assessments — the dependency runs the other way.

## Known limitations

PDF ingestion is not implemented (text only). Retrieval has no vector index — see the ADR for when that would need revisiting. Embeddings are Gemini-only; no other provider implements `Core\AIGateway\Contracts\EmbeddingProviderInterface` yet.
