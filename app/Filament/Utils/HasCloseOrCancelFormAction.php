<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Override;

/**
 * Labels the form's way out "Close" while the form holds nothing unsaved, "Cancel" once it does.
 *
 * Create and edit pages stay on the record after saving, so this button is how the user leaves,
 * and a permanent "Cancel" reads as if leaving always threw something away. Dirty means what
 * Filament's unsaved-changes alert means by it: the client hashes the form state and compares it
 * with the hash the server stored when the form was filled or last saved, so a save turns the
 * button back into "Close" and a live field's round trip does not. That hash exists only while the
 * panel has unsaved-changes alerts on; without it the button keeps Filament's own label.
 *
 * For pages extending EditRecord or CreateRecord, which both declare getCancelFormAction().
 */
trait HasCloseOrCancelFormAction
{
    /**
     * Filament's own check, from its unsaved-changes alert script. The server hashes the form
     * state with its backslashes removed, so the client strips them the same way.
     */
    private const string FORM_IS_DIRTY_EXPRESSION = "window.jsMd5(JSON.stringify(\$wire.data).replace(/\\\\/g, '')) !== \$wire.savedDataHash";

    #[Override]
    protected function getCancelFormAction(): Action
    {
        $action = parent::getCancelFormAction();

        if (! $this->hasUnsavedDataChangesAlert()) {
            return $action;
        }

        $cancel_label = $action->getLabel();

        return $action->label(new HtmlString(sprintf(
            '<span x-data="%s"><span x-show="isDirty" x-cloak>%s</span><span x-show="! isDirty">%s</span></span>',
            e(sprintf('{ get isDirty() { return %s } }', self::FORM_IS_DIRTY_EXPRESSION)),
            $cancel_label instanceof Htmlable ? $cancel_label->toHtml() : e($cancel_label ?? ''),
            e(__('filament-actions::view.single.modal.actions.close.label')),
        )));
    }
}
