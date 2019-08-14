<?php

class Eupago_Multibanco_Model_Process extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'multibanco';
    protected $_paymentMethod = 'multibanco';
    protected $_formBlockType = 'multibanco/form';
    protected $_infoBlockType = 'multibanco/info';
    protected $_allowCurrencyCode = array('EUR');
    protected $_isGateway = false;
    protected $_canOrder = true;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;
    protected $_isInitializeNeeded = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canReviewPayment = false;
    protected $_canCreateBillingAgreement = false;
	// protected $chave_api = $this->getConfigData('chave');
	
    public function orderObserver($observer) {

        $chave_api = $this->getConfigData('chave');

        $id = $observer->getEvent()->getOrder()->getIncrementId();
        $order_value = $observer->getEvent()->getOrder()->getGrandTotal();
        $entity = $observer->getEvent()->getOrder()->getId();
        $sales_flat_order_payment = Mage::getSingleton('core/resource')->getTableName('sales_flat_order_payment');
        $sales_flat_quote_payment = Mage::getSingleton('core/resource')->getTableName('sales_flat_quote_payment');

        $resource = Mage::getSingleton('core/resource');
        $writeConnection = $resource->getConnection('core_write');
        $quote_id = Mage::getSingleton('checkout/session')->getQuoteId();

        if ($quote_id != "") {
            $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
            $query = $conn->query("SELECT  eupago_referencia FROM $sales_flat_quote_payment  WHERE quote_id =$quote_id");
            $referencia = $query->fetchColumn();

			
			if($chave_api){
				$demo = explode("-",$chave_api);
				if($demo['0']=='demo'){
					 $url = 'https://replica.eupago.pt/replica.eupagov3.wsdl';
				}
				else {
					 $url ='https://seguro.eupago.pt/eupagov3.wsdl';
				}	
			}
			

            if ($referencia == "") {
                if(class_exists('SOAPClient')){
					$arraydados = array("chave" => $chave_api, "valor" => $order_value, "id" => $id); //cada canal tem a sua chave
					$client = @new SoapClient($url, array('cache_wsdl' => WSDL_CACHE_NONE)); // chamada do servi�o SOAP
					$result = $client->gerarReferenciaMB($arraydados);
					$query = "UPDATE $sales_flat_order_payment SET  eupago_montante =    $order_value, eupago_entidade =    $result->entidade, eupago_referencia =    $result->referencia  WHERE parent_id =$entity";
					$query = "UPDATE $sales_flat_quote_payment SET  eupago_montante =    $order_value, eupago_entidade =    $result->entidade, eupago_referencia =    $result->referencia  WHERE quote_id =$quote_id";
				}else{    
					$client = new Varien_Http_Client();
					$client->setUri('https://replica.eupago.pt/bridge_clientes/bridge.php?servico=mb&chave_api='.$chave_api.'&valor='.$order_value.'&identificador='.$id)
						->setMethod('GET')
						->setConfig(array(
								'maxredirects'=>1,
								'timeout'=>30,
						));
						
					$response = $client->request()->getBody();
					$dados = explode('#', $response);
                    $entidade = $dados['0'];
                    $referencia = $dados['1'];
					$query = "UPDATE $sales_flat_order_payment SET  eupago_montante =    $order_value, eupago_entidade =    $entidade, eupago_referencia =    $referencia  WHERE parent_id =$entity";
					$query = "UPDATE $sales_flat_quote_payment SET  eupago_montante =    $order_value, eupago_entidade =    $entidade, eupago_referencia =    $referencia  WHERE quote_id =$quote_id";
				
				}
                $writeConnection->query($query);
                $writeConnection->query($query);

            } else {

                $writeConnection = $resource->getConnection('core_write');
                $query = $conn->query("SELECT  eupago_entidade FROM $sales_flat_quote_payment  WHERE quote_id =$quote_id");
                $entidade = $query->fetchColumn();
                $query = $conn->query("SELECT  eupago_montante FROM $sales_flat_quote_payment  WHERE quote_id =$quote_id");
                $montante = $query->fetchColumn();
                $query = "UPDATE $sales_flat_order_payment SET  eupago_montante =    $montante, eupago_entidade =   $entidade, eupago_referencia =   $referencia  WHERE parent_id =$entity";
                $writeConnection->query($query);
                $query = "UPDATE $sales_flat_quote_payment SET  eupago_montante =    $montante, eupago_entidade =    $entidade, eupago_referencia =   $referencia  WHERE quote_id =$quote_id";
                $writeConnection->query($query);
            }
        }
        
      
     
        return;
    }
     

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote() {
        return $this->getCheckout()->getQuote();
    }

}