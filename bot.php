<?php
require_once("settings.php");
require_once("db.php");
require_once("bittrex.php");

db_connect();

$satoshi_per_btc=100000000;
$markets_data=db_query_to_array("SELECT `uid`,`market`,`currency`,`bid`,`ask`,`lower_limit`,`upper_limit`,`step`,`step_count`,`timestamp` FROM `markets` WHERE `is_enabled`=1");

foreach($markets_data as $row) {
        $uid=$row['uid'];
        $market=$row['market'];
        $currency=$row['currency'];
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
                                $order_found=TRUE;
                                break;
                        }
                }
                if(!$order_found) {
                        db_query("UPDATE `orders` SET `is_completed`=1 WHERE `order_guid`='$order_guid'");
                        $orders_changed=TRUE;
                }
        }

        // If orders changed, replace orders with new ones
        if($orders_changed || count($order_not_completed_base)==0) {
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
                echo "Last bid: $last_bid\n";
                echo "Last ask: $last_ask\n";
                if($last_bid < $lower_limit || $last_ask > $upper_limit) {
                        echo "Market $market moved outside trading range, disabling\n";
                        db_query("UPDATE `markets` SET `is_enabled`=0 WHERE `uid`='$uid'");
                        continue;
                }

                // Create new orders
                echo "Getting BTC balance...\n";
                $btc_balance_info=bittrex_get_balance("BTC");
                $btc_balance=$btc_balance_info->result->Balance;
                echo "BTC balance: $btc_balance\n";

                echo "Getting $currency balance...\n";
                $currency_balance_info=bittrex_get_balance($currency);
                $currency_balance=$currency_balance_info->result->Balance;
                echo "$currency balance: $currency_balance\n";

                echo "Creating new orders...\n";
                $last_ask_satoshi=$last_ask*$satoshi_per_btc;
                $last_bid_satoshi=$last_bid*$satoshi_per_btc;
                $bid_amount=sprintf("%0.8F",$btc_balance*$bid);
                $ask_amount=sprintf("%0.8F",$currency_balance*$ask);

                echo "Current ask: $last_ask_satoshi\n";
                for($i=0;$i!=$step_count;$i++) {
                        $next_ask_satoshi=floor($last_ask_satoshi*(1+($i+1)*$step));
                        $next_ask_satoshi=max($next_ask_satoshi,$last_ask_satoshi+$i+1);
                        $next_ask=$next_ask_satoshi/$satoshi_per_btc;
                        $result_in_btc=$ask_amount*$next_ask;
                        echo "Ask $i step $next_ask amount $ask_amount ($result_in_btc BTC)\n";

                        $result=bittrex_selllimit($market,$ask_amount,$next_ask);
                        //var_dump("bittrex_selllimit($market,$ask_amount,$next_ask);",$result);
                        $uuid=$result->result->uuid;
                        if($uuid) {
                                db_query("INSERT INTO `orders` (`market`,`order_guid`,`comment`) VALUES ('$market','$uuid','Selllimit $ask_amount $currency with rate $next_ask - $result_in_btc BTC')");
                        } else {
                                var_dump("bittrex_selllimit($market,$ask_amount,$next_ask);",$result);
                        }
                }

                echo "Current bid: $last_bid_satoshi\n";
                for($i=0;$i!=$step_count;$i++) {
                        $next_bid_satoshi=floor($last_bid_satoshi*(1-($i+1)*$step));
                        $next_bid_satoshi=min($next_bid_satoshi,$last_bid_satoshi-($i+1));
                        $next_bid=$next_bid_satoshi/$satoshi_per_btc;
                        $result_in_currency=sprintf("%0.8F",$bid_amount/$next_bid);
                        echo "Bid $i step $next_bid amount $bid_amount ($result_in_currency $currency)\n";

                        $result=bittrex_buylimit($market,$result_in_currency,$next_bid);
//                      var_dump("bittrex_buylimit($market,$result_in_currency,$next_bid);",$result);
                        $uuid=$result->result->uuid;
                        if($uuid) {
                                db_query("INSERT INTO `orders` (`market`,`order_guid`,`comment`) VALUES ('$market','$uuid','Buylimit $bid_amount BTC with rate $next_bid - $result_in_currency $currency')");
                        } else {
                                var_dump("bittrex_buylimit($market,$result_in_currency,$next_bid);",$result);
                        }
                }

        } else {
                echo "Orders is not changed\n";
        }
}

?>
