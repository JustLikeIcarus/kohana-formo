<?php defined('SYSPATH') or die('No direct script access.');

abstract class Formo_Validator_Core extends Formo_Container {

	protected $_errors = array
	(
		// Error message for this field/form
		'error'		=> FALSE,
		// Error messages for fields inside
		'errors'	=> array(),
	);
	
	protected $_validators = array
	(
		// Pre filters
		'filters'		=> array(),
		// Normal validation rules
		'rules'			=> array(),
		// Special triggers
		'triggers'		=> array(),
		// Filter prior to rendering
		'post_filters'	=> array(),
	);
	
	// Convenience method for setting and retrieving error
	public function error($message = NULL, $translate = FALSE, array $param_names = NULL)
	{
		if (func_num_args() > 0)
		{
			($translate AND $message = $this->make_message($message, $param_names));
			return $this->_errors['error'] = $message;
		}
		
		return $this->_errors['error'];
	}

	// Convenience method for fetching/setting errors
	public function errors(array $errors = NULL)
	{
		if (func_num_args() == 1 AND $errors !== NULL)
			return $this->_errors['errors'] = $errors;
			
		return $this->_errors['errors'];
	}

	// Add a validator item
	public function add_validator($type, $rule)
	{
		$type = Inflector::plural($rule->type);
		// Allow giving a rule an alias
		$next = count($this->_validators[$type]);
		
		// Resolve the context
		$this->make_context($rule);
								
		$this->_validators[$type][$next] = $rule;
		
		return $this;
	}

	// Retrieve a validator set
	public function get_validator($type)
	{
		return $this->_validators[$type] ? $this->_validators[$type] : array();
	}
	
	// Remove a validator item by alias
	public function remove_validator($type, $alias)
	{
		unset($this->_validators[$type][$alias]);
		
		return $this;
	}
	
	// Determine whether data was sent
	public function sent(array $input = NULL)
	{
		if ( ! Formo::notset($this->get('sent'), $sent))
			return $sent;
		
		$input = ($input !== NULL) ? $input : $this->get('input');
		foreach ($input as $alias => $value)
		{
			if ($this->find($alias) !== TRUE)
			{
				$this->set('sent', TRUE);
				return TRUE;
			}
		}

		return FALSE;
	}
		
	// Run validation
	public function validate($validate_if_not_sent = FALSE)
	{
		if (Kohana::$profiling === TRUE)
		{
			// Start a new benchmark
			$benchmark = Profiler::start('Formo', __FUNCTION__);
		}
		
		$this->driver->pre_validate();
			
		if ($validate_if_not_sent === FALSE AND ! $this->sent())
			return FALSE;

		if ($this->error() !== FALSE)
			return FALSE;
		
		// Run through each validator type in order
		foreach ($this->_validators as $name => $rules)
		{
			// Don't do post_filters right now
			if ($name == 'post_filters')
				continue;
							
			// Execute each of the rules
			foreach ($rules as $rule)
			{
				// Make the proper parameteres
				$this->pseudo_args($rule->args);

				if ($name === 'filters')
				{
					$this->val($rule->execute());
				}
				
				if ($name === 'rules')
				{
					// Run the rule
					if ($rule->execute() === FALSE)
					{
						// Set this error
						$this->error($rule->error, TRUE, $this->param_names($rule));
						// No need to continue if there was an error
						break;
					}
				}				
			}
		}
		
		// Validate through each of the field's fields
		foreach ($this->get('fields') as $field)
		{
			// Don't do anything if it's ignored
			if ($field->get('ignore') === TRUE)
				continue;
			
			// Validate everything else
			if ($field->validate($validate_if_not_sent) === FALSE)
			{
				if ($field instanceof Formo_Form)
				{
					// If no errors are attached to the subform, continue
					if ( ! $field_errors = $field->errors())
						continue;
					
					// Attach subform errors to the parent's errors
					$this->errors(Arr::merge($this->errors(), array($field->alias() => $field->errors())));					
				}
				else
				{
					// Attach field's error to its parent
					$this->errors(Arr::merge($this->errors(), array($field->alias() => $field->error())));
				}
			}
		}

		if (isset($benchmark))
		{
			// Stop benchmarking
			Profiler::stop($benchmark);
		}
		
		$this->driver->post_validate();
				
		// What to return depends on if it's a field or form object
		if ($this instanceof Formo_Form)
		{
			// If the form/subform has an error message, return FALSE
			if ($this->error() !== FALSE)	
				return FALSE;

			// Otherwise return whether the form/subform has no errorss
			return (bool) $this->errors() === FALSE;
		}
		else
		{
			// Return whether passed validation based on field's error
			return (bool) $this->error() === FALSE;
		}
	}
	
	protected function build_rule($type, $field, $rule = NULL, array $args = NULL)
	{
		if (is_array($field))
		{
			foreach ($field as $_field => $_rule)
			{
				$this->build_rule($type, $_field, $_rule);
			}
			
			return $this;
		}
		
		if (is_array($rule))
		{
			foreach ($rule as $_rule => $_args)
			{
				if ($_args instanceof Formo_Validator_Item)
				{
					$this->build_rule($type, $field, $_args);
				}
				else
				{
					$this->build_rule($type, $field, $_rule, $_args);
				}
			}
			
			return $this;
		}
		
		if ($rule instanceof Formo_Validator_Item === FALSE)
		{
			$rule = Formo::$type($rule, $args);
		}
		
		// The field the rule is attached to
		$field = ($field === NULL) ? $this : $this->find($field);
		// Attach the rule to a field
		$field->add_validator($type, $rule);
		
		return $this;
	}
		
	// Allow inputting multiple filters
	public function filters($field, $callback = NULL, array $args = NULL)
	{
		return $this->build_rule('filter', $field, $callback, $args);
	}
			
	// Attach any kind of rule to the specified field
	public function rules($field, $callback = NULL, array $params = NULL)
	{		
		return $this->build_rule('rule', $field, $callback, $params);
	}
					
	// Add a trigger to an item in the container object
	public function triggere($field, Formo_Trigger $trigger)
	{
		// Allow the context to be this field
		$context = $field !== NULL ? $this->find($field) : $this;
		// Set the context of the trigger
		$trigger->context($context);
		// Add the trigger to the bunch
		$context->add_validator('triggers', $trigger);
		
		return $this;
	}
	
	// Allow inputting multiple triggers
	public function triggers($trigger)
	{
		if (is_array($trigger))
		{
			foreach ($trigger as $_trigger)
			{
				$this->triggers($_trigger);
			}
			
			return $this;
		}
		
		$this->add_validator($trigger);
		
		return $this;
	}
	
	// Determine the proper context for the rule to run against
	protected function make_context(Formo_Validator_Item $rule)
	{
		if (is_array($rule->callback))
			return list($rule->context, $rule->callback) = $rule->callback;

		$regex = '/:([a-zA-Z_0-9]+)::/';
		
		// Pseudo context is present
		if (preg_match($regex, $rule->callback, $matches))
		{
			switch ($matches[1])
			{
				case 'field':
					$rule->context = $this;
					break;
				case 'parent':
					$rule->context = $this->parent();
					break;
				case 'form':
					$rule->context = $this->parent(Formo::PARENT);
					break;
				case 'model':
					$rule->context = $this->model();
					break;
			}
			
			// Set the callback to the second part of the rule
			return $rule->callback = preg_replace($regex, '', $rule->callback);			
		}
		
		if (preg_match('/::/', $rule->callback))
		{
			// Separate the context from the callback
			return list($context, $callback) = explode('::', $rule->callback);
			$rule->context = $context;
			$rule->callback = $callback;
			
			return;
		}
			
		if (function_exists($rule->callback))
			// Set context to NULL if it's a stand-alone function
			return $rule->context = NULL;
			
		if (method_exists($this, $rule->callback))
			// Allow simple declarations of field rules
			return $rule->context = $this;
			
		if (is_callable(array('Validate', $rule->callback)))
		{
			// Set the context to Validate
			$rule->context = 'Validate';

			// Check to see if backwards compatibility for validate is set
			if (Kohana::config('formo')->validate_compatible === TRUE)
			{
				$rule->args = (array) $rule->args;
				if ( ! in_array(':value', $rule->args))
				{
					array_unshift($rule->args, ':value');
				}
			}
			
			return;
		}				
	}
	
	public function param_names($rule)
	{
		// Make the array
		$array = array(':value' => $this->val());		
		
		$i = 0;
		foreach ($rule->args as $pretty_name => $arg)
		{
			if ($i === 0 AND Kohana::config('formo')->validate_compatible === TRUE
				AND ! (is_object($rule->context))
				AND $rule->context == 'Validate')
			{
				$i++;
				continue;
			}
			
			($i === 0 AND $i++);
				
			$next = ':param'.$i;
			if (is_string($pretty_name))
			{
				// If the key is a string, use it as a pretty name
				$array[$next] = $pretty_name;
				
				$i++;
				continue;
			}
			
			// Use a Container object's alias
			$array[$next] = ($arg instanceof Formo)
				? $arg->alias()
				: $arg;
				
			$i++;
		}
		
		return $array;
	}
	
	// Determine which message file to use
	protected function message_file()
	{
		return $this->get('message_file')
			? $this->get('message_file')
			: Kohana::config('formo')->message_file;
	}
	
	public function make_message($error_name, array $param_names = NULL)
	{
		$file = $this->message_file();
				
		if ($message = Kohana::message($file, $this->alias().'.'.$error_name))
		{
			// Found a message for this field and error
		}
		elseif ($message = Kohana::message($file, $this->alias().'.default'))
		{
			// Found a default message for this field
		}
		elseif ($message = Kohana::message($file, $error_name))
		{
			// Found a default message for this error
		}
		else
		{
			// No message exists, display the path expected
			$message = $file.'.'.$this->alias().'.'.$error_name;
		}
		
		$values = Arr::merge(array(':value' => $this->val(), ':field' => $this->alias()), (array) $param_names);
		
		$message = strtr($message, $values);
		
		if (Kohana::config('formo')->translate === TRUE)
		{
			$values[':field'] = __($values[':field']);
			$message = __($message, $values);
		}
		
		return $message;
	}
	
	/*	Built-in Validation Rules	*/
	
	// Replace pseudo_params
	public function pseudo_args( & $params)
	{
		$new_params = $params;

		if (($key = array_search(':field', $params)) !== FALSE)
		{
			$new_params[$key] = $this;
		}
		
		if (($key = array_search(':alias', $params)) !== FALSE)
		{
			$new_params[$key] = $this->alias();
		}
		
		if (($key = array_search(':parent', $params)) !== FALSE)
		{
			$new_params[$key] = $this->parent();
		}

		if (($key = array_search(':form', $params)) !== FALSE)
		{
			$new_params[$key] = $this->parent(Container::PARENT);
		}

		if (($key = array_search(':model', $params)) !== FALSE)
		{
			$new_params[$key] = ($this instanceof Formo)
				? $this->get('model')
				: $this->parent()->get('model');
		}
		
		if (($key = array_search(':value', $params)) !== FALSE)
		{
			$new_params[$key] = $this->val();
		}
		
		$params = empty($new_params) ? array($this->val()) : $new_params;
		
		return $params;
	}
	
	public function not_empty()
	{
		// The driver handles whether the field is empty
		return $this->driver->not_empty();
	}
			
	public function matches($match_against)
	{
		return $this->val() === $this->parent(Formo::PARENT)
			->find($match_against)
			->val();
	}

	/*	Built-in Triggers	*/
	public function validated()
	{
		return (bool) $this->error() === FALSE;
	}

}