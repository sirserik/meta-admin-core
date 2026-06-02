<?php

namespace Meta\AdminCore\Contracts;

/**
 * Implement on a model that owns documents to control anonymous access to
 * its attached files. When the documentable implements this, the
 * DocumentController serves its files to anonymous users only while
 * `isPubliclyVisible()` is true (admins always pass). Documentables that
 * don't implement it are treated as public.
 */
interface PubliclyVisible
{
    public function isPubliclyVisible(): bool;
}
