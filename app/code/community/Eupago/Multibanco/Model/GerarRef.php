<?php

class Eupago_Multibanco_Model_GerarRef extends Mage_Payment_Model_Method_Abstract {

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
    protected $_canManageRecurringProfiles = true;

    public function getMensagem() {
        return $this->getConfigData('mensagem');
    }

    public function assignData($data) {
        
    }

    public function validate() {

        parent::validate();

        $min = $this->getConfigData('min');
        $max = $this->getConfigData('max');

        if($min=="")
            $min='0';
        if($max=="")
            $max='9999.99';

        $paymentInfo = $this->getInfoInstance();

        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
            $quote = $paymentInfo->getOrder();
        } else {
            $quote = $paymentInfo->getQuote();
        }
        $currency_code = $quote->getBaseCurrencyCode();


        if (!in_array($currency_code, $this->_allowCurrencyCode)) {
            Mage::throwException(Mage::helper('multibanco')->__('A moeda selecionada (' . $currency_code . ') não é compatível com este meio de pagamento'));
        }
        $order_value = number_format($quote->getBaseGrandTotal(), 2, '.', '');
        if ($order_value < 1) {
            Mage::throwException(Mage::helper('multibanco')->__('Impossível gerar referência MB para valores inferiores a 1 Euro.'));
        }
        if ($order_value >= 999999.99) {
            Mage::throwException(Mage::helper('multibanco')->__('O valor excede o limite para pagamento na rede MB'));
        }
        $order_value = number_format($quote->getBaseGrandTotal(), 2, '.', '');
        if ($order_value < $min) {
            Mage::throwException(Mage::helper('multibanco')->__('Meio de pagamento disponível para compras superiores a ' . $min . ' EUR .'));
        }

        if ($order_value > $max) {
            Mage::throwException(Mage::helper('multibanco')->__('Meio de pagamento disponível para compras inferiores a ' . $max . ' EUR .'));
        }

        return $this;
    }

    public function getQuote() {
        if (empty($this->_quote)) {
            $this->_quote = $this->getCheckout()->getQuote();
        }
        return $this->_quote;
    }

    public function getCheckout() {

        if (empty($this->_checkout)) {
            $this->_checkout = Mage::getSingleton('checkout/session');
        }


        return $this->_checkout;
    }

}