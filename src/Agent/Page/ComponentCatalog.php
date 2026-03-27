<?php

/**
 * A2P Component Catalog — fixed set of UI components the agent can use.
 *
 * Atomic Design hierarchy:
 *   Atoms     → smallest visual units (text, button, input)
 *   Molecules → combinations of atoms (form-field, stat-card)
 *   Organisms → complex sections (data-table, form, card-grid)
 *   Templates → full page structures (dashboard, login, list-detail)
 *   Layouts   → page shells (admin-sidebar, public-centered)
 */

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Page;

class ComponentCatalog
{
    /**
     * Get all available components grouped by level.
     */
    public static function all(): array
    {
        return [
            'atoms'     => self::atoms(),
            'molecules' => self::molecules(),
            'organisms' => self::organisms(),
            'templates' => self::templates(),
            'layouts'   => self::layouts(),
        ];
    }

    /**
     * Get a flat list of all component names.
     */
    public static function names(): array
    {
        $all = self::all();
        $names = [];
        foreach ($all as $level => $components) {
            foreach ($components as $c) {
                $names[] = $c['name'];
            }
        }
        return $names;
    }

    /**
     * Validate that a component exists.
     */
    public static function exists(string $name): bool
    {
        return in_array($name, self::names(), true);
    }

    /**
     * Get component definition by name.
     */
    public static function get(string $name): ?array
    {
        foreach (self::all() as $components) {
            foreach ($components as $c) {
                if ($c['name'] === $name) return $c;
            }
        }
        return null;
    }

    // ── Atoms ──────────────────────────────────────────────────────

    public static function atoms(): array
    {
        return [
            ['name' => 'text',    'props' => ['content' => 'string', 'tag' => 'string', 'class' => 'string']],
            ['name' => 'heading', 'props' => ['content' => 'string', 'level' => 'integer']],
            ['name' => 'button',  'props' => ['label' => 'string', 'action' => 'string', 'to' => 'string', 'variant' => 'string', 'tool' => 'string', 'args' => 'object']],
            ['name' => 'input',   'props' => ['name' => 'string', 'type' => 'string', 'label' => 'string', 'placeholder' => 'string', 'required' => 'boolean']],
            ['name' => 'badge',   'props' => ['label' => 'string', 'color' => 'string']],
            ['name' => 'icon',    'props' => ['name' => 'string', 'size' => 'integer']],
            ['name' => 'image',   'props' => ['src' => 'string', 'alt' => 'string', 'width' => 'integer']],
            ['name' => 'link',    'props' => ['label' => 'string', 'to' => 'string', 'external' => 'boolean']],
            ['name' => 'divider', 'props' => []],
        ];
    }

    // ── Molecules ──────────────────────────────────────────────────

    public static function molecules(): array
    {
        return [
            ['name' => 'form-field',  'props' => ['name' => 'string', 'type' => 'string', 'label' => 'string', 'required' => 'boolean', 'options' => 'array', 'placeholder' => 'string']],
            ['name' => 'stat-card',   'props' => ['label' => 'string', 'value' => 'string', 'icon' => 'string', 'color' => 'string', 'source' => 'object']],
            ['name' => 'nav-item',    'props' => ['label' => 'string', 'to' => 'string', 'icon' => 'string', 'active' => 'boolean']],
            ['name' => 'search-bar',  'props' => ['placeholder' => 'string', 'action' => 'string', 'tool' => 'string']],
            ['name' => 'alert',       'props' => ['message' => 'string', 'type' => 'string']],
            ['name' => 'breadcrumb',  'props' => ['items' => 'array']],
            ['name' => 'avatar',      'props' => ['name' => 'string', 'src' => 'string', 'size' => 'string']],
            ['name' => 'menu-item',   'props' => ['label' => 'string', 'to' => 'string', 'icon' => 'string', 'children' => 'array']],
        ];
    }

    // ── Organisms ──────────────────────────────────────────────────

    public static function organisms(): array
    {
        return [
            ['name' => 'data-table',    'props' => ['source' => 'object', 'columns' => 'array', 'row_actions' => 'array', 'paginate' => 'boolean', 'searchable' => 'boolean']],
            ['name' => 'form',          'props' => ['fields' => 'array', 'submit_label' => 'string', 'action' => 'string', 'tool' => 'string', 'method' => 'string']],
            ['name' => 'card-grid',     'props' => ['source' => 'object', 'card_title' => 'string', 'card_body' => 'string', 'card_actions' => 'array', 'columns' => 'integer']],
            ['name' => 'stat-cards',    'props' => ['cards' => 'array']],
            ['name' => 'button-group',  'props' => ['buttons' => 'array']],
            ['name' => 'nav-bar',       'props' => ['items' => 'array', 'brand' => 'string', 'brand_to' => 'string']],
            ['name' => 'sidebar-nav',   'props' => ['items' => 'array', 'header' => 'string']],
            ['name' => 'detail-view',   'props' => ['source' => 'object', 'fields' => 'array', 'title_field' => 'string', 'actions' => 'array']],
            ['name' => 'chart',         'props' => ['type' => 'string', 'source' => 'object', 'x_field' => 'string', 'y_field' => 'string', 'label' => 'string']],
            ['name' => 'timeline',      'props' => ['source' => 'object', 'date_field' => 'string', 'title_field' => 'string', 'body_field' => 'string']],
            ['name' => 'empty-state',   'props' => ['title' => 'string', 'message' => 'string', 'action' => 'object']],
        ];
    }

    // ── Templates ──────────────────────────────────────────────────

    public static function templates(): array
    {
        return [
            ['name' => 'dashboard',     'slots' => ['header', 'stats', 'main', 'sidebar'],       'description' => 'Overview page with stats and main content area'],
            ['name' => 'list-page',     'slots' => ['header', 'actions', 'filters', 'main'],     'description' => 'List of records with filters and actions'],
            ['name' => 'detail-page',   'slots' => ['header', 'main', 'sidebar', 'actions'],     'description' => 'Single record detail view'],
            ['name' => 'form-page',     'slots' => ['header', 'main'],                           'description' => 'Form for creating or editing a record'],
            ['name' => 'login-page',    'slots' => ['main'],                                     'description' => 'Authentication page'],
            ['name' => 'landing-page',  'slots' => ['hero', 'features', 'cta', 'footer'],        'description' => 'Public marketing page'],
            ['name' => 'settings-page', 'slots' => ['header', 'nav', 'main'],                    'description' => 'Settings with sidebar navigation'],
            ['name' => 'error-page',    'slots' => ['main'],                                     'description' => 'Error display (404, 500, etc)'],
        ];
    }

    // ── Layouts ────────────────────────────────────────────────────

    public static function layouts(): array
    {
        return [
            ['name' => 'admin-sidebar',   'regions' => ['nav', 'header', 'content'],         'description' => 'Admin panel with sidebar navigation'],
            ['name' => 'public-centered', 'regions' => ['header', 'content', 'footer'],      'description' => 'Centered public page'],
            ['name' => 'fullwidth',       'regions' => ['header', 'content', 'footer'],      'description' => 'Full-width content'],
            ['name' => 'split',           'regions' => ['left', 'right'],                    'description' => 'Two-panel split view'],
            ['name' => 'stacked',         'regions' => ['content'],                          'description' => 'Simple stacked sections'],
        ];
    }
}
