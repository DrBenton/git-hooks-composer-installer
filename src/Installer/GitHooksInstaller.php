<?php

namespace Ams\Composer\GitHooks\Installer;

use Ams\Composer\GitHooks\GitHooks;
use Ams\Composer\GitHooks\Plugin;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Silencer;

class GitHooksInstaller extends LibraryInstaller
{
    /**
     * @inheritdoc
     */
    public function supports($packageType)
    {
        return $packageType == Plugin::PACKAGE_TYPE;
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $originPath = realpath($this->getInstallPath($package));
        $targetPath = realpath($this->vendorDir . '/../.git/hooks');

        if ($this->io->isVerbose()) {
            $this->io->write(sprintf('Installing git hooks from %s into %s', $originPath, $targetPath));
        }

        // throws exception if one of both paths doesn't exists
        $this->filesystem->ensureDirectoryExists($originPath);
        $this->filesystem->ensureDirectoryExists($targetPath);

        $this->copyGitHooks($originPath, $targetPath);
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);

        $originPath = realpath($this->getInstallPath($initial));
        $targetPath = realpath($this->vendorDir . '/../.git/hooks');

        if ($this->io->isVerbose()) {
            $this->io->write(sprintf('Updating git hooks from %s into %s', $originPath, $targetPath));
        }

        // throws exception if one of both paths doesn't exists
        $this->filesystem->ensureDirectoryExists($originPath);
        $this->filesystem->ensureDirectoryExists($targetPath);

        $this->copyGitHooks($originPath, $targetPath, true);
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $originPath = realpath($this->getInstallPath($package));
        $targetPath = realpath($this->vendorDir . '/../.git/hooks');

        if ($this->io->isVerbose()) {
            $this->io->write(sprintf('Uninstalling git hooks from %s into %s', $originPath, $targetPath));
        }

        $this->removeGitHooks($originPath, $targetPath);

        parent::uninstall($repo, $package);
    }

    private function copyGitHooks($sourcePath, $targetPath, $isUpdate = false)
    {
        $i = new \FilesystemIterator($sourcePath);

        foreach ($i as $githook) {
            // ignore all files not matching a git hook name
            if (!array_search($githook->getFilename(), GitHooks::$hookFilename)) {
                continue;
            }

            $newPath = $targetPath.'/'.$githook->getFilename();

            // check if .sample version exists in that case rename it
            if (file_exists($newPath . GitHooks::SAMPLE)) {
                rename($newPath . GitHooks::SAMPLE, $newPath . GitHooks::SAMPLE . '.bk');
            }

            // check if there is already a git hook with same name do nothing
            if (file_exists($newPath) && !$isUpdate) {
                $this->io->write(sprintf('Found already existing %s git hook. Doing nothing.', $githook->getFilename()));
                continue;
            }

            // if hook already exist but anything changed update it
            if (file_exists($newPath) && $isUpdate && sha1_file($githook->getPathname()) === sha1_file($newPath)) {
                continue;
            }

            $this->io->write(sprintf('Installing git hook %s', $githook->getFilename()));
            copy($githook->getPathname(), $newPath);
            Silencer::call('chmod', $newPath, 0777 & ~umask());
        }
    }

    private function removeGitHooks($sourcePath, $targetPath)
    {
        $i = new \FilesystemIterator($sourcePath);

        foreach ($i as $githook) {
            // ignore all files not matching a git hook name
            if (!array_search($githook->getFilename(), GitHooks::$hookFilename)) {
                continue;
            }

            $newPath = $targetPath.'/'.$githook->getFilename();
            if (file_exists($newPath)) {
                $this->io->write(sprintf('Removing git hook %s', $githook->getFilename()));
                unlink($newPath);
            }
        }
    }
}
