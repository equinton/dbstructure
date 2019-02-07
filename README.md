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

--export=filename set the name of the generated file (default: dbstructure-YYYYMMDDHHmm.html)

--format=tex generate a file in latex format (default: html)
