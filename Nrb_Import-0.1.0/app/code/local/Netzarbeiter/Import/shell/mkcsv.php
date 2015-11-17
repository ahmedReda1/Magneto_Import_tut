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
 * Sample shell script to generate random product records in CSV format.
 */
class Netzarbeiter_Mkcsv extends Mage_Shell_Abstract
{
	/**
	 * Array to count the amount of each product type created
	 *
	 * @var array
	 */
	protected $_typeCount = array();

	/**
	 * Trigger the record build
	 */
	public function run()
	{
		$headerOutput = true;

		$n = $this->getArg('n');
		if (0 >= $n)
		{
			die($this->usageHelp());
		}

        if (false === $type = $this->getArg('t'))
        {
            $type = null;
        }

		$generator = Mage::getModel('Netzarbeiter_Import/generator');
        for ($stepSize = 100, $i = 0; $n > 0; $n-=$stepSize)
        {
            if ($stepSize > $n)
            {
                $stepSize = $n;
            }
            if ($this->getArg('v'))
            {
                $this->_displayProcessStatus($i*$stepSize, $i++*$stepSize+100);
            }
            $data = $generator->createProducts($stepSize, $type);
            foreach ($data as $record)
            {
                if (! $headerOutput)
                {
                    $header = array();
                    foreach ($record as $key => $value)
                    {
                        if (in_array($key, array('options', 'type_id'), true))
                        {
                            continue;
                        }
                        $header[] = $key;
                    }
                    $this->_outputRecord($header);
                    $headerOutput = true;
                }
                $this->_outputRecord($record);
            }
        }

		if ($this->getArg('v'))
		{
			$this->_displayTypeCounts();
		}
		
	}

	/**
	 * Increment the counter for the specified product type
	 *
	 * @param string $type
	 */
	protected function countType($type)
	{
		if (! isset($this->_typeCount[$type]))
		{
			$this->_typeCount[$type] = 0;
		}
		$this->_typeCount[$type]++;
	}

	/**
	 * Display the number of product records by type on stderr
	 */
	protected function _displayTypeCounts()
	{
		$f = fopen('php://stderr', 'a');
		$total = 0;

		if (isset($this->_typeCount['simple']) && isset($this->_typeCount['associated simple']))
		{
			$this->_typeCount['simple'] -= $this->_typeCount['associated simple'];
		}
		
		foreach ($this->_typeCount as $type => $num)
		{
			$total += $num;
			fwrite($f, sprintf("%4d\t\t%s\n", $num, $type));
		}
		fwrite($f, sprintf("%4d\t\ttotal\n", $total));
		fclose($f);
	}

    protected function _displayProcessStatus($from, $to)
    {
        $f = fopen('php://stderr', 'a');
        fwrite($f, sprintf("Generating records %d - %d of %d\n", $from, $to, $this->getArg('n')));
        fclose($f);
    }

	/**
	 * Build a CSV line from an record array
	 *
	 * @param array $record
	 * @return string CSV Line
	 */
	protected function _buildRecord(array $record)
	{
		$cells = array();
		foreach ($record as $key => $value)
		{
			if (in_array($key, array('options', 'type_id'), true))
			{
				continue;
			}
			
			if (is_array($value))
			{
				$value = implode(',', $value);
			}
			$cells[] = '"' . str_replace('"', '""', $value) . '"';
		}
		
		if (isset($record['options']) && $record['options'])
		{
			$this->countType('configurable');
			
			/*
			 * Ignore the configurable product, just output the associated simple products
			 */
			$cells = '';
			foreach ($record['options'] as $option)
			{
				$this->countType('associated simple');
				$cells .= $this->_buildRecord($option);
			}
		}
		else
		{
			/*
			 * Simple product, just output the row
			 */
			$this->countType('simple');
			$cells = implode(',', $cells) . "\n";
		}
		return $cells;
	}

	/**
	 * Build and output the product records
	 *
	 * @param array $record
	 */
	protected function _outputRecord(array $record)
	{
		echo $this->_buildRecord($record);
	}

	/**
	 * Retrieve usage help message
	 *
	 */
	public function usageHelp()
	{
		return <<<USAGE
Usage:  php -f run.php -- [options]

  -n            Number of products to create, must be >= 0
  -t            Optional type "simple" or "configurable". Random if omitted.
  -v            Display the number of products in the resulting fily by type
  -h            Short alias for help
  help          This help

USAGE;
	}

}

$main = new Netzarbeiter_Mkcsv();
error_reporting(-1);
$main->run();