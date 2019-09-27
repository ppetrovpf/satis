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

namespace Composer\Satis\Extractor;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Satis\Builder\ArchiveBuilderHelper;
use Composer\Satis\Distribution\Path;
use Composer\Util\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extracts readme file from packages
 */
class ReadmeExtractor
{
    /** @var Composer A Composer instance. */
    private $composer;

    /** @var OutputInterface $output The output Interface. */
    private $output;

    /** @var string $outputDir The directory where to build. */
    private $outputDir;

    /** @var array $config The parameters from ./satis.json. */
    private $config;

    /**
     * @var ArchiveBuilderHelper
     */
    private $helper;

    /**
     * ReadmeExtractor constructor.
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

        $this->helper = new ArchiveBuilderHelper($this->output, $this->outputDir, $this->config['archive']);
    }

    /**
     * Extracts a readme file from each package and updates the readme dist url property
     *
     * @param PackageInterface[] $packages
     *
     * @return void
     */
    public function extract(array $packages): void
    {
        $this->output->writeln('<info>Extracting README.md from packages</info>');

        $versionByPackage = $this->resolveVersions($packages);

        /** @var PackageInterface $package */
        foreach ($versionByPackage as list($package, $packageVersion)) {
            $packageDistReadmeUrl = $this->extractByVersion($package, $packageVersion);

            if (empty($packageDistReadmeUrl)) {
                continue;
            }

            $this->setDistReadmeUrl($package, $packageDistReadmeUrl);
        }
    }

    public function setComposer(Composer $composer): self
    {
        $this->composer = $composer;

        return $this;
    }

    /**
     * Resolves version of archive (for each package) from which readme file will be extracted
     *
     * @param PackageInterface[] $packages
     *
     * @return array
     */
    private function resolveVersions(array $packages): array
    {
        $versionByPackage = [];

        foreach ($packages as $package) {
            if ($this->helper->isSkippable($package)) {
                continue;
            }

            $packageName    = $package->getName();
            $packageVersion = $package->getPrettyVersion();

            if (!array_key_exists($packageName, $versionByPackage)
                || 1 === version_compare($package->getVersion(), $versionByPackage[$packageName][1])) {
                $versionByPackage[$packageName] = [$package, $packageVersion];
            }
        }

        return $versionByPackage;
    }

    /**
     * Performs package archive extract action for specified version and returns a readme url for distribution
     *
     * @param PackageInterface $package
     * @param string           $version
     *
     * @return string|null
     */
    private function extractByVersion(PackageInterface $package, string $version): ?string
    {
        $distPath        = new Path($this->output, $this->outputDir, $this->config);
        $packageDistPath = $distPath->getPackageDistPath($package, $version);

        $extractPath = realpath($packageDistPath);
        $tmpPath     = sys_get_temp_dir() . '/readme_extractor' . uniqid();

        $downloadManager = $this->composer->getDownloadManager();
        $downloader      = $downloadManager->getDownloader('zip');

        $filesystem = new Filesystem();

        $packageDistUrl = $package->getDistUrl();
        $distReadmeUrl  = dirname($packageDistUrl) . DIRECTORY_SEPARATOR . 'readme.md';

        if ($this->helper->isUnmodified($package)) {
            return $distReadmeUrl;
        }

        $this->output->writeln(
            sprintf(
                "<info>Extracting README.md from package '%s' with highest version '%s'</info>",
                $package->getName(),
                $version
            )
        );

        $downloader->extract($extractPath, $tmpPath);

        try {
            $packageExtra = $package->getExtra();

            $readmeExplicitPath = $packageExtra['readme'] ?? 'readme.md';
            $readmeSourcePath   = $tmpPath . DIRECTORY_SEPARATOR . $readmeExplicitPath;

            if (file_exists($readmeSourcePath)) {
                $readmeTargetPath = dirname($packageDistPath) . DIRECTORY_SEPARATOR . 'readme.md';
                $filesystem->copy($readmeSourcePath, $readmeTargetPath);
            }
        } finally {
            $filesystem->remove($tmpPath);
        }

        return $distReadmeUrl;
    }

    /**
     * Sets readme distribution url for the specified package
     *
     * @param PackageInterface $package
     * @param string           $distReadmeUrl
     *
     * @return void
     */
    private function setDistReadmeUrl(PackageInterface $package, string $distReadmeUrl): void
    {
        $packageExtra = $package->getExtra();

        $packageExtraModified = array_replace_recursive($packageExtra, ['distReadmeUrl' => $distReadmeUrl]);
        $package->setExtra($packageExtraModified);
    }
}
