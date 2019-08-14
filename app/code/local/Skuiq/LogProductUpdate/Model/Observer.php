<?php
/**
 * Our class name should follow the directory structure of
 * our Observer.php model, starting from the namespace,
 * replacing directory separators with underscores.
 * i.e. app/code/local/Skuiq/LogProductUpdate/Model/Observer.php
 */
class Skuiq_LogProductUpdate_Model_Observer
{
    public function logUpdate(Varien_Event_Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
        $name = $product->getName();
        $sku = $product->getSku();
        $id = $product->getId();

        $product->getResource()->getTypeId();
        $prod_type = $product->getTypeId();
        Mage::log("{$id} {$name} ({$sku}) type={$prod_type} has been updated", null, 'product-updates.txt');
    }

    public function jsonFile(Varien_Event_Observer $observer)
    {
        $apiRunning = Mage::getSingleton('api/server')->getAdapter() != null;
        Mage::log("API IS RUNNING? (1 if 'YES', '' if NO)  '{$apiRunning}'", null, 'product-updates.txt');

        $base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $uri = "https://api.skuiq.com/magento/webhooks/products";

        if ($apiRunning != 1) {

            $product = $observer->getEvent()->getProduct();
            $product->getResource()->getTypeId();
            $prodType = $product->getTypeId();

            if ($prodType == 'configurable') {
                Mage::log("Is configurable", null, 'product-updates.txt');
                $childProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $product);
                $childArray = array();
                $i = 0;
                foreach($childProducts as $child) {
                    Mage::log(($child), null, 'product-updates.txt');
                    $cID = $child->getID();
                    $qty = intval(Mage::getModel('cataloginventory/stock_item')->loadByProduct($cID)->getQty());
                    $childArray[$i]["childID"] = $cID;
                    $childArray[$i]["quantity"] = $qty;
                    Mage::log(" QTY IS {$qty} for Child ID {$cID}", null, 'product-updates.txt');
                    //$childArray = array_merge($childArray, $tmpArray);
                    $i++;
                }
                Mage::log((array_values($childArray)), null, 'product-updates.txt');
            }

            $product["children"] = $childArray;
            $json = Mage::helper('core')->jsonEncode($product);
            $client = new Zend_Http_Client($uri);
            $client->setHeaders('Content-type', 'application/json');
            $client->setParameterPost('base_url', $base_url);
            $client->setParameterPost('product', $json);
            $response = $client->request('POST');
        }
    }

    // Called if a sale is cancelled
    public function stockChange(Varien_Event_Observer $observer)
    {
        $base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $uri = "https://api.skuiq.com/magento/webhooks/cancel_order";

        $event = $observer->getEvent();
        $_item = $event->getItem();

        if ((int)$_item->getData('qty') != (int)$_item->getOrigData('qty')) {
          $prod_id = $_item->getPoductId();

          $product["children"] = "";
          $json = Mage::helper('core')->jsonEncode($_item);
          $client = new Zend_Http_Client($uri);
          $client->setHeaders('Content-type', 'application/json');
          $client->setParameterPost('base_url', $base_url);
          $client->setParameterPost('product', $json);
          $client->setParameterPost('reason', 'cancel');
          $response = $client->request('POST');
          Mage::log(" Stock Change - Cancel - {$json} ", null, 'product-updates.txt');
        }
    }

    // Called if a credit memo is created to cancel a sale
    public function refundOrderInventory(Varien_Event_Observer $observer)
    {
        $base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $uri = "https://api.skuiq.com/magento/webhooks/cancel_order";

        $creditmemo = $observer->getEvent()->getCreditmemo();
        $items = array();
        foreach ($creditmemo->getAllItems() as $item) {
            $qty = $item->getQty();
            $product_id = $item->getProductId();
            $return = $item->getBackToStock();
            $incrementId = $creditmemo->getIncrementId();

            if ($return == 1) {
              Mage::log(" Stock Change - Credit Memo - Quantity:{$qty} Product ID:{$product} Return: {$return}", null, 'product-updates.txt');
              $product["children"] = "";
              $_item = Mage::getModel('catalog/product')->load($product_id);
              $json = Mage::helper('core')->jsonEncode($_item);
              $client = new Zend_Http_Client($uri);
              $client->setHeaders('Content-type', 'application/json');
              $client->setParameterPost('base_url', $base_url);
              $client->setParameterPost('product', $json);
              $client->setParameterPost('reason', 'credit_memo');
              $response = $client->request('POST');
            }
        }
    }
}
