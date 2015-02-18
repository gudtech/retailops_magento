<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Catalog_Api extends Mage_Catalog_Model_Product_Api
{
    protected $_attributeSets;

    protected $_attributes;
    protected $_simpleAttributes;
    protected $_sourceAttributes;
    protected $_attributeOptionCache;

    protected $_categories;

    protected $_linkTypes;

    /**
     * Get Products
     *
     * @param  mixed $filters
     * @return array
     */
    public function catalogPull($filters = null)
    {
        /** @var $collection Mage_Catalog_Model_Resource_Product_Collection */
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addStoreFilter($this->_getStoreId())
            ->addAttributeToSelect('*');

        $apiHelper = $this->getHelper();
        $filters = $apiHelper->applyPager($collection, $filters);
        $filters = $apiHelper->parseFilters($filters, $this->_filtersMap);
        try {
            foreach ($filters as $field => $value) {
                $collection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }

        $result = array();

        try {

            Mage::dispatchEvent(
                'retailops_catalog_pull_collection_prepare',
                array('collection' => $collection)
            );

            $result['totalCount'] = $collection->getSize();
            $result['count'] = count($collection);

            $this->_initAttributes();
            $this->_initializeCategories($collection);
            $records = array();
            /** @var $product Mage_Catalog_Model_Product */
            foreach ($collection as $product) {
                $record = array(
                    'product_id'   => $product->getId(),
                    'set'          => $this->_attributeSets[$product->getAttributeSetId()],
                    'type'         => $product->getTypeId(),
                );
                foreach ($this->_simpleAttributes as $code) {
                    $record[$code] = $product->getData($code);
                }

                foreach ($this->_sourceAttributes as $code) {
                    $value = $product->getData($code);
                    $optionLabel = array_search($value, $this->_attributeOptionCache[$code]);
                    if ($optionLabel) {
                        $record[$code] = $optionLabel;
                    } else {
                        $record[$code] = $value;
                    }
                }

                $record['media'] = $this->_getMediaData($product);

                $categoryIds = $product->getCategoryIds();
                $categoryPaths = array();
                foreach ($categoryIds as $categoryId) {
                    $categoryPath = $this->_getCategoryData($categoryId);
                    $categoryPaths[]['path'] = $categoryPath;
                }

                $record['categories'] = $categoryPaths;

                $record['links'] = $this->_getLinksData($product);

                $parents = $this->_getParents($product);
                if ($parents) {
                    $record['parent_sku'] = $parents;
                }

                $associations = $this->_getAssociations($product);
                if ($associations) {
                    $record['associations'] = $associations;
                }

                $record['tags'] = $this->_getTagsData($product->getId());

                $record['custom_options'] = $this->_getCustomOptions($product);

                $recordObj = new Varien_Object($record);

                Mage::dispatchEvent(
                    'retailops_catalog_pull_record',
                    array('record' => $recordObj)
                );

                $records[] = $recordObj->getData();
            }

            $result['records'] = $records;
        } catch (Mage_Core_Exception $e) {
            $this->_fault('catalog_pull_error', $e->getMessage());
        }

        return $result;
    }

    /**
     * @return RetailOps_Api_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('retailops_api');
    }

    /**
     * @return RetailOps_Api_Model_Resource_Api
     */
    protected function _getResource()
    {
        return Mage::getResourceModel('retailops_api/api');
    }

    /**
     * @return RetailOps_Api_Model_Catalog_Media
     */
    protected function _getMediaModel()
    {
        return Mage::getModel('retailops_api/catalog_media');
    }

    /**
     * Init product attributes and attirubtes options
     */
    protected function _initAttributes()
    {
        /** @var $attributeSetCollection Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection */
        $attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_set_collection');
        $attributeSetCollection->setEntityTypeFilter(Mage::getResourceModel('catalog/product')->getEntityType()->getId());
        $this->_attributeSets = $attributeSetCollection->toOptionHash();

        /** @var $attributeCollection Mage_Catalog_Model_Resource_Product_Attribute_Collection */
        $attributeCollection = Mage::getResourceModel('catalog/product_attribute_collection');
        $attributeCollection->setItemObjectClass('catalog/resource_eav_attribute');
        $attributeCollection->addFieldToSelect('*');

        $this->_sourceAttributes = array();
        $this->_simpleAttributes = array();
        foreach ($attributeCollection as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            if ($attribute->usesSource()) {
                /** @var Mage_Eav_Model_Entity_Attribute $attribute */
                $this->_attributeOptionCache[$attributeCode] =
                    $this->getHelper()->arrayToOptionHash(
                        $attribute->getSource()->getAllOptions(),
                        'label',
                        'value',
                        false
                    );
                $this->_sourceAttributes[] = $attributeCode;
            } else {
                $this->_simpleAttributes[] = $attributeCode;
            }
        }
    }

    /**
     * Prepare and load categories based on product collection
     *
     * @param  Mage_Catalog_Model_Resource_Product_Collection $productCollection
     * @return RetailOps_Api_Model_Catalog_Api
     */
    protected function _initializeCategories($productCollection)
    {
        $usedCategoryIds = array();
        /** @var $product Mage_Catalog_Model_Product */
        foreach ($productCollection as $product) {
            $usedCategoryIds = array_merge($usedCategoryIds, $product->getCategoryIds());
        }
        $usedCategoryIds = array_unique($usedCategoryIds);
        /** @var $collection Mage_Catalog_Model_Resource_Category_Collection */
        $collection = Mage::getModel('catalog/category')->getCollection();
        $collection->addAttributeToSelect('path');
        $collection->addIdFilter($usedCategoryIds);
        $categoryToLoad = array();
        /** @var $category Mage_Catalog_Model_Category */
        foreach ($collection as $category) {
            $categoryToLoad = array_merge($categoryToLoad, $category->getPathIds());
        }
        $categoryToLoad = array_unique($categoryToLoad);
        /** @var $fullCollection Mage_Catalog_Model_Resource_Category_Collection */
        $fullCollection = Mage::getModel('catalog/category')->getCollection();
        $fullCollection->addAttributeToSelect('*');
        $fullCollection->addIdFilter($categoryToLoad);
        foreach ($fullCollection as $category) {
            $this->_categories[$category->getId()] = $category;
        }

        return $this;
    }

    /**
     * Get category data
     *
     * @param $categoryId
     * @return array
     */
    protected function _getCategoryData($categoryId)
    {
        $data = array();
        if (isset($this->_categories[$categoryId])) {
            $category = $this->_categories[$categoryId];
            $path = array_slice($category->getPathIds(), 1);
            foreach ($path as $catId) {
                if (isset($this->_categories[$catId])) {
                    $parentCategory = $this->_categories[$catId];
                    $data[] = $parentCategory->getData();
                }
            }
        }

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
        $linkTypes = $this->_getLinkTypes();
        $link = $product->getLinkInstance();
        foreach ($linkTypes as $linkTypeId => $linkType) {
            $link->setLinkTypeId($linkTypeId);
            $linkedProducts = $this->_initLinksCollection($link, $product);
            foreach ($linkedProducts as $linkedProduct) {
                $row = array(
                    'product_id' => $linkedProduct->getId(),
                    'type'       => $linkedProduct->getTypeId(),
                    'set'        => $this->_attributeSets[$linkedProduct->getAttributeSetId()],
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

    /**
     * Get product link types
     */
    protected function _getLinkTypes()
    {
        if (!$this->_linkTypes) {
            $this->_linkTypes = $this->_getResource()->getLinkTypes();
        }

        return $this->_linkTypes;
    }

    /**
     * Get products tags data
     *
     * @param $productId
     * @return array
     */
    protected function _getTagsData($productId)
    {
        $data = array();
        $tags = Mage::getModel('tag/tag')->getCollection()->addProductFilter($productId)->addPopularity();
        /** @var $tag Mage_Tag_Model_Tag */
        foreach ($tags as $tag) {
            $result = array();
            $result['status'] = $tag->getStatus();
            $result['name'] = $tag->getName();
            $result['base_popularity'] = (is_numeric($tag->getBasePopularity())) ? $tag->getBasePopularity() : 0;
            $data[] = $result;
        }

        return $data;
    }

    /**
     * Get product custom options data
     *
     * @param Mage_Catalog_Model_Product $product
     */
    protected function _getCustomOptions($product)
    {
        $data = array();
        foreach ($product->getProductOptionsCollection() as $option) {
            $result = array(
                'title' => $option->getTitle(),
                'type' => $option->getType(),
                'is_require' => $option->getIsRequire(),
                'sort_order' => $option->getSortOrder(),
                // additional_fields should be two-dimensional array for all option types
                'additional_fields' => array(
                    array(
                        'price' => $option->getPrice(),
                        'price_type' => $option->getPriceType(),
                        'sku' => $option->getSku()
                    )
                )
            );
            // Set additional fields to each type group
            switch ($option->getGroupByType()) {
                case Mage_Catalog_Model_Product_Option::OPTION_GROUP_TEXT:
                    $result['additional_fields'][0]['max_characters'] = $option->getMaxCharacters();
                    break;
                case Mage_Catalog_Model_Product_Option::OPTION_GROUP_FILE:
                    $result['additional_fields'][0]['file_extension'] = $option->getFileExtension();
                    $result['additional_fields'][0]['image_size_x'] = $option->getImageSizeX();
                    $result['additional_fields'][0]['image_size_y'] = $option->getImageSizeY();
                    break;
                case Mage_Catalog_Model_Product_Option::OPTION_GROUP_SELECT:
                    $result['additional_fields'] = array();
                    foreach ($option->getValuesCollection() as $value) {
                        $result['additional_fields'][] = array(
                            'value_id' => $value->getId(),
                            'title' => $value->getTitle(),
                            'price' => $value->getPrice(),
                            'price_type' => $value->getPriceType(),
                            'sku' => $value->getSku(),
                            'sort_order' => $value->getSortOrder()
                        );
                    }
                    break;
                default:
                    break;
            }

            $data[] = $result;
        }

        return $data;
    }

    /**
     * Get media data
     *
     * @param $product
     * @return mixed
     */
    protected function _getMediaData($product)
    {
        return $this->_getMediaModel()->getMediaData($product);
    }

    /**
     * Get product parents
     *
     * @param Mage_Catalog_Model_Product $product
     */
    protected function _getParents($product)
    {
        if ($product->getTypeId() !== 'simple') {
            return false;
        }
        $parents = array();
        $parents = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
        $parents = array_merge($parents, Mage::getModel('bundle/product_type')->getParentIdsByChild($product->getId()));
        $parents = $this->_getResource()->getSkuByProductIds($parents);

        return $parents;
    }

    /**
     * Get product associations
     *
     * @param Mage_Catalog_Model_Product $product
     */
    protected function _getAssociations($product)
    {
        if ($product->getTypeId() !== Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE) {
            return false;
        }
        $children = $product->getTypeInstance(true)->getChildrenIds($product->getId());
        if ($children) {
            $children = $children[0];
        }
        $associations = $this->_getResource()->getSkuByProductIds($children);

        return $associations;
    }
}
