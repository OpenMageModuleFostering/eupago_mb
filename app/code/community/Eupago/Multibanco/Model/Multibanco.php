<?php

//http://excellencemagentoblog.com/blog/2012/05/01/magento-create-custom-payment-method-api-based/
   
class Eupago_Multibanco_Model_Multibanco extends Mage_Payment_Model_Method_Abstract{
    
    protected $_code = 'multibanco';
	   
	protected $_paymentMethod = 'eupago_multibanco';
     
    protected $_isGateway = true;
 
    protected $_canAuthorize = false;
 
    protected $_canCapture = true;

    protected $_canCapturePartial = false;
 
    protected $_canOrder = true;
	
	protected $_canRefund = false;
 
    protected $_canVoid = false;
 
    protected $_canUseInternal = true;
 
    protected $_canUseCheckout = true;
 
    protected $_canUseForMultishipping  = true;
 
    protected $_canSaveCc = false; // WARNING: you can't keep card data unless you have PCI complience licence
	
	protected $_formBlockType = 'multibanco/form_multibanco';
	
    protected $_infoBlockType = 'multibanco/info_multibanco';
	  
	public function order(Varien_Object $payment, $amount)
    {
		if(class_exists('SOAPClient')){
			$result = $this->soapApiGeraRefMB($payment,$amount);
		}else{
			$result = $this->restApiGeraRefMB($payment,$amount);
		}

        if($result == false) {
            $errorMsg = $this->_getHelper()->__('Error Processing the request');
        } else {
            if($result->estado == 0){
                $payment->setTransactionId($result->referencia);
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,array('entidade'=>$result->entidade, 'referencia'=>$result->referencia,'method'=>'MULTIBANCO','resposta'=>$result->resposta)); 
				$payment->setIsTransactionClosed(false);
				$payment->setAdditionalInformation('entidade', $result->entidade);
				$payment->setAdditionalInformation('referencia', $result->referencia);
				$payment->setAdditionalInformation('valor', $result->valor);
		   }else{
			    $payment->setTransactionId(-1);
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,array('error_cod'=>$result->estado,'error_description'=>$result->resposta));
				$payment->setIsTransactionClosed(false);
			    $errorMsg = $result->resposta;  
            }
        }
				
        if(isset($errorMsg)){
			Mage::log("pedido com erro: ".$errorMsg, null, 'eupago_multibanco.log');
            Mage::getSingleton('core/session')->addError($errorMsg);
			Mage::throwException($errorMsg);
        }

        return $this;
    }
	
	public function capture(Varien_Object $payment)
	{
		if($payment->getMethod() != 'multibanco')
			return;
		
		// vai á base de dados buscar todas as transaction desta encomenda
		$collection = Mage::getModel('sales/order_payment_transaction')
                  ->getCollection()
                  ->addAttributeToFilter('order_id', array('eq' => $payment->getOrder()->getEntityId()))
                  ->addAttributeToFilter('txn_type', array('eq' => 'order'))
                  ->addPaymentIdFilter($payment->getId());

		foreach($collection as $transaction){
			   $rawValue = $transaction->getAdditionalInformation();
			   if($rawValue['raw_details_info']['method'] == 'MULTIBANCO' && is_numeric($rawValue['raw_details_info']['referencia'])){
					$entidade = $rawValue['raw_details_info']['entidade'];
					$referencia = $rawValue['raw_details_info']['referencia'];   
			   }
		}
		
		if(!(isset($referencia) && $referencia != null) && !(isset($entidade) && $entidade != null)){
			Mage::throwException("Não foi encontrado pedido Multibanco");
		}	

		$result = $this->soapApiInformacaoReferencia($referencia, $entidade);
 
		if($result == false) {
            $errorMsg = $this->_getHelper()->__('Error Processing the request');
        } else {
            if($result->estado_referencia == 'paga' || $result->estado_referencia == 'transferida'){
				// neste sistema altera logo para pago
				$payment->setTransactionId($referencia."-capture");
				$payment->setParentTransactionId($referencia);
                $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,array('referencia'=>$referencia,'resposta'=>$result->resposta,'method'=>'MULTIBANCO', "data de pagamento"=>$result->data_pagamento,  "hora de pagamento"=>$result->hora_pagamento));
				$payment->setIsTransactionClosed(true);
		    }else{
                $errorMsg = "a referencia não se encontra paga";
				Mage::log("pedido com erro: ".$errorMsg, null, 'eupago_multibanco.log');
            }
        }
		
        if(isset($errorMsg)){
            Mage::throwException($errorMsg);
        }
		
        return $this;
	}


	private function getSoapUrl(){
		$version = 'eupagov7';
		$chave = $this->getConfigData('chave');
		$demo = explode("-",$chave);

		if($demo[0] == 'demo'){
			return 'https://replica.eupago.pt/replica.'.$version.'.wsdl';
		}
		return 'https://seguro.eupago.pt/'.$version.'.wsdl';
	}
	
	private function getRestUrl(){
		$chave = $this->getConfigData('chave');
		$demo = explode("-",$chave);

		if($demo[0] == 'demo'){
			return 'https://replica.eupago.pt/clientes/rest_api/multibanco/create';
		}
		return 'https://seguro.eupago.pt/clientes/rest_api/multibanco/create';
	}
	
	
	// faz pedido à eupago via SOAP Para gerar Referencias Multibanco
	private function soapApiGeraRefMB(Varien_Object $payment, $amount){
		
		$per_dup = $this->getConfigData('per_dup'); 
		
		$n_dias = $this->getConfigData('payment_deadline');
		
		$order = $payment->getOrder();
		
		$client = new SoapClient($this->getSoapUrl(), array('cache_wsdl' => WSDL_CACHE_NONE));// chamada do serviço SOAP

		try {
			if($per_dup != 1 || (is_numeric($n_dias) && $n_dias > 0)){
				$data_inicio = date("Y-m-d");	
				$data_fim = (is_numeric($n_dias) && $n_dias > 0) ? date('Y-m-d', strtotime('+'.$n_dias.' day', strtotime($data_inicio))) : "2099-12-31";
				$per_dup =($per_dup == true) ? 1 : 0;		
				$arraydados = array("chave" => $this->getConfigData('chave'), "valor" => $amount, "id" => $order->getIncrementId(), "data_inicio"=>$data_inicio, "data_fim"=>$data_fim, "valor_minimo"=>$amount, "valor_maximo"=>$amount, 'per_dup' => $per_dup);
				$result = $client->gerarReferenciaMBDL($arraydados);
			}else{
				$arraydados = array("chave" => $this->getConfigData('chave'), "valor" => $amount, "id" => $order->getIncrementId());
				$result = $client->gerarReferenciaMB($arraydados);
			}
		}
		catch (SoapFault $fault) {
			Mage::throwException("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring}");
			return false;
		}
				
		return $result;
	}
	 
	// faz pedido à eupago para obter o estado da referencia
	private function soapApiInformacaoReferencia($referencia, $entidade){
		
		$arraydados = array("chave" => $this->getConfigData('chave'), "referencia" => $referencia, "entidade" => $entidade);
		
		$client = new SoapClient($this->getSoapUrl(), array('cache_wsdl' => WSDL_CACHE_NONE));// chamada do serviço SOAP

		try {
			$result = $client->informacaoReferencia($arraydados);
		}
		catch (SoapFault $fault) {
			Mage::throwException("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring}");
			return false;
		}
				
		return $result;
	}
	
	// faz pedido à eupago via REST Para gerar Referencias Multibanco
	private function restApiGeraRefMB(Varien_Object $payment, $amount){
		
		$per_dup = $this->getConfigData('per_dup'); 
		
		$n_dias = $this->getConfigData('payment_deadline');
		
		$order = $payment->getOrder();
		
		$client = new Varien_Http_Client($this->getRestUrl());
		$client->setMethod(Varien_Http_Client::POST);
		$client->setParameterPost('chave', $this->getConfigData('chave'));
		$client->setParameterPost('valor', $amount);
		$client->setParameterPost('id', $order->getIncrementId());
		if($per_dup != 1 || (is_numeric($n_dias) && $n_dias > 0)){
			$data_inicio = date("Y-m-d");	
			$data_fim = (is_numeric($n_dias) && $n_dias > 0) ? date('Y-m-d', strtotime('+'.$n_dias.' day', strtotime($data_inicio))) : "2099-12-31";
			$per_dup =($per_dup == true) ? 1 : 0;
			$client->setParameterPost('data_inicio', $data_inicio);
			$client->setParameterPost('data_fim', $data_fim);
			$client->setParameterPost('per_dup', $per_dup);
		}
		
		$result = $client->request();
		if ($result->isSuccessful()){
			return json_decode($result->getBody());
		}else{
			Mage::throwException("Ocorreu um erro ao gerar a referência multibanco");
			return false;
		}
					
	}
	
		 
    public function validate(){
        parent::validate();
		
		// acrescentar as validações de valor caso existam
 
        // if(isset($errorMsg)){
            // Mage::throwException($errorMsg);
        // }
		
        return $this;
    }
		 
 }