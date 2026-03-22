<?php

/**
 * A2P Page Builder — materializes page definitions into renderable structures.
 *
 * The agent declares pages using components from the catalog.
 * The builder validates, resolves data sources (tools), and produces
 * a render-ready JSON structure that any frontend can consume.
 *
 * Pages are stored and served via REST API.
 * The frontend (React, Vue, vanilla JS, mobile) renders based on component type.
 */

declare(strict_types=1);

namespace Framework\Agent\Page;

use Framework\Agent\AgentService;

class PageBuilder
{
    private string $storagePath;

    /** @var array<string, array> */
    private array $pages = [];

    private ?AgentService $agent;

    public function __construct(string $storagePath, ?AgentService $agent = null)
    {
        $this->storagePath = rtrim($storagePath, '/');
        $this->agent       = $agent;

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $this->load();
    }

    /**
     * Define a page from a declarative definition.
     */
    public function define(array $definition): array
    {
        $slug = self::sanitize($definition['page'] ?? $definition['slug'] ?? '');
        if ('' === $slug) {
            return ['error' => 'Page slug is required.'];
        }

        // Validate template
        $template = $definition['template'] ?? 'dashboard';
        $templateDef = null;
        foreach (ComponentCatalog::templates() as $t) {
            if ($t['name'] === $template) {
                $templateDef = $t;
                break;
            }
        }
        if (!$templateDef) {
            return ['error' => "Unknown template: {$template}. Available: " . implode(', ', array_column(ComponentCatalog::templates(), 'name'))];
        }

        // Validate layout
        $layout = $definition['layout'] ?? 'admin-sidebar';
        $layoutDef = null;
        foreach (ComponentCatalog::layouts() as $l) {
            if ($l['name'] === $layout) {
                $layoutDef = $l;
                break;
            }
        }
        if (!$layoutDef) {
            return ['error' => "Unknown layout: {$layout}. Available: " . implode(', ', array_column(ComponentCatalog::layouts(), 'name'))];
        }

        // Validate sections
        $sections = [];
        foreach ($definition['sections'] ?? [] as $i => $section) {
            $component = $section['component'] ?? '';
            if (!ComponentCatalog::exists($component)) {
                return ['error' => "Unknown component '{$component}' in section {$i}. Available: " . implode(', ', ComponentCatalog::names())];
            }
            $sections[] = [
                'slot'      => $section['slot'] ?? 'main',
                'component' => $component,
                'props'     => $section['props'] ?? [],
            ];
        }

        $page = [
            'slug'        => $slug,
            'title'       => $definition['title'] ?? ucfirst(str_replace('-', ' ', $slug)),
            'description' => $definition['description'] ?? '',
            'template'    => $template,
            'layout'      => $layout,
            'sections'    => $sections,
            'auth'        => $definition['auth'] ?? ['required' => false],
            'nav'         => $definition['nav'] ?? null,
            'created_at'  => date('c'),
        ];

        $this->pages[$slug] = $page;
        $this->persist();

        return [
            'page'       => $slug,
            'template'   => $template,
            'layout'     => $layout,
            'sections'   => count($sections),
            'status'     => 'defined',
        ];
    }

    /**
     * Render a page — resolve data sources and return render-ready structure.
     */
    public function render(string $slug): ?array
    {
        $page = $this->pages[$slug] ?? null;
        if (!$page) return null;

        // Resolve data sources in sections
        $resolved = [];
        foreach ($page['sections'] as $section) {
            $props = $this->resolveProps($section['props']);
            $resolved[] = [
                'slot'      => $section['slot'],
                'component' => $section['component'],
                'props'     => $props,
            ];
        }

        return [
            'slug'     => $page['slug'],
            'title'    => $page['title'],
            'template' => $page['template'],
            'layout'   => $page['layout'],
            'auth'     => $page['auth'],
            'nav'      => $page['nav'],
            'sections' => $resolved,
        ];
    }

    /**
     * Remove a page.
     */
    public function remove(string $slug): array
    {
        if (!isset($this->pages[$slug])) {
            return ['error' => "Page '{$slug}' not found."];
        }

        unset($this->pages[$slug]);
        $this->persist();

        return ['page' => $slug, 'status' => 'removed'];
    }

    /**
     * List all defined pages.
     */
    public function list(): array
    {
        return array_map(fn($p) => [
            'slug'     => $p['slug'],
            'title'    => $p['title'],
            'template' => $p['template'],
            'layout'   => $p['layout'],
            'sections' => count($p['sections']),
            'auth'     => $p['auth']['required'] ?? false,
        ], array_values($this->pages));
    }

    /**
     * Get the component catalog (for the agent to know what's available).
     */
    public function catalog(): array
    {
        return ComponentCatalog::all();
    }

    // ── Private ────────────────────────────────────────────────────

    /**
     * Resolve data sources in props.
     * Props with 'source' key call the specified tool to get data.
     */
    private function resolveProps(array $props): array
    {
        $resolved = [];

        foreach ($props as $key => $value) {
            if ('source' === $key && is_array($value) && isset($value['tool'])) {
                // Resolve tool call
                $resolved[$key] = $value; // keep definition
                $resolved['data'] = $this->callTool($value['tool'], $value['args'] ?? []);
            } elseif (is_array($value)) {
                // Recursively resolve nested arrays
                $resolved[$key] = $this->resolveProps($value);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    private function callTool(string $name, array $args): mixed
    {
        if (!$this->agent) return ['error' => 'No agent available for data resolution'];

        try {
            return $this->agent->invokeToolByName($name, $args);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private static function sanitize(string $name): string
    {
        return preg_replace('/[^a-z0-9-]/', '-', strtolower(trim($name)));
    }

    private function persist(): void
    {
        file_put_contents(
            $this->storagePath . '/pages.json',
            json_encode($this->pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function load(): void
    {
        $file = $this->storagePath . '/pages.json';
        if (!file_exists($file)) return;

        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            $this->pages = $data;
        }
    }
}
