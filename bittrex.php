<?php
$orders_url="https://bittrex.com/api/v1.1/market/getopenorders";
$ticker_url="https://bittrex.com/api/v1.1/public/getticker";
$open_orders_url="https://bittrex.com/api/v1.1/market/getopenorders";
$cancel_url="https://bittrex.com/api/v1.1/market/cancel";
$buylimit_url="https://bittrex.com/api/v1.1/market/buylimit";
$selllimit_url="https://bittrex.com/api/v1.1/market/selllimit";
$balance_url="https://bittrex.com/api/v1.1/account/getbalance";

function bittrex_query($url,$query) {
        global $private_key;
        sleep(1);
        $ch=curl_init();
        $nonce=time();

        curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,TRUE);
        curl_setopt($ch,CURLOPT_POST,FALSE);
        $full_url="$url?$query&nonce=$nonce";
        curl_setopt($ch,CURLOPT_URL,$full_url);
        $sign=hash_hmac('sha512',$full_url,$private_key);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('apisign:'.$sign));
        $result=curl_exec($ch);
        return $result;
}

function bittrex_get_order($market) {
        global $public_key;
        global $orders_url;
        $query="apikey=$public_key&market=$market";
        $result=bittrex_query($orders_url,$query);
        $json=json_decode($result);
        return $json;
}

function bittrex_get_ticker($market) {
        global $ticker_url;
        $query="market=$market";
        $result=bittrex_query($ticker_url,$query);
        $json=json_decode($result);
        return $json;
}

function bittrex_get_open_orders($market) {
        global $public_key;
        global $open_orders_url;
        $query="apikey=$public_key&market=$market";
        $result=bittrex_query($open_orders_url,$query);
        $json=json_decode($result);
        return $json;
}

function bittrex_cancel_order($order_uuid) {
        global $public_key;
        global $cancel_url;
        $query="apikey=$public_key&uuid=$order_uuid";
        $result=bittrex_query($cancel_url,$query);
        $json=json_decode($result);
        return $json;
}

function bittrex_get_balance($currency) {
        global $public_key;
        global $balance_url;
        $query="apikey=$public_key&currency=$currency";
        $result=bittrex_query($balance_url,$query);
        $json=json_decode($result);
        return $json;
}

function bittrex_buylimit($market,$quantity,$rate) {
        global $public_key;
        global $buylimit_url;
        $query="apikey=$public_key&market=$market&quantity=$quantity&rate=$rate";
        $result=bittrex_query($buylimit_url,$query);
        $json=json_decode($result);
        return $json;
}

function bittrex_selllimit($market,$quantity,$rate) {
        global $public_key;
        global $selllimit_url;
        $query="apikey=$public_key&market=$market&quantity=$quantity&rate=$rate";
        $result=bittrex_query($selllimit_url,$query);
        $json=json_decode($result);
        return $json;
}

?>
