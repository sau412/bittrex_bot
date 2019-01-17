<?php
require_once("settings.php");
require_once("db.php");

$public_key='';
$private_key='';

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

function bittrex_create_order($market,$type,$order) {
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

db_connect();

$satoshi_per_btc=100000000;
$markets_data=db_query_to_array("SELECT `uid`,`market`,`currency`,`bid`,`ask`,`lower_limit`,`upper_limit`,`step`,`step_count`,`timestamp` FROM `markets` WHERE `is_enabled`=1");

$currency="BTC";
var_dump(bittrex_get_balance($currency));

foreach($markets_data as $row) {
        $uid=$row['uid'];
        $market=$row['market'];
        $bid=$row['bid'];
        $ask=$row['ask'];
        $lower_limit=$row['lower_limit'];
        $upper_limit=$row['upper_limit'];
        $step=$row['step'];
        $step_count=$row['step_count'];

        // Get open orders (bittrex)
        echo "Get open orders for market $market...\n";
        $open_orders=bittrex_get_open_orders($market);

        // Get open orders (base)
        $order_not_completed_base=db_query_to_array("SELECT `uid`,`order_guid`,`comment` FROM `orders` WHERE `is_completed`=0 AND `market`='$market'");
        $orders_changed=FALSE;
        foreach($order_not_completed_base as $order_row) {
                $order_guid=$order_row['order_guid'];
                $order_found=FALSE;
                foreach($open_orders->result as $open_row) {
                        $guid=$open_row->OrderUuid;
                        if($guid==$order_guid) {
                                $order_found=FALSE;
                                break;
                        }
                }
                if(!$order_found) {
                        $orders_changed=TRUE;
                }
        }

        // If orders changed, replace orders with new ones
        if($orders_changed) {
                echo "Orders changed, cancelling old...\n";
                // Close all orders
                foreach($open_orders->result as $open_row) {
                        $uuid=$open_row->OrderUuid;
                        echo "Closing order: $uuid\n";
                        bittrex_cancel_order($uuid);
                        db_query("DELETE FROM `orders` WHERE `order_guid`='$uuid'");
                }

                // Check if limit exceeded
                echo "Get actual tickers...\n";
                $ticker_data=bittrex_get_ticker($market);
                $last_bid=$ticker_data->result->Bid;
                $last_ask=$ticker_data->result->Ask;
                if($last_bid < $lower_limit || $last_ask > $upper_limit) {
                        db_query("UPDATE `markets` SET `is_enabled`=0 WHERE `uid`='$uid'");
                        continue;
                }

                // Create new orders
                echo "Creating new orders...\n";
                $last_ask_satoshi=$last_ask*$satoshi_per_btc;
                $last_bid_satoshi=$last_bid*$satoshi_per_btc;
                echo "Current ask: $last_ask_satoshi\n";
                for($i=0;$i!=$step_count;$i++) {
                        $next_ask=floor($last_ask_satoshi*(1+($i+1)*$step));
                        $next_ask=max($next_ask,$last_ask_satoshi+$i+1);
                        echo "$i step $next_ask\n";
                }

        } else {
                echo "Orders is not changed\n";
        }
}

?>
