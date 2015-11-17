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
 * Require the Magento abstract class for cli scripts
 * Workaround for varying depths because of possible .modman directory
 */
$path = '/../../../../../../shell/abstract.php';
while (! file_exists(dirname(__FILE__) . $path))
{
    $path = '/..' . $path;
}
require_once dirname(__FILE__) . $path;

/**
 * Shell script to trigger the import of product records from a custom CSV format
 * using the new (1.5) ImportExport module
 */
class Netzarbeiter_Import extends Mage_Shell_Abstract
{
	/**
	 * Trigger the import
	 */
	public function run()
	{
		$import = Mage::getModel('importexport/import');
		/* @var $import Mage_ImportExport_Model_Import */
		$import->setEntity('catalog_product');
		$validationResult = $import->validateSource($this->getFile());
		if ($import->getProcessedRowsCount() > 0)
		{
			if (!$validationResult)
			{
				$message = sprintf("File %s contains %s corrupt records (from a total of %s)",
					$this->getFile(), $import->getInvalidRowsCount(), $import->getProcessedRowsCount()
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

	/**
	 * Return the specified source file
	 *
	 * @return string
	 */
	public function getFile()
	{
		$file = $this->getArg('s');
		if (!$file)
		{
			$file = $this->getArg('source');
		}
		if (!$file)
		{
			$file = Mage::getStoreConfig('netzarbeiter_import/general/file');
		}
		if (!$file)
		{
			die($this->usageHelp());
		}

		return $file;
	}

	/**
	 * Retrieve usage help message
	 *
	 */
	public function usageHelp()
	{
		return <<<USAGE
Usage:  php -f run.php -- [options]

  -s            Path to import file, or
  --source      Path to import file
  -h            Short alias for help
  help          This help

USAGE;
	}

}

$main = new Netzarbeiter_Import();
$main->run();