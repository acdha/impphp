DBObject
========

Since the concept of having multiple instances representing a
single table is unwise, this class uses a factory approach to
ensure that all future instantiations of a given (class, id)
will return the same instance

Basic Usage
-----------

Create a new object:

	$object = DerivedClass::get()
	
Retrieving an existing object:

	$object = DerivedClass::get($ID)
	
Creating an object without a database query by passing a data array:

	$object = DerivedClass::get(array $Properties)

Retrieving multiple objects using a single query:

	$objects = DerivedClass::get(array $IDs)

Retrieving objects matching some constraint:

	$objects = DerviedClass::find(array('property' => 'value'))

Notes
-----
* DB column names must match property names exactly
* All date/time fields must be stored as database date/time types but are
  processed as PHP time_t values. FROM_UNIXTIME() and UNIX_TIMESTAMP() will be
  automatically supplied.
* Properties is an array. The key is the property name; the value is either a
  string (shorthand for the property's type) or an array:

	type      => property type (integer, string, boolean, timestamp, datetime/date, set, enum or object)
	class     => name of a PHP class, only necessary if the class name is different than the property name
	formfield => boolean indicating whether this property corresponds directly to a form field
	required  => boolean indicating whether this is a required form field
	lazy			=> boolean indicating whether this should be loaded on demand (default: true)

* Subclasses must implement the get() and find() static functions because these cannot be inherited from this class until at least PHP 5.3. See DBObject-template.php.

Collections
-----------
* Common properties:
	* `class` (identical to a normal class value)
	* Constraints: arguments for the query's where constraint can be supplied
    in two forms: 

				"constraint"  => $string
				"constraints" => $array_of_strings
				
		In either case they are ANDed to the existing WHERE constraint. Currently
    bind variables cannot be used.
	* sorting: the result arrays will be called with uasort(). If provided
		`sort_function` will be passed to `uasort`; the default value is
		`array($class, 'defaultSortFunction')`
* Relationships
	* One-to-Many: the collection's members have a key which maps to this
		object's ID - e.g Document->Children where each child is itself a Document
		with a Parent column:

			Property  = array(
				'type'   => 'collection',
				'class'  => 'Document',
				'table'  => 'Documents', // Optional since we're going to use the DBObject constructor
				'our_column' => 'Parent'
			);

		is equivalent to:

			return Document::get($DB->queryValues('SELECT ID FROM Documents WHERE Parent = ?', $this->ID));

	* Many-to-Many: the collection's members are determined using a third map
		table which has columns for this object's ID and the member's ID (e.g.
    Order->Products with an OrderProducts table containing Order IDs and
    Product IDs)

			Property         = array(
				'type'          => 'collection',
				'class'         => 'Product',
				'table'         => 'OrderProducts',
				'our_column'    => 'Order',  // Optional if keyname = class name
				'member_column' => 'Product' // Optional if keyname = class name
			);

		is equivalent to:

			return Product::get($DB->queryValues('SELECT Product FROM OrderProducts WHERE Order = ?', $this->ID));