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

class Netzarbeiter_Import_Model_Observer
{

	public function processImport($schedule)
	{
		$import = Mage::getModel('importexport/import');
		/* @var $import Mage_ImportExport_Model_Import */
		$import->setEntity('catalog_product');
		$file = Mage::getStoreConfig('netzarbeiter_import/general/file');

		if ($file && file_exists($file))
		{
			$validationResult = $import->validateSource($file);
			if ($import->getProcessedRowsCount() > 0)
			{
				if (!$validationResult)
				{
					$message = sprintf("File %s contains %s corrupt records (from a total of %s)",
									$file, $import->getInvalidRowsCount(), $import->getProcessedRowsCount()
					);
					foreach ($import->getErrors() as $type => $lines)
					{
						$message .= "\n:::: " . $type . " ::::\nIn Line(s) " . implode(", ", $lines) . "\n";
					}
					Mage::throwException($message);
				}
				$import->importSource();
				$import->invalidateIndex();
			}
		}
	}

}
