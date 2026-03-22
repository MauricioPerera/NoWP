<?php

declare(strict_types=1);

namespace Framework\Agent\Project;

/**
 * Project Manager — handles multi-project support.
 *
 * Each project is an isolated directory with its own:
 * - Database (separate MySQL DB or SQLite)
 * - Vector store
 * - Agent memory (memories, skills, knowledge, sessions, profiles)
 * - Pages (A2P definitions)
 * - Integrations (A2I services)
 * - Schedules (A2E crons)
 * - Scaffolding state
 *
 * Projects are stored in a root directory with a manifest file.
 */
class ProjectManager
{
    private string $rootPath;
    private ?string $activeProjectId = null;
    private array $projects = [];

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, '/');

        if (!is_dir($this->rootPath)) {
            mkdir($this->rootPath, 0755, true);
        }

        $this->loadManifest();
    }

    /**
     * Create a new project.
     */
    public function create(string $name, string $description = ''): array
    {
        $id = $this->slugify($name);

        if (isset($this->projects[$id])) {
            return ['error' => "Project '{$id}' already exists"];
        }

        $projectPath = $this->projectPath($id);
        mkdir($projectPath, 0755, true);

        // Create project subdirectories
        foreach (['vectors', 'agent/memory', 'agent/pages', 'agent/integrations', 'agent/schedules', 'agent/scaffolding'] as $dir) {
            mkdir($projectPath . '/' . $dir, 0755, true);
        }

        $project = [
            'id'          => $id,
            'name'        => $name,
            'description' => $description,
            'status'      => 'active',
            'createdAt'   => date('c'),
            'updatedAt'   => date('c'),
        ];

        // Save project config
        file_put_contents(
            $projectPath . '/project.json',
            json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->projects[$id] = $project;
        $this->saveManifest();

        // Auto-activate if no active project
        if (!$this->activeProjectId) {
            $this->activate($id);
        }

        return $project;
    }

    /**
     * List all projects.
     */
    public function list(): array
    {
        $list = [];
        foreach ($this->projects as $id => $project) {
            $project['active'] = ($id === $this->activeProjectId);
            $project['stats'] = $this->getProjectStats($id);
            $list[] = $project;
        }
        return $list;
    }

    /**
     * Get a project by ID.
     */
    public function get(string $id): ?array
    {
        $project = $this->projects[$id] ?? null;
        if (!$project) return null;

        $project['active'] = ($id === $this->activeProjectId);
        $project['stats'] = $this->getProjectStats($id);
        return $project;
    }

    /**
     * Activate a project — all subsequent operations use this project's storage.
     */
    public function activate(string $id): array
    {
        if (!isset($this->projects[$id])) {
            return ['error' => "Project '{$id}' not found"];
        }

        $this->activeProjectId = $id;
        $this->saveManifest();

        return ['id' => $id, 'status' => 'activated'];
    }

    /**
     * Delete a project and all its data.
     */
    public function delete(string $id): array
    {
        if (!isset($this->projects[$id])) {
            return ['error' => "Project '{$id}' not found"];
        }

        // Remove directory recursively
        $path = $this->projectPath($id);
        if (is_dir($path)) {
            $this->removeDir($path);
        }

        unset($this->projects[$id]);

        // If deleting active project, deactivate
        if ($this->activeProjectId === $id) {
            $this->activeProjectId = array_key_first($this->projects) ?: null;
        }

        $this->saveManifest();

        return ['id' => $id, 'status' => 'deleted'];
    }

    /**
     * Rename a project.
     */
    public function rename(string $id, string $newName): array
    {
        if (!isset($this->projects[$id])) {
            return ['error' => "Project '{$id}' not found"];
        }

        $this->projects[$id]['name'] = $newName;
        $this->projects[$id]['updatedAt'] = date('c');
        $this->saveManifest();

        // Update project.json
        $projectFile = $this->projectPath($id) . '/project.json';
        if (file_exists($projectFile)) {
            $data = json_decode(file_get_contents($projectFile), true) ?: [];
            $data['name'] = $newName;
            $data['updatedAt'] = date('c');
            file_put_contents($projectFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return ['id' => $id, 'name' => $newName, 'status' => 'renamed'];
    }

    /**
     * Get the active project ID.
     */
    public function activeId(): ?string
    {
        return $this->activeProjectId;
    }

    /**
     * Get paths for the active project's storage directories.
     */
    public function activePaths(): array
    {
        if (!$this->activeProjectId) {
            // Fallback to default paths
            return [
                'vectors'       => 'storage/vectors',
                'memory'        => 'storage/agent/memory',
                'pages'         => 'storage/agent/pages',
                'integrations'  => 'storage/agent/integrations',
                'schedules'     => 'storage/agent/schedules',
                'scaffolding'   => 'storage/agent/scaffolding',
            ];
        }

        $base = $this->projectPath($this->activeProjectId);
        return [
            'vectors'       => $base . '/vectors',
            'memory'        => $base . '/agent/memory',
            'pages'         => $base . '/agent/pages',
            'integrations'  => $base . '/agent/integrations',
            'schedules'     => $base . '/agent/schedules',
            'scaffolding'   => $base . '/agent/scaffolding',
        ];
    }

    /**
     * Get the database name for the active project.
     */
    public function activeDatabase(): string
    {
        if (!$this->activeProjectId) {
            return env('DB_DATABASE', 'nowp_test');
        }
        return 'nowp_' . str_replace('-', '_', $this->activeProjectId);
    }

    // ── Private ──────────────────────────────────────────────────

    private function projectPath(string $id): string
    {
        return $this->rootPath . '/' . $id;
    }

    private function getProjectStats(string $id): array
    {
        $path = $this->projectPath($id);

        $countFiles = function (string $dir) use ($path): int {
            $full = $path . '/' . $dir;
            if (!is_dir($full)) return 0;
            $count = 0;
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($full, \FilesystemIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if ($file->getExtension() === 'json') $count++;
            }
            return $count;
        };

        // Count pages
        $pagesFile = $path . '/agent/pages/pages.json';
        $pagesCount = 0;
        if (file_exists($pagesFile)) {
            $pages = json_decode(file_get_contents($pagesFile), true);
            $pagesCount = is_array($pages) ? count($pages) : 0;
        }

        // Count services
        $servicesFile = $path . '/agent/integrations/services.json';
        $servicesCount = 0;
        if (file_exists($servicesFile)) {
            $services = json_decode(file_get_contents($servicesFile), true);
            $servicesCount = is_array($services) ? count($services) : 0;
        }

        return [
            'memories'  => $countFiles('agent/memory'),
            'pages'     => $pagesCount,
            'services'  => $servicesCount,
        ];
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    private function loadManifest(): void
    {
        $file = $this->rootPath . '/manifest.json';
        if (!file_exists($file)) return;

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) return;

        $this->projects = $data['projects'] ?? [];
        $this->activeProjectId = $data['active'] ?? null;
    }

    private function saveManifest(): void
    {
        file_put_contents(
            $this->rootPath . '/manifest.json',
            json_encode([
                'active'   => $this->activeProjectId,
                'projects' => $this->projects,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
