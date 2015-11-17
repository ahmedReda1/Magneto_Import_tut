<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Mage
 * @package    Netzarbeiter_Import
 * @copyright  Copyright (c) 2011 Vinai Kopp http://netzarbeiter.com/
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Sample class to generate random product records in array format.
 * The purpose is to easily be able to create import data for testing.
 */
class Netzarbeiter_Import_Model_Generator
{
	/**
	 * Store all used SKU's to avoid duplicates
	 *
	 * @var array
	 */
	protected $_usedSkus = array();

    /**
     * Buffer of categories
     *
     * @var array
     */
    protected $_categories;

    /**
     * List of options for the color attribute
     *
     * @var array
     */
    protected $_colorOptions;

	/**
	 * Create the specified amount of product records.
	 * The configurabe options are counted as part of the configurable product,
	 * so the real number of products in magento will be about 4-5 times as many
	 * as the specified amount. Eg:
	 * $num = 10
	 *   configurable products = 3
	 *   associated simple products = 43
	 *   simple products = 7
	 *   total = 53
	 *
	 * The exact amount of simple vs configurable products is random.
	 *
	 * @param int $num
     * @param string $type
	 * @return array product records
	 */
	public function createProducts($num, $type = null)
	{
		$records = array();
		for ($i = 0; $i < $num; $i++)
		{
			$record = $this->createProduct($type);
			if (!in_array($record['sku'], $this->_usedSkus))
			{
				$records[] = $record;
			}
		}
		return $records;
	}

	/**
	 * Create a product record array
	 *
     * @param string $type
	 * @return array
	 */
	public function createProduct($type = null)
	{
		$product = array(
			'sku' => $this->generateSku(),
			'type_id' => $this->getProductType($type),
			'name' => $this->generateName(),
			'description' => $this->generateDescription(),
			'short_description' => $this->generateDescription(60),
			'store_id' => $this->getStoreId(),
			'websites' => $this->getWebsites(),
			'categories' => $this->getCategories(),
			'color' => '',
			'weight' => '0',
			'price' => $this->generatePrice(10, 100),
			'qty' => $this->generateQty(),
		);

		if (Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE == $product['type_id'])
		{
			$product['options'] = $this->_generateProductOptions($product);
		}
		return $product;
	}

	/**
	 * Create the associated product records for a configurable product.
	 * Each option has a 80% chance of being part of the configurable product.
	 *
	 * @param array $super
	 * @return array
	 */
	protected function _generateProductOptions(array $super)
	{
		$associatedProducts = array();
		foreach ($this->getProductColorOptions() as $i => $option)
		{
			if (mt_rand(1, 100) <= 20)
			{
				continue;
			}
			$product = $super;
			$product['type_id'] = $this->getProductType(true);
			$product['sku'] .= '-' . (count($associatedProducts)+1);
			$product['color'] = $option;
			$associatedProducts[] = $product;
		}

		return $associatedProducts;
	}

	/**
	 * Return array with possible values for the attribute used to specify the
	 * configurable product option
	 *
	 * @return array
	 */
	public function getProductColorOptions()
	{
        if (is_null($this->_colorOptions))
        {
            $this->_colorOptions = array();
            $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'color');
            foreach ($attribute->getSource()->getAllOptions() as $option)
            {
                $this->_colorOptions[] = $option['label'];
            }
        }
		return $this->_colorOptions;
        //array('Yellow', 'Red', 'Green', 'Blue', 'Brown', 'Black', 'White', 'Magenta', 'Cyan', 'Pink', 'Mauve', 'Indigo', '');
	}

	/**
	 * Return the characters to use to build random words.
	 * Do not use -
	 * It has a special meaning within these sample scripts.
	 *
	 * @return array
	 */
	protected function _getWordCharArray()
	{
		return array('a', 'b', 'c', 'd', 'e', 'f', 'g', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0');
	}

	/**
	 * Generate a random word with the specified length.
	 *
	 * @param int $min
	 * @param int $max
	 * @return string
	 */
	public function generateWord($min, $max = null)
	{
		if (is_null($max))
		{
			$length = $min;
		}
		else
		{
			$length = mt_rand($min, $max);
		}

		$word = '';
		$chars = $this->_getWordCharArray();
		for ($word = ''; $length > 0; $length--)
		{
			do
			{
				$chr = $chars[array_rand($chars)];
			}
			while (! $word && ! $chr);
			$word .= $chars[array_rand($chars)];
		}
		return $word;
	}

	/**
	 * Generate a random sku.
	 *
	 * @param int $length
	 * @param bool $unique
	 * @return string
	 */
	public function generateSku($length = 12, $unique = true)
	{
		do
		{
			$sku = $this->generateWord($length);
		}
		while (! $unique || in_array($sku, $this->_usedSkus));

		return $sku;
	}

	/**
	 * Generate a random price value within the specified boundries.
	 *
	 * @param int $min
	 * @param int $max
	 * @return float
	 */
	public function generatePrice($min = 100, $max = null)
	{
		if (is_null ($max))
		{
			$price = $min;
		}
		else
		{
			$price = floatval(mt_rand($min * 10000, $max * 10000)) / 10000.00;
		}
		return $price;
	}

	/**
	 * Return the string specifiying the product type
	 *
	 * @param bool|string $forceSimple
	 * @return string
	 */
	public function getProductType($forceSimple = false)
	{
        $percentace = 66; // default
        if (Mage_Catalog_Model_Product_Type::TYPE_SIMPLE === $forceSimple)
        {
            $forceSimple = true;
        }
        elseif (Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE === $forceSimple)
        {
            $forceSimple = false;
            $percentace = 101;
        }
		if (true === $forceSimple || mt_rand(1, 100) < $percentace)
		{
			return Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
		}
		return Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE;
	}

	/**
	 * Generate a random product name
	 *
	 * @param int $length
	 * @return string
	 */
	public function generateName($length = 16)
	{
		return $this->generateWord($length);
	}

	/**
	 * Generate a product description consisting of random words
	 *
	 * @param int $length
	 * @return string
	 */
	public function generateDescription($length = 256)
	{
		$desc = '';
		do
		{
			$max = max(3, min(array($length - strlen($desc), 25)));
			$desc .= $this->generateWord(3, $max) . ' ';
		}
		while (strlen($desc) < $length);
		
		if (strlen($desc) > $length)
		{
			$desc = substr($desc, 0, $length);
		}

		return $desc;
	}


	/**
	 * Generate a stock item quantity between 10 and 1000
	 *
	 * @return int
	 */
	public function generateQty()
	{
		return mt_rand(10, 1000);
	}

	/**
	 * Return the ID of the admin store vire
	 *
	 * @return int
	 */
	public function getStoreId()
	{
		return Mage::app()->getStore(Mage_Core_Model_Store::ADMIN_CODE)->getId();
	}

	/**
	 * Return the codes for all websites
	 *
	 * @return array
	 */
	public function getWebsites()
	{
		return array_keys(Mage::app()->getWebsites(false, true));
	}

	/**
	 * Return the names of all first level categories as a comma seperated string
	 *
	 * @return string
	 */
	public function getCategories()
	{
		if (is_null($this->_categories))
		{
			$rootCategoryId = Mage::app()->getDefaultStoreView()->getRootCategoryId();
			$rootCat = Mage::getModel('catalog/category')
				->setStoreId(Mage::app()->getDefaultStoreView()->getId())
				->load($rootCategoryId);
			/* @var $rootCat Mage_Catalog_Model_Category */
			$baseCategories = $rootCat->getCollection()
					->addIdFilter($rootCat->getChildren())
					->addAttributeToSelect('name');
			foreach ($baseCategories as $category)
			{
				$this->_categories[] = $category->getName();
			}
			$this->_categories = implode(',', $this->_categories);
		}
		return $this->_categories;
	}
}
