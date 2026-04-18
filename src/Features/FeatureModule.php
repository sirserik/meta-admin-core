<?php

namespace Meta\AdminCore\Features;

use Meta\AdminCore\AdminCore;

/**
 * Base class for packaged feature modules (Green Deal Center, SDG portal,
 * Library, etc.). A feature module self-registers its resources, menu
 * items, dashboard widgets when enabled — consumer apps don't need to
 * duplicate that registration per site.
 *
 * Subclass, implement register(), then add to
 * AdminCoreServiceProvider::$features list (or register via
 * AdminCore::registerFeature).
 */
abstract class FeatureModule
{
    /** Machine-readable name (e.g. 'green_deal'). Must match config key. */
    abstract public function name(): string;

    /** Human-readable label shown in the Features admin page. */
    abstract public function label(): string;

    /** One-line description of what this module adds. */
    abstract public function description(): string;

    /** FontAwesome icon for the features page card. */
    public function icon(): string { return 'fa-puzzle-piece'; }

    /**
     * Register resources, menu items, dashboard widgets, etc.
     * Called once at service-provider boot if the feature is enabled.
     */
    abstract public function register(AdminCore $core): void;

    /**
     * Can this module's registration actually run? If it depends on a
     * consumer-specific model/class, override this to check class_exists.
     * Returning false means the module is not available on this install
     * and the Features page will show it as "недоступно".
     */
    public function available(): bool
    {
        return true;
    }

    /** Message to show on the Features page when `available()` is false. */
    public function unavailableReason(): ?string
    {
        return null;
    }
}
