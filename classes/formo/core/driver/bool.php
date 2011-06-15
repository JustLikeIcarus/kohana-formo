<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Formo_Driver_Bool_Core class.
 * 
 * @package   Formo
 * @category  Drivers
 */
class Formo_Core_Driver_Bool extends Formo_Driver {

	protected $view = 'bool';
	public $empty_input = TRUE;
		
	public function checked()
	{
		// Check if field was sent. If so, the new value shoulda been posted
		if ($this->field->sent() AND Formo::is_set($this->field->get('new_value')) === FALSE)
			return FALSE;
						
		return $this->val() == TRUE;
	}
	
	public function get_val()
	{
		$new_value = $this->field->get('new_value');

		// If the form was sent but the field wasn't set, return FALSE
		if ($this->field->sent() AND Formo::is_set($new_value) === FALSE)
			return FALSE;
			
		// Otherwise return the value that's set
		return (Formo::is_set($new_value) === TRUE)
			? (bool) $new_value
			: (bool) $this->field->get('value');
	}
	
	public function not_empty()
	{
		// If it's checked, it is not empty
		return $this->checked() === TRUE;
	}
	
	// Make the field checked
	public function check()
	{
		// Set this value to 1
		$this->field->set('value', TRUE);
	}
	
	public function uncheck()
	{
		$this->field->set('value', 0);
	}
	
	public function html()
	{
		$this->decorator
			->set('tag', 'input')
			->attr('type', 'checkbox')
			->attr('name', $this->name())
			->attr('value', 1);
		
		$parent_value = $this->field->parent()->val();
		
		if ($this->field->checked())
		{
			$this->decorator->attr('checked', 'checked');
		}
	}

}