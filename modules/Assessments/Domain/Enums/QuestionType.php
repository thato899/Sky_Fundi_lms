<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case ShortResponse = 'short_response';
    case LongResponse = 'long_response';

    public function isObjective(): bool
    {
        return in_array($this, [self::MultipleChoice, self::TrueFalse], true);
    }
}
