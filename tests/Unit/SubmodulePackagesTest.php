<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The packages in packages/ are git submodules that Composer doesn't install, so
 * this app has to do what Composer and package discovery would from each package's
 * composer.json: require its dependencies, autoload its namespaces and register its
 * providers. These fail when a package gains one the app hasn't picked up.
 */
class SubmodulePackagesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function packages(): array
    {
        return ['cms' => ['cms'], 'ecommerce' => ['ecommerce']];
    }

    #[DataProvider('packages')]
    public function test_the_app_requires_the_packages_dependencies(string $package): void
    {
        $required = array_keys(self::composer()['require']);

        $missing = array_diff(array_keys(self::composer("packages/{$package}/")['require']), $required);

        $this->assertSame([], array_values($missing), "Add these to the require of composer.json, as packages/{$package}/composer.json has them.");
    }

    #[DataProvider('packages')]
    public function test_the_app_autoloads_the_packages_namespaces(string $package): void
    {
        $autoload = self::composer()['autoload']['psr-4'];

        foreach (self::composer("packages/{$package}/")['autoload']['psr-4'] as $namespace => $path) {
            $this->assertSame("packages/{$package}/{$path}", $autoload[$namespace] ?? null, "Autoload {$namespace} from packages/{$package}/{$path} in composer.json.");
        }
    }

    #[DataProvider('packages')]
    public function test_the_app_registers_the_packages_providers(string $package): void
    {
        $providers = require dirname(__DIR__, 2).'/bootstrap/providers.php';

        foreach (self::composer("packages/{$package}/")['extra']['laravel']['providers'] ?? [] as $provider) {
            $this->assertContains($provider, $providers, "Register {$provider} in bootstrap/providers.php.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function composer(string $directory = ''): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2)."/{$directory}composer.json"), true, flags: JSON_THROW_ON_ERROR);
    }
}
