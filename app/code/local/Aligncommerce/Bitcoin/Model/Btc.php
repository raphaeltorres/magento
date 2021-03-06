<?php
    class Aligncommerce_Bitcoin_Model_Btc extends Mage_Payment_Model_Method_Abstract
    {
        protected $_code = 'bitcoin_btc';
        protected $_oauthUrl = 'https://api.aligncommerce.com/oauth/access_token';
        protected $_scope = 'products,invoice,buyer';


        public function apiConfig()
        {
            require_once Mage::getBaseDir('lib').'/aligncommerce/apiconfig.php';
            $apiconfig = new apiconfig();
            return $apiconfig;
        }

        public function isAvailable($quote = null) {
            $is_currency = false;
            $username   = Mage::getStoreConfig('payment/bitcoin_btc/username');
            $password   = Mage::getStoreConfig('payment/bitcoin_btc/password');
            $apiconfig = $this->apiConfig();
            $currency = $apiconfig->getCurrency($username , $password );

            $currency_code = Mage::app()->getStore()->getCurrentCurrencyCode();

            foreach($currency['currency']['data'] as $curr){

                if($curr['code'] == $currency_code)
                    $is_currency = true;
            }

            if(Mage::getStoreConfig('payment/bitcoin_btc/active') && in_array($currency_code,explode(',',Mage::getStoreConfig('payment/bitcoin_btc/allowspecific_currency'))) && $is_currency){
                return true;
            }else{
                return false;
            }
        }


        public function authorize(Varien_Object $payment, $amount)
        {
            return $this->CreateInvoiceAndRedirect($payment, $amount);

        }


        public function CreateInvoiceAndRedirect($payment, $amount)
        {
            $username   = Mage::getStoreConfig('payment/bitcoin_btc/username');
            $password   = Mage::getStoreConfig('payment/bitcoin_btc/password');
            $client_id  = Mage::getStoreConfig('payment/bitcoin_btc/client_id');
            $secret_key = Mage::getStoreConfig('payment/bitcoin_btc/secret_key');

            $apiconfig = $this->apiConfig();
            $auth = $apiconfig->getAuthorizationCode($username , $password , $client_id , $secret_key);

            if(isset($auth['error'])){
                Mage::throwException($auth['error_message']);
            }

            $access_token = $auth['access_token'];
            $product_details   = Mage::helper('bitcoin')->getOrderedProductDetails($payment);
            $buyer_info = Mage::helper('bitcoin')->getBillingInfo($payment);

            $order   = $payment->getOrder();
            $orderId = $order->getIncrementId();

            $apiconfig = $this->apiConfig();
            $currency = $apiconfig->getCurrency($username , $password );
            $currency_code = Mage::app()->getStore()->getCurrentCurrencyCode();
            foreach($currency['currency']['data'] as $curr){

                if($curr['code'] == $currency_code)
                    $currency_id = $curr['currency_id'];
            }

            $post_data = array(
                'access_token'  => $access_token,
                'checkout_type' => 'btc',
                'products'      => $product_details,
                'buyer_info'    => $buyer_info,
                'currency'      => $currency_id,
                'order_id'      => $orderId,

            );


            $invoice =  $apiconfig->createInvoice($username , $password , $access_token , $post_data);
            $payment->setIsTransactionPending(true);

            if($invoice['invoice']['error'] || !isset($invoice) || $invoice['invoice']['data']['invoice_url'] == null){
                Mage::throwException($invoice['invoice']['error_message']);
            }

            $redirect_url = $invoice['invoice']['data']['invoice_url'];
            //$invoiceId = Mage::getModel('sales/order_invoice_api')->create($orderId, array());
            Mage::getSingleton('customer/session')->setRedirectUrl($redirect_url);

            return $this;


        }

        public function getOrderPlaceRedirectUrl()
        {
            return Mage::getSingleton('customer/session')->getRedirectUrl();
        }
}