# User Interface Markup Language for PHP

> XML component layout to HTML

Simple and descriptive User Interface Markup Language to HTML written in PHP. Uses extended `SimpleXMLElement` class.

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

Gallery Tag Component: `tags/gallery.tag`

```php
<div class="<?=((@$__prefix__) ? (@$__prefix__).'-' : '')?>gallery">
    <yield/>
</div>
```

Header Tag Component: `tags/header.tag`

```php
<div class="<?=((@$__prefix__) ? (@$__prefix__).'-' : '')?>header">
    <h1><yield/></h1>
</div>
```

Slides Tag Component: `tags/slides.tag`

```php
<div class="<?=((@$__prefix__) ? (@$__prefix__).'-' : '')?>slides">
    <yield/>
</div>
```

Image Tag Component: `tags/image.tag`

```php
<div class="<?=((@$__prefix__) ? (@$__prefix__).'-' : '')?>image">
    <img class="image-media" src="<?=$src?>" alt="<?=$alt?>" />
    <p class="image-caption"><?=$alt?></p>
</div>
```

Every tag has access to original UIML attributes and can use them within templates.

Also there is special variable `$__prefix__` holds tags path as dash separated string.

It all combined together produces...

### Result:

```php
<div class="gallery">
    <div class="gallery-header">
        <h1>Very <em>nice</em> gallery</h1>
    </div>
    <div class="gallery-slides">
        <div class="gallery-slides-image">
            <img class="image-media" src="123.gif" alt="123"/>
            <p class="image-caption">123</p>
        </div>
        <div class="gallery-slides-image">
            <img class="image-media" src="456.gif" alt="456"/>
            <p class="image-caption">456</p>
        </div>
        <div class="gallery-slides-image">
            <img class="image-media" src="789.gif" alt="789"/>
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
