FLightModel - Light Yii2 style active record for Yii
====================================================

  Active record gives very convenient way of DB communication. However, active
  record is not as fast as query builder. Due to some benchmarks query builder 
  3x faster than active record.
  
  `FLightModel` extends `CDbCommand` class and adds active record style methods.
  Those methods are highly inspired by Yii2's active record. Note that `FLightModel`
  is mainly intended for data retrieving, it does not have `save()` method.
 
  `FLightModel` provides the following methods to retrieve the query results:
 
  - `one($condition)`: returns a single record populated with the first row of data.
  - `all($condition)`: returns all records based on the query results.
  - `count($condition)`: returns the number of records.
  - `exists($condition)`: returns a value indicating whether the query result has
     data or not.
  - `with($methods)`: list of relations that this query should be performed with.
  - `indexBy($column)`: the name of the column by which the query result should
     be indexed.
  - `asArray()`: whether to return each record as an array.
  
  Usage: Let's say we have table `posts`
  
  ```php
  class Post extends FLightModel
  {
       public static function tableName()
       {
           return 'posts';
       }
       
       // if primary key is different than id, we should override this method
       public static function primaryKey()
       {
           return 'code';
       }
  }
  ```
  
  `FLightModel` instances are usually created by `FLightModel::find()` or
  `FLightModel::findAll()` or `FLightModel::findOne()`.
  
  ```php
  // fetch all rows as array of stdClass objects
  Post::findAll();
  
  // equivalent to above     
  Post::find()->all()
  
  // fetch all active posts
  Post::findAll('active' => 1);
  
  // equivalent to above    
  Post::find()->all('active' => 1);
  
  // equivalent to above  
  Post::find()->where('active=:value', array(':value'=>1))->all();
  
  // fetch all active posts with comments     
  Post::find()->with('comments')->all('active' => 1);      
  ```
  
  Class `Post` must have relation method comments
  
  ```php
  public function comments()
  {
       $this->comments = Comment::findAll(['post_id' => $this->pk]);
  }
  ```
  
  Relation method may have params
  
  ```php
  Post::find()->with(['comments' => [$a, $b]])->all('active' => 1);
  
  // also loads users
  Post::find()->with(['comments' => [$a, $b], 'users'])->all('active' => 1);
  
  // fetch result as array   
  Post::find()->with('comments')->asArray()->all('active' => 1);   
  ```
  
  By default, light model returns results as stdClass object or array of stdClass
  objects. Calling `asArray()` makes the result an array, but this does not apply
  to result of relation methods. In order to correct this, one must:
  
  ```php
  public function comments()
  {
       $this->comments = Comment::find()->
           setFetchMode($this->getFetchMode())->
           all(['post_id' => $this->pk]);
  }
  ```
  
  There is `afterFind()` method which enables us to call custom methods after
  finding the result. Its calling syntax is same as `with()`. Let's say our post
  table is multilingual and has `name_en`, `name_uz` fields. We want to have virtual
  field `name` indicating current language version of `name`.
  
  ```php
  Post::find()->with('comments')->with('user')->afterFind('langs')->all();
  ...
  public function langs()
  {
       $this->name = $this->{'name_' . Yii::app()->language};
  } 
  ```
  
  Some more examples:
  
  ```php
  // fetch one row whose id is 8
  Post::findOne(8);
  
  // same as above        
  Post::find()->one(8);
       
  Post::findOne(['active' => 1, 'code' => 8]);
  
  Post::exists(['user_id' => Yii::app()->user->id]);
  
  // fetch post whose id in 7 or 8 or 9
  Post::findAll(['code' => [7,8,9]]);  
  
  // number of all posts
  Post::count();
  
  // number of all inactive posts           
  Post::count('active' => 0);  
  ```
