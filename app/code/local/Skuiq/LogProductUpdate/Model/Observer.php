<?php
class Skuiq_LogProductUpdate_Model_Observer
{
    public function logUpdate(Varien_Event_Observer $observer)
    {
      try {
        $product = $observer->getEvent()->getProduct();
        $name = $product->getName();
        $sku = $product->getSku();
        $id = $product->getId();

        $product->getResource()->getTypeId();
        $prod_type = $product->getTypeId();
        Mage::log("{$id} {$name} ({$sku}) type={$prod_type} has been updated", null, 'product-updates.txt');
      } catch (Exception $e) {
        Mage::log("Error in logUpdate", null, 'product-updates.txt');
      }
    }

    public function jsonFile(Varien_Event_Observer $observer)
    {
      try {
        $apiRunning = Mage::getSingleton('api/server')->getAdapter() != null;
        Mage::log("SKU IQ: API IS RUNNING? (1 if 'YES', '' if NO)  '{$apiRunning}'", null, 'product-updates.txt');

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
      } catch (Exception $e) {
        Mage::log("Error in jsonFile", null, 'product-updates.txt');
      }
    }

    public function stockChange(Varien_Event_Observer $observer)
    {
      try {
        $base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $uri = "https://api.skuiq.com/magento/webhooks/cancel_order";
        Mage::log("SKU IQ: stockChange", null, 'product-updates.txt');

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
      } catch (Exception $e) {
        Mage::log("Error in stockChange", null, 'product-updates.txt');
      }
    }

    public function refundOrderInventory(Varien_Event_Observer $observer)
    {
      try {
        $base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $uri = "https://api.skuiq.com/magento/webhooks/cancel_order";

        $creditmemo = $observer->getEvent()->getCreditmemo();

        $cr_uri = "https://api.skuiq.com/magento/webhooks/credit_memo";
        $cr_memo_id =  $creditmemo->getIncrementId();
        Mage::log(" Credit Memo ID:{$cr_memo_id}", null, 'product-updates.txt');

        $cr_client = new Zend_Http_Client($uri);
        $cr_client->setHeaders('Content-type', 'application/json');
        $cr_client->setParameterPost('base_url', $base_url);
        $cr_client->setParameterPost('credit_memo_id', $cr_memo_id);
        $response = $cr_client->request('POST');

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
      } catch (Exception $e) {
        Mage::log("Error in refundOrderInventory", null, 'product-updates.txt');
      }
    }
}
