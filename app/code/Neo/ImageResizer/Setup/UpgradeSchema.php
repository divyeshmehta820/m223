<?php
namespace Neo\ImageResizer\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade( SchemaSetupInterface $setup, ModuleContextInterface $context ) {
		      
		    $setup->startSetup();
			if (version_compare($context->getVersion(), '1.0.0', '<')) {
					$setup->getConnection()->addColumn(
	                $setup->getTable('catalog_product_entity_media_gallery'),
	                'resized_at',
	                [
	                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
	                    'nullable' => true,
	                    'comment' => 'it saves current time'                    
	                ]
	            );
			}
        
        $setup->endSetup();
	}
}
