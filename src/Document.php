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
    protected $tags = [];

    public static $voidTags = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'
    ];

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
        }, glob($this->path.'/*-*.'.$this->ext));

        // Sort by number of parts, more === more specific, takes precedence
        uasort($this->tags, function($a, $b) {
            return substr_count($a, '-') > substr_count($b, '-') ? -1 : 1;
        });

        $this->tags = array_combine(array_map(function($f) {
            return str_replace('-', '-.*?', $f).'$';
        }, $this->tags), $this->tags);
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

    protected function expand(SimpleXMLElement $node, $inherited = '')
    {
        // Node name
        $nodeName = $node->getName();

        // Remember breadcrumbs
        $this->breadcrumbs[] = $nodeName;

        // Node argsuments
        $nodeAttrs = (array) $node->attributes();
        $nodeAttrs = $nodeAttrs['@attributes'];

        if (!$nodeAttrs) {
            $nodeAttrs = [];
        }

        if ($inherited) {
            $nodeAttrs['__prefix__'] = $inherited;
        }

        // Expand with template
        try {
            $nodeNameSpecific = null;

            foreach ($this->tags as $tagRegex => $tag) {
                if (preg_match('/'.$tagRegex.'/', implode('-', $this->breadcrumbs), $match)) {
                    $nodeNameSpecific = $this->tags[$tagRegex];
                    break;
                }
            }

            // Load template
            try {
                $template = $this->loadView($nodeNameSpecific, $nodeAttrs);
            } catch (\Exception $e) {
                $template = $this->loadView($nodeName, $nodeAttrs);
            }

            // set inherited prefix
            $inherited = $inherited ? $inherited.'-'.$nodeName : $nodeName;

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
                    $newXMLChild = $this->expand(simplexml_import_dom($domNodeItem, __NAMESPACE__.'\SimpleXMLElement'), $inherited);
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

        array_pop($this->breadcrumbs);

        return $newNode;
    }

    protected function loadView($view, array $args = [])
    {
        $viewFile = $this->path.'/'.$view.'.'.$this->ext;
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
