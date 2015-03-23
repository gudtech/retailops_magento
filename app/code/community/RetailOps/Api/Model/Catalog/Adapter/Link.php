<?php
/**
The MIT License (MIT)

Copyright (c) 2015 Gud Technologies Incorporated (RetailOps by GüdTech)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
 */

class RetailOps_Api_Model_Catalog_Adapter_Link extends RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    protected $_section = 'links';

    protected $_linksData = array();
    protected $_linkTypes = array();

    protected function _construct()
    {
        $this->_linkTypes = $this->_getResource()->getLinkTypes();
        $this->_errorCodes = array(
            'cant_save_links_data' => 501,
        );
        parent::_construct();
    }

    /**
     * @param array $productData
     * @param Mage_Catalog_Model_Product $product
     * @return mixed|void
     */
    public function processData(array &$productData, $product)
    {
        if (isset($productData['links'])) {
            foreach ($this->_linkTypes as $linkTypeId => $linkTypeCode) {
                if (isset($productData['links'][$linkTypeCode])) {
                    $this->_linksData[$linkTypeId][$productData['sku']] = $productData['links'][$linkTypeCode];
                }
            }
        }
    }

    /**
     * @param array $skuToIdMap
     * @return $this|void
     */
    public function afterDataProcess(array &$skuToIdMap)
    {
        $failedSkus = array();
        if (!empty($this->_linksData)) {
            foreach ($this->_linksData as $typeId => $linksData) {
                foreach ($linksData as $sku => $productLinksData) {
                    $productId = $skuToIdMap[$sku];
                    /** @var Mage_Catalog_Model_Product $product */
                    $product = Mage::getModel('catalog/product')->setId($productId);
                    $link = $product->getLinkInstance()->setLinkTypeId($typeId);
                    $links = array();
                    foreach ($productLinksData as $productLinkData) {
                        if (!isset($skuToIdMap[$productLinkData['sku']])) {
                            continue;
                        }
                        $linkedProductId = $skuToIdMap[$productLinkData['sku']];
                        $links[$linkedProductId] = array();
                        foreach ($link->getAttributes() as $attribute) {
                            if (isset($productLinkData[$attribute['code']])) {
                                $links[$linkedProductId][$attribute['code']] = $productLinkData[$attribute['code']];
                            }
                        }
                    }
                    try {
                        if ($typeId == Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED) {
                            $link->getResource()->saveGroupedLinks($product, $links, $typeId);
                            $this->getHelper()->reindexProducts(array($product->getId()),
                                Mage_Catalog_Model_Product_Type_Grouped::TYPE_CODE);
                        } else {
                            $link->getResource()->saveProductLinks($product, $links, $typeId);
                        }
                    } catch (Exception $e) {
                        $failedSkus[] = $sku;
                    }
                }
            }
        }
        if ($failedSkus) {
            $this->_throwException('Links data is not saved for ' . implode(',', array_unique($failedSkus)),
                'cant_save_links_data');
        }
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function outputData($product)
    {
        $data = array();
        $data['links'] = $this->_getLinksData($product);

        return $data;
    }

    /**
     * Collect product links data
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    protected function _getLinksData(Mage_Catalog_Model_Product $product)
    {
        $linksData = array();
        $link = $product->getLinkInstance();
        foreach ($this->_linkTypes as $linkTypeId => $linkType) {
            $link->setLinkTypeId($linkTypeId);
            $linkedProducts = $this->_initLinksCollection($link, $product);
            foreach ($linkedProducts as $linkedProduct) {
                $row = array(
                    'product_id' => $linkedProduct->getId(),
                    'type'       => $linkedProduct->getTypeId(),
                    'sku'        => $linkedProduct->getSku()
                );

                foreach ($link->getAttributes() as $attribute) {
                    $row[$attribute['code']] = $linkedProduct->getData($attribute['code']);
                }

                $linksData[$linkType][] = $row;
            }
        }

        return $linksData;
    }

    /**
     * Initialize and return linked products collection
     *
     * @param Mage_Catalog_Model_Product_Link $link
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Link_Product_Collection
     */
    protected function _initLinksCollection($link, $product)
    {
        $collection = $link
            ->getProductCollection()
            ->setIsStrongMode()
            ->setProduct($product);

        return $collection;
    }
}
