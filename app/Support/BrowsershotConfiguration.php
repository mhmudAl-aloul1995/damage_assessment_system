<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Spatie\Browsershot\Browsershot;

class BrowsershotConfiguration
{
    public function apply(Browsershot $browsershot, int $timeout = 120): Browsershot
    {
        $browsershot
            ->setNodeModulePath(base_path('node_modules'))
            ->noSandbox()
            ->showBackground()
            ->timeout($timeout)
            ->addChromiumArguments([
                '--disable-dev-shm-usage',
                '--disable-gpu',
            ]);

        $nodeBinary = config('services.browsershot.node_binary')
            ?: $this->firstExistingPath(['C:\\Program Files\\nodejs\\node.exe']);

        if ($nodeBinary) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        $npmBinary = config('services.browsershot.npm_binary')
            ?: $this->firstExistingPath(['C:\\Program Files\\nodejs\\npm.cmd']);

        if ($npmBinary) {
            $browsershot->setNpmBinary($npmBinary);
        }

        if ($chromePath = $this->chromePath()) {
            $browsershot->setChromePath($chromePath);
        }

        return $browsershot;
    }

    public function chromePath(): ?string
    {
        $configuredPath = config('services.browsershot.chrome_path');

        if (is_string($configuredPath) && $configuredPath !== '' && File::exists($configuredPath)) {
            return $configuredPath;
        }

        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Chromium\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Chromium\\Application\\chrome.exe',
            'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
            'C:\\Program Files (x86)\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
        ];

        return $this->firstExistingPath($candidates);
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function firstExistingPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
