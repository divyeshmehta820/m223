<?php

namespace Neo\ImageResizer\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;


class InstallData implements InstallDataInterface
{
    /**
     * @var \Magento\Variable\Model\VariableFactory
     */
	protected $_variable;

    /**
     * InstallData constructor.
     * @param \Magento\Variable\Model\VariableFactory $_variable
     */
	public function __construct(\Magento\Variable\Model\VariableFactory $_variable)
	{

	    $this->_variable = $_variable;
	}

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
	public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
	{
		
			$data = [
				'code'         => "command_falg",
				'name' 		   => "Command Flag",
				'plain_value'      => '0',
				'html_value'         => '0'
			];
			$post = $this->_variable->create();
			$post->addData($data)->save();
		
	}
}