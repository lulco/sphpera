<?php

declare(strict_types=1);

namespace Sphpera\Analysis;

use Symfony\Component\Finder\Finder;

final class FileScanner
{
    /**
     * @param list<string> $dirs
     * @return list<string>
     */
    public function scan(array $dirs): array
    {
        $files = [];
        foreach (Finder::create()->files()->name('*.php')->in($dirs) as $file) {
            $files[] = (string) $file;
        }

        sort($files);

        return $files;
    }
}
