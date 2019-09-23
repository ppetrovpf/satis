<?php

declare(strict_types=1);

/*
 * This file is part of composer/satis.
 *
 * (c) Composer <https://github.com/composer>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Composer\Satis\Skipper\Package;

use Composer\Package\PackageInterface;
use Composer\Satis\Distribution\Path;
use Composer\Util\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Skips a package whenever it doesn't contain any changes
 */
class UnmodifiedSkipper
{
    /**
     * The output Interface.
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * @var string
     */
    private $outputDir;

    /**
     * The parameters from ./satis.json.
     *
     * @var array
     */
    private $config;

    /**
     * UnmodifiedSkipper constructor.
     *
     * @param OutputInterface $output
     * @param string          $outputDir
     * @param array           $config
     */
    public function __construct(OutputInterface $output, string $outputDir, array $config)
    {
        $this->output    = $output;
        $this->outputDir = $outputDir;
        $this->config    = $config;
    }

    public function isSkippable(PackageInterface $package): bool
    {
        $distPath       = new Path($this->output, $this->outputDir, $this->config);
        $packageVersion = $package->getPrettyVersion();

        $packageDistPath = $distPath->getPackageDistPath($package, $packageVersion);

        $readmeTargetPath = dirname($packageDistPath) . DIRECTORY_SEPARATOR . 'readme.md';

        $changelogFilename   = sprintf('changelog-%s.md', $packageVersion);
        $changelogTargetPath = dirname($packageDistPath) . DIRECTORY_SEPARATOR . $changelogFilename;

        return file_exists($packageDistPath) && file_exists($readmeTargetPath) && file_exists($changelogTargetPath);
    }
}
