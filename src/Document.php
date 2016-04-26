<?php

namespace UIML;

/**
 *  Component Markup Language
 */
class Document
{
    protected $path;
    protected $tree;
    protected $ext;

    protected $breadcrumbs = [];
    protected $className   = [];

    protected $tags = [];
    protected $priorityTags = [];

    /**
     * List of HTML5 void tags
     */
    public static $voidTags = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'
    ];

    /**
     * BEM naming convention
     */
    public static $classJoiner = '__';

    /**
     * Whether to keep '-' in tagname
     *
     * '-' >>> `multi-tag-names`
     * '' >>>  `multitagnames` (removes dash)
     * '^' >>> `multiTagNames` camelCase
     */
    public static $tagJoiner  = '-';

    /**
     * Default number of class words to use when class is missing
     *
     * Set 0 to disable class replacing.
     */
    public static $classLenth  = 3;

    /**
     * Perserves original UILM class if enabled
     *
     * Usually not needed, because node name is being transformed and
     * first class *word* is used instead of class name to build new class
     */
    public static $perserveTagClass = false;

    public function __construct(SimpleXMLElement $view, $path, $ext = '.php')
    {
        if (!is_string($path) || !realpath($path) || !is_dir($path)) {
            throw new \Exception('Expecting string as argument', 400);
        }

        if (!is_string($ext) || trim(strlen($ext), ' .*') === 0) {
            throw new \Exception('Extension must be non-empty string', 500);
        }

        $this->tree    = $view;
        $this->path    = realpath($path);
        $this->ext     = trim($ext, ' .*');

        $this->tags = array_map(function($f) {
            return pathinfo($f, PATHINFO_FILENAME);
        }, glob($this->path.'/*.'.$this->ext));

        $this->priorityTags = array_map(function($f) {
            return pathinfo($f, PATHINFO_FILENAME);
        }, glob($this->path.'/*-*.'.$this->ext));

        // Sort by number of parts, more === more specific, takes precedence
        uasort($this->priorityTags, function($a, $b) {
            return substr_count($a, '-') > substr_count($b, '-') ? -1 : 1;
        });

        $this->priorityTags = array_combine(array_map(function($f) {
            return str_replace('-', '-.*?', $f).'$';
        }, $this->priorityTags), $this->priorityTags);
    }

    public function __toString()
    {
        try {
            $expanded = $this->expand($this->tree);

            if (!$expanded) {
                return '';
            }

            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }

            $dom = new \DOMDocument("1.0");
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($expanded->asXML());

            return preg_replace('#</(?:'.implode('|', (array) static::$voidTags).')>#', '', preg_replace("|<\?xml.*?\?>\n|", '', preg_replace_callback('|&#x[0-9ABCDEF]+;|', function($v) {
                return mb_convert_encoding($v[0], "UTF-8", "HTML-ENTITIES");
            }, $dom->saveXML($dom, LIBXML_NOEMPTYTAG))));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    protected function expand(SimpleXMLElement $node)
    {
        // Node name
        $nodeName = $originalNodeName = $node->getName();

        // Node argsuments
        $localVars = (array) $node->attributes();
        $localVars = (array) $localVars['@attributes'];

        // Pass original node name down to tag template
        $localVars['nodeName']    = $nodeName;

        // Pass array of breadcrumbs down to tag template
        $localVars['nodeParents'] = $this->breadcrumbs;

        // Add node name to full breadcrumb array
        $this->breadcrumbs[] = $nodeName;

        // Expand with template
        try {
            $nodeNameSpecific = null;

            // Find most relevant tag template, more specific is used
            foreach ($this->priorityTags as $tagRegex => $tag) {
                if (preg_match('/'.$tagRegex.'/', implode(static::$classJoiner, $this->breadcrumbs), $match)) {
                    $nodeNameSpecific = $this->priorityTags[$tagRegex];

                    break;
                }
            }

            // Load template
            try {
                $template = $this->loadView($nodeNameSpecific, $localVars);
            } catch (\Exception $e) {
                $template = $this->loadView($nodeName, $localVars);
            }

            // Add node name to className array, but use first class of list if class is present
            if ($node['class'] && strlen(trim($node['class'])) > 0) {
                if (static::$tagJoiner === '^') {
                    $this->className[] = str_replace('-', static::$tagJoiner, lcfirst(implode('', array_map('ucfirst', explode('-', trim(array_shift(explode('-', $node['class']))))))));
                } else {
                    $this->className[] = str_replace('-', static::$tagJoiner, trim(array_shift(explode(' ', $node['class']))));
                }
            } else {
                if (static::$tagJoiner === '^') {
                    $this->className[] = str_replace('-', static::$tagJoiner, lcfirst(implode('', array_map('ucfirst', explode('-', $nodeName)))));
                } else {
                    $this->className[] = str_replace('-', static::$tagJoiner, $nodeName);
                }
            }

            // Variables available in template (5 should be enough)
            $localVars['class5']  = implode(static::$classJoiner, array_slice($this->className, -5, 5));
            $localVars['class4']  = implode(static::$classJoiner, array_slice($this->className, -4, 4));
            $localVars['class3']  = implode(static::$classJoiner, array_slice($this->className, -3, 3));
            $localVars['class2']  = implode(static::$classJoiner, array_slice($this->className, -2, 2));
            $localVars['class1']  = $nodeName;

            // Default class variable to be passed down to tag template
            if ((int) static::$classLenth > 0) {
                $localVars['class'] = implode(static::$classJoiner, array_slice($this->className, -1 * static::$classLenth, static::$classLenth));
            } else {
                $localVars['class'] = $localVars['class3'];
            }

            // xpath('//*/yield/parent::*')
            // if ($yieldNode = $template->sortedXPath('//*/yield')) {
            if ($yieldNode = $template->xpath('//*/yield')) {
                if (count($yieldNode) !== 1) {
                    throw new \Exception("There is more than one YIELD node for ${nodeName}", 500);
                }

                // Yield node:
                $yieldNode   = $yieldNode[0];

                $yieldNode->appendChildren($node);
                $yieldNode->remove();
            }

            // Clone original arguments
            if (!$template['class']) {
                $template->addAttribute('class', $localVars['class']);
            }

            $nodeAttrs = (array) $node->attributes();
            $nodeAttrs = (array) $nodeAttrs['@attributes'];

            foreach ($nodeAttrs as $k => $v) {
                if (!$template[$k]) {
                    $template->addAttribute($k, $v);
                } elseif (static::$perserveTagClass && $k === 'class') {
                    // Original UIML had class, so perserve it even though template
                    // overwrited class attribute by defining new
                    $template['class'] = $v.' '.$template['class'];
                }
            }

            // Post fix for duplicates
            $template['class'] = implode(' ', array_unique(explode(' ', $template['class'])));

            // replace node with new expanded node by template
            $node = $template;
        } catch (\Exception $e) {}

        // Node name again
        $nodeName = $node->getName();

        // Node argsuments again
        $nodeAttrs = (array) $node->attributes();
        $nodeAttrs = $nodeAttrs['@attributes'];

        if (!$nodeAttrs) {
            $nodeAttrs = [];
        }

        // Process text nodes
        if($node->count() > 0) {
            $domNode = dom_import_simplexml($node);
            $nodeString = '';

            foreach ($domNode->childNodes as $domNodeItem) {
                if ($domNodeItem->nodeType !== 1) {
                    $nodeString .= $domNodeItem->nodeValue;
                } else {
                    $newXMLChild = $this->expand(simplexml_import_dom($domNodeItem, __NAMESPACE__.'\SimpleXMLElement'));
                    $nodeString.= $newXMLChild->asXML();
                }
            }

            // Create new instance
            $newNode = simplexml_load_string('<'.$nodeName.'>'.$nodeString.'</'.$nodeName.'>', __NAMESPACE__.'\SimpleXMLElement');

            // Clone attributes
            foreach ($nodeAttrs as $k => $v) {
                $newNode->addAttribute($k, $v);
            }
        } else {
            $newNode = simplexml_load_string('<'.$nodeName.'>'.htmlspecialchars($node, ENT_HTML5, 'UTF-8').'</'.$nodeName.'>', __NAMESPACE__.'\SimpleXMLElement');

            // Clone attributes
            foreach ($nodeAttrs as $k => $v) {
                $newNode->addAttribute($k, $v);
            }
        }

        // Remove from breadcrumb
        if (in_array($originalNodeName, $this->tags)) {
            array_pop($this->className);
        }

        // Remove from className
        array_pop($this->breadcrumbs);

        return $newNode;
    }

    protected function camelCase($value = '', $separators = ' -_')
    {
        return lcfirst(implode('', array_map('ucfirst', explode(' ', trim(strtr($value, $separators, str_repeat(' ', strlen($separators))))))));
    }

    protected function loadView($view, array $args = [])
    {
        $viewFile = $this->path.'/'.$view.'.'.$this->ext;

        // Change arguments from `w3c-standard` to `camelCase`
        foreach ($args as $k => $v) {
            $args[$this->camelCase($k)] = $v;
        }

        try {
            return self::loadUIML($viewFile, $args);
        } catch (\Exception $e) {
            throw new \Exception("Unable to load view ${view}", 404);
        }
    }

    public function loadUIML($file, array $args = [])
    {
        libxml_use_internal_errors(true);
        extract($args);

        if (!is_string($file) || !is_readable($file)) {
            throw new \Exception("Unable to load view", 404);
        }

        ob_start();
        include $file;
        $html = ob_get_contents();
        ob_end_clean();

        $doc = new \DOMDocument;
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);

        if (!$xml = simplexml_import_dom($doc, __NAMESPACE__.'\SimpleXMLElement')) {
            throw new \Exception("Please close tags in `${view}` tag/view. Must be a valid XML.", 500);
        }

        if (preg_match('|<html.*?>|', $html) && !preg_match('|<body.*?>|', $html)) {
            return $xml->body;
        }

        if (preg_match('|<html.*?>|', $html) && preg_match('|<body.*?>|', $html)) {
            return $xml;
        }

        if (preg_match('|<body.*?>|', $html)) {
            return $xml->body;
        }

        return $xml->body->children()[0];
    }
}
