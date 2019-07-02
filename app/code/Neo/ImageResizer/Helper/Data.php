<?php

namespace Neo\ImageResizer\Helper;
use \Magento\Framework\App\Helper\AbstractHelper;


class Data extends AbstractHelper
{
    /**
     * @var \Magento\Framework\App\Http\Context
     */
    private $httpContext;

    /**
     * @var \Magento\Variable\Model\Variable
     */
    private $variable;


    const FLAG = 'command_falg';

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param \Magento\Variable\Model\Variable $variable
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Variable\Model\Variable $variable
    ) 
    {
        parent::__construct($context);
        $this->httpContext = $httpContext;
        $this->variable = $variable;
    }

    /**
     * @return mixed
     */
   public function getCommandFlag(){
         return $this->variable->loadByCode(self::FLAG)->getPlainValue();    
   }

    /**
     * @param $value
     */
   public function setCommandFlag($value){	   	
		$this->variable->loadByCode(self::FLAG)->setPlainValue($value)->save();
	}
}