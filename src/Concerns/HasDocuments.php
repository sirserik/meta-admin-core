<?php

namespace Meta\AdminCore\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Meta\AdminCore\Models\Document;

/**
 * Attach downloadable files to a model: `$model->documents`. Use the
 * `admin-core.documents.*` routes (DocumentController) to upload/manage/serve
 * them. Pair with the `PubliclyVisible` contract to gate anonymous downloads
 * of files attached to unpublished parents.
 */
trait HasDocuments
{
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->ordered();
    }
}
