[![Build Status](https://travis-ci.org/dionsnoeijen/sexy-field-api.svg?branch=master)](https://travis-ci.org/dionsnoeijen/sexy-field-api)

# SexyFieldApi

This package adds endpoints for sections to SexyField.

## GET requests
Sexy-field-api is very powerful when it comes to retrieving the data that you need.
Out of the box you can get all the information of your entities, including parent and child entities.

Here are some examples of how you can build your GET requests.

### One recipe
```
/v1/section/info/{sectionHandle}/{id}
/v1/section/recipe/15?fields=id,title,description,recipeType,created,updated
```

### All recipes
```
/v1/section/{sectionHandle}
/v1/section/recipe?fields=id,title,description,recipeType,created,updated
```

### Recipe filtered by field value
```
/v1/section/fieldvalue/{sectionHandle}/{fieldHandle}
/v1/section/fieldvalue/recipe/recipeType?value=vegan&fields=id,title,description
```

### Recipe by slug
```
/v1/section/{sectionHandle}/slug/{slug}
/v1/section/recipe/slug/recipe-20180325?fields=title
```

### Recipe with ingredients (show fields of child entities as well)
The child entity is indicated by the name of the property in the parent entity.
So in this case the `recipe` entity has a property called `ingredients`
The fields `name`, `amount`, `unit` are properties of the ingredient.

```
/v1/section/recipe/3?fields=id,title,description,recipeType,created,updated,ingredients,name,amount,unit
```


## POST requests
For creating a new recipe, you would have to use a POST request to:

```
/v1/section/recipe
```

Content-Type: application/x-www-form-urlencoded

Form data:
```
form[title]: Black bean soup
form[description]: A delicious soup
form[recipeType]: vegan
```

## PUT, DELETE and OPTIONS requests
See [src/config/routing/api.yml](src/config/routing/api.yml)
