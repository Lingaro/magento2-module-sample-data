<?php

namespace Orba\SampleData\Console;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\MaintenanceMode;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Orba\SampleData\Helper\Products;
use Orba\SampleData\Model\Backup\Media as MediaBackup;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Media
 * @package Orba\SampleData\Console
 */
class Media extends Command
{
    /** @var string */
    private const NAME = 'orba:sampledata:media';

    /** @var string */
    private const MAX_SIZE = 'max-size';

    /** @var string */
    private const SKUS = 'skus';

    /** @var string */
    private const MAINTENANCE = 'maintenance';

    /** @var string Default backup directory */
    private const DEFAULT_BACKUP_DIRECTORY = 'backups';

    /** @var string */
    private const ALL_PRODUCTS_ATTACHED = 'All products images attached to backup';

    /** @var string */
    private const PRODUCTS_ATTACHED = 'Product images attached to backup';

    /** @var string */
    private const SELECTIVE_PRODUCTS_ATTACHED = 'Selective product images (%1) attached to backup';

    /** @var string */
    private const SIZE_LIMIT_PRODUCTS_ATTACHED = ' with size limit (%1MB)';

    /**
     * @var MaintenanceMode
     */
    private $maintenanceMode;
    /**
     * @var DirectoryList
     */
    private $directoryList;
    /**
     * @var string
     */
    private $backupsDir;
    /**
     * @var MediaBackup
     */
    private $backup;

    /** @var Products */
    private $productsHelper;

    /** @var State */
    private $appState;

    /**
     * Media constructor.
     * @param MaintenanceMode $maintenanceMode
     * @param DirectoryList $directoryList
     * @param MediaBackup $backup
     * @param Products $products
     * @throws FileSystemException
     */
    public function __construct(
        MaintenanceMode $maintenanceMode,
        DirectoryList $directoryList,
        MediaBackup $backup,
        Products $products,
        State $appState
    ) {
        $this->maintenanceMode = $maintenanceMode;
        $this->backup = $backup;
        $this->productsHelper = $products;
        $this->directoryList = $directoryList;
        $this->appState = $appState;

        $this->backupsDir = $this->directoryList->getPath(DirectoryList::VAR_DIR)
            . '/' . self::DEFAULT_BACKUP_DIRECTORY;

        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $options = [
            new InputOption(
                self::MAX_SIZE,
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum size of dumped images in MB'
            ),
            new InputOption(
                self::SKUS,
                null,
                InputOption::VALUE_REQUIRED,
                'list of skus separated by a comma'
            ),
            new InputOption(
                self::MAINTENANCE,
                null,
                InputOption::VALUE_NONE,
                'Enable maintenance mode'
            )
        ];

        $this->setName(self::NAME)
            ->setDescription('Orba media backup')
            ->setDefinition($options);

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->appState->setAreaCode(Area::AREA_GLOBAL);
        $output->writeln('Backup is starting...');

        // enable maintenance mode
        if ($input->getOption(self::MAINTENANCE)) {
            $this->maintenanceMode->set(true);
            $output->writeln('Enabled maintenance mode.');
        }

        $maxSize = $input->getOption(self::MAX_SIZE);
        $skus = $input->getOption(self::SKUS) ? explode(',', $input->getOption(self::SKUS)) : [];

        // if we have no filter and we have no max-size
        // we dont need any getImagePaths values, we are dumping all images
        if (!empty($maxSize) || !empty($skus)) {
            try {
                $this->productsHelper->generateAllImageFilesForStoreProducts(
                    $maxSize,
                    $skus ? ['sku' => $skus] : []
                );

                if ($skus) {
                    $productsMessage = __(self::SELECTIVE_PRODUCTS_ATTACHED, $input->getOption(self::SKUS));
                } else {
                    $productsMessage = $maxSize ? self::PRODUCTS_ATTACHED : self::ALL_PRODUCTS_ATTACHED;
                }
                $productsMessage .= $maxSize ? __(self::SIZE_LIMIT_PRODUCTS_ATTACHED, $maxSize) : '';
                $output->writeln($productsMessage);
            } catch (Exception $e) {
                $output->writeln($e->getMessage());
                return Cli::RETURN_FAILURE;
            }
        }

        try {
            $backupPath = $this->backup->run($this->backupsDir, $this->productsHelper->getImagePaths());
            $output->writeln('Backup path: ' . $backupPath);
        } catch (LocalizedException $e) {
            $output->writeln($e->getMessage());
            return Cli::RETURN_FAILURE;
        }

        if ($input->getOption(self::MAINTENANCE)) {
            // disable maintenance mode
            $this->maintenanceMode->set(true);
            $output->writeln('Disabled maintenance mode.');
        }
        $output->writeln('Backup completed successfully.');

        return Cli::RETURN_SUCCESS;
    }
}
