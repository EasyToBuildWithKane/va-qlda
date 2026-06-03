<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureViteTestingManifest();
    }

    /**
     * CI PHPUnit job does not run npm build; @vite in app.blade.php needs a manifest.
     */
    private function ensureViteTestingManifest(): void
    {
        $buildDir = public_path('build');
        $manifestPath = $buildDir.'/manifest.json';

        if (is_file($manifestPath)) {
            return;
        }

        if (! is_dir($buildDir)) {
            mkdir($buildDir, 0755, true);
        }

        $assetsDir = $buildDir.'/assets';
        if (! is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }

        foreach (['app.js' => '', 'app.css' => '/* test */'] as $file => $contents) {
            $path = "{$assetsDir}/{$file}";
            if (! is_file($path)) {
                file_put_contents($path, $contents);
            }
        }

        file_put_contents($manifestPath, json_encode([
            'resources/css/app.css' => [
                'file' => 'assets/app.css',
                'src' => 'resources/css/app.css',
                'isEntry' => true,
            ],
            'resources/js/app.js' => [
                'file' => 'assets/app.js',
                'src' => 'resources/js/app.js',
                'isEntry' => true,
                'css' => ['assets/app.css'],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
