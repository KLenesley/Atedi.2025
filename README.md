# ATEDI

> **A**ssistance **T**echnique **E**t **D**épannage **I**nformatique (ATEDI)



## Download

Open your CMD and copy this line : 
```
git clone https://github.com/ndlaprovidence/Atedi.git
```




## Install

When download is complete, open your CMD 
and execute these commands :
```
cd atedi
composer install
```

> Composer is free to download at this link : https://getcomposer.org/download/





## Create database


Start a DBMS like MariaDB via Ddev or WAMP



### Option 1 - Ddev
 
```sh
ddev config
```

> Ddev is free to download at this link : https://ddev.com/




### Option 2 - WAMP 

MariaDB is included into WAMP server

Then copy .env file to .env.local 
and update .env.local tu configure your database :

```sh
DATABASE_URL=mysql://db_user:db_password@127.0.0.1:3306/atedi
```
Replace db_user with : **root**

Replace db_password with : (let it blank)


Finaly, execute this line :

```sh
php bin/console doctrine:database:create
```

> WAMP is free to download at this link : http://www.wampserver.com/







## Load database

There are some default datau that you can load into the database. 

Open your CMD in the Atedi directory and copy these lines :

```
php bin/console doctrine:migrations:migrate
```

```
php bin/console doctrine:fixtures:load
```




## Run

Once you've installed everything, execute this line in the atedi directory :

```sh
ddev start
```

or

```sh
php -S localhost:8000 -t public
```

or

```sh
symfony server:start
```

You can access to your local server with your browser at : 
- `http://localhost:8000` 
  
or

- `http://atedi.2024.ddev.site/login` .






## Sign in

Next, you will have to connect with the default admin account.

* Email : admin@gmail.com

* Password : admin
