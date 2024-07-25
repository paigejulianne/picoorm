# PicoORM

PicoORM: a very lightweight ORM for PHP 8.0+

by Paige Julianne Sullivan <paige@paigejulianne.com> 

https://paigejulianne.com

---

## Background

Back in 2010, I was building a hosted VOIP PBX that required a LOT of calls to the database.  I wanted to make
it as simple as I could without having to import a huge ORM (you know which ones I'm talking about).  I wrote this
code and have been making continual enhancements over the years to it.

---


## Contributing, Issues, and Support

I welcome your contributions to the source code.  Feel free to fork the repository and submit pull requests.
For support and issues, please use the GitHub issue tracker at https://github.com/paigejulianne/picoorm/issues

You may also find additional information on the GitHub discussions page at https://github.com/paigejulianne/picoorm/discussions

Finally, you can check my blog at https://paigejulianne.com/?s=picoorm for additional information and tutorials.

---

## Installation

### Via composer

Execute the following command in your CLI:

~~~
composer require paigejulianne/picoorm
~~~

### Via direct download

Download the ***PicoORM.php*** file into your source directory and **include** it.

~~~
include_once("PicoORM.php");
~~~

---

## Usage


### Setup the PDO Connection

~~~
$GLOBALS['_PICO_PDO'] = new PDO("mysql:host=hostname;dbname=database_name;","database_user","database_password");
~~~

### Inherit the PicoORM class

To begin working with tables, simply extend the **PicoORM** class, naming your class the same as the table name. For example, to work with a table called **users**, you would create a class called **Users** that extends **PicoORM**. 
(Note: if using MySQL or MariaDB, the class/table name is case-insensitive.  Other databases may be case-sensitive.)

***NOTE:  Since version 1.7-alpha, the namespace for this class is PicoORM\PicoORM***

~~~
use PicoORM;
class Users extends PicoORM {
    // your code here
}
~~~

PicoORM will assume that the table can be found in the database that you specified in the PDO connection.  If you need to specify a different database, 
you can do so by adding a prefix to the class name.  For example, if you have a table called **users** in a database called **mydatabase**, 
you would create a class called **mydatabase\Users** that extends **PicoORM**.  Example:

~~~
use PicoORM;
class mydatabase\Users extends PicoORM {
    // your code here
}
~~~

### Loading a record from the database

Simply call the constructor with the primary key value as the parameter.  For example, if your table has a primary key called **id**, you would call the constructor like this:

~~~
$user = new Users(1);
~~~

### Loading a record from the database using a different column as the primary key

If your table uses a different column as the primary key, you can specify that column name as the second parameter to the constructor.  For example, if your table has a primary key called **user_id**, you would call the constructor like this:

~~~
$user = new Users(1,"user_id");
~~~

### Loading a record from the database using a different column as the primary key and a different database

~~~
$user = new mydatabase\Users(1,"user_id");
~~~

### Creating a new record

To create a new record, simply call the constructor with no parameters.  For example:

~~~ 
$user = new Users();
~~~

### Saving a record

To save a record, simply call the **save()** method.  For example:

~~~
$user->save();
~~~

### Deleting a record

To delete a record, simply call the **delete()** method.  For example:

~~~
$user->delete();
~~~

### Setting a field value

To set a field value, simply set the property.  For example:

~~~
$user->first_name = "Paige";
~~~

The field will not be saved to the database until you call the **save()** method or the object is destroyed (goes out of scope or script ends).

### Getting a field value

To get a field value, simply get the property.  For example:

~~~
echo $user->first_name;
~~~


### Using custom SQL queries

To use custom SQL queries, simply call the **query()** method.  For example:

~~~
$user = new Users();
$user->query("SELECT * FROM __DB__ WHERE first_name = ? AND last_name = ?",["Paige","Sullivan"]);
~~~

Note:  the ***__ DB __*** placeholder will be replaced with the table name.  For example, if your table name is **users**, the query will be executed as:

~~~
SELECT * FROM users WHERE first_name = ? AND last_name = ?
~~~


### Using custom SQL queries with a different database

~~~
$user = new mydatabase\Users();
$user->query("SELECT * FROM __DB__ WHERE first_name = ? AND last_name = ?",["Paige","Sullivan"]);
~~~

