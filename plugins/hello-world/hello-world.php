<?php
declare(strict_types=1);
namespace Plugins\HelloWorld;
use ChimeraNoWP\Plugin\PluginInterface;
use ChimeraNoWP\Core\Container;
use ChimeraNoWP\Plugin\HookSystem;
class HelloWorld implements PluginInterface {
    public function __construct(private Container $container, private HookSystem $hooks) {}
    public function register(): void {}
    public function boot(): void {
        if ($this->container->has(\ChimeraNoWP\Plugin\PluginManager::class)) {
            $pm = $this->container->get(\ChimeraNoWP\Plugin\PluginManager::class);
            $pm->registerRoute('GET', '/api/hello', function() {
                return new \ChimeraNoWP\Core\Response(
                    json_encode(['message' => 'Hello from plugin!', 'timestamp' => date('c')]),
                    200, ['Content-Type' => 'application/json']
                );
            });
        }
    }
    public function deactivate(): void {}
    public function getName(): string { return 'Hello World'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDependencies(): array { return []; }
}
