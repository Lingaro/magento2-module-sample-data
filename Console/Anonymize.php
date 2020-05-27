<?php

namespace Orba\SampleData\Console;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Console\Cli;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;


class Anonymize extends Command
{
    const ANONYMIZE_CONFIRM_MESSAGE = "<question>The data will be irrevocably anonymized.\n Do you want to continue? (y/n)[y]</question>\n";

    const PLATFORM = 'magento2';
    /**
     * @var Reader
     */
    private $moduleReader;
    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @param Reader $moduleReader
     * @param DeploymentConfig $deploymentConfig
     * @param string|null $name
     */
    public function __construct(
        Reader $moduleReader,
        DeploymentConfig $deploymentConfig,
        string $name = null
    ) {
        parent::__construct($name);
        $this->moduleReader = $moduleReader;
        $this->deploymentConfig = $deploymentConfig;
    }

    protected function configure()
    {
        $this->setName('orba:sampledata:db-anonymize')
            ->setDescription('Orba database anonymize');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->confirmQuestion(self::ANONYMIZE_CONFIRM_MESSAGE, $input, $output)) {
            $return = @shell_exec(implode(' ', ['cd ' . $this->getAnonymizeDir(), $this->getRunAnonymizeCommand()]));
            $output->writeln($return);
            $output->writeln('Database has been anonymized');
        } else {
            $output->writeln('Database has NOT been anonymized');
        }
        return Cli::RETURN_SUCCESS;
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
    protected function getAnonymizeDir(): string
    {
        $dir = $this->moduleReader->getModuleDir(
            Dir::MODULE_SETUP_DIR,
            'Orba_SampleData'
        );
        return $dir . '/Masquerade;';
    }

    /**
     * @return string
     */
    protected function getRunAnonymizeCommand(): string
    {
        $dbConfig = $this->getDbConfig();

        return sprintf(
            'php masquerade.phar run  --platform=%s --database=%s --username=%s --password=%s  --host=%s',
            self::PLATFORM,
            $dbConfig['dbname'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['host']
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
}
