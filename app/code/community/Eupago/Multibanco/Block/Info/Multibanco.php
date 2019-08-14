<?php
class Eupago_Multibanco_Block_Info_Multibanco extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
		if(Mage::getStoreConfig('payment/multibanco/frontend_template') == 'multibanco')
			$this->setTemplate('eupago/multibanco/info/multibanco.phtml');
		else
			$this->setTemplate('eupago/multibanco/info/default.phtml');
    }
    
    public function getInfo()
    {
        $info = $this->getData('info');
        if (!($info instanceof Mage_Payment_Model_Info)) {
            Mage::throwException($this->__('Can not retrieve payment info model object.'));
        }
        return $info;
    }
	
	public function getMultibancoData(){
		$info = $this->getData('info');
		
		$multibanco_data = (Object)$info['additional_information'];
		if(!isset($multibanco_data->referencia))
			$multibanco_data =  (Object)array("entidade" => $info['eupago_entidade'], "referencia" => str_pad($info['eupago_referencia'], 9, "0", STR_PAD_LEFT), "valor"=> $info['eupago_montante']);
        if($multibanco_data->referencia > 0)			
			return $multibanco_data;
		else
			return null;
	}
    
    public function getMethod()
    {
        return $this->getInfo()->getMethodInstance();
    }
	
	 public function getMethodCode()
    {
        return $this->getInfo()->getMethodInstance()->getCode();
    }
}