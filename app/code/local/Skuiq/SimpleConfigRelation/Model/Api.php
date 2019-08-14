<?php
class Skuiq_SimpleConfigRelation_Model_Api extends Mage_Catalog_Model_Api_Resource
{
    public function getRelationship($productId, $storeId = null)
    {
        try {
            $result = array();
            $this->_configProductId = $productId;
            $product = $this->_getProduct($productId, $storeId);
            $this->_getProduct = Mage::getModel('catalog/product');

            // $this->_getProduct->setStoreId($storeId);
            $this->_getProduct->load($productId);

            if ($this->_getProduct->getId()) {
                $result['optiondata']=$this->alldropdownData();
            }

            return $result;
        } catch (Exception $e) {
            Mage::log("Error in getRelationship", null, 'config-relations.txt');
        }
    }

    public function addRelationship($configurableProduct, $childProduct)
    {
        try {
            $_product = Mage::getModel('catalog/product')->load($configurableProduct);

            $childIds     = $_product->getTypeInstance()->getUsedProductIds();
            $new_children = array_merge($childIds, $childProduct);

            $result = Mage::getResourceSingleton('catalog/product_type_configurable')->saveProducts($_product, $new_children);
            return true;
        } catch (Exception $e) {
            Mage::log("Error in addRelationship", null, 'config-relations.txt');
            return false;
        }
    }

    public function getChildsByParentId()
    {
    }

    public function getVarienPrice()
    {
    }

    public function alldropdownData()
    {
        $attributes = array();
        $options    = array();

        $currentProduct = $this->_getProduct;

        $products = array();

        $skipSaleableCheck = Mage::helper('catalog/product')->getSkipSaleableCheck();

        /* getting all simple products */
        $allProducts = $this->_getProduct->getTypeInstance(true)
            ->getUsedProducts(null, $this->_getProduct);

        foreach ($allProducts as $product) {
          $products[] = $product;
        }

        /* getting all configurable attributes of that products */
        $AllowAttributes=$this->_getProduct->getTypeInstance(true)
            ->getConfigurableAttributes($this->_getProduct);

        $producVarien=array();

        foreach ($products as $product) {
            $productsData[$product->getId()]=$product->getData();

            foreach ($AllowAttributes as $attribute) {
                $productAttribute   = $attribute->getProductAttribute();
                $productAttributeId = $productAttribute->getId();
                $attributeValue     = $product->getData($productAttribute->getAttributeCode());

                /* Simple Product data */
                if (!isset($producVarien[$product->getId()])) {
                    $producVarien[$product->getId()] = array();
                }

                if (!isset($producVarien[$product->getId()][$productAttributeId])) {
                    $producVarien[$product->getId()][$productAttributeId] = array();
                }

                /* getting option text value */
                if ($productAttribute->usesSource()) {
                    $MYabel = $productAttribute->getSource()->getOptionText($attributeValue );
                }else{
                    $MYabel='';
                }
                $info = array(
                    'id'           => $productAttribute->getId(),
                    'code'         => $productAttribute->getAttributeCode(),
                    'label'        => $attribute->getLabel(),
                    'optionslabel' => $MYabel
                );

                $producVarien[$product->getId()][$productAttributeId] = array('value'=>$attributeValue, 'all'=>$info);

                /* end of simple product data */
                if (!isset($options[$productAttributeId])) {
                    $options[$productAttributeId] = array();
                }

                if (!isset($options[$productAttributeId][$attributeValue])) {
                    $options[$productAttributeId][$attributeValue] = array();
                }

                $options[$productAttributeId][$attributeValue][] = $productId;
            }
        }

        return $producVarien;
    }
}
