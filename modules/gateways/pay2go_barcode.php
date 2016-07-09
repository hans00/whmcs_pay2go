<?php
/**
 * WHMCS Pay2Go BarCode
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
function pay2go_barcode_MetaData() {
    return array(
        'DisplayName' => 'Pay2Go - 超商條碼',
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
function pay2go_barcode_config() {
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => '超商條碼',
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
        // the yesno field type displays a single checkbox option
        'testMode' => array(
            'FriendlyName' => '測試模式',
            'Type' => 'yesno',
            'Description' => '測試模式',
        ),
    );
}

function pay2go_barcode_link($params) {

    $TimeStamp = time();
    # Gateway Specific Variables
    $gatewayMerchantID = $params['MerchantID'];

    # Invoice Variables
    $invoiceid = $params['InvoicePrefix'].$TimeStamp.$params['invoiceid'];
    $amount = $params['amount']; # Format: ##.##
    $TotalAmount = round($amount); # Format: ##

    if ( $TotalAmount < 20 || $TotalAmount > 20000 ) return 'Error Amount';

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
    <input type=hidden name="BARCODE" value="1">
    <input type="submit" class="btn btn-success btn-sm" value="'.$params['langpaynow'].'">
    </form>';
    return $code;
}
