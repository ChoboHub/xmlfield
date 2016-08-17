<?php

if (!defined('__IN_SYMPHONY__')) {
    die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
}

require_once(TOOLKIT . '/fields/field.textarea.php');

class FieldXML extends fieldTextarea
{
    public function __construct()
    {
        parent::__construct();
        $this->_name = 'XML';
        $this->_required = true;

        // Set defaults
        $this->set('show_column', 'no');
        $this->set('required', 'yes');
    }

    public function checkPostFieldData($data, &$message, $entry_id = null)
    {
        $message = null;

        if ($this->get('required') == 'yes' && strlen($data) == 0) {
            $message = __("'%s' is a required field.", array($this->get('label')));

            return self::__MISSING_FIELDS__;
        }

        if (empty($data)) {
            self::__OK__;
        }

        include_once(TOOLKIT . '/class.xsltprocess.php');
        $xsltProc = new XsltProcess;

        if (!General::validateXML($data, $errors, false, $xsltProc)) {

            $message = __('"%1$s" contains invalid XML. The following error was returned: <br/><code>%2$s</code>', array($this->get('label'), $errors[0]['message']));

            return self::__INVALID_FIELDS__;
        }

        return self::__OK__;

    }

    public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
    {
        $status = self::__OK__;

        return array(
            'value' => $data
        );
    }

    public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
    {
        Field::displaySettingsPanel($wrapper, $errors);

        // Textarea Size
        $label = Widget::Label(__('Number of default rows'));
        $input = Widget::Input('fields['.$this->get('sortorder').'][size]', (string)$this->get('size'));
        $label->appendChild($input);

        $div = new XMLElement('div');
        $div->appendChild($label);
        $wrapper->appendChild($div);

        // Requirements and table display
        $this->appendStatusFooter($wrapper);
    }

    public function createTable()
    {
        return Symphony::Database()->query(
            "CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
              `id` int(11) unsigned NOT null auto_increment,
              `entry_id` int(11) unsigned NOT null,
              `value` text,
              PRIMARY KEY  (`id`),
              KEY `entry_id` (`entry_id`),
              FULLTEXT KEY `value` (`value`)
            ) TYPE=MyISAM;"
        );
    }

    public function commit()
    {
        $this->set('formatter', 'none');
        if (!parent::commit()) {
            return false;
        }
        $id = $this->get('id');
        if ($id === false) {
            return false;
        }

        $fields = array();
        $fields['size'] = $this->get('size');

        return FieldManager::saveSettings($id, $fields);
    }

    public function fetchIncludableElements()
    {
        return array(
            $this->get('element_name')
        );
    }

    public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
    {
        $value = trim($data['value']);
        $wrapper->appendChild(
            new XMLElement(
                $this->get('element_name'),
                ($encode ? General::sanitize($value) : $value)
            )
        );
    }

    public function checkFields(array &$errors, $checkForDuplicates = true)
    {
        $required = array();
        if ($this->get('size') == '' || !is_numeric($this->get('size'))) {
            $required[] = 'size';
        }

        return parent::checkFields($required, $checkForDuplicates);
    }

    public function prepareTableValue($data, XMLElement $link = null, $entry_id = null)
    {
        $max_length = Symphony::Configuration()->get('cell_truncation_length', 'symphony');
        $max_length = ($max_length ? $max_length : 75);

        $value = $data['value'];

        if (function_exists('mb_substr')) {
            $value = (strlen($value) <= $max_length ? $value : mb_substr($value, 0, $max_length, 'utf-8') . '...');
        } else {
            $value = (strlen($value) <= $max_length ? $value : substr($value, 0, $max_length) . '...');
        }

        if (strlen($value) == 0) {
            $value = __('None');
        }

        if ($link) {
            $link->setValue(htmlspecialchars($value));

            return $link->generate();
        }

        return htmlspecialchars($value);
    }
}
