<?php
namespace Fakable;

use Faker\Factory as Faker;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

/**
 * Generates a fake model
 */
class Fakable
{
	/**
	 * The model to fake
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * The attributes to set on the fake models
	 *
	 * @var array
	 */
	protected $attributes = array();

	/**
	 * The pool of models
	 *
	 * @var integer
	 */
	protected $pool;

	/**
	 * Whether fake models created should be saved or not
	 *
	 * @var integer
	 */
	protected $save = false;

	/**
	 * Create a new Fakable instance
	 *
	 * @param Model   $model
	 * @param array   $attributes
	 * @param boolean $saved
	 */
	public function __construct(Model $model)
	{
		$this->faker = Faker::create();
		$this->model = clone $model;
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////////// OPTIONS ////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Save or not the generated models
	 *
	 * @param boolean $saved
	 */
	public function setSaved($saved)
	{
		$this->saved = $saved;
	}

	/**
	 * Set the attributes to overwrite on the fake model
	 *
	 * @param array $attributes
	 */
	public function setAttributes(array $attributes = array())
	{
		$this->attributes = $attributes;
	}

	////////////////////////////////////////////////////////////////////
	///////////////////////////////// POOL /////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Set the pool of models
	 *
	 * @param integer $min
	 * @param integer $max
	 *
	 * @return self
	 */
	public function setPool($min, $max = null)
	{
		$max = $max ?: $min + 5;
		$this->pool = $this->faker->randomNumber($min, $max);

		return $this;
	}

	/**
	 * Set the pool from the count of another model
	 *
	 * @param string  $model
	 * @param integer $power
	 *
	 * @return self
	 */
	public function setPoolFromModel($model, $power = 2)
	{
		$this->pool = $model::count() * $power;

		return $this;
	}

	////////////////////////////////////////////////////////////////////
	///////////////////////////// GENERATION ///////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Fake a single model instance
	 *
	 * @param boolean $save
	 *
	 * @return Model
	 */
	public function fakeModel()
	{
		// Get the fakable attributes
		$fakables = $this->model->getFakables();
		$instance = new static;

		// Generate dummy attributes
		$relations = array();
		$defaults  = array();
		foreach ($fakables as $attribute => $signature) {
			$signature = (array) $signature;
			$value = $this->callFromSignature($defaults, $attribute, $signature);

			if (method_exists($this->model, $attribute) and $signature[0] === 'randomModels') {
				$relations[$attribute] = ['sync', $value];
			}
		}

		// Fill attributes and save
		$attributes = array_merge($defaults, $attributes);
		$instance->fill($attributes);
		if ($this->saved) {
			$instance->save();
		}

		// Set relations
		foreach($relations as $name => $signature) {
			list ($method, $value) = $signature;
			$instance->$name()->$method($value);
		}

		return $instance;
	}

	/**
	 * Fake multiple model instances
	 *
	 * @param integer $min
	 * @param integer $max
	 *
	 * @return void
	 */
	public function fakeMultiple($min = null, $max = null)
	{
		if ($min) {
			$this->setPool($min, $max);
		}

		for ($i = 0; $i <= $this->pool; $i++) {
			$this->fakeModel();
		}
	}

	////////////////////////////////////////////////////////////////////
	//////////////////////////// RELATIONSHIPS /////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Get a random primary key of a model
	 *
	 * @param string $model
	 * @param array  $notIn
	 *
	 * @return integer
	 */
	protected function randomModel($model, array $notIn = array())
	{
		$model  = new $model;
		$models = $model::query();
		if ($notIn) {
			$models = $models->whereNotIn($model->getKeyName(), $notIn);
		}

		return $this->faker->randomElement($models->lists('id'));
	}

	/**
	 * Get a random polymorphic relation
	 *
	 * @param string|array $models The possible models
	 *
	 * @return array [string, type]
	 */
	public function randomPolymorphic($models)
	{
		$models = (array) $models;
		$model  = $this->faker->randomElement($models);

		return [$model, $this->randomModel($model)];
	}

	/**
	 * Return an array of random models IDs
	 *
	 * @param string $model
	 *
	 * @return array
	 */
	protected function randomModels($model, $min = 5, $max = null)
	{
		// Get a random number of elements
		$max       = $max ?: $min + 5;
		$available = $model::lists('id');
		$number    = $this->faker->randomNumber($min, $max);

		$entries = array();
		for ($i = 0; $i <= $number; $i++) {
			$entries[] = $this->faker->randomElement($available);
		}

		return $entries;
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////////// HELPERS ////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Transform a fakable array to a signature
	 *
	 * @param array  $attributes
	 * @param string $attribute
	 * @param array  $signature
	 *
	 * @return array
	 */
	protected function callFromSignature(array &$attributes, $attribute, $signature)
	{
		// Get the method signature
		if (is_array($signature)) {
			$method    = array_get($signature, 0);
			$arguments = (array) array_get($signature, 1, array());
		} else {
			$method    = $signature;
			$arguments = array();
		}

		// For 1:1, get model name
		$model     = $this->getModelFromAttributeName($attribute);
		$arguments = $this->getArgumentsFromMethod($method, $attribute, $arguments);

		// Get the source of the method
		$source = method_exists($this, $method) ? $this : $this->faker;
		$value  = call_user_func_array([$source, $method], $arguments);

		if ($method === 'randomPolymorphic') {
			list ($model, $key) = $value;
			$attributes[$attribute.'_type'] = $model;
			$attributes[$attribute.'_id']  = $key;
		} else {
			$attributes[$attribute] = $value;
		}

		return $value;
	}

	/**
	 * Get the model associated with an attribute
	 *
	 * @param string $attribute
	 *
	 * @return string
	 */
	protected function getModelFromAttributeName($attribute)
	{
		return ucfirst(str_replace('_id', '', $attribute));
	}

	/**
	 * Get the default arguments for a relation method
	 *
	 * @param string $method
	 * @param string $attribute
	 * @param array  $arguments
	 *
	 * @return array
	 */
	protected function getArgumentsFromMethod($method, $attribute, $arguments = array())
	{
		if (!empty($arguments)) {
			return $arguments;
		}

		// Compute default model arguments
		$model = $this->getModelFromAttributeName($attribute);
		if (Str::contains($attribute, '_id')) {
			$arguments = [$model];
		} elseif ($method === 'randomModels') {
			$arguments = [Str::singular($model)];
		}

		return $arguments;
	}
}