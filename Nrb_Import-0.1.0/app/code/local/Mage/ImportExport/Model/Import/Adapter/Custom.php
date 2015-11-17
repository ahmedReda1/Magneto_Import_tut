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
 * Sample import source adapter for custom CSV format import files
 */
class Mage_ImportExport_Model_Import_Adapter_Custom extends Netzarbeiter_Import_Model_Import_Adapter_Csv_Seekable
{
	/**
	 * Pointers to the nbr csv field contents
	 */
	const CELL_SKU = 0;
	const CELL_NAME = 1;
	const CELL_DESC = 2;
	const CELL_SHORT_DESC = 3;
	const CELL_STORE_ID = 4;
	const CELL_WEBSITES = 5;
	const CELL_CATEGORIES = 6;
	const CELL_COLOR = 7;
	const CELL_WEIGHT = 8;
	const CELL_PRICE = 9;
	const CELL_QTY = 10;

    /**
     * Return true if the previous and the current row are part of the same
     * configurable product.
     *
     * @param array $previousRow
     * @param array $currentRow
     * @return bool
     */
    protected function _isCombinedRow(array $previousRow, array $currentRow)
    {
        if (isset($previousRow[self::CELL_SKU]) && isset($currentRow[self::CELL_SKU]))
        {
            @list($prevBaseSku, $prevOptionSkuId) = explode('-', $previousRow[self::CELL_SKU]);
            if (isset($prevOptionSkuId))
            {
                @list($currBaseSku, $currOptionSkuId) = explode('-', $currentRow[self::CELL_SKU]);
                return isset($currOptionSkuId) && $prevBaseSku === $currBaseSku;
            }
        }
        return false;
    }

    /**
     * Process the input data to match the format expected by the entity adapter.
     * If more then one row are passed we can assume they belong to the same
     * configurable product (the check is made by the method _isCombinedRow(),
     * see above).
     *
     * @param array $row
     * @return array
     */
    protected function _processCsvRows(array $rows)
    {
        $records = array();
        $count = count($rows);
        if (1 == $count)
        {
            // single product in the record
            $records[] = $this->_formatRecord(reset($rows));
        }
        elseif ($count > 0)
        {
            $simpleProductRecords = array();
            for ($i = 0; $i < $count-1; $i++)
            {
                $row = $this->_formatRecord($rows[$i]);
                if (0 == $i)
                {
                    // Add a configuable product to the records array
                    $config = $row;
                    @list($config['sku']) = explode('-', $row['sku']);
					$config['_type'] = Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE;
					$config['required_options'] = 1;
					$config['_super_products_sku'] = $row['sku'];
					$config['_super_attribute_code'] = 'color'; // Set the attribute to use to specify options
					$records[] = $config;
                }
                else
                {
                    // add simple products sku record to array of records following the config product
                    $optionRow = $this->_getEmptyRecordArray();
                    $optionRow['_super_products_sku'] = $row['sku'];
                    $records[] = array_values($optionRow);
                }
                // This simple product must be part of a configurable product
                $row['visibility'] = Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;
                $row['_category'] = '';
                $simpleProductRecords[] = $row;
            }
            $records = array_merge($records, $simpleProductRecords);
        }
        return $records;
        
    }

    /**
     * Return an array with the column names
     *
     * @return array
     */
    protected function _getColumnNames()
    {
        return array_keys($this->_getEmptyRecordArray());
    }


    

	/**
	 * Set all used parsed and default values on the product record array
	 *
	 * @param array $row
	 * @return array
	 */
	protected function _formatRecord(array $row)
	{
		/*
		 * Parsed values from input file
		 */
		$record = $this->_getEmptyRecordArray();
		$record['sku'] = $row[self::CELL_SKU];
		$record['name'] = $row[self::CELL_NAME];
		$record['description'] = $row[self::CELL_DESC];
		$record['short_description'] = $row[self::CELL_SHORT_DESC];
		$record['color'] = $row[self::CELL_COLOR];
		$record['price'] = $row[self::CELL_PRICE];
		$record['_store'] = $row[self::CELL_STORE_ID];
		$record['_product_websites'] = $row[self::CELL_WEBSITES];
		$record['_category'] = $row[self::CELL_CATEGORIES];
		$record['qty'] = $row[self::CELL_QTY];
		$record['weight'] = $row[self::CELL_WEIGHT];

		/*
		 * Non-empty default values
		 */
		$record['_attribute_set'] = 'Default';
		$record['_type'] = Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
		$record['is_in_stock'] = intval($record['qty'] > 0);
		$record['cost'] = '0';
		$record['created_at'] = now();
		$record['required_options'] = '0';
		$record['status'] = Mage_Catalog_Model_Product_Status::STATUS_ENABLED;
		$record['is_imported'] = 'No';
		$record['tax_class_id'] = '2';
		$record['visibility'] = Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH;
		$record['use_config_min_qty'] = '1';
		$record['use_config_backorders'] = '1';
		$record['use_config_min_sale_qty'] = '1';
		$record['use_config_max_sale_qty'] = '1';
		$record['use_config_notify_stock_qty'] = '1';
		$record['use_config_manage_stock'] = '1';
		$record['use_config_enable_qty_increments'] = '1';
		
		return $record;
	}

	/**
	 * Return an array with all the keys the import model expects, but no values
	 *
	 * @return array
	 */
	protected function _getEmptyRecordArray()
	{
		$fields = array(
			'sku' => '',
			'_store' => '',
			'_attribute_set' => '',
			'_type' => '',
			'_category' => '',
			'_product_websites' => '',
			'color' => '',
			'cost' => '',
			'created_at' => '',
			'custom_design' => '',
			'custom_design_from' => '',
			'custom_design_to' => '',
			'custom_layout_update' => '',
			'description' => '',
			'enable_googlecheckout' => '',
			'gallery' => '',
			'gift_message_available' => '',
			'has_options' => '',
			'image' => '',
			'image_label' => '',
			'is_imported' => '',
			'manufacturer' => '',
			'media_gallery' => '',
			'meta_description' => '',
			'meta_keyword' => '',
			'meta_title' => '',
			'minimal_price' => '',
			'name' => '',
			'news_from_date' => '',
			'news_to_date' => '',
			'options_container' => '',
			'page_layout' => '',
			'price' => '',
			'required_options' => '',
			'short_description' => '',
			'small_image' => '',
			'small_image_label' => '',
			'special_from_date' => '',
			'special_price' => '',
			'special_to_date' => '',
			'status' => '',
			'tax_class_id' => '',
			'thumbnail' => '',
			'thumbnail_label' => '',
			'updated_at' => '',
			'url_key' => '',
			'url_path' => '',
			'visibility' => '',
			'weight' => '',
			'qty' => '',
			'min_qty' => '',
			'use_config_min_qty' => '',
			'is_qty_decimal' => '',
			'backorders' => '',
			'use_config_backorders' => '',
			'min_sale_qty' => '',
			'use_config_min_sale_qty' => '',
			'max_sale_qty' => '',
			'use_config_max_sale_qty' => '',
			'is_in_stock' => '',
			'notify_stock_qty' => '',
			'use_config_notify_stock_qty' => '',
			'manage_stock' => '',
			'use_config_manage_stock' => '',
			'use_config_qty_increments' => '',
			'qty_increments' => '',
			'use_config_enable_qty_increments' => '',
			'enable_qty_increments' => '',
			'_links_related_sku' => '',
			'_links_related_position' => '',
			'_links_crosssell_sku' => '',
			'_links_crosssell_position' => '',
			'_links_upsell_sku' => '',
			'_links_upsell_position' => '',
			'_associated_sku' => '',
			'_associated_default_qty' => '',
			'_associated_position' => '',
			'_tier_price_website' => '',
			'_tier_price_customer_group' => '',
			'_tier_price_qty' => '',
			'_tier_price_price' => '',
			'_super_products_sku' => '',
			'_super_attribute_code' => '',
			'_super_attribute_option' => '',
			'_super_attribute_price_corr' => '',
		);
		return $fields;
	}
}
