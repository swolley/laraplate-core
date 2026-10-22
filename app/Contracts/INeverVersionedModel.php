<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A model whose rows are never versioned, whatever the per-table setting says: a contribution of somebody else
 * to a record, such as a comment or a rating, which is not part of the document (D8). The translation models are
 * never versioned either, through {@see \Modules\Core\Services\Translation\Definitions\ITranslated}: their state
 * belongs to the owner's history (D10, D28).
 */
interface INeverVersionedModel {}
