<?php
/**
 * @name: montradapaymentgateway.php
 * @author: maxkeil
 * @version: 1.0
 * @created: 15.08.2011 15:13
 * @copyright Copyright (c) 2011, all2e GmbH
 */

/**
 * implements the payment over montrada redirect gateway
 */
class montradaPaymentGateway extends xrowEPaymentGateway
{
    const EZ_PAYMENT_GATEWAY_TYPE_MONTRADA = "montradaPayment";

    /**
     * constructor for montrada payment gateway. Initialize the logger
     */
    function __construct()
    {
        $this->logger = eZPaymentLogger::CreateForAdd( "var/log/MontradaPaymentGateway.log" );
        $this->logger->writeTimedString( 'montradaPaymentGateway::montradaPaymentGateway()' );
    }

    /**
     * add product names and prices to the data, which would be send to montrada
     *
     * @return void
     */
    protected function addDescriptionToProcess()
    {
        $productItems = $this->order->attribute( 'product_items' );
        //create header description for columns
        $this->data['h.1'] = iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', ezpI18n::tr( 'design/standard/shop', 'Count' ) );
        $this->data['h.2'] = ezpI18n::tr( 'design/standard/shop', 'Product' );
        $this->data['h.3'] = ezpI18n::tr( 'design/standard/shop', 'Price inc. VAT' );
        $this->data['h.4'] = ezpI18n::tr( 'design/standard/shop', 'Total price inc. VAT' );
        foreach( $productItems as $key => $item )
        {
            $this->data['w.'.$key.'.1'] = substr( $item['item_count'], 0, 50 );
            $this->data['w.'.$key.'.2'] = iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', substr( $item['object_name'], 0, 50 ) );
            $this->data['w.'.$key.'.3'] = substr( $item['price_inc_vat'], 0, 50 );
            $this->data['w.'.$key.'.4'] = substr( $item['total_price_inc_vat'], 0, 50 );
        }
        unset( $productItems );
    }

    /**
     * creates a new payment object for further transactions
     *
     * @param int $processID
     * @param int $orderID
     * @return eZPaymentObject
     */
    function createPaymentObject( $processID, $orderID )
    {
        $this->logger->writeTimedString("createPaymentObject");
        return eZPaymentObject::createNew( $processID, $orderID, 'montradaPayment' );
    }

    /**
     * creates an url for redirection and payment
     *
     * @param eZWorkflowProcess $process
     * @return string url for redirection
     */
    function createRedirectionUrl()
    {
        $ini = eZINI::instance( 'montradapayment.ini' );

        $this->data = array(
            'secret'    => $ini->variable( 'AccessSettings', 'Secret' ),
            'merchid'   => $ini->variable( 'AccessSettings', 'MerchId' ),
            'orderid'   => $this->order->attribute( 'id' ),
            'payments'  => $ini->variable( 'APISettings', 'AffortedPayments' ),
            'amount'    => (int)($this->order->attribute('total_inc_vat') * 100),
            'currency'  => $this->order->currencyCode(),
            'command'   => $ini->variable( 'APISettings', 'Paycommand' ),
            'timestamp' => gmstrftime('%Y%m%d%H%M%S')
        );

        $data_serial = implode( '-', $this->data );
        unset( $this->data['secret'] );

        switch( strtolower( $ini->variable( 'APISettings', 'HashBuild' ) ) )
        {
            case 'sha1':
            case 'sha256':
            case 'md5':
            {
                $hash = hash( strtolower( $ini->variable( 'APISettings', 'HashBuild' ) ), $data_serial );
            }break;
            default:
            {
                $hash = hash('sha256', $data_serial );
            }break;
        }
        unset( $data_serial );

        $this->data['psphash'] = $hash;
        $returnurl = '/montrada/redirector';
        eZURI::transformURI( $returnurl );
        $this->data['url'] = $returnurl;
        /**
         * Add description to the transmitted data
         */
        if( $ini->variable( 'OrderSettings', 'AddDescriptionToProcess' ) == 'enabled' )
        {
            $this->addDescriptionToProcess();
        }

        $server_uri = $ini->variable( 'APISettings', 'AccessURL' ) . '?';
        $server_uri .= http_build_query( $this->data, null, '&' );
        
        return $server_uri;
    }

    /**
     * general method for executing the workflow
     *
     * @param eZWorkflowProcess $process
     * @param eZWorkflowEvent $event
     * @return int status
     */
    function execute( $process, $event )
    {
        $processParams = $process->attribute( 'parameter_list' );
        $orderID = $processParams['order_id'];
        $this->order = eZOrder::fetch( $orderID );

        $xmlstring = $this->order->attribute( 'data_text_1' );
        if ( $xmlstring != null )
        {
            $doc = new DOMDocument( );
            $doc->loadXML( $xmlstring );
            $root = $doc->documentElement;
            $invoice = $doc->createElement( xrowECommerce::ACCOUNT_KEY_PAYMENTMETHOD, self::EZ_PAYMENT_GATEWAY_TYPE_MONTRADA );
            $root->appendChild( $invoice );
            $this->order->setAttribute( 'data_text_1', $doc->saveXML() );
            $this->order->store();
        }
        $paymentobject = $this->createPaymentObject( $process->attribute('id'), $orderID );
        $paymentobject->store();
        $process->RedirectUrl = $this->createRedirectionUrl();
        return eZWorkflowType::STATUS_REDIRECT_REPEAT;
    }

    private $order;
    private $data;
}

xrowEPayment::registerGateway( montradaPaymentGateway::EZ_PAYMENT_GATEWAY_TYPE_MONTRADA, "montradaPaymentGateway", 'montrada' );
