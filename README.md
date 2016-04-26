# User Interface Markup Language for PHP

> XML component layout to HTML

Simple and descriptive User Interface Markup Language to HTML written in PHP. Uses extended `SimpleXMLElement` class.

#### Features

1. Automatic `<tag>` to `class="tag"` conversion
   - tag name conversion modes `\UIML\Document::$tagJoiner`:
       - any string, eg. `'__'` BEM style, default is `'-'`
       - camel case - `'^'`
   - level of depth `\UIML\Document::$classJoiner` to use for class name
     generation, default is `3`
   - to skip some UIML tags, `\UIML\Document::$skipTags = ['some-tag-to-skip'];`,
     `'*'` skips all tags, disable automatic `<tag>` to `class="tag"` conversion
   - to change `tag-name` to `custom-name` use `<tag class="custom-name">`
   - to preserve original  class of `<tag class="original-class">`,
     set `\UIML\Document::$preserveTagClass = true;`, default is `false`
2. Tag `tag-attributes="value"` available as local `$tagAttr = 'value'` variables

## Example

### UIML view: `views/gallery.uiml`

Core structure of future HTML. UIML file is a regular PHP. You can use any PHP function, loops, conditionals as much as you wish.

```php
<gallery>
    <header>Very <em>nice</em> gallery</header>
    <slides>
        <image src="123.gif" alt="123" />
        <image src="456.gif" alt="456" />
        <image src="789.gif" alt="789" />
    </slides>
</gallery>
```
### Tags

Every tag is as well a regular PHP. You can use any PHP function, loops, conditionals as much as you wish.

*Note: `<body>` tags are skipped if not used as HTML body tag*

Gallery Tag Component: `tags/gallery.tag`

```php
<div>
    <yield/>
</div>
```

Header Tag Component: `tags/header.tag`

```php
<div>
    <h1><yield/></h1>
</div>
```

Slides Tag Component: `tags/slides.tag`

```php
<div>
    <yield/>
</div>
```

Image Tag Component: `tags/image.tag`

```php
<div>
    <img class="image-media" src="<?=$src?>" alt="<?=$alt?>" />
    <p class="image-caption"><?=$alt?></p>
</div>
```

Every tag has access to original UIML attributes and can use them within templates.

Also there is special variable `$__prefix` holds tags path as dash separated string.

It all combined together produces...

### Result:

```php

<div class="gallery">
    <div class="gallery__header">
        <h1>Very <em>nice</em> gallery</h1>
        </div>
    <div class="gallery__slides">
    <div class="gallery__slides__image" src="123.gif" alt="123">
        <img class="image-media" src="123.gif" alt="123">
        <p class="image-caption">123</p>
    </div>
    <div class="gallery__slides__image" src="456.gif" alt="456">
        <img class="image-media" src="456.gif" alt="456">
        <p class="image-caption">456</p>
    </div>
    <div class="gallery__slides__image" src="789.gif" alt="789">
        <img class="image-media" src="789.gif" alt="789">
        <p class="image-caption">789</p>
    </div>
  </div>
</div>
```

Example PHP:

```php
<?php

require_once 'vendor/autoload.php';

// Load UIML view
$view = \UIML\Document::loadUIML('views/gallery.uiml');

// Set new document: (view, path to tags, extension)
$document = new \UIML\Document($view, 'tags/', '*.tag');

// Print document as HTML
echo $document;
```

## Usage

Install using Composer

1. create `composer.json`

    ```json
    {
        "name": "yourproject",
        "repositories": [
            {
                "type": "vcs",
            	"url": "git@github.com:attitude/uiml-php.git"
        	}
        ],
        "require": {
            "attitude/uiml-php": "dev-master"
        }
    }
    ```
2. run `$ composer install`
