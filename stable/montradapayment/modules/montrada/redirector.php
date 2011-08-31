<?php
/**
 * @name: redirector.php
 * @author: maxkeil
 * @version: 1.0
 * @created: 17.08.11 10:18
 * @copyright Copyright (c) 2011, all2e GmbH
 */

/**
 * this module check if the request was successfull and redirect to the ini-setted urls success/abort
 */
$module = $Params['Module'];
$http = eZHTTPTool::instance();
$ini = eZINI::instance( 'montradapayment.ini' );
$redirectURL = $ini->variable( 'APISettings', 'ReturnURLOnAbort' );

if( $http->hasVariable( 'result' ) && $http->hasVariable( 'trefnum' ) && $http->variable( 'result' ) == 'success' )
{
    $redirectURL = $ini->variable( 'APISettings', 'ReturnURLOnSuccess' );
}
elseif( $http->hasVariable( 'orderid' ) )
{
    //cleanup the payment object on abort or error
    $paymentObject = eZPaymentObject::fetchByOrderID( $http->variable( 'orderid' ) );
    if( $paymentObject instanceof eZPaymentObject )
    {
        $workflowProcessID = $paymentObject->attribute( 'workflowprocess_id' );
        if( $workflowProcessID )
        {
            $theProcess = eZWorkflowProcess::fetch( $workflowProcessID );
            $theProcess->removeThis();
        }
        $paymentObject->remove();
    }
}

return $module->redirectTo( $redirectURL );