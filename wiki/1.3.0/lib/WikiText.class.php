<?php
class WTNode
{
    #region vars, constructor
    var
        $parent,
        $content,
        $title,
        $offset,
        $children,
        $delimiter,
        $delimiters = array('==','===','====','=====','======');

    function WTNode($content=null, $title=null, $offset=null)
    {
        if (isset($offset)) $this->setOffset($offset);
        if (isset($title)) $this->setTitle($title);
        if (isset($content)) $this->setContent($content);
    }
    #endregion

    function split($delimiter = '==', $recursive = true)
    {
        $i = array_search($delimiter, $this->delimiters);
        if ($i === false) return false;

        $text = $this->getContent();
        $regex = '/^' . $delimiter . '[^=]?[\s]?(.+?)[\s]?[^=]?' . $delimiter . '$/m';
        $paragraphs = preg_split($regex, $text, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);
        preg_match_all($regex, $text, $titles, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if (count($paragraphs) == 1 && $paragraphs[0][0] == $text) return null; //not splittable by $delimiter

        //make WTNodes from the 2 arrays
        $nodes = array();
        foreach ($paragraphs as $p) {
            $titleOffset = false;
            foreach ($titles as $t) {
                if ($p[1] == $t[1][1]) {
                    $node = new WTNode(null, $t[1][0], $titleOffset = $p[1]);
                    break;
                }
            }
            if (!$titleOffset) { //not title, but content paragraph.
                if (!isset($node)) { //for a node with no title (offset 0)
                    $node = new WTNode();
                    $node->setOffset($p[1]);
                }
                $node->setContent($p[0]);
                $node->setParent($this);
                $node->delimiter = $delimiter;
                //save the node
                $nodes[] = $node;

                if ($recursive) //go deeper
                {
                    $aux = $i;
                    $pieces = false;
                    while (isset($this->delimiters[++$aux]) && !$pieces)
                    {
                        $d = $this->delimiters[$aux];
                        $pieces = $node->split($d);
                    }
                    if ($pieces) $nodes = array_merge($nodes, $pieces);
                }

            }
        }
        return $nodes;
    }

    #region getters/setters
    function getRoot()
    {
        $root = $this;
        while ($parent = $this->getParent()) $root = $parent;
        return $root;
    }

    function setContent($content)
    {
        $this->content = $content;
    }

    function getContent()
    {
        return $this->content;
    }

    function setTitle($title)
    {
        $this->title = $title;
    }

    function getTitle()
    {
        return $this->title;
    }

    function getLevel()
    {
        if (isset($this->parent)) return $this->parent->getLevel() + 1;
        else return 0;
    }

    function setOffset($offset)
    {
        $this->offset = $offset;
    }

    function getOffset($real=false)
    {
        if ($real) {
            if ($parent = $this->getParent()) {
                return $this->offset + $parent->getOffset($real);
            }
            return 0;
        }
        return $this->offset;
    }

    function setParent(&$parent)
    {
        $this->parent =& $parent;
    }

    function getParent()
    {
        return isset($this->parent) ? $this->parent : false;
    }
    #endregion

    function __toString()
    {
        $title = ( $this->getTitle() !== null ? $this->getTitle() : 'none' );
        $content = ( $this->getContent() !== null ? $this->getContent() : 'none' );
        $strlen = strlen($content);
        $offset = array($this->getOffset(), $this->getOffset(true));
        $str = <<<TOSTR
<b><big>$title</big></b><BR>
<small>
<b>delimiter</b>: {$this->delimiter}<br>
<b>level</b>: {$this->getLevel()}<br>
<b>offset</b>: relative {$offset[0]}; absolute {$offset[1]};<BR>
<b>content</b> <small>(strlen $strlen)</small>: <pre>$content</pre>
</small>
TOSTR;
        return $str;
    }
}

class WTTree
{
    var $raw, $structured;
    function WTTree($nodes_vector) {
        if (!is_array($nodes_vector)) return false;
        if (count($nodes_vector) == 1) return $nodes_vector[0];

        $this->raw = $nodes_vector;
        $root = $nodes_vector[0]->getRoot();
        foreach($nodes_vector as $node) {

        }
    }
}


$text = <<<EOF
inceput inceput inceput
inceput
=== h3 inceput ===
ghdjasg jgd gasd guys gfiuy gdiy gfidyf
fdhsij gfihd gfih gdifg
== zazazazaza==
sasasasa
====zzz====
s as
sas grs gtr g
rfsd grs ghrs hgesr
=== aaaa ===
hfejdsh fjk hrjskhgfjr hgijkrgh
sasasas
==mmmms sa ==
sasjfskd hfjk hsdrjkghfjkrsd hjgkdrjhkfg
saj gsjahk gsh gahgsh
EOF;

/*$n = new WTNode($text);
$nodes = $n->split('==');
echo "<ul>";
function showNode($node) {
    $s = $node->__toString();
    echo "<li>$s</li>";
}
foreach ($nodes as $node)
{
    showNode($node);
}
echo "</ul>";*/

function split2($text)
{
    $paragraphs = preg_split('/^(={1,6})(?!=)(?P<content>.+?)(?<!=)\1$/m', $text, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);
    $nodes = array();
    foreach ($paragraphs as $p) {
        if (substr($p[0], 0, 1) === '=') { //we have a title, start building
            $title['wrapper'] = $p[0];
            $title['offset'] = $p[1];
            continue;
        }
        elseif (!isset($title)) {
            $item['wrapper'] = null;
            $item['offset'] = $p[1];
            $item['title'] = null;
            $item['paragraph'] = $p[0];
            $nodes[] = $item;
            unset($item);
        }
        if (isset($title['content'])) {
            $item = $title;
            $item['paragraph'] = $p[0];
            $nodes[] = $item;
            unset($title, $item);
            continue;
        }
        if (isset($title['wrapper'])) {
            $title['content'] = $p[0];
            continue;
        }
    }
    return $nodes;
}

print_r(split2($text));