<?php
class Skuiq_SendSaleInfo_Model_Observer
{
    public function sendSale(Varien_Event_Observer $observer)
    {
        try {
            Mage::log("SKU IQ: A sale has been saved or updated", null, 'sale-updates.txt');
            $order = $observer->getEvent()->getOrder();
            $uri = "https://api.skuiq.com/magento/webhooks/sales";
            $client = new Zend_Http_Client($uri);
            $base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);

            $ordered_items = $order->getAllItems();
            foreach($ordered_items as $i):
                $ordered_items = Mage::helper('core')->jsonEncode($i);
                $item[] = $ordered_items;
            endforeach;
            $shippingAddress = $order->getShippingAddress();

            $json_order = Mage::helper('core')->jsonEncode($order);
            $json_order_items = $item;
            $json_ship_add = Mage::helper('core')->jsonEncode($shippingAddress);

            $client->setHeaders('Content-type', 'application/json');
            $client->setParameterPost('base_url', $base_url);
            $client->setParameterPost('order', $json_order);
            $client->setParameterPost('items', $json_order_items);
            $client->setParameterPost('ship_add', $json_ship_add);

            /*
            $last_orderid = $order->getId();
            $order_status = $order->getStatus();
            $shipmentCollection = $order->getShipmentsCollection();
            foreach ($shipmentCollection as $shipment) {
                foreach($shipment->getAllTracks() as $tracknum)
                {
                    $tracknums[]=$tracknum->getNumber();
                }
            };
            $shipId = serialize($tracknums);

            $client->setParameterPost('order_id', $last_orderid);
            $client->setParameterPost('status', $order_status);
            $client->setParameterPost('tracking', $shipId);
            */

            $response = $client->request('POST');
        } catch (Mage_Core_Exception $e) {
            Mage::log("SKU IQ: Magento core error in sendSale", null, 'sale-updates.txt');
        } catch (Exception $e) {
            Mage::log("SKU IQ: Error in sendSale", null, 'sale-updates.txt');
        }
    }
}
