<?php

declare(strict_types=1);

namespace Lctrs\MinkPantherDriver\Extension\Driver;

use Behat\MinkExtension\ServiceContainer\Driver\DriverFactory;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverCapabilities;
use InvalidArgumentException;
use Lctrs\MinkPantherDriver\PantherDriver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use function array_key_exists;
use function is_array;
use function is_string;
use function method_exists;

final class PantherFactory implements DriverFactory
{
    public function getDriverName() : string
    {
        return 'panther';
    }

    public function supportsJavascript() : bool
    {
        return true;
    }

    public function configure(ArrayNodeDefinition $builder) : void
    {
        $builder
            ->children()
                ->arrayNode('chrome')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('binary')->defaultNull()->end()
                        ->variableNode('arguments')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(
                                    /**
                                     * @param list<string|float|int|bool|null>|string|float|int|bool|null $v
                                     */
                                    static function ($v) : bool {
                                        if ($v === null) {
                                            return false;
                                        }

                                        if (! is_array($v)) {
                                            return true;
                                        }

                                        foreach ($v as $child) {
                                            if (! is_string($child)) {
                                                return true;
                                            }
                                        }

                                        return false;
                                    }
                                )
                                ->thenInvalid('"arguments" must be an array of strings or null.')
                            ->end()
                        ->end()
                        ->arrayNode('options')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('scheme')->end()
                                ->scalarNode('host')->end()
                                ->integerNode('port')->end()
                                ->scalarNode('path')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('selenium')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('host')->defaultNull()->end()
                        ->scalarNode('browser')
                            ->defaultValue('chrome')
                            ->validate()
                                ->ifTrue(
                                    /**
                                     * @param string|bool|float|int $v
                                     */
                                    static function ($v) : bool {
                                        if (! is_string($v)) {
                                            return true;
                                        }

                                        return ! method_exists(DesiredCapabilities::class, $v);
                                    }
                                )
                                ->thenInvalid('%s is not a valid or supported browser.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(
                    /**
                     * @param array{selenium?: array{enabled: bool}} $v
                     */
                    static function (array $v) : bool {
                        return $v['selenium']['enabled'] ?? false;
                    }
                )
                ->then(
                    /**
                     * @param array{chrome?: array<string, string>} $v
                     */
                    static function (array $v) : array {
                        unset($v['chrome']);

                        return $v;
                    }
                )
            ->end()
            ->validate()
                ->ifTrue(
                    /**
                     * @param array{chrome?: array{enabled: bool}} $v
                     */
                    static function (array $v) : bool {
                        return $v['chrome']['enabled'] ?? false;
                    }
                )
                ->then(
                    /**
                     * @param array{selenium?: array<string, string>} $v
                     */
                    static function (array $v) : array {
                        unset($v['selenium']);

                        return $v;
                    }
                )
            ->end()
            ->validate()
                ->always(
                    /**
                     * @param array{chrome?: array{enabled?: bool}, selenium?: array{enabled?: bool}} $v
                     */
                    static function ($v) : array {
                        unset($v['chrome']['enabled']);
                        unset($v['selenium']['enabled']);

                        return $v;
                    }
                )
            ->end();
    }

    /**
     * @param mixed[] $config
     */
    public function buildDriver(array $config) : Definition
    {
        if (array_key_exists('chrome', $config)) {
            return (new Definition(PantherDriver::class))
                ->setFactory([PantherDriver::class, 'createChromeDriver'])
                ->setArguments([
                    $config['chrome']['binary'],
                    $config['chrome']['arguments'],
                    $config['chrome']['options'],
                ]);
        }

        if (array_key_exists('selenium', $config)) {
            return (new Definition(PantherDriver::class))
                ->setFactory([PantherDriver::class, 'createSeleniumDriver'])
                ->setArguments([
                    $config['selenium']['host'],
                    (new Definition(WebDriverCapabilities::class))
                        ->setFactory([DesiredCapabilities::class, $config['selenium']['browser']]),
                ]);
        }

        throw new InvalidArgumentException('Unable to build a ' . PantherDriver::class . ' instance with the given config.');
    }
}
