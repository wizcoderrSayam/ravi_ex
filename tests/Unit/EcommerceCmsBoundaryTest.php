<?php

namespace Tests\Unit;

use Peer\Ecommerce\EcommerceServiceProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * The ecommerce package reaches the CMS only through its contracts: their CMS
 * implementations in src/Integrations/Cms are the one place that may name it.
 */
class EcommerceCmsBoundaryTest extends TestCase
{
    private const CMS_REFERENCE = '/Peer\\\\CMS|CMS::|cms::|@cms|config\([\'"]cms\.|window\.CMS/';

    public function test_only_the_cms_integration_references_the_cms(): void
    {
        $root = dirname((string) (new ReflectionClass(EcommerceServiceProvider::class))->getFileName(), 2);
        $references = [];

        foreach (['src', 'resources', 'database', 'routes', 'config'] as $directory) {
            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$directory}", RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                $path = substr($file->getPathname(), strlen($root) + 1);

                if (! str_starts_with($path, 'src/Integrations/Cms/') && preg_match(self::CMS_REFERENCE, (string) file_get_contents($file->getPathname()))) {
                    $references[] = $path;
                }
            }
        }

        $this->assertSame([], $references, 'These files reference the CMS outside src/Integrations/Cms.');
    }
}
