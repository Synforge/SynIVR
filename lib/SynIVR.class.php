<?php

class SynIVR {
	
	private $_config = null;
	private $_agi = null;
	
	const CONFIG_PATH = "/etc/asterisk/synivr.conf";
	
	//Keeping state of the current user location
	private $_menu = 'default';
	private $_history = array();
	
	public function __construct($menu = 'default') {
		$this->_agi = new AGI();
		$this->_menu = $menu;
	}
	
	protected function _getConfig($menu = null) {
		if(is_null($this->_config)) {	
			//Load the config from the config path
			$config_file = file_get_contents(self::CONFIG_PATH);
		
			//JSON Decode the config file.
			$this->_config = json_decode($config_file, true);
			
			if($this->_config === null) {
				throw new Exception('Invalid JSON provided in configuration');
			}
		}
		
		if(!is_null($menu)) {
			return $this->_config[$menu];
		}
		
		return $this->_config;
	}
	
	protected function _inputEvent($digit) {
		$this->_agi->verbose("Input Recieved: {$digit}");
	
		//Check input events for the current context.
		$config = $this->_getConfig($this->_menu);
		$inputs = $config['input'];
		
		if(isset($inputs[$digit])) {		
			$actions = $inputs[$digit];
		} else {
			if(isset($inputs['invalid'])) {
				$actions = $inputs['invalid'];
			} else {
				//No Invalid Action - Default Action: Return to the start of the menu.
				return $this->_runActions(array(
					'action' => 'menu',
					'properties' => array(
						'name' => $this->_menu
					)
				));
			}
		}
		
		return $this->_runActions($actions);
	}
	
	protected function _runActions($actions){	
		if (!$actions) {
			$this->_agi->verbose("Error: No actions provided");
			return -1;
		}
		
		//Convert a single action into an array of one.
		$actions = (isset($actions['action'])) ? array($actions) : $actions;
		
		foreach($actions as $action) {
			switch($action['action']) {
				case 'menu':
					$this->_agi->verbose("Action: Menu recieved ({$action['properties']['name']})");
					$this->_menu = (isset($action['properties']['name'])) ? $action['properties']['name'] : $this->_menu;
					return true;
					break;
				case 'back':
					$this->_agi->verbose("Action: Back Recieved");
					if(count($this->_history) > 1) {
						$this->_agi->verbose(print_r($this->_history, true));
					
						//The last will always be the menu that the user is on, so take the second entry.
						$this->_menu = $this->_history[count($this->_history) - 2];
						//Remove the last two entries.
						$this->_history = array_slice($this->_history, 0, count($this->_history) - 2);
					}
					return true;
				case 'transfer':
					$this->_agi->verbose('Action: Transfer recieved.');
					$this->_agi->exec('TRANSFER SIP/'.$action['properties']['extension']);
					break;
				case 'hangup':
					$this->_agi->verbose('Action: Hangup recieved.');
					$this->_agi->hangup();
					break;
				case 'exit':
					$this->_agi->verbose('Action: Exit.');
					break;
				case 'prompt':
					$this->_agi->verbose('Action: Prompt.');
					for($i = 0; $i < $action['properties']['loop']; $i++) {
						$dtmf = $this->_agi->get_data(
							$action['properties']['source'], 
							(($delay = $action['properties']['delay']) > 0) ? $delay : 1, 
							(($length = $action['properties']['length']) > 0) ? $length : 1);
							
						if($dtmf['result'] > 0) {
							return $this->_inputEvent($dtmf['result']);
							break;
						} else {
							if($dtmf['data'] != 'timeout') {
								throw new Exception("Invalid return data from get_data, caller most likely hung up.");
							}
						}
					}
					break;
				default:
					$this->_agi->verbose('Unknown Action: Attempting to run as Asterisk Application');
					$this->_agi->exec($action['action'], $action['properties']);
			}
		}
		
		return false;
	}
		
	public function runMenu($menuid){
		if (!$menuid) {
			throw new Exception('No menu name provided');
		}
	
		$config = $this->_getConfig($menuid);

		if (!is_array($config)) {
			throw new Exception('Invalid menu name:' . $menuid);
		}
	
		$this->_menu = $menuid;
		
		$this->_agi->verbose("Added: {$this->_menu} to the history stack.");
		$this->_history[] = $this->_menu;
	
		$this->_agi->verbose("Loaded menu: {$menuid}");

		return $this->_runActions($config['enter']);
	}
		
	function run() {		
		try {
			//runMenu returns boolean indicating whether to continue. This way
			//menus aren't recursively called within each other.
			while($this->runMenu($this->_menu) == true);
		} catch(Exception $e) {
			$this->_agi->verbose('Error: ' . $e->getMessage());
		}
	}
	

}
?>
