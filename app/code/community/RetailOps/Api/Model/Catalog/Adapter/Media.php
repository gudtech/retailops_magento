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

class RetailOps_Api_Model_Catalog_Adapter_Media extends RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    /**
     * Attribute code for media gallery
     */
    const ATTRIBUTE_CODE      = 'media_gallery';

    const CRON_DOWNLOAD_LIMIT = 10;

    protected $_section   = 'media';

    protected $_mediaDataToSave = array();
    protected $_straightMediaProcessing = false;

    protected function _construct()
    {
        $this->_errorCodes = array(
            'no_media_attribute'   => 801,
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
        if ($product->getId() && (!isset($productData['unset_other_media']) || $productData['unset_other_media'])) {
            $this->clearProductGallery($product);
        }

        if (isset($productData['media'])) {
            $allMediaData = array();
            foreach ($productData['media'] as $mediaData) {
                $mediaData = new Varien_Object($mediaData);

                Mage::dispatchEvent('retailops_catalog_media_process_before',
                    array('media_data' => $mediaData));

                $allMediaData[] = $mediaData->getData();
            }
            $this->_mediaDataToSave[$productData['sku']] = json_encode($allMediaData);
        }
        if (isset($productData['straight_media_process']) && $productData['straight_media_process']) {
            $this->_straightMediaProcessing = true;
        }
    }

    /**
     * @param array $skuToIdMap
     * @return $this|void
     */
    public function afterDataProcess(array &$skuToIdMap)
    {
        if ($this->_mediaDataToSave) {
            foreach ($this->_mediaDataToSave as $sku => $data) {
                $productId = $skuToIdMap[$sku];
                $dataToSave['media_data'] = $data;
                $dataToSave['product_id'] = $productId;
                $item = Mage::getModel('retailops_api/catalog_media_item')->setData($dataToSave);
                if (!$this->_straightMediaProcessing) {
                    $item->save();
                } else {
                    $this->downloadProductImages($item);
                }
            }
        }
    }

    /**
     * Unset product image gallery
     *
     * @param $product
     */
    public function clearProductGallery($product)
    {
        $galleryData = $this->_prepareGallery($product);

        if (isset($galleryData['images']) && is_array($galleryData['images'])) {
            foreach ($galleryData['images'] as &$image) {
                $image['removed'] = 1;
            }
        }

        $product->setData(self::ATTRIBUTE_CODE, $galleryData);
    }

    /**
     * Download products media
     *
     * @param Varien_Object|null $item
     * @return array
     */
    public function downloadProductImages($item = null)
    {
        $ioAdapter = new Varien_Io_File();
        $tmpDirectory = Mage::getBaseDir('var') . DS . 'api' . DS . uniqid();
        $ioAdapter->checkAndCreateFolder($tmpDirectory);
        $ioAdapter->open(array('path' => $tmpDirectory));
        $remoteCopyRetryLimit = 3;
        $errorLogPath = '/tmp/retailops_magento_image_error.log';
        if (!$item) {
            $items = Mage::getModel('retailops_api/catalog_media_item')->getCollection();
            $limit = $this->getHelper()->getConfig('media_processing_products_limit');
            if (!is_numeric($limit)) {
                $limit = self::CRON_DOWNLOAD_LIMIT;
            }
            $items->getSelect()->limit($limit);
        } else {
            $items = array($item);
        }
        $result = array();
        /** @var $item RetailOps_Api_Model_Catalog_Media_Item */
        foreach ($items as $item) {
            $productId = $item->getProductId();
            $data = json_decode($item->getMediaData(), true);
            try {
                $imageResult = array();

                foreach ($data as $newImage) {
                    try {
                        $product = Mage::getModel('catalog/product')->load($productId);
                        $product->setStoreId(0); //using default store for images import
                        $gallery = $this->_getGalleryAttribute($product);
                        $sku = $product->getSku();
                        if (!isset($result[$sku])) {
                            $result[$sku] = array();
                        }

                        $isNewImage = false;
                        $file = $this->_existingImage($productId, $newImage);

                        if (!$file) {
                            $url = $newImage['download_url'];
                            if (!$this->_httpFileExists($url)) {
                                Mage::throwException('Image does not exist.');
                            }
                            $fileName = $this->_getFileName($url, $newImage);
                            $fileName = $tmpDirectory . DS . $fileName;
                            $ioAdapter->cp($url, $fileName);

                            $retry = 0;
                            $remoteCopySuccess = false;
                            while ($retry++ < $remoteCopyRetryLimit && !$remoteCopySuccess) {
                                $remoteCopySuccess = $ioAdapter->cp($url, $fileName);
                            }

                            if (!$remoteCopySuccess) {
                                $remoteCopyError = error_get_last();

                                throw new Exception($remoteCopyError['message']);
                            }

                            // Out of an abundance of caution, make sure that the
                            // image hasn't been added to product since first check.
                            // Possible if multiple scripts have kicked off cron jobs.
                            $recheck_file = $this->_existingImage($productId, $newImage);
                            if ($recheck_file) {
                                file_put_contents($errorLogPath, "$sku: Image added to product before download completed\n", FILE_APPEND);
                                $file = $recheck_file;
                            }
                            else {
                                // Adding image to gallery
                                $file = $gallery->getBackend()->addImage(
                                    $product,
                                    $fileName,
                                    null,
                                    true
                                );
                                $isNewImage = true;
                            }
                        }

                        $gallery->getBackend()->updateImage($product, $file, $newImage);
                        if (isset($newImage['types'])) {
                            $gallery->getBackend()->setMediaAttribute($product, $newImage['types'], $file);
                        }

                        $product->save();
                        if ($isNewImage) {
                            $this->_updateMediaKey($product->getId(), $file, $newImage);
                        }

                        $product->clearInstance();
                    } catch (Exception $e) {
                        $message = sprintf("Could not process image %s, error message: %s", $newImage['download_url'], $e->getMessage());
                        $imageResult[] = $message;
                        file_put_contents($errorLogPath, "$message\n", FILE_APPEND);
                    }
                }
                if ($imageResult) {
                    $result[$sku]['images'] = $imageResult;
                }
                if ($item->getId()) {
                    $item->delete();
                }
            } catch (Exception $e) {
                $result[$sku]['general'] = $e->getMessage();
                file_put_contents($errorLogPath, "{$e->getMessage()}\n", FILE_APPEND);
            }
        }

        // Remove temporary directory
        $ioAdapter->rmdir($tmpDirectory, true);

        return $result;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function outputData($product)
    {
        $galleryData = $this->_prepareGallery($product);
        $data = array();
        $data['media'] = array();

        if (!isset($galleryData['images']) || !is_array($galleryData['images'])) {
            return $data;
        }

        $result = array();
        $mediaWithMediaKey = $this->_getResource()->getProductMedia($product->getId());
        $valueIdToMediaKey = array();
        foreach ($mediaWithMediaKey as $media) {
            $valueIdToMediaKey[$media['value_id']] = $media['retailops_mediakey'];
        }
        foreach ($galleryData['images'] as &$image) {
            if (!empty($valueIdToMediaKey[$image['value_id']])) {
                $image['mediakey'] = $valueIdToMediaKey[$image['value_id']];
            }
            $result[] = $this->_imageToArray($image, $product);
        }

        $data['media'] = $result;

        return $data;
    }

    /**
     * Converts image to api array data
     *
     * @param array $image
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    protected function _imageToArray(&$image, $product)
    {
        $result = array(
            'file'      => $image['file'],
            'label'     => $image['label'],
            'position'  => $image['position'],
            'exclude'   => $image['disabled'],
            'mediakey'  => $image['mediakey'],
            'url'       => $this->_getMediaConfig()->getMediaUrl($image['file']),
            'types'     => array()
        );


        foreach ($product->getMediaAttributes() as $attribute) {
            if ($product->getData($attribute->getAttributeCode()) == $image['file']) {
                $result['types'][] = $attribute->getAttributeCode();
            }
        }

        return $result;
    }

    /**
     * Retrieve media config
     *
     * @return Mage_Catalog_Model_Product_Media_Config
     */
    protected function _getMediaConfig()
    {
        return Mage::getSingleton('catalog/product_media_config');
    }

    /**
     * Prepare product's gallery data
     *
     * @param $product
     * @return mixed
     */
    protected function _prepareGallery($product)
    {
        $gallery = $this->_getGalleryAttribute($product);
        $gallery->getBackend()->afterLoad($product);
        $galleryData = $product->getData(self::ATTRIBUTE_CODE);

        return $galleryData;
    }

    /**
     * Check if image download url is valid
     *
     * @param $url
     * @return bool
     */
    protected function _httpFileExists($url)
    {
        $headers = @get_headers($url);

        return !(strpos($headers[0], '200') === false);
    }

    /**
     * Get existing image filename, if any, based on mediakey and filename
     *
     * @param $productId
     * @param $newImageData
     * @return mixed
     */
    protected function _existingImage($productId, $newImageData)
    {
        $existingImages = $this->_getResource()->getProductMedia($productId);

        // Prioritize mediakeys. Search all existing images for mediakey before considering filename_match.
        foreach ($existingImages as $existingImage) {
            if ($newImageData['mediakey'] == $existingImage['retailops_mediakey']) {
                return $existingImage['value'];
            }
        }
        
        foreach ($existingImages as $existingImage) {
            $fileNameMatch = preg_quote($newImageData['filename_match'], '~');

            if (strlen($fileNameMatch) && preg_grep('~' . $fileNameMatch . '~', $existingImages['value'])) {
                return $existingImage['value'];
            }
        }
        
        return false;
    }

    /**
     * Get filename of uploaded file
     *
     * @param string $url
     * @param string $media_data
     * @return string $filename
     */
    protected function _getFileName($url, $media_data)
    {
        if (isset($media_data['file'])
            && isset($media_data['file']['name'])
            && strlen($media_data['file']['name'])
        ) {
            $fileName = $media_data['file']['name'];
        }
        else {
            $fileName = Varien_File_Uploader::getCorrectFileName(basename($url));
        }

        return trim($fileName, '_');
    }

    /**
     * Update product's gallery with mediakeys
     *
     * @param $productId
     * @param $file
     * @param $newImage
     */
    protected function _updateMediaKey($productId, $file, $newImage)
    {
        $allImages = Mage::getResourceModel('retailops_api/api')->getProductMedia($productId);
        $dataToUpdate = array();
        foreach ($allImages as $image) {
            if ($image['value'] == $file) {
                Mage::getResourceModel('retailops_api/api')->updateMediaKeys(array(
                    'value_id' => $image['value_id'],
                    'retailops_mediakey' => $newImage['mediakey'],
                ));
            }
        }
    }

     /**
     * Retrieve gallery attribute from product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Catalog_Model_Resource_Eav_Mysql4_Attribute|boolean
     */
    protected function _getGalleryAttribute($product)
    {
        $attributes = $product->getTypeInstance(true)
            ->getSetAttributes($product);

        if (!isset($attributes[self::ATTRIBUTE_CODE])) {
            $this->_throwException('Product has no media attribute', 'no_media_attribute');
        }

        return $attributes[self::ATTRIBUTE_CODE];
    }
}
