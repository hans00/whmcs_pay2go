<?php
/**
 * WHMCS Pay2Go Credit
 *
 * @see http://docs.whmcs.com/Gateway_Module_Developer_Docs
 *
 * @copyright Copyright (c) Hans 2016
 * @license https://github.com/hans00/whmcs_pay2go/blob/master/LICENSE
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see http://docs.whmcs.com/Gateway_Module_Meta_Data_Parameters
 *
 * @return array
 */
function pay2go_credit_MetaData() {
    return array(
        'DisplayName' => 'Pay2Go - 信用卡',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => false,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function pay2go_credit_config() {
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => '信用卡',
        ),
        // a text field type allows for single line text input
        'MerchantID' => array(
            'FriendlyName' => '商店代號',
            'Type' => 'text',
            'Size' => '15',
            'Default' => '',
            'Description' => '智付寶商店代號。',
        ),
        // a password field type allows for masked text input
        'HashKey' => array(
            'FriendlyName' => 'HashKey',
            'Type' => 'password',
            'Size' => '32',
            'Default' => '',
            'Description' => 'HashKey',
        ),
        'HashIV' => array(
            'FriendlyName' => 'HashIV',
            'Type' => 'password',
            'Size' => '16',
            'Default' => '',
            'Description' => 'HashIV',
        ),
        'TradeLimit' => array(
            'FriendlyName' => '交易秒數',
            'Type' => 'text',
            'Size' => '3',
            'Default' => '90',
            'Description' => '最少 60 秒',
        ),
        "InvoicePrefix" => array(
            "FriendlyName" => "帳單前綴",
            "Type" => "text",
            "Default" => "",
            "Description" => "選填（只能為數字、英文，且與帳單 ID 合併總字數不能超過 20）",
            "Size" => "5",
        ),
        'CreditRed' => array(
            'FriendlyName' => '信用卡紅利',
            'Type' => 'yesno',
            'Description' => '勾選以啟用',
        ),
        'UNIONPAY' => array(
            'FriendlyName' => '銀聯',
            'Type' => 'yesno',
            'Description' => '勾選以啟用',
        ),
        // the yesno field type displays a single checkbox option
        'testMode' => array(
            'FriendlyName' => '測試模式',
            'Type' => 'yesno',
            'Description' => '測試模式',
        ),
    );
}

function pay2go_credit_addpadding($string, $blocksize = 32) {
    $len = strlen($string);
    $pad = $blocksize - ($len % $blocksize);
    $string .= str_repeat(chr($pad), $pad);
    return $string;
}


/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see http://docs.whmcs.com/Payment_Gateway_Module_Parameters
 *
 * @return array Transaction response status
 */
function pay2go_credit_refund($params) {

    $amount = $params['amount']; # Format: ##.##
    $TotalAmount = round($amount);
    $transid = $params['transid'];

    # 是否為測試模式
    $posturl = ($params['testMode']=='on')  ? 'https://cweb.pay2go.com/API/CreditCard/Close'
                                            : 'https://web.pay2go.com/API/CreditCard/Close' ;

    // post data
    $PostData = http_build_query(
                    array( 'RespondType'      => 'String',
                            'Version'         => '1.0',
                            'Amt'             => $TotalAmount,
                            'TradeNo'         => $transid,
                            'TimeStamp'       => time(),
                            'IndexType'       => '2',
                            'CloseType'       => '2'
                ));
    $PostData = trim(
                    bin2hex(
                        mcrypt_encrypt( MCRYPT_RIJNDAEL_128,
                                        $params['HashKey'],
                                        pay2go_credit_addpadding($PostData),
                                        MCRYPT_MODE_CBC,
                                        $params['HashIV'])
                    )
                );

    // post
    $post = array( 'MerchantID_' => $params['MerchantID'],
                   'PostData_'   => $PostData);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $posturl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    if ($params['testMode']=='on') curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // On dev server only!
    $result = curl_exec($ch);
    curl_close($ch);

    parse_str(trim($result),$result);

    switch($result['Status']){
        case 'SUCCESS':
        case 'TRA10045':
            return array('status'=>'success', 'rawdata'=>$result, 'transid'=>$result['TradeNo'], 'fees'=>0);
        case 'TRA10058':
        case 'TRA10675':
        case 'TRA10013':
        case 'TRA10035':
            return array('status'=>'declined', 'rawdata'=>$result);
        default:
            return array('status'=>'error', 'rawdata'=>$result);
    }
}


function pay2go_credit_link($params) {

    $TimeStamp = time();
    # Gateway Specific Variables
    $gatewayMerchantID = $params['MerchantID'];

    # Invoice Variables
    $invoiceid = $params['InvoicePrefix'].$TimeStamp.$params['invoiceid'];
    $amount = $params['amount']; # Format: ##.##
    $TotalAmount = round($amount); # Format: ##

    # Client Variables
    $email = $params['clientdetails']['email'];
    $LangType = ($params['clientdetails']['language']=='english') ? 'en' : 'zh-tw';

    # System Variables
    $systemurl = $params['systemurl'];

    # 是否為測試模式
    $posturl = ($params['testMode']=="on")  ? 'https://capi.pay2go.com/MPG/mpg_gateway'
                                            : 'https://api.pay2go.com/MPG/mpg_gateway' ;

    # 產生檢查碼
    $CheckValue = array(    'Amt'             => $TotalAmount,
                            'MerchantID'      => $params['MerchantID'],
                            'MerchantOrderNo' => $invoiceid,
                            'TimeStamp'       => $TimeStamp,
                            'Version'         => '1.2',
                        );
    ksort($CheckValue);
    $CheckValue = http_build_query($CheckValue);
    $CheckValue = strtoupper(hash("sha256", 'HashKey='.$params['HashKey'].'&'.$CheckValue.'&HashIV='.$params['HashIV']));

    # 跳轉頁面
    $code = '<form action="'.$posturl.'" method="post">
    <input type=hidden name="RespondType" value="JSON">
    <input type=hidden name="MerchantID" value="'.$gatewayMerchantID.'">
    <input type=hidden name="MerchantOrderNo" value="'.$invoiceid.'">
    <input type=hidden name="Version" value="1.2">
    <input type=hidden name="RespondType" value="JSON">
    <input type=hidden name="TimeStamp" value="'.$TimeStamp.'">
    <input type=hidden name="Amt" value="'.$TotalAmount.'">
    <input type=hidden name="ItemDesc" value="'.$params['description'].'">
    <input type=hidden name="LangType" value="'.$LangType.'">
    <input type=hidden name="ReturnURL" value="'.$systemurl.'/clientarea.php">
    <input type=hidden name="ClientBackURL" value="'.$systemurl.'/clientarea.php">
    <input type=hidden name="Email" value="'.$email.'">
    <input type=hidden name="NotifyURL" value="'.$systemurl.'/modules/gateways/callback/pay2go.php">
    <input type=hidden name="TradeLimit" value="'.intval($params['TradeLimit']).'">
    <input type=hidden name="CheckValue" value="'.$CheckValue.'">
    <input type=hidden name="LoginType" value="0">
    <input type=hidden name="CREDIT" value="1">
    <input type=hidden name="CreditRed" value="'.(($params['CreditRed']=='on')?1:0).'">
    <input type=hidden name="UNIONPAY" value="'.(($params['UNIONPAY']=='on')?1:0).'">
    <input type="submit" class="btn btn-success btn-sm" value="'.$params['langpaynow'].'">
    </form>';
    return $code;
}
