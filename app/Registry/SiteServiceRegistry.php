<?php

declare(strict_types=1);

namespace App\Registry;

use App\Console\Commands\BuildCommand;
use App\Console\Commands\FetchCommand;
use App\Docs\DocsLibrary;
use App\Http\Controller\HomeController;
use App\Markdown\Highlighter;
use App\Markdown\LinkResolver;
use App\Markdown\MarkdownRenderer;
use App\Site\Exporter;
use App\Site\SiteContext;
use App\Source\SourceFetcher;
use Crystoline\Xpsr\Application;
use Crystoline\Xpsr\Configuration;
use Crystoline\Xpsr\Console\CommandRegistry;
use Crystoline\Xpsr\ServiceContainer\ServiceRegistry;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SiteServiceRegistry extends ServiceRegistry
{
    public function register(): void
    {
        $this->container->singleton(Highlighter::class);

        $this->container->factory(DocsLibrary::class, static function (ContainerInterface $container): DocsLibrary {
            $config = $container->get(Configuration::class);
            $nextPath = $config->get('site.docs.next_path');

            return new DocsLibrary(
                new MarkdownRenderer(new LinkResolver((string) $config->get('site.github')), $container->get(Highlighter::class)),
                (string) $config->get('site.docs.sources'),
                is_string($nextPath) && $nextPath !== '' ? $nextPath : null,
                (string) $config->get('site.docs.fallback_navigation'),
            );
        }, shared: true);

        $this->container->factory(SiteContext::class, static fn(ContainerInterface $container): SiteContext => new SiteContext(
            $container->get(Configuration::class),
            $container->get(DocsLibrary::class),
            self::root($container) . '/public',
        ), shared: true);

        $this->container->factory(HomeController::class, static fn(ContainerInterface $container): HomeController => new HomeController(
            $container->get(DocsLibrary::class),
            $container->get(SiteContext::class),
            $container->get(Highlighter::class),
            self::root($container) . '/resources/samples',
        ));

        $this->container->factory(SourceFetcher::class, static function (ContainerInterface $container): SourceFetcher {
            $docs = $container->get(Configuration::class)->get('site.docs');

            return new SourceFetcher(
                (string) $docs['repository'],
                (string) $docs['sources'],
                (string) $docs['next_branch'],
                (string) $docs['since'],
                array_values(array_map('strval', (array) $docs['ignore_tags'])),
            );
        });

        $this->container->factory(Exporter::class, static fn(ContainerInterface $container): Exporter => new Exporter(
            $container->get(RequestHandlerInterface::class),
            $container->get(ServerRequestFactoryInterface::class),
            $container->get(DocsLibrary::class),
            $container->get(SiteContext::class),
            self::root($container) . '/public',
        ));

        $this->container->bind(FetchCommand::class);
        $this->container->bind(BuildCommand::class);
    }

    public function boot(): void
    {
        $commands = $this->container->get(CommandRegistry::class);
        $commands->register('site:fetch', FetchCommand::class, 'Fetch the documentation of every version from the framework repository.');
        $commands->register('site:build', BuildCommand::class, 'Build the static site.');
    }

    private static function root(ContainerInterface $container): string
    {
        return $container->get(Application::class)->getRootDir();
    }
}
