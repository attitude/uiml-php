<?php

namespace UIML;

/**
 * SimpleXMLElement extended
 */
class SimpleXMLElement extends \SimpleXMLElement
{
    /**
    * remove a SimpleXmlElement from it's parent
    * @return $this
    */
    public function remove()
    {
        $node = dom_import_simplexml($this);
        $node->parentNode->removeChild($node);
        return $this;
    }

    public function replace(SimpleXmlElement $node, SimpleXMLElement $target = null)
    {
        if (!$target) {
            $target = $this;
        }

        $domTarget = dom_import_simplexml($target);
        $domNode = dom_import_simplexml($node);

        $domTarget->parentNode->replaceChild($domTarget->ownerDocument->importNode($domNode, true), $domTarget);
    }

    public function appendChildren(SimpleXMLElement $node, SimpleXMLElement $target = null)
    {
        if (!$target) {
            $target = $this;
        }

        $domTarget = dom_import_simplexml($target);
        $domNode = dom_import_simplexml($node);

        $doc = $domTarget->ownerDocument;
        $fragment = $doc->createDocumentFragment();

        $c = 0;

        foreach ($domNode->childNodes as $child){
            $fragment->appendChild($doc->importNode($child->cloneNode(true), true));
            $c++;
        }

        if ($c === 0) {
            return;
        }

        if ($domTarget->nextSibling) {
            return $domTarget->parentNode->insertBefore($fragment, $domTarget->nextSibling);
        } else {
            return $domTarget->parentNode->appendChild($fragment);
        }
    }

    public function appendChild(SimpleXMLElement $node, SimpleXMLElement $target = null)
    {
        if (!$target) {
            $target = $this;
        }

        $domTarget = dom_import_simplexml($target);
        $domNode = dom_import_simplexml($node);
        $domTarget->appendChild($domTarget->ownerDocument->importNode($domNode, true));
    }

    public function insertAfter(SimpleXMLElement $node, SimpleXMLElement $target = null)
    {
        if (!$target) {
            $target = $this;
        }

        $domTarget = dom_import_simplexml($target);
        $domNode = $domTarget->ownerDocument->importNode(dom_import_simplexml($node), true);

        if ($domTarget->nextSibling) {
            return $domTarget->parentNode->insertBefore($domNode, $domTarget->nextSibling);
        } else {
            return $domTarget->parentNode->appendChild($domNode);
        }
    }

    public function insertBefore(SimpleXMLElement $node, SimpleXMLElement $target = null)
    {
        if (!$target) {
            $target = $this;
        }

        $domTarget = dom_import_simplexml($target);
        $domNode = $domTarget->ownerDocument->importNode(dom_import_simplexml($node), true);

        if ($domTarget->previousSibling) {
            return $domTarget->parentNode->insertBefore($domNode, $domTarget->previousSibling);
        } else {
            return $domTarget->parentNode->appendChild($domNode);
        }
    }

    public function asXML($filename = null)
    {
        return preg_replace("|<\?xml.*?\?>\n|isU", '', parent::asXML());
    }
}
