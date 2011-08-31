<?php
/**
 * @name: montradapaymentchecker.php
 * @author: maxkeil
 * @version: 1.0
 * @created: 17.08.11 10:48
 * @copyright Copyright (c) 2011, all2e GmbH
 */


/**
 * montradaPaymentChecker extends the default eZPaymentCallbackChecker
 * validate the fields and activate the order
 */
class montradaPaymentChecker extends eZPaymentCallbackChecker
{
    /**
     * constructor overide for checker
     *
     * @param filename $iniFile
     */
    function __construct( $iniFile )
    {
        $this->eZPaymentCallbackChecker( $iniFile );
        $this->logger = eZPaymentLogger::CreateForAdd( 'var/log/MontradaPaymentChecker.log' );
        $this->logger->writeTimedString( 'starting new montradaPaymentChecker' );
    }

    /**
     * validate the notification hash with the generated hash from the order
     *
     * @return bool true if validation successfull or false if not
     */
    function hashValidation()
    {
        $this->logger->writeTimedString( 'hashValidation', 'begin validation' );

        $data = array(
            'secret'    => $this->ini->variable( 'AccessSettings', 'Secret' ),
            'merchid'   => $this->ini->variable( 'AccessSettings', 'MerchId' ),
            'orderid'   => $this->getFieldValue( 'orderid' ),
            'amount'    => $this->getFieldValue( 'amount' ),
            'currency'  => $this->getFieldValue( 'currency' ),
            'result'    => $this->getFieldValue( 'result' )
        );
        if( $this->getFieldValue( 'trefnum' ) )
        {
            $data['trefnum'] = $this->getFieldValue( 'trefnum' );
        }
        $data['timestamp'] = $this->getFieldValue( 'timestamp' );

        $data_serial = implode( '-', $data );
        switch( strtolower( $this->ini->variable( 'APISettings', 'HashBuild' ) ) )
        {
            case 'sha1':
            case 'sha256':
            case 'md5':
            {
                $hash = hash( strtolower( $this->ini->variable( 'APISettings', 'HashBuild' ) ), $data_serial );
            }break;
            default:
            {
                $hash = hash('sha256', $data_serial );
            }break;
        }

        if( $this->checkDataField( 'psphash', $hash ) )
        {
            $this->logger->writeTimedString( 'generated Hash: '.$hash, 'submited Hash: '.$this->getFieldValue( 'psphash' ) );
            return true;
        }

        $this->logger->writeTimedString( 'different hashes in hashValidation()' );
        return false;
    }

    /**
     * activate the given order by notification settings
     *
     * @return void
     */
    function approvePayment()
    {
        parent::approvePayment();
        $orderID = $this->order->attribute( 'id' );

        $db = eZDB::instance();
        $db->begin();
        $this->order->activate();

        $basket = eZBasket::currentBasket( true, $orderID);
        $basket->remove();
        $db->commit();
        
        // Fetch the shop account handler
        $accountHandler = eZShopAccountHandler::instance();
        $email = $accountHandler->email( $this->order );

        // Fetch the confirm order handler
        $confirmOrderHandler = eZConfirmOrderHandler::instance();
        $params = array( 'email' => $email,
                         'order' => $this->order );
        $confirmOrderStatus = $confirmOrderHandler->execute( $params );

        eZHTTPTool::instance()->setSessionVariable( "UserOrderID", $orderID );
    }
}

