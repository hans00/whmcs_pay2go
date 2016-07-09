<?php
/**
 * WHMCS Merchant Gateway 3D Secure Callback File
 *
 * The purpose of this file is to demonstrate how to handle the return post
 * from a 3D Secure Authentication process.
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
 * Users are expected to be redirected to this file as part of the 3D checkout
 * flow so it also demonstrates redirection to the invoice upon completion.
 *
 * For more information, please refer to the online documentation.
 *
 * @see http://docs.whmcs.com/Gateway_Module_Developer_Docs
 *
 * @copyright Copyright (c) WHMCS Limited 2015
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$result = json_decode(html_entity_decode($_POST['JSONData']), true);
$result['Result'] = json_decode($result['Result'], true);
$transactionStatus = ($result['Status']=='SUCCESS') ? 'Success' : $result['Message'];

switch ($result['Result']['PaymentType']) {
    case 'WEBATM': $gatewayModuleName = 'pay2go_webatm'; break;
    case 'VACC': $gatewayModuleName = 'pay2go_vacc'; break;
    case 'CVS': $gatewayModuleName = 'pay2go_cvs'; break;
    case 'CREDIT':
    case 'CreditRed':
    case 'UNIONPAY': $gatewayModuleName = 'pay2go_credit'; break;
    case 'BARCODE': $gatewayModuleName = 'pay2go_barcode'; break;
    default: die();
}

$params = getGatewayVariables($gatewayModuleName);
if (!$params['type']) {
    die("Module Not Activated");
}

$invoiceId = $result['Result']['MerchantOrderNo'];
$transactionId = $result['Result']['TradeNo'];
$paymentAmount = $result['Result']["Amt"];
$paymentFee = 0;

$CheckCode = array(
    'Amt' => $paymentAmount,
    'MerchantID' => $params['MerchantID'],
    'MerchantOrderNo' => $invoiceId,
    'TradeNo' => $transactionId
);
ksort($CheckCode);
$CheckCode = http_build_query($CheckCode);
$CheckCode = strtoupper(hash("sha256", 'HashIV='.$params['HashIV'].'&'.$CheckCode.'&HashKey='.$params['HashKey']));

if ( $CheckCode != $result['Result']['CheckCode'] ) $transactionStatus = 'Verification Failure';

$invoiceId = substr($invoiceId, strlen($params['InvoicePrefix'])+10);

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 */
$invoiceId = checkCbInvoiceID($invoiceId, $params['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 */
checkCbTransID($transactionId);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($params['name'], $result, $transactionStatus);

//$paymentSuccess = false;

if ($transactionStatus=='Success') {

    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );

    //$paymentSuccess = true;

}

/**
 * Redirect to invoice.
 *
 * Performs redirect back to the invoice upon completion of the 3D Secure
 * process displaying the transaction result along with the invoice.
 *
 * @param int $invoiceId        Invoice ID
 * @param bool $paymentSuccess  Payment status
 */
//callback3DSecureRedirect($invoiceId, $paymentSuccess);
