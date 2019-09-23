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

namespace Composer\Satis\Distribution;

use Composer\Package\PackageInterface;
use Composer\Satis\Builder\ArchiveBuilderHelper;
use Symfony\Component\Console\Output\OutputInterface;

class Path
{
    /** @var OutputInterface $output The output Interface. */
    private $output;
    /** @var string $outputDir The directory where to build. */
    private $outputDir;
    /** @var array $config The parameters from ./satis.json. */
    protected $config;

    public function __construct(OutputInterface $output, string $outputDir, array $config)
    {
        $this->output = $output;
        $this->outputDir = $outputDir;
        $this->config = $config;
    }

    /**
     * Returns local dist path for the specified package
     *
     * @param PackageInterface $package
     * @param string $version
     *
     * @return string
     */
    public function getPackageDistPath(PackageInterface $package, string $version): string
    {
        $archiveConfig = $this->config['archive'] ?? $this->config;

        $helper = new ArchiveBuilderHelper($this->output, $this->outputDir, $archiveConfig);
        $basedir = $helper->getDirectory($this->outputDir);

        $packageName = $package->getName();
        $packageDistType = $package->getDistType();

        $intermediatePath = preg_replace('#/#i', '-', $packageName);

        $packagePath = $packageName . DIRECTORY_SEPARATOR . $intermediatePath . '-' . $version;
        $packageDistPath = $basedir . DIRECTORY_SEPARATOR . $packagePath . '.' . $packageDistType;

        return $packageDistPath;
    }
}
