<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	class FieldXML extends Field {
		
		public function __construct(){
			parent::__construct();			
			$this->_name = 'XML';		
			$this->_required = true;

			// Set default
			$this->set('show_column', 'no');
			$this->set('required', 'yes');
		}
		
		function canFilter(){
			return true;
		}
		
		public function canImport(){
			return true;
		}
		
		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$label = Widget::Label($this->get('label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));
			
			$textarea = Widget::Textarea('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, (int)$this->get('size'), 50, (strlen($data['value']) != 0 ? General::sanitize($data['value']) : NULL));
			
			Symphony::ExtensionManager()->notifyMembers('ModifyTextareaFieldPublishWidget', '/backend/', array(
			    'field' => &$this, 
			    'label' => &$label, 
			    'textarea' => &$textarea
			));
			
			$label->appendChild($textarea);
			
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}
		
		function commit(){
			
			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			$fields['size'] = $this->get('size');
			
			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");		
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
					
		}	
					
		function checkPostFieldData($data, &$message, $entry_id=NULL){
			
			$message = NULL;
			
			if($this->get('required') == 'yes' && strlen($data) == 0){
				$message = __("'%s' is a required field.", array($this->get('label')));
				return self::__MISSING_FIELDS__;
			}	
			
			if(empty($data)) self::__OK__;
			
			include_once(TOOLKIT . '/class.xsltprocess.php');
			$xsltProc =& new XsltProcess;	
			
			if(!General::validateXML($data, $errors, false, $xsltProc)){
				$message = __('"%1$s" contains invalid XML. The following error was returned: <br/><code>%2$s</code>', array($this->get('label'), $errors[0]['message']));
				return self::__INVALID_FIELDS__;
			}
			
			return self::__OK__;
							
		}
		
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$status = self::__OK__;
			return array(
				'value' => $data
			);
		}
		
		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][size]', (string)$this->get('size'));
			$input->setAttribute('size', '3');
			$label->setValue(__('Make textarea %s rows tall', array($input->generate())));
			$wrapper->appendChild($label);

			$div =  new XMLElement('div', NULL, array('class' => 'compact'));
			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);				
		}
		
		function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` text,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  FULLTEXT KEY `value` (`value`)
				) TYPE=MyISAM;"
			
			);
		}
		
		public function buildDSRetrievalSQL($data, &$joins, &$where) {
			$field_id = $this->get('id');
			
			if (self::isFilterRegex($data[0])) {
				$this->_key++;
				$pattern = str_replace('regexp:', '', $this->cleanValue($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.value REGEXP '{$pattern}'
				";
				
			} else {
				if (is_array($data)) $data = $data[0];
				
				$data = $this->cleanValue($data);
				$this->_key++;
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND MATCH (t{$field_id}_{$this->_key}.value) AGAINST ('{$data}' IN BOOLEAN MODE)
				";
			}
			
			return true;
		}
		
		function appendFormattedElement(&$wrapper, $data, $encode=false){
			$value = trim($data['value']);
			$wrapper->appendChild(new XMLElement($this->get('element_name'), ($encode ? General::sanitize($value) : $value)));
		}

		function checkFields(&$required, $checkForDuplicates=true, $checkForParentSection=true){
			$required = array();		
			if($this->get('size') == '' || !is_numeric($this->get('size'))) $required[] = 'size';
			return parent::checkFields($required, $checkForDuplicates, $checkForParentSection);
		}

		function findDefaults(&$fields){
			if(!isset($fields['size'])) $fields['size'] = 15;				
		}

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Textarea('fields['.$this->get('element_name').']', $this->get('size'), 50));
			
			return $label;
		}
		
		public function prepareTableValue($data, XMLElement $link = null) {
			$max_length = Symphony::Configuration()->get('cell_truncation_length', 'symphony');
			$max_length = ($max_length ? $max_length : 75);

			//$value = strip_tags($data['value']);
			$value = $data['value'];
			
			if(function_exists('mb_substr')) {
				$value = (strlen($value) <= $max_length ? $value : mb_substr($value, 0, $max_length, 'utf-8') . '...');
			}
			else {
				$value = (strlen($value) <= $max_length ? $value : substr($value, 0, $max_length) . '...');
			}

			if (strlen($value) == 0) $value = __('None');

			if ($link) {
				$link->setValue(htmlspecialchars($value));
				return $link->generate();
			}

			return htmlspecialchars($value);
		}	
		
	}
