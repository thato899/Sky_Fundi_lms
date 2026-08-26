<?php

declare(strict_types=1);

namespace Modules\Materials\Domain\Enums;

/**
 * `Pdf` is deliberately unused today — see docs/adr/011-materials-retrieval.md
 * ("ingestion scope for this pass is text only"). It exists so the
 * eventual PDF-extraction follow-up is a source-type addition, not a
 * schema migration.
 */
enum MaterialSourceType: string
{
    case Text = 'text';
    case Pdf = 'pdf';
}
