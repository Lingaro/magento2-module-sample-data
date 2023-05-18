<?php

/**
 * Copyright © 2023 Lingaro sp. z o.o. All rights reserved.
 * See LICENSE for license details.
 */

namespace Lingaro\SampleData\Model\Backup;

use DirectoryIterator;
use FilesystemIterator;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Backup\FilesystemFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Media
{
    /**
     * @var FilesystemFactory
     */
    private $filesystemFactory;
    /**
     * @var FileDriver
     */
    private $file;
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * Backup constructor.
     * @param FilesystemFactory $filesystemFactory
     * @param FileDriver $file
     */
    public function __construct(
        FilesystemFactory $filesystemFactory,
        FileDriver $file,
        DirectoryList $directoryList
    ) {
        $this->filesystemFactory = $filesystemFactory;
        $this->file = $file;
        $this->directoryList = $directoryList;
    }

    /**
     * Take backup for media
     *
     * @param string $backupDirectory
     * @param array $productsIncludePaths
     * @return string
     * @throws LocalizedException
     */
    public function run(string $backupDirectory, array $productsIncludePaths): string
    {
        $fsBackup = $this->filesystemFactory->create();
        $fsBackup->setRootDir($this->directoryList->getRoot());
        $fsBackup->setName('media');
        $fsBackup->addIgnorePaths($this->getAllIgnoredPaths($productsIncludePaths));
        $fsBackup->setBackupsDir($backupDirectory);
        $fsBackup->setBackupExtension('tgz');
        $fsBackup->setTime(time());
        $fsBackup->create();

        return $fsBackup->getBackupPath();
    }

    /**
     * @param array $includePaths
     * @return array
     */
    protected function getAllIgnoredPaths(array $includePaths)
    {
        return array_merge(
            $this->getMediaBackupIgnorePaths(),
            $this->getAdditionalMediaBackupIgnorePaths(),
            $this->generateProductIgnorePaths($includePaths)
        );
    }

    /**
     * Get paths that should be excluded during iterative searches for locations for media backup only
     *
     * @return array
     */
    private function getMediaBackupIgnorePaths()
    {
        $ignorePaths = [];
        foreach (new DirectoryIterator($this->directoryList->getRoot()) as $item) {
            if (!$item->isDot() && ($this->directoryList->getPath(DirectoryList::PUB) !== $item->getPathname())) {
                $ignorePaths[] = str_replace('\\', '/', $item->getPathname());
            }
        }
        foreach (new DirectoryIterator($this->directoryList->getPath(DirectoryList::PUB)) as $item) {
            if (!$item->isDot() && ($this->directoryList->getPath(DirectoryList::MEDIA) !== $item->getPathname())) {
                $ignorePaths[] = str_replace('\\', '/', $item->getPathname());
            }
        }
        return $ignorePaths;
    }

    /**
     * @return array
     * @throws FileSystemException
     */
    private function getAdditionalMediaBackupIgnorePaths()
    {
        $pubDir = $this->directoryList->getPath(DirectoryList::MEDIA);
        $ignoredPaths = [
            'catalog/product/cache',
            'captcha',
            'downloadable/tmp',
            'css',
            'css_secure',
            'js'
        ];

        return preg_filter('/^/', $pubDir . '/', $ignoredPaths);
    }

    /**
     * @param array $includePaths
     * @return array
     * @throws FileSystemException
     */
    private function generateProductIgnorePaths(array $includePaths)
    {
        if (empty($includePaths)) {
            return [];
        }
        $catalogProductPath = $this->directoryList->getPath(DirectoryList::MEDIA) . '/catalog/product';
        $directory = new RecursiveDirectoryIterator($catalogProductPath, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory);
        $files = [];
        /** @var SplFileInfo $info */
        foreach ($iterator as $info) {
            if (!in_array(str_replace($catalogProductPath, '', $info->getPathname()), $includePaths)) {
                $files[] = $info->getPathname();
            }
        }

        return $files;
    }
}
