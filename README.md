# dbstructure
PHP script to generate the description of a Postgresql database

## How to use

* rename param.ini.dist to param.ini
* edit param.ini :
    * set the parameters to connect the database
    * define all schemas to parse, with a comma between each
* run the script :

```php dbstructure.php```

__Options:__

--help  display a help message

--export=filename: name of export file (default: dbstructure-YYYYMMDDHHmm.html

--format=tex|html|csv: export format (html by default)

--summary=y: display a list of all tables at the top of the html file

--csvtype=columns|tables: extract the list of columns or the list of tables (in conjunction with csv export)
