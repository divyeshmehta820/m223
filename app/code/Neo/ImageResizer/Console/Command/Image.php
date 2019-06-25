<?php
    namespace Neo\ImageResizer\Console\Command;
    
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Magento\Framework\App\Area;
    use Magento\Framework\App\State;
    use Neo\ImageResizer\Service\ImageResize;
    use Symfony\Component\Console\Helper\ProgressBar;
    use Magento\Framework\ObjectManagerInterface;
    
    class Image extends Command
    {
        /**
         * @var ImageResize
         */
        private $resize;

        /**
         * @var State
         */
        private $appState;

        /**
         * @var ObjectManagerInterface
         */
        private $objectManager;
        
        /**
         * @inheritDoc
         */

        public function __construct(
        State $appState,
        ImageResize $resize,
        ObjectManagerInterface $objectManager
        ) {
            parent::__construct();
            $this->resize = $resize;
            $this->appState = $appState;
            $this->objectManager = $objectManager;
        }

        protected function configure()
        {
            $this->setName('catalog:image:resize-new-images-only');
            $this->setDescription('This is Custom Command to resize only new Images');            
            parent::configure();
        }
    
        /**
         * @param InputInterface $input
         * @param OutputInterface $output
         *
         * @return null|int
         */
        protected function execute(InputInterface $input, OutputInterface $output)
        {
            
                try {
                $this->appState->setAreaCode(Area::AREA_GLOBAL);
                $generator = $this->resize->resizeFromThemes();

                /** @var ProgressBar $progress */
                $progress = $this->objectManager->create(ProgressBar::class, [
                    'output' => $output,
                    'max' => $generator->current()
                ]);
                $progress->setFormat(
                    "%current%/%max%  [%bar%] %percent:3s%% %elapsed% %memory:6s% \t| <info>%message%</info>"
                );

                if ($output->getVerbosity() !== OutputInterface::VERBOSITY_NORMAL) {
                    $progress->setOverwrite(false);
                }

                for (; $generator->valid(); $generator->next()) {
                    $progress->setMessage($generator->key());
                    $progress->advance();
                }
            } catch (\Exception $e) {
                $output->writeln("<error>{$e->getMessage()}</error>");
                // we must have an exit code higher than zero to indicate something was wrong
                return \Magento\Framework\Console\Cli::RETURN_FAILURE;
            }

            $output->write(PHP_EOL);
            $output->writeln("<info>Product images resized successfully</info>");
        }
}