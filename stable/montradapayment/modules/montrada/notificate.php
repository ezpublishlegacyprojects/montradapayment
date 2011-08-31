<?php
/**
 * @name: notificate.php
 * @author: maxkeil
 * @version: 1.0
 * @created: 15.08.11 17:56
 * @copyright Copyright (c) 2011, all2e GmbH
 */

/**
 * notificator module for montrada payment gateway
 *
 */
ext_activate( 'montradapayment', 'classes/montradapaymentchecker.php' );

$checker = new montradaPaymentChecker( 'montradapayment.ini' );
if( $checker->createDataFromGET() )
{
    if( $checker->hashValidation() )
    {
        $orderID = $checker->getFieldValue( 'orderid' );
        if( $checker->setupOrderAndPaymentObject( $orderID ) )
        {
            $currency = $checker->getFieldValue( 'currency' );
            $checker->logger->writeTimedString( 'check currency' );
            if( $checker->checkCurrency( $currency ) )
            {
                $checker->logger->writeTimedString( 'currency approved - approve payment' );
                if( $checker->ini->variable( 'OrderSettings', 'SetConfirmedStatus' ) == 'enabled' )
                {
                    $confirmedStatus = $checker->ini->variable( 'OrderSettings', 'ConfirmStatusID' );
                    $checker->order->modifyStatus( $confirmedStatus, 14 );
                }
                $checker->approvePayment();
            }
        }
    }
}

eZExecution::cleanExit();