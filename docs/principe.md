# Principe

This package was created to quickly prototype API without having to loss what was done or change the fundamantal libraries if the project go bigger.

For the quick prototyping, we are automatizing the resources and routes registration based on Eloquent models. Just create your model and that it.

To not have to change everything:
- We are using Spatie packages that are good for production use, so it can be easy to just switch to them later one.
- We are allowing to "slowly" go out of this package, you can for example disable the auto routing, or auto resources registration, etc.


## How you do that?

### Automatic resources registration

This package will find all Eloquent models present in /app/Models and register them as "resources".

Resource name will use plural kebal case for Model name. Namespace will be keep as directory separator.

Example

- app/Models/User.php
- app/Models/Businesses/Business.php
- app/Models/Businesses/BusinessHour.php

Will create 3 resources.

- users
- businesses/businesses
- busineeses/business-hours

### Automatic routes creation

It will then create CRUD routes for each resources, using the same name and just prefixing it with `api`.

- [GET] api/businesses/businesses
- [GET] api/businesses/businesses/{id}
- [POST] api/businesses/businesses
- [PATCH] api/businesses/businesses/{id}
- [DELETE] api/businesses/businesses/{id}

And that it, with that you can start using 

#### Browse route

When the browse (and read) route is used, it will use a `DefaultQueryBuilder` that add every database fields as `fields`, `sort` and `filter`.
Meaning you can by default filter, sort and limit fields on everything.

**Specific behaviour**

- `Model::$hidden` will not be used

## More Usage

You want to register custom resource? Or remove a specific route?

See [usage.md](usage.md)

#### Add / Update routes


**Specific behaviour**

- It respects `Model::$fillable` and `Model::$guarded`
