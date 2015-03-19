<?php
/**
 * This file is part of the Marvarid project.
 *
 * (c) Fazliddin Jo'raev
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

/**
 * Active record gives very convenient way of DB communication. However, active record is not as fast as query builder. Due to some benchmarks query builder 3x faster than active record.
 * 
 * `FLightModel` extends `CDbCommand` class and adds active record style methods. Those methods are highly inspired by Yii2's active record. Note that `FLightModel` is mainly intended for data retrieving, it does not have `save()` method.
 *
 * `FLightModel` provides the following methods to retrieve the query results:
 *
 * - `one($condition)`: returns a single record populated with the first row of data.
 * - `all($condition)`: returns all records based on the query results.
 * - `count($condition)`: returns the number of records.
 * - `exists($condition)`: returns a value indicating whether the query result has data or not.
 * - `with($methods)`: list of relations that this query should be performed with.
 * - `indexBy($column)`: the name of the column by which the query result should be indexed.
 * - `asArray()`: whether to return each record as an array.
 * 
 * Usage: Let's say we have table `posts`
 * 
 * ```php
 * class Post extends FLightModel
 * {
 *      public static function tableName()
 *      {
 *          return 'posts';
 *      }
 *      
 *      // if primary key is different than id, we should override this method
 *      public static function primaryKey()
 *      {
 *          return 'code';
 *      }
 * }
 * ```
 * `FLightModel` instances are usually created by `FLightModel::find()` or `FLightModel::findAll()` or `FLightModel::findOne()`.
 * 
 * ```php
 * Post::findAll();     // fetch all rows as array of stdClass objects
 * Post::find()->all()  // equivalent to above
 * Post::findAll('active' => 1);    // fetch all active posts
 * Post::find()->all('active' => 1);  // equivalent to above
 * Post::find()->where('active=:value', array(':value'=>1))->all();     // equivalent to above
 * Post::find()->with('comments')->all('active' => 1);      // fetch all active posts with comments
 * ```
 * Class `Post` must have relation method comments
 * ```php
 * public function comments()
 * {
 *      $this->comments = Comment::findAll(['post_id' => $this->pk]);
 * }
 * ```
 * Relation method may have params
 * ```php
 * Post::find()->with(['comments' => [$a, $b]])->all('active' => 1);
 * Post::find()->with(['comments' => [$a, $b], 'users'])->all('active' => 1);   // also loads users
 * Post::find()->with('comments')->asArray()->all('active' => 1);   // fetch result as array
 * ```
 * By default, light model returns results as stdClass object or array of stdClass objects. Calling `asArray()` makes the result an array, but this does not apply to result of relation methods. In order to correct this, one must:
 * ```php
 * public function comments()
 * {
 *      $this->comments = Comment::find()->
 *          setFetchMode($this->getFetchMode())->
 *          all(['post_id' => $this->pk]);
 * }
 * ```
 * There is `afterFind()` method which enables us to call custom methods after finding the result. Its calling syntax is same as `with()`. Let's say our post table is multilingual and has `name_en`, `name_uz` fields. We want to have virtual field `name` indicating current language version of `name`.
 * ```php
 * Post::find()->with('comments')->with('user')->afterFind('langs')->all();
 * ...
 * public function langs()
 * {
 *      $this->name = $this->{'name_' . Yii::app()->language};
 * } 
 * ```
 * Some more examples:
 * ```php
 * Post::findOne(8);        // fetch one row whose id is 8
 * Post::find()->one(8)     // same as above
 * Post::findOne(['active' => 1, 'code' => 8]);
 * Post::exists(['user_id' => Yii::app()->user->id]);
 * Post::findAll(['code' => [7,8,9]]);  // fetch post whose id in 7 or 8 or 9
 * Post::count();           // number of all posts
 * Post::count('active' => 0);  // number of all inactive posts
 * ```
 */
abstract class FLightModel extends CDbCommand
{
    /**
     * Holds table column values for certain row. Methods passed to with() and
     * afterFind() can access values as $this->field_name. After calling findAll()
     * or all(), this attribute holds only last row's values.
     * @var array/object
     */
    protected $fields = [];
    
    /**
     * Array of methods which return data from related models.
     * E.g: ['method1', 'method2' => [param1, param2]]
     * @var array
     */
    private $related = [];
    
    /**
     * Same as $related, but this holds methods which must be executed after 
     * query result is retrieved
     * @var array
     */
    private $afterFind = [];
    
    /**
     * Column name which is used for indexing array result of all()
     * @var string 
     */
    private $indexBy = null;
    
    /**
     * PDO fetch mode
     */
    private $fetchMode = PDO::FETCH_OBJ;

    public function __construct()
    {
        Yii::app()->db->setActive(true);
		parent::__construct(Yii::app()->db, null);
    }
    
    /**
     * Table name in db. Child class must implement this method.
     */
	public static function tableName()
    {    
    }
    
    /**
     * Returns name of table's primary key
     * @return string
     */
    public static function primaryKey()
    {
        return 'id';
    }
    
    /**
     * Returns primary key values
     */
    public function getPk()
    {
        return $this->{static::primaryKey()};
    }
    
    /**
     * Works with fields
     */
    public function __get($name)
	{
		if(is_array($this->fields) && isset($this->fields[$name]))
			return $this->fields[$name];
        elseif(is_object($this->fields) && isset($this->fields->{$name}))
            return $this->fields->{$name};
        else
			return parent::__get($name);
	}
    
    /**
     * Works with fields
     */
	public function __isset($name)
	{
        if(is_array($this->fields) && isset($this->fields[$name]))
			return true;
        elseif(is_object($this->fields) && isset($this->fields->{$name}))
            return true;
        else
			return parent::__isset($name);
	}
    
    /**
     * Works with fields
     */
	public function __set($name,$value)
	{
		if(is_array($this->fields))
			$this->fields[$name] = $value;
        elseif(is_object($this->fields))
            $this->fields->{$name} = $value;
        else
			return parent::__set($name);
	}
    
    public function getFetchMode()
    {
        return $this->fetchMode;
    }

    public function setFetchMode($mode)
    {
        $this->fetchMode = $mode;
        
    return $this;
    }
    

    /**
     * Creates class instance.
     * @return FLightModel
     */
    public static function find()
    {
        $model = get_called_class();
        $model = new $model(null);
        return $model->from(static::tableName() . ' t');
    }
    
    /**
     * Sets owner model methods that return related data. Usage:
     *      Model::find()->with('method')->...
     * You can pass params to method:
     *      Model::find()->with(['method' => [$a, $b])->...
     * You can set more than one method:
     *      Model::find()->with(['method1', 'method2'])->...
     * You can use this method repeatedly:
     *      Model::find()->with('method1')->with('method2')->...
     * @param mixed $methods
     * @return FLightModel
     */
    public function with($methods)
    {
        if(is_string($methods))
            $methods = [$methods];
        $this->related = array_merge($this->related, $methods);
    
    return $this;
    }
    
    /**
     * Same as with(), but afterFind sets methods for execution after query
     * result is retrieved.
     * @param mixed $methods
     * @return FLightModel
     */
    public function afterFind($methods)
    {
        if(is_string($methods))
            $methods = [$methods];
        $this->afterFind = array_merge($this->afterFind, $methods);
    
    return $this;
    }
    
    /**
     * Whether to return the result as an array. By default result is returned as
     * stdClass object.
     * @return FLightModel
     */
    public function asArray()
    {
        $this->setFetchMode(PDO::FETCH_ASSOC);
    
    return $this;
    }
    
    /**
     * Sets $indexBy attribute
     * @param string $column
     * @return \FLightModel
     */
    public function indexBy($column)
    {
        $this->indexBy = $column;
        
    return $this;
    }
    
    /**
     * Same as one(), except that this is called statically and $condition param
     * is mandatory
     */
    public static function findOne($condition)
    {
        return static::find()->one($condition);
    }
    
    /**
     * Same as all(), except that this is called statically
     */
    public static function findAll($condition = null)
    {
        return static::find()->all($condition);
    }

    /**
     * Same as queryAll(), but all() also joins related data and applies afterFind
     * methods to the result.
     * Fetch all rows from the table:
     *      Model::find()->all();
     * Fetch all rows which meet some condition:
     *      Model::find()->all(['field' => $value]);
     * Above is equivalent to:
     *      Model::find()->where('field=:value', [':value' = > $value])->all();
     * If condition array has more than one element, then they are joined by AND.
     * You can make IN conditions as following:
     *      Model::find()->all(['field' => [$val1, $val2]]);
     * @param array $condition
     * @return type
     */
    public function all($condition = null)
	{
        parent::setFetchMode($this->fetchMode);
        $this->addCondition($condition, false);
        $result = $this->queryAll();
        
        if(!empty($result) && 
            (!empty($this->related) || 
                !empty($this->afterFind) || 
                    !empty($this->indexBy)))
        {
            foreach ($result as $i => $row)
            {
                $this->fields = $row;
                $this->applyMethods('related');
                $this->applyMethods('afterFind');
                if(!empty($this->indexBy))
                {
                    $result[$this->{$this->indexBy}] = $this->fields;
                    unset($result[$i]);
                }
                else
                    $result[$i] = $this->fields;
            }
        }
        
    return $result;
	}

    /**
     * Same as all(), but calls queryOne(). Another difference is that if $condition
     * is not array, it is treated as primary key value.
     * Finds the row, pk of which is equal to 5;
     *      Model::find()->...->one(5);
     * @param string/array $condition
     * @return array/object
     */
    public function one($condition = null)
	{
        parent::setFetchMode($this->fetchMode);
        $this->addCondition($condition, true);
		$this->fields = $this->queryRow();
        
        if(!empty($this->fields) && (!empty($this->related) || !empty($this->afterFind)))
        {
            $this->applyMethods('related');
            $this->applyMethods('afterFind');
        }
        
    return $this->fields;
	}
    
    /**
     * Returns the number of records.
     */
    public function count($condition = null)
    {
        $this->addCondition($condition, false);
        return $this->select("COUNT(*)")->queryScalar();
    }
    
    /**
     * Returns a value indicating whether the query result contains any row of data.
     */
    public function exists($condition = null)
    {
        $this->addCondition($condition, false);
        return $this->select(static::primaryKey())->queryScalar() !== false;
    }

    /**
     * Dynamically calls
     * @param string $methodAttribute
     */
    protected function applyMethods($methodAttribute)
    {
        if(empty($this->{$methodAttribute}))
            return;
        
        foreach ($this->{$methodAttribute} as $key => $value)
        {
            if(is_string($key))
                call_user_func_array([$this, $key], $value);
            else
                call_user_func([$this, $value]);
        }
    }
    
    /**
     * Adds easily styled key/value condition as andWhere(). This methos is
     * internally used by all() and one().
     * @param mixed $condition
     * @param boolean $one
     */
    protected function addCondition($condition, $one)
    {
        // query by primary key
        if($one && !empty($condition) && !is_array($condition))
        {
            $this->andWhere(static::primaryKey() . '=:id', [':id'=>$condition]);
        }
        elseif(!empty($condition))
        {
            foreach($condition as $key => $value)
            {
                if(is_array($value))
                    $this->andWhere(['in', $key, $value]);
                else
                    $this->andWhere($key . "=:value$key", [":value$key" => $value]);
            }
        }
    }
}
