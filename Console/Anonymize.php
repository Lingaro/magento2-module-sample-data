<?php

namespace Orba\SampleData\Console;

use Elgentos\Masquerade\Helper\Config;
use Elgentos\Masquerade\Helper\Config as ConfigHelper;
use Exception;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Faker\Provider\Base;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;


class Anonymize extends Command
{
    const ANONYMIZE_CONFIRM_MESSAGE = "<question>The data will be irrevocably anonymized.\n Do you want to continue? (y/n)[y]</question>\n";

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var Reader
     */
    protected $moduleReader;
    /**
     * @var DeploymentConfig
     */
    protected $deploymentConfig;

    protected $config;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Connection
     */
    protected $db;

    protected $group = [];

    /**
     * @var Capsule
     */
    private $capsule;

    /**
     * @var array
     */
    protected $fakerInstanceCache;

    /**
     * @var DirectoryList
     */
    private $directoryList;


    /**
     * @param Reader $moduleReader
     * @param DeploymentConfig $deploymentConfig
     * @param Config $configHelper
     * @param Capsule $capsule
     * @param DirectoryList $directoryList
     * @param string|null $name
     */
    public function __construct(
        Reader $moduleReader,
        DeploymentConfig $deploymentConfig,
        ConfigHelper $configHelper,
        Capsule $capsule,
        DirectoryList $directoryList,
        string $name = null
    ) {
        parent::__construct($name);
        $this->moduleReader = $moduleReader;
        $this->deploymentConfig = $deploymentConfig;
        $this->configHelper = $configHelper;
        $this->capsule = $capsule;
        $this->directoryList = $directoryList;
    }

    protected function configure()
    {
        $this->setName('orba:sampledata:db-anonymize')
            ->setDescription('Orba database anonymize')
            ->addOption('platform', null, InputOption::VALUE_OPTIONAL)
            ->addOption('driver', null, InputOption::VALUE_OPTIONAL, 'Database driver [mysql]')
            ->addOption('database', null, InputOption::VALUE_OPTIONAL)
            ->addOption('username', null, InputOption::VALUE_OPTIONAL)
            ->addOption('password', null, InputOption::VALUE_OPTIONAL)
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Database host [localhost]')
            ->addOption('prefix', null, InputOption::VALUE_OPTIONAL, 'Database prefix [empty]')
            ->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Locale for Faker data [en_US]')
            ->addOption('group', null, InputOption::VALUE_OPTIONAL, 'Which groups to run masquerade on [all]');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->confirmQuestion(self::ANONYMIZE_CONFIRM_MESSAGE, $input, $output)) {
            $this->input = $input;
            $this->output = $output;

            $this->setup();

            foreach ($this->config as $groupName => $tables) {
                if (!empty($this->group) && !in_array($groupName, $this->group)) {
                    continue;
                }
                foreach ($tables as $tableName => $table) {
                    $table['name'] = $tableName;
                    $this->fakeData($table);
                }
            }
            $output->writeln('Database has been anonymized');
        } else {
            $output->writeln('Database has NOT been anonymized');
        }
        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param array $table
     */
    private function fakeData(array $table): void
    {
        if (!$this->db->getSchemaBuilder()->hasTable($table['name'])) {
            $this->output->writeln('Table ' . $table['name'] . ' does not exist.');
            return;
        }

        foreach ($table['columns'] as $columnName => $columnData) {
            if (!$this->db->getSchemaBuilder()->hasColumn($table['name'], $columnName)) {
                unset($table['columns'][$columnName]);
                $this->output->writeln('Column ' . $columnName . ' in table ' . $table['name'] . ' does not exist; skip it.');
            }
        }

        $this->output->writeln('');
        $this->output->writeln('Updating ' . $table['name']);

        $totalRows = $this->db->table($table['name'])->count();
        $progressBar = new ProgressBar($this->output, $totalRows);
        $progressBar->setRedrawFrequency($this->calculateRedrawFrequency($totalRows));
        $progressBar->start();

        $primaryKey = array_get($table, 'pk', 'entity_id');

        // Null columns before run to avoid integrity constrains errors
        foreach ($table['columns'] as $columnName => $columnData) {
            if (array_get($columnData, 'nullColumnBeforeRun', false)) {
                $this->db->table($table['name'])->update([$columnName => null]);
            }
        }

        $this->db->table($table['name'])->orderBy($primaryKey)->chunk(100,
            function ($rows) use ($table, $progressBar, $primaryKey) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($table['columns'] as $columnName => $columnData) {
                        $formatter = array_get($columnData, 'formatter.name');
                        $formatterData = array_get($columnData, 'formatter');
                        $providerClassName = array_get($columnData, 'provider', false);

                        if (!$formatter) {
                            $formatter = $formatterData;
                            $options = [];
                        } else {
                            $options = array_values(array_slice($formatterData, 1));
                        }

                        if (!$formatter) {
                            continue;
                        }

                        if ($formatter == 'fixed') {
                            $updates[$columnName] = array_first($options);
                            continue;
                        }

                        try {
                            $fakerInstance = $this->getFakerInstance($columnData, $providerClassName);
                            if (array_get($columnData, 'unique', false)) {
                                $updates[$columnName] = $fakerInstance->unique()->{$formatter}(...$options);
                            } elseif (array_get($columnData, 'optional', false)) {
                                $updates[$columnName] = $fakerInstance->optional()->{$formatter}(...$options);
                            } else {
                                $updates[$columnName] = $fakerInstance->{$formatter}(...$options);
                            }
                        } catch (InvalidArgumentException $e) {
                            // If InvalidArgumentException is thrown, formatter is not found, use null instead
                            $updates[$columnName] = null;
                        }
                    }
                    $this->db->table($table['name'])->where($primaryKey, $row->{$primaryKey})->update($updates);
                    $progressBar->advance();
                }
            });

        $progressBar->finish();

        $this->output->writeln('');
    }

    private function setup()
    {
        $this->config = $this->getConfig();

        $dbConfig = $this->getDbConfig();

        $this->capsule->addConnection([
            'driver' => 'mysql',
            'host' => $dbConfig['host'],
            'database' => $dbConfig['dbname'],
            'username' => $dbConfig['username'],
            'password' => $dbConfig['password'],
            'prefix' => '',
            'charset' => 'utf8',
        ]);

        $this->db = $this->capsule->getConnection();
        $this->db->statement('SET FOREIGN_KEY_CHECKS=0');

        $this->locale = 'en_US';

        $this->group = array_filter(array_map('trim', explode(',', $this->input->getOption('group'))));
    }

    /**
     * @return array
     * @throws FileSystemException
     */
    protected function getConfig()
    {
        $config = [];
        $dirs = [
            $this->getConfigDir(),
            $this->directoryList->getPath(Dir::MODULE_ETC_DIR)
        ];
        foreach ($dirs as $dir) {
            $content = $this->configHelper->readYamlDir($dir, 'anonymize');
            $config = array_merge($config, $content);
        }
        return $config;
    }

    /**
     * @return mixed
     */
    protected function getDbConfig()
    {
        $dbConfig = $this->deploymentConfig->getConfigData('db');
        return $dbConfig['connection']['default'];
    }

    /**
     * @return string
     */
    protected function getConfigDir(): string
    {
        return $this->moduleReader->getModuleDir(
            Dir::MODULE_ETC_DIR,
            'Orba_SampleData'
        );
    }


    /**
     * @param string $message
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    private function confirmQuestion(string $message, InputInterface $input, OutputInterface $output)
    {
        $confirmationQuestion = new ConfirmationQuestion($message, true);
        return (bool)$this->getHelper('question')->ask($input, $output, $confirmationQuestion);
    }

    /**
     * @param array $columnData
     * @param bool $providerClassName
     * @return Generator
     * @throws Exception
     * @internal param bool $provider
     */
    private function getFakerInstance(array $columnData, $providerClassName = false): Generator
    {
        $key = md5(serialize($columnData) . $providerClassName);
        if (isset($this->fakerInstanceCache[$key])) {
            return $this->fakerInstanceCache[$key];
        }

        $fakerInstance = FakerFactory::create($this->locale);

        $provider = false;
        if ($providerClassName) {
            $provider = new $providerClassName($fakerInstance);
        }

        if (is_object($provider)) {
            if (!$provider instanceof Base) {
                throw new Exception('Class ' . get_class($provider) . ' is not an instance of \Faker\Provider\Base');
            }
            $fakerInstance->addProvider($provider);
        }

        $this->fakerInstanceCache[$key] = $fakerInstance;

        return $fakerInstance;
    }

    /**
     * @param int $totalRows
     * @return int
     */
    private function calculateRedrawFrequency(int $totalRows): int
    {
        $percentage = 10;

        if ($totalRows < 100) {
            $percentage = 10;
        } elseif ($totalRows < 1000) {
            $percentage = 1;
        } elseif ($totalRows < 10000) {
            $percentage = 0.1;
        } elseif ($totalRows < 100000) {
            $percentage = 0.01;
        } elseif ($totalRows < 1000000) {
            $percentage = 0.001;
        }

        return (int)ceil($totalRows * $percentage);
    }
}
