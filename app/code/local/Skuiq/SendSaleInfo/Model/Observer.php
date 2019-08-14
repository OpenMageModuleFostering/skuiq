<?php
class Skuiq_SendSaleInfo_Model_Observer
{
    /**
     * Magento passes a Varien_Event_Observer object as
     * the first parameter of dispatched events.
     */
    public function sendSale(Varien_Event_Observer $observer)
    {
        Mage::log("THIS IS A SALE been updated", null, 'product-updates.txt');
        $uri = "https://api.skuiq.com/magento/webhooks/sales";
        $client = new Zend_Http_Client($uri);
        $base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);

        $order = $observer->getEvent()->getOrder();
        $incrementId = $order->getIncrementId();
        $ordered_items = $order->getAllItems();
        $shippingAddress = $order->getShippingAddress();
        //$items = Mage::getModel('sales/order')->loadByIncrementId($incrementId);

        foreach($ordered_items as $i):
            //$item_sku = $i->getProductId();
            //$items = $i;
            //Mage::log("{$item_sku}. INFO", null, 'orders.txt');
            $ordered_items = Mage::helper('core')->jsonEncode($i);
            $item[] = $ordered_items;
        endforeach;

        $json_order = Mage::helper('core')->jsonEncode($order);
        $json_order_items = $item; //Mage::helper('core')->jsonEncode($item);
        $json_ship_add = Mage::helper('core')->jsonEncode($shippingAddress);

        $client->setHeaders('Content-type', 'application/json');
        $client->setParameterPost('base_url', $base_url);
        $client->setParameterPost('order', $json_order);
        $client->setParameterPost('items', $json_order_items);
        $client->setParameterPost('ship_add', $json_ship_add);
        $response = $client->request('POST');
    }

    public function updateSale(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $uri = "https://api.skuiq.com/magento/webhooks/sales/update";
        $client = new Zend_Http_Client($uri);
        $base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);

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

        $client->setHeaders('Content-type', 'application/json');
        $client->setParameterPost('order_id', $last_orderid);
        $client->setParameterPost('status', $order_status);
        $client->setParameterPost('tracking', $shipId);

        $response = $client->request('POST');

        Mage::log(" UPDATE TO ORDER ID {$last_orderid} - STATUS IS NOW : {$order_status} - Tracking Number : {$shipId} ", null, 'product-updates.txt');
    }
}
