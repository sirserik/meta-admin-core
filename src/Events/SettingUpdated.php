<?php

namespace Meta\AdminCore\Events;

use Meta\AdminCore\Models\Setting;

/**
 * Fired after a Setting row is updated via the admin UI. Consumer apps
 * register listeners for side-effects: syncing `university_name` into
 * a hero block, pinging a CDN, warming cached renders, etc.
 */
class SettingUpdated
{
    public function __construct(
        public readonly Setting $setting,
        public readonly array $oldValue,
        public readonly array $newValue,
    ) {}
}
