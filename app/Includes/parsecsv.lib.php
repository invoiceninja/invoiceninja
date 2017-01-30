<?php

class parseCSV
{
    /*

    Class: parseCSV v0.3.2
    http://code.google.com/p/parsecsv-for-php/


    Fully conforms to the specifications lined out on wikipedia:
     - http://en.wikipedia.org/wiki/Comma-separated_values

    Based on the concept of Ming Hong Ng's CsvFileParser class:
     - http://minghong.blogspot.com/2006/07/csv-parser-for-php.html



    Copyright (c) 2007 Jim Myhrberg (jim@zydev.info).

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



    Code Examples
    ----------------
    # general usage
    $csv = new parseCSV('data.csv');
    print_r($csv->data);
    ----------------
    # tab delimited, and encoding conversion
    $csv = new parseCSV();
    $csv->encoding('UTF-16', 'UTF-8');
    $csv->delimiter = "\t";
    $csv->parse('data.tsv');
    print_r($csv->data);
    ----------------
    # auto-detect delimiter character
    $csv = new parseCSV();
    $csv->auto('data.csv');
    print_r($csv->data);
    ----------------
    # modify data in a csv file
    $csv = new parseCSV();
    $csv->sort_by = 'id';
    $csv->parse('data.csv');
    # "4" is the value of the "id" column of the CSV row
    $csv->data[4] = array('firstname' => 'John', 'lastname' => 'Doe', 'email' => 'john@doe.com');
    $csv->save();
    ----------------
    # add row/entry to end of CSV file
    #  - only recommended when you know the extact sctructure of the file
    $csv = new parseCSV();
    $csv->save('data.csv', array('1986', 'Home', 'Nowhere', ''), true);
    ----------------
    # convert 2D array to csv data and send headers
    # to browser to treat output as a file and download it
    $csv = new parseCSV();
    $csv->output (true, 'movies.csv', $array);
    ----------------


*/

    /**
     * Configuration
     * - set these options with $object->var_name = 'value';.
     */

    // use first line/entry as field names
    public $heading = true;

    // override field names
    public $fields = [];

    // sort entries by this field
    public $sort_by = null;
    public $sort_reverse = false;

    // delimiter (comma) and enclosure (double quote)
    public $delimiter = ',';
    public $enclosure = '"';

    // basic SQL-like conditions for row matching
    public $conditions = null;

    // number of rows to ignore from beginning of data
    public $offset = null;

    // limits the number of returned rows to specified amount
    public $limit = null;

    // number of rows to analyze when attempting to auto-detect delimiter
    public $auto_depth = 15;

    // characters to ignore when attempting to auto-detect delimiter
    public $auto_non_chars = "a-zA-Z0-9\n\r";

    // preferred delimiter characters, only used when all filtering method
    // returns multiple possible delimiters (happens very rarely)
    public $auto_preferred = ",;\t.:|";

    // character encoding options
    public $convert_encoding = false;
    public $input_encoding = 'ISO-8859-1';
    public $output_encoding = 'ISO-8859-1';

    // used by unparse(), save(), and output() functions
    public $linefeed = "\r\n";

    // only used by output() function
    public $output_delimiter = ',';
    public $output_filename = 'data.csv';

    /**
     * Internal variables.
     */

    // current file
    public $file;

    // loaded file contents
    public $file_data;

    // array of field values in data parsed
    public $titles = [];

    // two dimentional array of CSV data
    public $data = [];

    /**
     * Constructor.
     *
     * @param   input   CSV file or string
     * @param null|mixed $input
     * @param null|mixed $offset
     * @param null|mixed $limit
     * @param null|mixed $conditions
     *
     * @return nothing
     */
    public function __construct($input = null, $offset = null, $limit = null, $conditions = null)
    {
        if ($offset !== null) {
            $this->offset = $offset;
        }
        if ($limit !== null) {
            $this->limit = $limit;
        }
        if (count($conditions) > 0) {
            $this->conditions = $conditions;
        }
        if (! empty($input)) {
            $this->parse($input);
        }
    }

    // ==============================================
    // ----- [ Main Functions ] ---------------------
    // ==============================================

    /**
     * Parse CSV file or string.
     *
     * @param   input   CSV file or string
     * @param null|mixed $input
     * @param null|mixed $offset
     * @param null|mixed $limit
     * @param null|mixed $conditions
     *
     * @return nothing
     */
    public function parse($input = null, $offset = null, $limit = null, $conditions = null)
    {
        if (! empty($input)) {
            if ($offset !== null) {
                $this->offset = $offset;
            }
            if ($limit !== null) {
                $this->limit = $limit;
            }
            if (count($conditions) > 0) {
                $this->conditions = $conditions;
            }
            if (is_readable($input)) {
                $this->data = $this->parse_file($input);
            } else {
                $this->file_data = &$input;
                $this->data = $this->parse_string();
            }
            if ($this->data === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Save changes, or new file and/or data.
     *
     * @param   file     file to save to
     * @param   data     2D array with data
     * @param   append   append current data to end of target CSV if exists
     * @param   fields   field names
     * @param null|mixed $file
     * @param mixed      $data
     * @param mixed      $append
     * @param mixed      $fields
     *
     * @return true or false
     */
    public function save($file = null, $data = [], $append = false, $fields = [])
    {
        if (empty($file)) {
            $file = &$this->file;
        }
        $mode = ($append) ? 'at' : 'wt';
        $is_php = (preg_match('/\.php$/i', $file)) ? true : false;

        return $this->_wfile($file, $this->unparse($data, $fields, $append, $is_php), $mode);
    }

    /**
     * Generate CSV based string for output.
     *
     * @param   output      if true, prints headers and strings to browser
     * @param   filename    filename sent to browser in headers if output is true
     * @param   data        2D array with data
     * @param   fields      field names
     * @param   delimiter   delimiter used to separate data
     * @param mixed      $output
     * @param null|mixed $filename
     * @param mixed      $data
     * @param mixed      $fields
     * @param null|mixed $delimiter
     *
     * @return CSV data using delimiter of choice, or default
     */
    public function output($output = true, $filename = null, $data = [], $fields = [], $delimiter = null)
    {
        if (empty($filename)) {
            $filename = $this->output_filename;
        }
        if ($delimiter === null) {
            $delimiter = $this->output_delimiter;
        }
        $data = $this->unparse($data, $fields, null, null, $delimiter);
        if ($output) {
            header('Content-type: application/csv');
            header('Content-Disposition: inline; filename="'.$filename.'"');
            echo $data;
        }

        return $data;
    }

    /**
     * Convert character encoding.
     *
     * @param   input    input character encoding, uses default if left blank
     * @param   output   output character encoding, uses default if left blank
     * @param null|mixed $input
     * @param null|mixed $output
     *
     * @return nothing
     */
    public function encoding($input = null, $output = null)
    {
        $this->convert_encoding = true;
        if ($input !== null) {
            $this->input_encoding = $input;
        }
        if ($output !== null) {
            $this->output_encoding = $output;
        }
    }

    /**
     * Auto-Detect Delimiter: Find delimiter by analyzing a specific number of
     * rows to determine most probable delimiter character.
     *
     * @param   file           local CSV file
     * @param   parse          true/false parse file directly
     * @param   search_depth   number of rows to analyze
     * @param   preferred      preferred delimiter characters
     * @param   enclosure      enclosure character, default is double quote (").
     * @param null|mixed $file
     * @param mixed      $parse
     * @param null|mixed $search_depth
     * @param null|mixed $preferred
     * @param null|mixed $enclosure
     *
     * @return delimiter character
     */
    public function auto($file = null, $parse = true, $search_depth = null, $preferred = null, $enclosure = null)
    {
        if ($file === null) {
            $file = $this->file;
        }
        if (empty($search_depth)) {
            $search_depth = $this->auto_depth;
        }
        if ($enclosure === null) {
            $enclosure = $this->enclosure;
        }

        if ($preferred === null) {
            $preferred = $this->auto_preferred;
        }

        if (empty($this->file_data)) {
            if ($this->_check_data($file)) {
                $data = &$this->file_data;
            } else {
                return false;
            }
        } else {
            $data = &$this->file_data;
        }

        $chars = [];
        $strlen = strlen($data);
        $enclosed = false;
        $n = 1;
        $to_end = true;

        // walk specific depth finding posssible delimiter characters
        for ($i = 0; $i < $strlen; $i++) {
            $ch = $data{$i};
            $nch = (isset($data{$i + 1})) ? $data{$i + 1} : false;
            $pch = (isset($data{$i - 1})) ? $data{$i - 1} : false;

            // open and closing quotes
            if ($ch == $enclosure && (! $enclosed || $nch != $enclosure)) {
                $enclosed = ($enclosed) ? false : true;

            // inline quotes
            } elseif ($ch == $enclosure && $enclosed) {
                $i++;

            // end of row
            } elseif (($ch == "\n" && $pch != "\r" || $ch == "\r") && ! $enclosed) {
                if ($n >= $search_depth) {
                    $strlen = 0;
                    $to_end = false;
                } else {
                    $n++;
                }

            // count character
            } elseif (! $enclosed) {
                if (! preg_match('/['.preg_quote($this->auto_non_chars, '/').']/i', $ch)) {
                    if (! isset($chars[$ch][$n])) {
                        $chars[$ch][$n] = 1;
                    } else {
                        $chars[$ch][$n]++;
                    }
                }
            }
        }

        // filtering
        $depth = ($to_end) ? $n - 1 : $n;
        $filtered = [];
        foreach ($chars as $char => $value) {
            if ($match = $this->_check_count($char, $value, $depth, $preferred)) {
                $filtered[$match] = $char;
            }
        }

        // capture most probable delimiter
        ksort($filtered);
        $delimiter = reset($filtered);
        $this->delimiter = $delimiter;

        // parse data
        if ($parse) {
            $this->data = $this->parse_string();
        }

        return $delimiter;
    }

    // ==============================================
    // ----- [ Core Functions ] ---------------------
    // ==============================================

    /**
     * Read file to string and call parse_string().
     *
     * @param   file   local CSV file
     * @param null|mixed $file
     *
     * @return 2D array with CSV data, or false on failure
     */
    public function parse_file($file = null)
    {
        if ($file === null) {
            $file = $this->file;
        }
        if (empty($this->file_data)) {
            $this->load_data($file);
        }

        return (! empty($this->file_data)) ? $this->parse_string() : false;
    }

    /**
     * Parse CSV strings to arrays.
     *
     * @param   data   CSV string
     * @param null|mixed $data
     *
     * @return 2D array with CSV data, or false on failure
     */
    public function parse_string($data = null)
    {
        if (empty($data)) {
            if ($this->_check_data()) {
                $data = &$this->file_data;
            } else {
                return false;
            }
        }

        $rows = [];
        $row = [];
        $row_count = 0;
        $current = '';
        $head = (! empty($this->fields)) ? $this->fields : [];
        $col = 0;
        $enclosed = false;
        $was_enclosed = false;
        $strlen = strlen($data);

        // walk through each character
        for ($i = 0; $i < $strlen; $i++) {
            $ch = $data{$i};
            $nch = (isset($data{$i + 1})) ? $data{$i + 1} : false;
            $pch = (isset($data{$i - 1})) ? $data{$i - 1} : false;

            // open and closing quotes
            if ($ch == $this->enclosure && (! $enclosed || $nch != $this->enclosure)) {
                $enclosed = ($enclosed) ? false : true;
                if ($enclosed) {
                    $was_enclosed = true;
                }

            // inline quotes
            } elseif ($ch == $this->enclosure && $enclosed) {
                $current .= $ch;
                $i++;

            // end of field/row
            } elseif (($ch == $this->delimiter || ($ch == "\n" && $pch != "\r") || $ch == "\r") && ! $enclosed) {
                if (! $was_enclosed) {
                    $current = trim($current);
                }
                $key = (! empty($head[$col])) ? $head[$col] : $col;
                $row[$key] = $current;
                $current = '';
                $col++;

                // end of row
                if ($ch == "\n" || $ch == "\r") {
                    if ($this->_validate_offset($row_count) && $this->_validate_row_conditions($row, $this->conditions)) {
                        if ($this->heading && empty($head)) {
                            $head = $row;
                        } elseif (empty($this->fields) || (! empty($this->fields) && (($this->heading && $row_count > 0) || ! $this->heading))) {
                            if (! empty($this->sort_by) && ! empty($row[$this->sort_by])) {
                                if (isset($rows[$row[$this->sort_by]])) {
                                    $rows[$row[$this->sort_by].'_0'] = &$rows[$row[$this->sort_by]];
                                    unset($rows[$row[$this->sort_by]]);
                                    for ($sn = 1; isset($rows[$row[$this->sort_by].'_'.$sn]); $sn++) {
                                    }
                                    $rows[$row[$this->sort_by].'_'.$sn] = $row;
                                } else {
                                    $rows[$row[$this->sort_by]] = $row;
                                }
                            } else {
                                $rows[] = $row;
                            }
                        }
                    }
                    $row = [];
                    $col = 0;
                    $row_count++;
                    if ($this->sort_by === null && $this->limit !== null && count($rows) == $this->limit) {
                        $i = $strlen;
                    }
                }

            // append character to current field
            } else {
                $current .= $ch;
            }
        }
        $this->titles = $head;
        if (! empty($this->sort_by)) {
            ($this->sort_reverse) ? krsort($rows) : ksort($rows);
            if ($this->offset !== null || $this->limit !== null) {
                $rows = array_slice($rows, ($this->offset === null ? 0 : $this->offset), $this->limit, true);
            }
        }

        return $rows;
    }

    /**
     * Create CSV data from array.
     *
     * @param   data        2D array with data
     * @param   fields      field names
     * @param   append      if true, field names will not be output
     * @param   is_php      if a php die() call should be put on the first
     *                      line of the file, this is later ignored when read.
     * @param   delimiter   field delimiter to use
     * @param mixed      $data
     * @param mixed      $fields
     * @param mixed      $append
     * @param mixed      $is_php
     * @param null|mixed $delimiter
     *
     * @return CSV data (text string)
     */
    public function unparse($data = [], $fields = [], $append = false, $is_php = false, $delimiter = null)
    {
        if (! is_array($data) || empty($data)) {
            $data = &$this->data;
        }
        if (! is_array($fields) || empty($fields)) {
            $fields = &$this->titles;
        }
        if ($delimiter === null) {
            $delimiter = $this->delimiter;
        }

        $string = ($is_php) ? "<?php header('Status: 403'); die(' '); ?>".$this->linefeed : '';
        $entry = [];

        // create heading
        if ($this->heading && ! $append) {
            foreach ($fields as $key => $value) {
                $entry[] = $this->_enclose_value($value);
            }
            $string .= implode($delimiter, $entry).$this->linefeed;
            $entry = [];
        }

        // create data
        foreach ($data as $key => $row) {
            foreach ($row as $field => $value) {
                $entry[] = $this->_enclose_value($value);
            }
            $string .= implode($delimiter, $entry).$this->linefeed;
            $entry = [];
        }

        return $string;
    }

    /**
     * Load local file or string.
     *
     * @param   input   local CSV file
     * @param null|mixed $input
     *
     * @return true or false
     */
    public function load_data($input = null)
    {
        $data = null;
        $file = null;
        if ($input === null) {
            $file = $this->file;
        } elseif (file_exists($input)) {
            $file = $input;
        } else {
            $data = $input;
        }
        if (! empty($data) || $data = $this->_rfile($file)) {
            if ($this->file != $file) {
                $this->file = $file;
            }
            if (preg_match('/\.php$/i', $file) && preg_match('/<\?.*?\?>(.*)/ims', $data, $strip)) {
                $data = ltrim($strip[1]);
            }
            if ($this->convert_encoding) {
                $data = iconv($this->input_encoding, $this->output_encoding, $data);
            }
            if (substr($data, -1) != "\n") {
                $data .= "\n";
            }
            $this->file_data = &$data;

            return true;
        }

        return false;
    }

    // ==============================================
    // ----- [ Internal Functions ] -----------------
    // ==============================================

    /**
     * Validate a row against specified conditions.
     *
     * @param   row          array with values from a row
     * @param   conditions   specified conditions that the row must match
     * @param mixed      $row
     * @param null|mixed $conditions
     *
     * @return true of false
     */
    public function _validate_row_conditions($row = [], $conditions = null)
    {
        if (! empty($row)) {
            if (! empty($conditions)) {
                $conditions = (strpos($conditions, ' OR ') !== false) ? explode(' OR ', $conditions) : [$conditions];
                $or = '';
                foreach ($conditions as $key => $value) {
                    if (strpos($value, ' AND ') !== false) {
                        $value = explode(' AND ', $value);
                        $and = '';
                        foreach ($value as $k => $v) {
                            $and .= $this->_validate_row_condition($row, $v);
                        }
                        $or .= (strpos($and, '0') !== false) ? '0' : '1';
                    } else {
                        $or .= $this->_validate_row_condition($row, $value);
                    }
                }

                return (strpos($or, '1') !== false) ? true : false;
            }

            return true;
        }

        return false;
    }

    /**
     * Validate a row against a single condition.
     *
     * @param   row          array with values from a row
     * @param   condition   specified condition that the row must match
     * @param mixed $row
     * @param mixed $condition
     *
     * @return true of false
     */
    public function _validate_row_condition($row, $condition)
    {
        $operators = [
            '=', 'equals', 'is',
            '!=', 'is not',
            '<', 'is less than',
            '>', 'is greater than',
            '<=', 'is less than or equals',
            '>=', 'is greater than or equals',
            'contains',
            'does not contain',
        ];
        $operators_regex = [];
        foreach ($operators as $value) {
            $operators_regex[] = preg_quote($value, '/');
        }
        $operators_regex = implode('|', $operators_regex);
        if (preg_match('/^(.+) ('.$operators_regex.') (.+)$/i', trim($condition), $capture)) {
            $field = $capture[1];
            $op = $capture[2];
            $value = $capture[3];
            if (preg_match('/^([\'\"]{1})(.*)([\'\"]{1})$/i', $value, $capture)) {
                if ($capture[1] == $capture[3]) {
                    $value = $capture[2];
                    $value = str_replace('\\n', "\n", $value);
                    $value = str_replace('\\r', "\r", $value);
                    $value = str_replace('\\t', "\t", $value);
                    $value = stripslashes($value);
                }
            }
            if (array_key_exists($field, $row)) {
                if (($op == '=' || $op == 'equals' || $op == 'is') && $row[$field] == $value) {
                    return '1';
                } elseif (($op == '!=' || $op == 'is not') && $row[$field] != $value) {
                    return '1';
                } elseif (($op == '<' || $op == 'is less than') && $row[$field] < $value) {
                    return '1';
                } elseif (($op == '>' || $op == 'is greater than') && $row[$field] > $value) {
                    return '1';
                } elseif (($op == '<=' || $op == 'is less than or equals') && $row[$field] <= $value) {
                    return '1';
                } elseif (($op == '>=' || $op == 'is greater than or equals') && $row[$field] >= $value) {
                    return '1';
                } elseif ($op == 'contains' && preg_match('/'.preg_quote($value, '/').'/i', $row[$field])) {
                    return '1';
                } elseif ($op == 'does not contain' && ! preg_match('/'.preg_quote($value, '/').'/i', $row[$field])) {
                    return '1';
                } else {
                    return '0';
                }
            }
        }

        return '1';
    }

    /**
     * Validates if the row is within the offset or not if sorting is disabled.
     *
     * @param   current_row   the current row number being processed
     * @param mixed $current_row
     *
     * @return true of false
     */
    public function _validate_offset($current_row)
    {
        if ($this->sort_by === null && $this->offset !== null && $current_row < $this->offset) {
            return false;
        }

        return true;
    }

    /**
     * Enclose values if needed
     *  - only used by unparse().
     *
     * @param   value   string to process
     * @param null|mixed $value
     *
     * @return Processed value
     */
    public function _enclose_value($value = null)
    {
        if ($value !== null && $value != '') {
            $delimiter = preg_quote($this->delimiter, '/');
            $enclosure = preg_quote($this->enclosure, '/');
            if (preg_match('/'.$delimiter.'|'.$enclosure."|\n|\r/i", $value) || ($value{0} == ' ' || substr($value, -1) == ' ')) {
                $value = str_replace($this->enclosure, $this->enclosure.$this->enclosure, $value);
                $value = $this->enclosure.$value.$this->enclosure;
            }
        }

        return $value;
    }

    /**
     * Check file data.
     *
     * @param   file   local filename
     * @param null|mixed $file
     *
     * @return true or false
     */
    public function _check_data($file = null)
    {
        if (empty($this->file_data)) {
            if ($file === null) {
                $file = $this->file;
            }

            return $this->load_data($file);
        }

        return true;
    }

    /**
     * Check if passed info might be delimiter
     *  - only used by find_delimiter().
     *
     * @param mixed $char
     * @param mixed $array
     * @param mixed $depth
     * @param mixed $preferred
     *
     * @return special string used for delimiter selection, or false
     */
    public function _check_count($char, $array, $depth, $preferred)
    {
        if ($depth == count($array)) {
            $first = null;
            $equal = null;
            $almost = false;
            foreach ($array as $key => $value) {
                if ($first == null) {
                    $first = $value;
                } elseif ($value == $first && $equal !== false) {
                    $equal = true;
                } elseif ($value == $first + 1 && $equal !== false) {
                    $equal = true;
                    $almost = true;
                } else {
                    $equal = false;
                }
            }
            if ($equal) {
                $match = ($almost) ? 2 : 1;
                $pref = strpos($preferred, $char);
                $pref = ($pref !== false) ? str_pad($pref, 3, '0', STR_PAD_LEFT) : '999';

                return $pref.$match.'.'.(99999 - str_pad($first, 5, '0', STR_PAD_LEFT));
            } else {
                return false;
            }
        }
    }

    /**
     * Read local file.
     *
     * @param   file   local filename
     * @param null|mixed $file
     *
     * @return Data from file, or false on failure
     */
    public function _rfile($file = null)
    {
        if (is_readable($file)) {
            if (! ($fh = fopen($file, 'r'))) {
                return false;
            }
            $data = fread($fh, filesize($file));
            fclose($fh);

            return $data;
        }

        return false;
    }

    /**
     * Write to local file.
     *
     * @param   file     local filename
     * @param   string   data to write to file
     * @param   mode     fopen() mode
     * @param   lock     flock() mode
     * @param mixed $file
     * @param mixed $string
     * @param mixed $mode
     * @param mixed $lock
     *
     * @return true or false
     */
    public function _wfile($file, $string = '', $mode = 'wb', $lock = 2)
    {
        if ($fp = fopen($file, $mode)) {
            flock($fp, $lock);
            $re = fwrite($fp, $string);
            $re2 = fclose($fp);
            if ($re != false && $re2 != false) {
                return true;
            }
        }

        return false;
    }
}
