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
 * The only methods that probably will need to be implemented / extended are
 * located at the end of this class:
 * _processCsvRows(), _isCombinedRow() and _getColumnNames()
 * The _isCombinedRow() method is only needed of your records span more then one
 * row in the input file, e.g. for configurable products.
 */
abstract class Netzarbeiter_Import_Model_Import_Adapter_Csv_Seekable extends Mage_ImportExport_Model_Import_Adapter_Abstract
{
	/**
	 * Field delimiter.
	 *
	 * @var string
	 */
	protected $_delimiter = ',';

	/**
	 * Field enclosure character.
	 *
	 * @var string
	 */
	protected $_enclosure = '"';

	/**
	 * Source file handler.
	 *
	 * @var resource
	 */
	protected $_fileHandle;

    /**
     * Buffer of parsed rows
     *
     * @var array
     */
    protected $_buffer = array();

    /**
     * Number of lines to buffer ahead from cSV file
     *
     * @var int
     */
    protected $_readAhead = 100;

    /**
     * Temporary stash of the next line to return
     *
     * @var array
     */
    protected $_stash;

    /**
     * Optional parameter to fgetcsv()
     * Slightly slower but safer if set to null
     * See http://php.net/manual/de/function.fgetcsv.php
     *
     * @var int
     */
    protected $_maxCsvLineLength = null;

	/**
	 * Method called as last step of object instance creation, can be overrided in child classes.
	 *
	 * @return Mage_ImportExport_Model_Import_Adapter_Abstract
	 */
	protected function _init()
	{
		$this->_fileHandle = fopen($this->_source, 'r');
        $this->rewind();

        return parent::_init();
    }


    /**
     * Move forward to next element
     *
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        try
        {
            $this->seek($this->_currentKey +1);
        }
        catch (OutOfBoundsException $e) {}
    }

    /**
     * Rewind resource, reset column names, set first row as current
     *
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        try
        {
            $this->seek(0);
        }
        catch (OutOfBoundsException $e) {}
    }

    /**
     * Seeks to a position.
     * Since only forward seeks are used by the ImportExport module (appart from
     * rewind) this implementation only optimises in that regard.
     *
     * @param int $position The row to seek to.
     * @throws OutOfBoundsException
     * @return void Any returned value is ignored.
     */
    public function seek($position)
    {
        if ($position !== $this->_currentKey)
        {
            if (! isset($this->_buffer[$position]))
            {
                $this->_currentRow = null;
                $this->_currentKey = null;
                $this->_moveBuffer($position);
            }

            if (isset($this->_buffer[$position]))
            {
                $this->_currentKey = $position;
                $this->_currentRow = $this->_buffer[$this->_currentKey];
                return;
            }
            throw new OutOfBoundsException(Mage::helper('importexport')->__('Invalid seek position'));
        }
    }

    /**
     * Move the buffer window start row to $position.
     * Read and buffer $_bufferSize lines or to the end of the file from there
     * whatever comes first).
     * Also set the column names from the first line in the file if the
     * target position is 0.
     *
     * @param int $position Line number to move the start of the buffer to.
     * @return void Any returned value is ignored.
     */
    protected function _moveBuffer($position)
    {
        if ($this->_buffer)
        {
            $arrayKeys = array_keys($this->_buffer);
            $lastLineInBuffer = end($arrayKeys);
        }
        else
        {
            $lastLineInBuffer = -1;
        }

        //$mem = floor(memory_get_usage(true) / 1024);
        //Mage::log('Reading buffer from line ' . ($lastLineInBuffer+1) . ' for at least to position ' . ($position + $this->_readAhead-1) . ' (' . $mem .')');

        if (0 == $position || $position < $lastLineInBuffer)
        {
            $lastLineInBuffer = -1;
            $this->_stash = null;
            fseek($this->_fileHandle, 0);
            $this->_colNames = $this->_getColumnNames();
        }
        $this->_buffer = array();
        $linesToRead = $this->_readAhead;
        while (false !== ($rows = $this->_getNextRecords()) && ! empty($rows))
        {
            foreach ($rows as $row)
            {
                if ($position > ++$lastLineInBuffer)
                {
                    continue;
                }
                $this->_buffer[$lastLineInBuffer] = $row;
            }
            
            if (0 == --$linesToRead)
            {
                break;
            }
        }
    }

    /**
     * Read one line from the CSV file
     *
     * @return array Line from CSV
     * @return false If the EOF is reached
     */
    protected function _readCsvLine()
    {
        if (! is_null($this->_stash))
        {
            $row = $this->_stash;
            $this->_stash = null;
        } 
        else
        {
            $row = fgetcsv($this->_fileHandle, $this->_maxCsvLineLength, $this->_delimiter, $this->_enclosure);
        }
        return $row;
    }

    /**
     * Return the array of records resulting from the next processed row.
     * If the rewind was called this must return the column names.
     *
     * Each row is passed to the method _isCombinesRow(). If it returns true,
     *
     *
     * @return array Array of arrays (rows)
     * @return false If EOF of CSV has been reached
     */
    protected function _getNextRecords()
    {
        $records = array();
        $count = null;
        while (false !== ($row = $this->_readCsvLine()))
        {
            if (is_null($count))
            {
                $count = 0;
                $records[$count] = $row;
            }
            else
            {
                if ($this->_isCombinedRow($records[$count], $row))
                {
                    $records[++$count] = $row;
                }
                else
                {
                    $this->_stash = $row;
                    break;
                }
            }
        }
        return $this->_processCsvRows($records);
    }

    /**
     * Return true if the current row needs to be processed together with the
     * previous row.
     * Override this method if needed by your CSV format,
     *
     * @param array $previousRow
     * @param array $currentRow
     * @return bool
     */
    protected function _isCombinedRow(array $previousRow, array $currentRow)
    {
        return false;
    }

    /**
     * Process the passed array of rows.
     * Return one or more records as an array of arrays.
     *
     * @param array The rows from the CSV to process
     * @return array
     */
    abstract protected function _processCsvRows(array $row);

    /**
     * Return the array with the column names.
     * Override if your csv file doeesn't have column names in the first row.
     *
     * @return array
     */
    abstract protected function _getColumnNames();
}
