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
use Composer\Satis\Distribution\Path;
use Composer\Satis\Extractor\Changelog\Diff;
use Composer\Util\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extracts changelogs for packages
 */
class ChangelogExtractor
{
    /** @var Composer A Composer instance. */
    private $composer;

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
     * Calculates and extracts diffs between package versions
     *
     * @param PackageInterface[] $packages
     *
     * @return void
     */
    public function extract(array $packages): void
    {
        $versionPairs = $this->findVersionPairs($packages);

        foreach ($versionPairs as list($version, $versionPrevious)) {
            $packageDistChangelogUrl = $this->extractDiff($version, $versionPrevious);

            if (empty($packageDistChangelogUrl)) {
                continue;
            }

            $this->setDistChangelogUrl($version, $packageDistChangelogUrl);
        }
    }

    public function setComposer(Composer $composer): self
    {
        $this->composer = $composer;

        return $this;
    }

    /**
     * Returns all possible version pairs [$cur, $prev] for changelog's diff calculation
     *
     * @param PackageInterface[] $packages
     *
     * @return array
     */
    private function findVersionPairs(array $packages): array
    {
        $versionPairs = [];

        $index = $this->buildIndex($packages);

        foreach ($index as $packageName => $versions) {
            $versionPrevious = array_shift($versions);
            $versionPairs[] = [$versionPrevious, null];

            foreach ($versions as $version) {
                $versionPairs[] = [$version, $versionPrevious];

                $versionPrevious = $version;
            }
        }

        return $versionPairs;
    }

    /**
     * Builds index for versions pairs extracting step
     *
     * @param PackageInterface[] $packages
     *
     * @return array
     */
    private function buildIndex(array $packages): array
    {
        $index = [];

        foreach ($packages as $package) {
            $packageName = $package->getName();

            if (!array_key_exists($packageName, $index)) {
                $index[$packageName] = [$package];

                continue;
            }

            $index[$packageName][] = $package;
        }

        foreach ($index as $packageName => &$versions) {
            usort($versions, function ($first, $second) {
                return version_compare($first->getVersion(), $second->getVersion(), '>=');
            });
        }

        return $index;
    }

    /**
     * Extracts a changelog file with diff set for given package versions and returns an url for distribution
     *
     * @param PackageInterface $version
     * @param PackageInterface|null $versionPrevious
     *
     * @return string|null
     */
    private function extractDiff(PackageInterface $version, ?PackageInterface $versionPrevious): ?string
    {
        $packageDistChangelogUrl = null;

        $versionPath = $this->extractVersion($version);

        $distPath = new Path($this->output, $this->outputDir, $this->config);
        $versionDistPath = $distPath->getPackageDistPath($version, $version->getPrettyVersion());

        $changelogFilename = sprintf('changelog-%s.md', $version->getPrettyVersion());

        $filesystem = new Filesystem();
        $changelogTargetPath = dirname($versionDistPath) . DIRECTORY_SEPARATOR . $changelogFilename;

        $versionChangelogSourcePath = $versionPath . DIRECTORY_SEPARATOR . 'changelog.md';

        if (empty($versionPrevious)) {
            $filesystem->copy($versionChangelogSourcePath, $changelogTargetPath);
        } else {
            $versionPreviousPath = $this->extractVersion($versionPrevious);
            $versionPreviousChangelogSourcePath = $versionPreviousPath . DIRECTORY_SEPARATOR . 'changelog.md';

            $diff = Diff::compareFiles($versionPreviousChangelogSourcePath, $versionChangelogSourcePath);
            $diffData = Diff::toRaw($diff);

            file_put_contents($changelogTargetPath, $diffData);

            $filesystem->remove($versionPreviousPath);
        }

        $filesystem->remove($versionPath);

        $versionDistUrl = $version->getDistUrl();
        $packageDistChangelogUrl = dirname($versionDistUrl) . DIRECTORY_SEPARATOR . $changelogFilename;

        return $packageDistChangelogUrl;
    }

    /**
     * Extracts package archive into the temp directory and return a path
     *
     * @param PackageInterface $package
     *
     * @return string
     */
    private function extractVersion(PackageInterface $package): string
    {
        $distPath = new Path($this->output, $this->outputDir, $this->config);
        $packageDistPath = $distPath->getPackageDistPath($package, $package->getPrettyVersion());

        $extractPath = realpath($packageDistPath);
        $tmpPath = sys_get_temp_dir() . '/changelog_extractor' . uniqid();

        $downloadManager = $this->composer->getDownloadManager();
        $downloader = $downloadManager->getDownloader('zip');

        $downloader->extract($extractPath, $tmpPath);

        return $tmpPath;
    }

    /**
     * Sets changelog distribution url for the specified package
     *
     * @param PackageInterface $package
     * @param string $distChangelogUrl
     *
     * @return void
     */
    private function setDistChangelogUrl(PackageInterface $package, string $distChangelogUrl): void
    {
        $packageExtra = $package->getExtra();

        $packageExtraModified = array_replace_recursive($packageExtra, ['distChangelogUrl' => $distChangelogUrl]);
        $package->setExtra($packageExtraModified);
    }
}
