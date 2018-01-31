# Migratum: Templated Database Migrations

### Intro
Most of the PHP migration tools force you to write the migrations in PHP with classes and function, 
You are database agnostic then, but on the other hand you are usually limited to the lowest 
common denominator of what's supported by databases in features you can use.
With Migratum you write migrations directly in database specific DDL. 
On top of that you can pass some context into each migration.

Most people think that having the migrations in DB specific DDL doesn't allow you to change the database
but let's be honest here. Almost nobody changes the database after it has been decided on the platform.


### Features

* utilize any DDL & DML functionality your database provides
* pass parameters/context to your migrations
* create migrations for multiple database providers e.g MySQL and PostgreSQL
* migrate up and down
* migrate pending up migrations
* migrations in multiple directories and/or namespaces
* multiple environments 
* uses twig to 

### Supported Adapters
Migratum natively supports following database platforms:

* PostgreSQL

### Install & Run

1. Install Composer:

    ```
    curl -sS https://getcomposer.org/installer | php
    ```

1. Require Migratum as a dependency using Composer:

    ```
    php composer.phar require mvrhov/migratum
    ```

1. Execute Migratum:

    ```
    php composer.phar install
    ```

### Commands

All commands have a dry-run mode where you can se what's going to be run. If you also provide `-v` you can also see the queries that are going to be executed.

#### migratum:init
Initializes migratum, by creating default configuration file

#### migratum:create
Create empty migration in some namespace

`bin/migratum migratum:create "create table account"` creates an empty migration in default namespace `<current_timestamp>_create_table_account`

`bin/migratum migratum:create "create table foo" -s foo.1` creates an empty migration in namespace `foo` named `<current_timestamp>_create_table_foo`

#### migratum:migrate

`bin/migratum migratum:migrate` apply all pending migrations in order regardless the namespace they come from

`bin/migratum migratum:migrate -t <timestamp>` apply all pending migrations up to `<timestamp>` in order regardless the namespace they come from


#### migratum:pending
Sometimes when multiple people work on a project it happens that there are some migrations that other people have created
and those migrations sit in between yours.
When this happens you should review those migrations and if they don't conflict with yours, then you should run the pending 
command. If they do conflict, then unfortunately the only way to do proper migrations is to rollback back up to that 
migration, fix your migrations and apply them again.

#### migratum:rollback
Revert some of the applied migrations 

`bin/migratum migratum:rollback -b <count>` rollback applied migrations up to `<count>` migrations regardless of version

`bin/migratum migratum:rollback -t <timestamp>` rollback applied migrations up to `<timestamp>` migrations

#### migratum:status
The status command will report the current state of the database.

### Migration example

* timestamps.sql.twig
```jinja
{% macro timestamp_triggers(tableName) %}
DROP TRIGGER IF EXISTS set_updated_at_{{ tableName }} ON {{ tableName }};

CREATE TRIGGER set_updated_at_{{ tableName }}
BEFORE UPDATE ON {{ tableName }} FOR EACH ROW
EXECUTE PROCEDURE set_updated_at_column();

{% endmacro timestamp_triggers %}

{% macro drop_timestamp_triggers(tableName) %}
DROP TRIGGER IF EXISTS set_updated_at_{{ tableName }} ON {{ tableName }};
{% endmacro drop_timestamp_triggers %}
```

* 20180110851900_create_default_triggers.sql.twig
```jinja
{% block up %}
-- @Migratum\QueryBlockStart
CREATE OR REPLACE FUNCTION set_updated_at_column() RETURNS TRIGGER AS $$
BEGIN
   NEW.updated_at = now();
   RETURN NEW;
END;
$$ language 'plpgsql';
{% endblock up %}

{% block down %}
DROP FUNCTION set_updated_at_column;
{% endblock down %}
```

* 20180111115900_create_account.sql.twig
```jinja
{% block up %}
{% import 'timestamps.sql.twig' as t %}

{% set table = 'account' %}

CREATE TABLE {{ table }} (
    id_{{ table }} INTEGER GENERATED BY DEFAULT AS IDENTITY,
    name varchar(200) NOT NULL,
    email varchar(200) NOT NULL,
    enabled boolean NOT NULL,
    created_at timestamp DEFAULT now() NOT NULL,
    updated_at timestamp,

    PRIMARY KEY(id_{{ table }})
);

{{ t.timestamp_triggers(table) }}
{% endblock up %}

{% block down %}
{% set table = 'account' %}

{{ t.drop_timestamp_triggers(table) }}

DROP TABLE {{ table }};
{% endblock down %}
```

### Gotchas

If you look closely, the migrations above you can see that there is a specific SQL comment present in create triggers migration
This comment is needed because the Migratum breaks the migration per SQL statement based on `;` character.
This becomes a problem when one has stored procedures/triggers... that are basically a group of multiple SQL sentences 
or said stored procedure/trigger... utilizes the language where the ; is also end of statement. 
As there is too many different dialects of this you must mark the unbreakable parts with `-- @Migratum\QueryBlockStart`.
You can repeat the `-- @Migratum\QueryBlockStart` as may times as you need inside a migration and each of them  
starts new non breakable statement. 
If you want to exit non breakable statement mode you should add the following SQL comment `-- @Migratum\QueryBlockEnd`.

 
