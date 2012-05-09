<?php
class WTParser
{
    var $text, $array = array();

    function WTParser($text)
    {
        $this->setText($text);
    }

    function setText($text, $paragraph=null)
    {
        if (is_int($paragraph)) {
            if (!isset($this->array[$paragraph])) return false;
            $item = $this->array[$paragraph];
            $text = substr_replace($this->getText(), $text . "\n", $item['offset'], $item['length']);
        }
        $this->text = $text;
        $this->array = $this->split($text);
    }

    function getText($paragraph=null)
    {
        if (!is_int($paragraph)) return $this->text;
        if (!isset($this->array[$paragraph])) return false;
        if (!isset($this->array[$paragraph+1])) return substr($this->text, $this->array[$paragraph]['offset'], $this->array[$paragraph]['length']);
        $item = $this->array[$paragraph];
        $startPosition = $item['offset'];
        $rank = strlen($item['wrapper']);
        while (isset($this->array[++$paragraph])) {
            $slaveRank = strlen($this->array[$paragraph]['wrapper']);
            if ($slaveRank > $rank) {
                $item = $this->array[$paragraph];
            }
        }
        return substr($this->text, $startPosition, $item['length'] + $item['offset'] - $startPosition);
    }

    /**
     * Returns an array of child items for item with $idm keeping the keys.
     * @param $id
     * @param null $array
     * @param bool $justCheck
     * @return array|bool
     */
    function getChildren($id, $array=null, $justCheck=false)
    {
        if (!isset($this->array[$id])) return false;
        $tmp = $array ? $array : $this->array;
        while (key($tmp) !== $id) next($tmp);
        $root = current($tmp);
        $possibles = array();
        while ( ($item = next($tmp)) && strlen($item['wrapper']) > strlen($root['wrapper']) ) {
            if ($justCheck) return true;
            $possibles[key($tmp)] = $item;
        }
        if ($justCheck) return false;
        $rez = $possibles;
        foreach ($possibles as $i=>$possible) {
            $deepers = $this->getChildren($i, $possibles);
            $rez = array_diff_key($rez, $deepers);
        }
        return $rez;
    }

    /**
     * Returns the table of contents as unordered list
     * @param int $id Defaults to 0 (root)
     * @return string
     */
    function toc($id=0)
    {
        $children = $this->getChildren($id);
        $str = '<ul class="toc">';
        foreach ($children as $i=>$item)
        {
            $deeperChildren = $this->getChildren($i);
            $number = $i+1;
            $liContent = "<a href='#{$item['slug']}'><span class='toc_number'>$number</span> {$item['title']}</a>";
            if (!empty($deeperChildren)) $liContent .= $this->toc($i);
            $str .= "<li>$liContent</li>";
        }
        $str .= "</ul>\n";
        return $str;
    }

    function getTocOffset()
    {
        if (!isset($this->array[0])) return false;
        return is_null($this->array[0]['title']) ? $this->array[0]['length'] : 0;
    }

    /**
     * Returns a slug for $title
     * @param $title
     * @param string $space Character to replace spaces with
     * @param bool $toLower convert to lowercase ?
     * @return string
     */
    function slugify($title, $space='_', $toLower=false)
    {
        if (empty($title)) return 'n-a';
        $title = preg_replace('~[^\\pL\d]+~u', $space, $title);
        $title = trim($title, $space);
        if (function_exists('iconv')) $title = iconv('utf-8', 'us-ascii//TRANSLIT', $title);
        if ($toLower) $title = strtolower($title);
        $title = preg_replace('~[^-\w]+~', '', $title);
        return $title;
    }

    /**
     * Splits $text into an array of items containing headings, paragraphs, offsets and lengths
     * @param $text
     * @return array
     */
    function split($text)
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
                $item['title'] = $item['slug'] = null;
                $item['paragraph'] = $p[0];
                $item['length'] = strlen($item['paragraph']);
                $nodes[] = $item;
                unset($item);
                continue;
            }
            if (isset($title['content'])) {
                $item = $title;
                $item['title'] = $item['content']; unset($item['content']);
                $item['slug'] = $this->slugify($item['title']);
                $item['paragraph'] = $p[0];
                $item['length'] = 2 * strlen($item['wrapper']) + strlen($item['title']) + strlen($item['paragraph']);
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

}
/*
$text = <<<EOF
sasasasa
==Level 2==
===Level 3===
====Level 4====
=====Level 5=====
===Level 3===
======Level 6======
==hehe==
EOF;
$text = preg_replace('/^(={1})(?!=)(.+?)(?<!=)\1$/m', "<h1>$2</h1>", $text);
$text = preg_replace('/^(={2})(?!=)(.+?)(?<!=)\1$/m', "<h2>$2</h2>", $text);
$text = preg_replace('/^(={3})(?!=)(.+?)(?<!=)\1$/m', "<h3>$2</h3>", $text);
$text = preg_replace('/^(={4})(?!=)(.+?)(?<!=)\1$/m', "<h4>$2</h4>", $text);
$text = preg_replace('/^(={5})(?!=)(.+?)(?<!=)\1$/m', "<h5>$2</h5>", $text);
$text = preg_replace('/^(={6})(?!=)(.+?)(?<!=)\1$/m', "<h6>$2</h6>", $text);
die($text);
$content = new WTParser($text);
echo $content->toc();
foreach ($content->array as $piece) {
    $h = strlen($piece['wrapper']);
    if ($piece['title'] !== null) echo "<h$h><a name='{$piece['slug']}'>{$piece['title']}</a></h$h>";
    echo "<p>{$piece['paragraph']}</p>";
}

echo $content->getText(2);*/