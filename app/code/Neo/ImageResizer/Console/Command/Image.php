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
use Neo\ImageResizer\Helper\Data as NeoHelper;
use Magento\Framework\Exception\NotFoundException;


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
     * @var NeoHelper
     */
    private $neoHepler;


    /**
     * Image constructor.
     * @param State $appState
     * @param ImageResize $resize
     * @param ObjectManagerInterface $objectManager
     * @param NeoHelper $neoHepler
     */
    public function __construct(
        State $appState,
        ImageResize $resize,
        ObjectManagerInterface $objectManager,
        NeoHelper $neoHepler
    ) {
        parent::__construct();
        $this->resize = $resize;
        $this->appState = $appState;
        $this->objectManager = $objectManager;
        $this->neoHepler = $neoHepler;
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

            if($this->neoHepler->getCommandFlag() == 0){
                $this->neoHepler->setCommandFlag(1);
                $this->appState->setAreaCode(Area::AREA_GLOBAL);
                $generator = $this->resize->resizeFromThemes();
                $pendingCount = $this->resize->getPendingMediaCount();


                /** @var ProgressBar $progress */
                $progress = $this->objectManager->create(ProgressBar::class, [
                    'output' => $output,
                    'max' => $pendingCount
                ]);
                $progress->setFormat(
                    "%current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s% \t| <info>%message%</info>"
                );

                if ($output->getVerbosity() !== OutputInterface::VERBOSITY_NORMAL) {
                    $progress->setOverwrite(false);
                }

                for (; $generator->valid(); $generator->next()) {
                    $progress->setMessage($generator->key());
                    $progress->advance();
                }
               $this->neoHepler->setCommandFlag(0);
            }
            else{
                throw new NotFoundException(__('Command Already Running in Some Terminal Please Wait...'));
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