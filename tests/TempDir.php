<?php

declare(strict_types=1);

namespace App\Tests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TempDir
{
    public readonly string $path;

    public function __construct()
    {
        $this->path = sys_get_temp_dir() . '/blog-test-' . bin2hex(random_bytes(6));
        mkdir($this->path);
    }

    public function copy(string $sourceDir, string $targetDir): void
    {
        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        mkdir($this->path . '/' . $targetDir);
        foreach ($files as $file) {
            $target = $this->path . '/' . $targetDir . substr($file->getPathname(), strlen($sourceDir));
            $file->isDir() ? mkdir($target) : copy($file->getPathname(), $target);
        }
    }

    public function remove(): void
    {
        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->path);
    }
}
