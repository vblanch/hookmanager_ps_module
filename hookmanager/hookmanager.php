<?php

/*  Copyright 2014  Victor Blanch  (email : victor@vblanch.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Thanks to Prestashop Forum user eleazar for the corrected German translations */

if (!defined('_PS_VERSION_'))
  exit;
  
class HookManager extends Module
{
	public function __construct()
		{
		$this->name = 'hookmanager';
		$this->tab = 'content_management';
		$this->version = '1.0.4';
		$this->author = 'Victor Blanch';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.4.0.0', 'max' => '1.6.9.9');

		parent::__construct();

		$this->displayName = $this->l('Hook Manager');
		$this->description = $this->l('Manage Prestashop hooks easily.');
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
		
		/* Backward compatibility */
		if (_PS_VERSION_ < '1.5')
			   require(_PS_MODULE_DIR_.$this->name.'/backward_compatibility/backward.php');		
	}
  
	public function install()
	{
		//context feature only works in ps 1.5 or higher
		if (_PS_VERSION_ >= '1.5')
			if (Shop::isFeatureActive())
				Shop::setContext(Shop::CONTEXT_ALL);

		return parent::install();
	}
	
	public function uninstall()
	{
		if (!parent::uninstall())
			return false;
		return true;
	}	
	
	//show configure button in backend
	public function getContent()
	{
		
		$output = null;
		$output .= '<link type="text/css" rel="stylesheet" href="'.__PS_BASE_URI__.'modules/hookmanager/css/styles.css" />';
		
		//1. Hook update
		
		if (Tools::isSubmit('submitHookUpdate')){
			$name_hook = Tools::getValue('name_hook');
			if ($name_hook=="0"){
				$output .= $this->displayError( $this->l('Invalid hook to update') );
			}else{
				$position = Tools::getValue('position');
				if($position==null) $pos_val = 0; else $pos_val = 1;				
				Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'hook SET position = "'.$pos_val.'" WHERE name = "'.$name_hook.'"'); 	
				$output .= '<div class="conf confirm">'.$this->l('Hook updated successfully').'</div>';			
			}
		}
		
		//2. Hook creation

		if (Tools::isSubmit('submitNewHook')){
			if (!($hook_names = Tools::getValue('hook_names')) || empty($hook_names))
				$output .= '<div class="alert error">'.$this->l('Please complete the \'Hook names\' field').'</div>';
			else{
				//get title and desc
				$hook_title = Tools::getValue('hook_title');
				$hook_desc = Tools::getValue('hook_desc');
				//parse hook names, register them			
				$hook_names_list = explode(',',$hook_names);
				foreach($hook_names_list as $hname){
					$hname = trim($hname);
					if (_PS_VERSION_ < '1.5')
						$registry = $this->registerHookCustom($hname);	//for PS 1.4 or older
					else
						$registry = $this->registerHook($hname);
					if(!$registry){
						$output .= '<div class="alert error">'.$this->l('Hook could not be created').'</div>';
						$output .= '<div class="alert error">'.$registry.'</div>';
						$output .= '<div class="alert error">'.$hname.'</div>';
					}else{				
						//1. update show in positions
						$pos_val = Tools::getValue('show_in_positions');
						if($pos_val==null) $pos_val = 0; 		
						Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'hook SET position = "'.$pos_val.'", title = "'.$hook_title.'", description = "'.$hook_desc.'" WHERE name = "'.$hname.'"'); 	
						
						//2. remove from hook-module (keep the hook clean!)						
						// Get module id 
						$sql = 'SELECT id_module
							FROM `'._DB_PREFIX_.'module`
							WHERE `name` = "'.$this->name.'"';
							
						//delete this module attachment to the hook (so it's empty!)
						if ($id_module = Db::getInstance()->getValue($sql)){
							Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'hook_module` WHERE `id_module` = "'.$id_module.'"');						
						}
						 
						$output .= '<div class="conf confirm">'.$this->l('Hook created successfully').'</div>';	
					}
				}
			}
		} 
		
		//3. Hook deletion
		if (Tools::isSubmit('submitHookDelete')){
			$name_hook = Tools::getValue('name_hook_delete');
			if ($name_hook=="0"){
				$output .= $this->displayError( $this->l('Invalid hook to delete') );
			}else{			
				//get the hook ID
				//Get hook id 
				$sql = 'SELECT id_hook
					FROM `'._DB_PREFIX_.'hook`
					WHERE `name` = "'.$name_hook.'"';
				if ($id_hook = Db::getInstance()->getValue($sql)){
					//delete all module attachments
					Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'hook_module` WHERE `id_hook` = "'.$id_hook.'"');										
					//delete hook
					Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'hook WHERE name = "'.$name_hook.'"'); 	
					$output .= '<div class="conf confirm">'.$this->l('Hook deleted successfully').'</div>';	
				}else{	
					$output .= $this->displayError( $this->l('Invalid hook to delete') );
				}						
			}
		}
		
		return $output.$this->displayForm();
	}	
	
	//shows and displays form
	public function displayForm()
	{		
		$hooks = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT name, position FROM '._DB_PREFIX_.'hook ORDER BY name');
		
		//1. hook update
		
		if (!count($hooks)){
			$html .= $this->displayError($this->l('No hooks available.'));
		}else{		
			foreach ($hooks as $hook)
				$html .= '<div style="display:none" id="position_'.$hook['name'].'">'.$hook['position'].'</div>';
				
			$html .= '
			<script>
				function setCheckbox(sel, check){
					var nodesel = document.getElementById( sel );
					var nodecheck = document.getElementById( check );
					if(nodesel.selectedIndex>0){
						var name = nodesel.options[nodesel.selectedIndex].value;
						var pos = document.getElementById("position_"+name).innerHTML;
						if(pos=="1"){
							nodecheck.checked = true;
						}
						else{ 
							nodecheck.checked = false; 
						}
					}else{
						nodecheck.checked = false; 
					}
				} 
			</script>			
			<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
				<fieldset>
					<legend>'.$this->l('Configure').'</legend>
					<label>'.$this->l('Hook').':</label>
					<div class="margin-form">
						<select id="select_hook" name="name_hook" onchange="setCheckbox(\'select_hook\', \'show_position\');" onkeyup="setCheckbox(\'select_hook\', \'show_position\');">
							<option value="0">('.$this->l('Select a hook').')</option>';
			
			foreach ($hooks as $hook)				
				$html .= '					
							<option value="'.$hook['name'].'">'.$hook['name'].'</option>';
			
				$html .= '</select>						
						<input type="checkbox" name="position" value="Position" id="show_position">Show in Positions<br>
						<p class="clear">'.$this->l('Choose the hook you want to update, set it\'s visibility in Positions and press the \'Save\' button.').'</p>						
					</div>
					<p class="center"><input class="button" type="submit" name="submitHookUpdate" value="'.$this->l('Save').'" /></p>
				</fieldset>
			</form>
			';
		}
		
		/* 2. hook creation */
		$html .= '
		<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
		<fieldset><legend>'.$this->l('New hook').'</legend>
			<label>'.$this->l('Hook name(s):').'</label>
				<div class="margin-form">
					<input type="text" name="hook_names" value="" />
					<p class="clear">'.$this->l('Enter the name or names of the hooks to create, separated by commas.').'</p>
				</div>				
				<div class="margin-form">
					<input type="text" name="hook_title" value="" />
					<p class="clear">'.$this->l('Enter the title for the hook(s) to create, or leave it blank.').'</p>
				</div>						
				<div class="margin-form">
					<input type="text" name="hook_desc" value="" />
					<p class="clear">'.$this->l('Enter the description for the hook(s) to create, or leave it blank.').'</p>
				</div>				
				<label>'.$this->l('Show in Positions:').'</label>
				<div class="margin-form">
					<input type="radio" name="show_in_positions" id="show_on" value="1" '.'checked="checked"'.'/>
					<label class="t" for="show_on"> <img src="../img/admin/enabled.gif" alt="'.$this->l('Enabled').'" title="'.$this->l('Enabled').'" /></label>
					<input type="radio" name="show_in_positions" id="show_off" value="0"/>
					<label class="t" for="show_off"> <img src="../img/admin/disabled.gif" alt="'.$this->l('Disabled').'" title="'.$this->l('Disabled').'" /></label>
					<p class="clear">'.$this->l('Show the hook in Positions to enable module attachment.').'</p>
				</div>
				<center><input type="submit" name="submitNewHook" value="'.$this->l('Save').'" class="button" /></center>
			</fieldset>
		</form>';		
		/* end of second part */
		
		/* 3.Hook deletion */
		
		if (!count($hooks)){
			$html .= $this->displayError($this->l('No hooks available.'));
		}else{		
			foreach ($hooks as $hook)
				$html .= '<div style="display:none" id="position_'.$hook['name'].'">'.$hook['position'].'</div>';
				
			$html .= '
			<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
				<fieldset>
					<legend>'.$this->l('Delete').'</legend>
					<label>'.$this->l('Hook').':</label>
					<div class="margin-form">
						<select id="select_hook" name="name_hook_delete">
							<option value="0">('.$this->l('Select a hook').')</option>';
			
			foreach ($hooks as $hook)				
				$html .= '					
							<option value="'.$hook['name'].'">'.$hook['name'].'</option>';			
				$html .= '</select>
					<p class="clear">'.$this->l('Choose the hook you want to delete and press the \'Delete\' button.').'</p>
					</div>
					<p class="center"><input class="button" type="submit" name="submitHookDelete" value="'.$this->l('Delete').'" onClick="return confirm(\''.$this->l('Are you sure you want to delete this hook?').'\');" /></p>
				</fieldset> 
			</form>
			';
		}
		
		$html .= '	
			<fieldset class="fieldset-donate">
				<legend>'.$this->l('Donate').'</legend>
				<div>'.$this->l('If you like this module, please consider making a donation to support the author using Paypal, Bitcoin, Litecoin or Dogecoin').':</div> 
				<div class="margin-form">	</div>	
					<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
					<input type="hidden" name="cmd" value="_donations">
					<input type="hidden" name="business" value="victor@vblanch.com">
					<input type="hidden" name="lc" value="US">
					<input type="hidden" name="item_name" value="Hookmanager PS Module">
					<input type="hidden" name="no_note" value="0">
					<input type="hidden" name="currency_code" value="EUR">
					<input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHostedGuest">
					<input type="image" src="https://www.paypalobjects.com/es_XC/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal, la forma más segura y rápida de pagar en línea.">
					<img alt="" border="0" src="https://www.paypalobjects.com/es_XC/i/scr/pixel.gif" width="1" height="1">
					</form>
					<div class="margin-form">	</div>	
					<div class="pay-method">
						Bitcoin : 12NZncFaCSv5xE8GCVDFBMaCAoLDDmqhL4
					</div>					
					<div class="margin-form">	</div>	
					<div class="pay-method">
						Litecoin: LYqdGQ9Eu2XCva6kSHWJ3uSTxBivFyfDNM
					</div>					
					<div class="margin-form">	</div>	
					<div class="pay-method">
						Dogecoin: DShCGaE7c9Ur9N29kd7Wfs7xqTCQTKoLAg
					</div>														
			</fieldset>
		';
		
		return $html;		
	}
	
	//function to register a hook in prestashop 1.4.x and older
	public function registerHookCustom($hook_name)
	{
		$hook_title = $hook_name; //TODO - allow custom hook name and description and setup a liveedit option
		
		//find max hook id
		$max_result = Db::getInstance()->getRow('
			SELECT MAX(`id_hook`) AS max
			FROM `'._DB_PREFIX_.'hook`');
		
		if (!$max_result)
			return false; 
		
		//calculate hook id
		$hook_id = (int)($max_result['max'])+1;
		
		//register module in hook table
		$return = Db::getInstance()->Execute('
			INSERT INTO `'._DB_PREFIX_.'hook` (`id_hook`, `name`, `title`, `description`, `position`, `live_edit`) 
			VALUES ('.$hook_id.',
			\''.$hook_name.'\', 
			\''.$hook_title.'\', NULL, 0, 0)');			
		
		return $return;
	}
}
?>