<?php

/**
 * Local Override to map Product Media to RetailOps CDN
 *
 * @category  Class
 * @package   DollsKill_RetailOpsCDN
 * @author    jared@dollskill.com, Groove Commerce
 * @copyright 2016 Groove Commerce, LLC. All Rights Reserved.
 */

/**
 * Extended to allow for dynamic passing of width needed
 * in the init() calls from various template files. Since
 * Many image sizes needed vary more than media_attributes
 * would allow to maintain without muddying up the already
 * over-complicated EAV tables.
 *
 * @category Class_Type_Helper
 * @package  DollsKill_RetailOpsCDN
 * @author   jared@dollskill.com, Groove Commerce
 */

class DollsKill_RetailOpsCDN_Helper_Catalog_Image
 extends Groove_RetailOpsCDN_Helper_Catalog_Image {

	/*
	 * Lookup Table to match closest dimensions requested
	 * Some Mappings needed don't match the configured settings
	 * image,small_image,thumb,etc.
	 *
	 * Details: http://help.retailops.com/hc/en-us/articles/207043516-Feed-Syntax-Media-Methods

		Implement new custom sizes provided from RetailOps:

		26 -> jpeg-fixed65x78
		27 -> jpeg-fixed75x75
		28 -> jpeg-fixed90x128
		29 -> jpeg-fixed110x120
		30 -> jpeg-fixed120x150
		31 -> jpeg-fixed211x300
		32 -> jpeg-fixed460x580
		33 -> jpeg-fixed570x600
		34 -> jpeg-fixed1405x2000

		Current Image Sizes:
		Category view: 460x580
		Barilliance suggestion: 460x580
		Product Page:
		Main Image: 570x600
		Thumbnail: 65x78
		Original Image: 1140x1200 - 2667x3796 - 1405x2000
		Mini-Cart Dropdown: 110x120
		Cart Page: 120x150
		Checkout Page: 75x75
		Email: 90x128
		Wishlist drop-down: 211x300

	 */

	// Unfixed (no white space padding)
	protected $_lookupSizeTable = Array(
		65=>  '26', // Thumbnail
		75=>  '27', // Checkout Page
		90=>  '28', // Email
		110=> '29', // Minicart
		120=> '30', // Cart Page
		211=> '31', // Wishlist
		460=> '32', // Category
		570=> '33', // Main PDP Image
		1140=>'34', // Original Image
		2667=>'34', // Original Image
		1405=>'34', //  Original Image
		40  => '7', // 40x40
		90  => '5', // 90x90
		150 => '9', // 150x150
		100 => '4', // 100x100
		200 => '2', // 200x200
		500 => '3', // 500x500
		600 => '6', // 600x600
		800 => '8', // 800x800
		1024=> '18', // 1024x1024
		1200=> '24', // 1200x1200
		1300=> '1' // original
	);

	// Fixed (white space padding added)
	protected $_lookupFixedSizeTable = Array(
		65=>  '26', // Thumbnail
		75=>  '27', // Checkout Page
		90=>  '28', // Email
		110=> '29', // Minicart
		120=> '30', // Cart Page
		211=> '31', // Wishlist
		460=> '32', // Category
		570=> '33', // Main PDP Image
		1140=>'34', // Original Image
		2667=>'34', // Original Image
		1405=>'34', //  Original Image
		40  => '19', // 40x40
		90  => '10', // 90x90
		100 => '11', // 100x100
		500 => '12', // 500x500
		800 => '13', // 800x800
		1000=> '14', // 1000x1000
		250 => '15', // 250x250
		200 => '16', // 200x200
		800 => '17', // 800x800
		1200=> '25', // 1200x1200
		1300=> '1' // original
	);

	/*
	 * Find closet number in lookup table, since we want to match up
	 * a specefic width parameter to what exists in RetailOps CDN
	 * and bypass the mapping dynamically
	 *
	 * @param array $array Haystack to search through
	 * @param int $number Needle to find
	 *
	 * @return array
	 */
	public function _getClosest($number, $candidates) {
		asort($candidates);
		$current = reset($candidates);
		foreach ($candidates as $candidate => $format) {
			if (abs($number-$candidate) < abs($number-$current)) {
				$current = $candidate;
				$diff    = abs($current-$number);
				$fmt     = $format;
			}
		}
		//return array("format" => $fmt, "current" => $current, "diff" => $diff);
		return $fmt;
	}

	/**
	 * Translate a given filename into its requested variant.
	 *
	 * @param string $file The input filename.
	 * @param string $type The image type.
	 *
	 * @return string
	 */
	public function getMatchedDimMappedFileName($file, $width = 0, $fixed = false) {

		// determine which lookup tables to use
		if ($fixed) {
			$match = $this->_getClosest($width, $this->_lookupFixedSizeTable);
		} else {
			$match = $this->_getClosest($width, $this->_lookupSizeTable);
		}
		$parts     = explode('.', $file);
		$extension = array_pop($parts);
		$name      = implode('.', $parts);
		$format    = array_pop((explode('-', $name)));

		if ($format) {
			$name = str_replace("-{$format}", '', $name);
		}

		$construct = array(
			$name,
			'-'.$match,
			'.'.$extension,
		);
		//Zend_Debug::dump(implode('', $construct));
		return implode('', $construct);
	}

	/**
	 * Prepare the helper for image processing.
	 *
	 * @param Mage_Catalog_Model_Product $product       The product on which to operate.
	 * @param string                     $attributeName The image attribute name.
	 * @param mixed                      $imageFile     An optional image file to specify.
	 *
	 * @return Groove_RetailOpsCDN_Helper_Catalog_Image
	 */
	public function init(Mage_Catalog_Model_Product $product, $attributeName, $imageFile = null, $width = 0, $fixed = false) {

		if (!Mage::helper('retailops_cdn')->isEnabled()) {
			return parent::init($product, $attributeName, $imageFile);
		}

		if($product->getTypeId() == Enterprise_GiftCard_Model_Catalog_Product_Type_Giftcard::TYPE_GIFTCARD) {
		    $_helper = Mage::helper('dollskill_giftcard');
		    $imageFile = $_helper->getGiftCardImage();
		}

		if($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE && $imageFile == null) {
			$childProducts = Mage::getModel('catalog/product_type_configurable')
                    ->getUsedProducts(null,$product);
            foreach($childProducts as $childProduct) {
            	$imageFile = $childProduct->getImage();
            	break;
            }
		}

		// reset previous data, and reset accordingly
		$this->_reset();
		$this->_setModel(Mage::getModel('retailops_cdn/catalog_product_image'));
		$this->_getModel()->setDestinationSubdir($attributeName);
		$this->setProduct($product);

		// if specific image filename is passed
		if ($imageFile) {
			$this->setImageFile($imageFile);
		} else {
			$this->_getModel()
			     ->setBaseFile(
				$this->getProduct()->getData(
					$this->_getModel()->getDestinationSubdir()
				)
			);
		}
		$this->_prepareWatermark();

		// if a width is specified override media attribute mapping
		if ($width >= 1) {
			$returnedFmt = $this->getMatchedDimMappedFileName($imageFile, $width, $fixed);
			$imageFile = $returnedFmt;
			$this->setImageFile($imageFile);
			$this->_getModel()
			     ->setBaseFile($imageFile);
			$closestImageWidth = $this->_getModel()->getBaseMediaUrl().ltrim($imageFile, './');
			return $closestImageWidth;
		}

		return $this;
	}

}