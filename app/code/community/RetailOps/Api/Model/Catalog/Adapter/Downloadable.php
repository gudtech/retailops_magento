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

class RetailOps_Api_Model_Catalog_Adapter_Downloadable extends RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    protected $_section             = 'downloadable';

    protected $_downloadableData = array();

    protected function _construct()
    {
        $this->_errorCodes = array(
            'cant_save_downloadable_data' => '901'
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
        $sku = $productData['sku'];

        if (isset($productData['downloadable_links'])) {
            $this->_downloadableData[$sku] = $productData['downloadable_links'];
        }
    }

    /**
     * @param array $skuToIdMap
     * @return $this|void
     */
    public function afterDataProcess(array &$skuToIdMap)
    {
        $failedSkus = array();
        $idsToReindex = array();
        if (isset($this->_downloadableData)) {

            foreach ($this->_downloadableData as $sku => $downloadableData) {
                try {
                    $productId = $skuToIdMap[$sku];
                    /** @var Mage_Catalog_Model_Product $downloadable */
                    $downloadable = Mage::getModel('catalog/product')->load($productId);
                    $data = array();
                    foreach ($downloadableData as $resource) {
                        $resourceType = $resource['link_type'];
                        if (!isset($data[$resourceType])) {
                            $data[$resourceType] = array();
                        }
                        $this->_getValidator()->validateType($resourceType);
                        $this->_getValidator()->validateAttributes($resource, $resourceType);
                        $resource['is_delete'] = 0;
                        if ($resourceType == 'link') {
                            $resource['link_id'] = 0;
                        } elseif ($resourceType == 'sample') {
                            $resource['sample_id'] = 0;
                        }

                        if ($resource['type'] == 'file') {
                            if (isset($resource['file'])) {
                                $resource['file'] = $this->_uploadFile($resource['file'], $resourceType);
                            }
                        } elseif ($resource['type'] == 'url') {
                            unset($resource['file']);
                        }

                        if ($resourceType == 'link' && $resource['sample']['type'] == 'file') {
                            if (isset($resource['sample']['file'])) {
                                $resource['sample']['file'] = $this->_uploadFile($resource['sample']['file'], 'link_samples');
                            }
                            unset($resource['sample']['url']);
                        } elseif ($resourceType == 'link' && $resource['sample']['type'] == 'url') {
                            $resource['sample']['file'] = null;
                        }
                        $data[$resourceType][] = $resource;
                    }
                    $downloadable->setDownloadableData($data);
                    $downloadable->save();
                    $downloadable->clearInstance();
                } catch (Exception $e) {
                    echo $e->getMessage();
                    $failedSkus[] = $sku;
                }
            }
        }
        if ($failedSkus) {
            $this->_throwException('Downloadable data is not saved for ' . implode(',', array_unique($failedSkus)),
                'cant_save_downloadable_data');
        }
    }

    /**
     * Return validator instance
     *
     * @return RetailOps_Api_Model_Catalog_Push_Downloadable_Validator
     */
    protected function _getValidator()
    {
        return Mage::getSingleton('retailops_api/catalog_push_downloadable_validator');
    }

    /**
     * Decode file from base64 and upload it to donwloadable 'tmp' folder
     *
     * @param array $fileInfo
     * @param string $type
     * @return string
     */
    protected function _uploadFile($fileInfo, $type)
    {
        $tmpPath = '';
        if ($type == 'sample') {
            $tmpPath = Mage_Downloadable_Model_Sample::getBaseTmpPath();
        } elseif ($type == 'link') {
            $tmpPath = Mage_Downloadable_Model_Link::getBaseTmpPath();
        } elseif ($type == 'link_samples') {
            $tmpPath = Mage_Downloadable_Model_Link::getBaseSampleTmpPath();
        }

        $result = array();
        $url = $fileInfo['url'];
        $remoteFileName = $fileInfo['name'];
        $ioAdapter = new Varien_Io_File();
        $ioAdapter->checkAndCreateFolder($tmpPath);
        $ioAdapter->open(array('path' => $tmpPath));
        $fileName = $tmpPath . DS . Varien_File_Uploader::getCorrectFileName($remoteFileName);
        if ($ioAdapter->cp($url, $fileName)) {
            Mage::helper('core/file_storage_database')->saveFile($fileName);
        }
        $result['file'] = $remoteFileName;
        $result['status'] = 'new';
        $result['name'] = $remoteFileName;
        return Mage::helper('core')->jsonEncode(array($result));
    }
}
