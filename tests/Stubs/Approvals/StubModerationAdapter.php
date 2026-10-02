<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Approvals;

use Modules\Core\Contracts\ModerationAdapter;
use Modules\Core\Data\ModerationInput;
use Modules\Core\Data\ModerationRequest;
use Modules\Core\Models\Modification;
use Modules\Core\Tests\Stubs\HasApprovalsStubModel;

/**
 * Offers automated moderation for {@see HasApprovalsStubModel}, the way a module registers an
 * adapter for its own content, so Core tests need no module to have a moderated model.
 */
final readonly class StubModerationAdapter implements ModerationAdapter
{
    public function modelClass(): string
    {
        return HasApprovalsStubModel::class;
    }

    public function supports(Modification $modification): bool
    {
        return $modification->modifiable_type === HasApprovalsStubModel::class;
    }

    public function build(Modification $modification): ModerationRequest
    {
        return new ModerationRequest(
            input: new ModerationInput(subjectText: '', locale: 'en', contextSections: [], profile: 'core.stub'),
            systemPrompt: 'Moderate.',
            userPrompt: '',
        );
    }
}
