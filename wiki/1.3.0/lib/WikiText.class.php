<?php
class WTItem
{
    var $title, $content, $offset, $wrapper, $slug;

    function toArray()
    {
        return array(
            'title' => $this->title,
            'wrapper' => $this->wrapper,
            'paragraph' => $this->content,
            'offset' => $this->offset,
            'slug' => $this->slug,
            'length' => $this->length()
        );
    }

    function length()
    {
        if (is_array($this->wrapper)) return
            strlen($this->wrapper['left']) +
            strlen($this->title) +
            strlen($this->wrapper['right']) +
            strlen($this->content);
        return 2 * strlen($this->wrapper) + strlen($this->title) + strlen($this->content);
    }
    function rank()
    {
        if (is_array($this->wrapper)) return $this->wrapper['h'];
        return strlen($this->wrapper);
    }
}

class WTParser
{
    var $text, $mode, $array = array();

    function WTParser($text, $mode='wikitext', $wiki_site=null)
    {
        $this->mode = $mode;
        $this->setText($text);
        $this->wiki_site = $wiki_site;
    }

    function setText($text, $paragraph=null)
    {
		$text = str_replace(chr(13), '', $text);
        if (is_numeric($paragraph)) {
            if (!isset($this->array[$paragraph])) return false;
            $item = $this->array[$paragraph];
            $len = strlen($this->getText($paragraph));
            $text = substr_replace($this->getText(), "$text\n", $item->offset, $len);
        }
        $this->text = $text;
        $this->array = $this->split($text);
        $this->dealWithDuplicateSlugs();
    }

	/**
	 * @brief Returns all content corresponding to a section;
	 * If a section with h1 also has subheadings (h2, h3 etc), their sections are included too;
	 * @param null $paragraph
	 * @return bool|string
	 */
    function getText($paragraph=null)
    {
        if (!is_int($paragraph)) return $this->text;
        if (!isset($this->array[$paragraph])) return false;
        if (!isset($this->array[$paragraph+1])) {
            return substr($this->text, $this->array[$paragraph]->offset, $this->array[$paragraph]->length());
        }
        $item = $this->array[$paragraph];
        $startPosition = $item->offset;
        $rank = $item->rank();
        while (isset($this->array[++$paragraph])) {
            $slaveRank = $this->array[$paragraph]->rank();
			// If a heading of equal rank is encountered, stop looking for children
			if($slaveRank == $rank) break;
			// If a subheading was found, include it and its corresponding content
            if ($slaveRank > $rank) $item = $this->array[$paragraph];
        }
        return substr($this->text, $startPosition, $item->length() + $item->offset - $startPosition);
    }

    /**
     * Returns an array of child items for item with $id keeping the keys.
     * @param $id
     * @param null $array
     * @param bool $justCheck
     * @return array|bool
     */
    function getChildren($id=0, $array=null, $justCheck=false)
    {
        $tmp = ( $array ? $array : $this->array );
        reset($tmp);
        /*if (!is_numeric($id)) { //get first level
            $min = current($tmp);
            if (!$min->wrapper) $min = next($tmp);
            $rez = array(key($tmp) => $min);
            while ($item = next($tmp)) {
                if ($item->rank() < $min->rank()) {
                    $min = $item;
                    $rez = array(key($tmp) => $item);
                }
                elseif ($item->rank() == $min->rank()) $rez[key($tmp)] = $item;
            }
            return $rez;
        }
        else {*/
        if (!isset($tmp[$id])) return false;
        while (key($tmp) !== $id) next($tmp);
        $root = current($tmp);
        $possibles = array();
        while ( ($item = next($tmp)) && $item->rank() > $root->rank() ) {
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
        //}
    }

    /**
     * Returns the table of contents as unordered list
     * @param int $id Defaults to 0 (root)
     * @return string
     */
    function toc($id=0, $chapter='')
    {
        $children = $this->getChildren($id);
        $str = "<ul>";
        $innerCount = 1;
        foreach ($children as $i=>$item)
        {
            $deeperChildren = $this->getChildren($i);
            $chapterAux = ( $chapter ? $chapter . '.' : '' ) . $innerCount++;
            $liContent = "<a href='#{$item->slug}'><span class='toc_number'>$chapterAux</span> {$item->title}</a>";
            if (!empty($deeperChildren)) $liContent .= $this->toc($i, $chapterAux);
            $str .= "\n<li>$liContent</li>";
        }
        $str .= "</ul>";
        return $str;
    }

    function toString($toc=true, $baseEditLink=false)
    {
        if (!$baseEditLink && $this->wiki_site) $baseEditLink = $this->wiki_site->getEditPageUrlForCurrentDocument();
        if (empty($this->array)) return false;
        $item = current($this->array);
        $tocIsInserted = false;
        $text = '';
        do {
            $section = key($this->array);
            if (!$tocIsInserted && $item->title && $toc) {
                $text .= "<div id='wikiToc'><span id='wikiTocTitle'>Contents</span>{$this->toc()}</div>";
                $tocIsInserted = true;
            }
            $hAttributes = array('title="' . trim($item->title) . '"');
            if ($toc) $hAttributes[] = 'id="' . $item->slug . '"';
            $hAttributes = implode(' ', $hAttributes);
            $edit = $baseEditLink ? "<span class='edit_link'><a href='$baseEditLink&section=$section'>edit</a></span>" : null;
            $depth = $item->rank();
            $text .= ( is_null($item->title) ? '' : "<h$depth $hAttributes>$edit{$item->title}</h$depth>" );
            if ($p = trim($item->content))
            {
                if ($this->mode == 'markdown') {
                    require_once ('markdown.php');
                    $p = Markdown($p, true);
                    $p = $this->markDownParseLinks($p);
                }
                elseif ($this->mode == 'googlecode') {
                    $p = "<p>$p</p>";
                }
                elseif ($this->mode == 'wikitext') {
                    $p = "<p>$p</p>";
                }
                $text .= "$p\n";
            }
        } while ($item = next($this->array));
        return $text;
    }

    /**
     * Returns a slug for $title
     * @param string $title
     * @param int $sectionId for calculating number of preceding occurrences
     * @param string $space Character to replace spaces with
     * @param bool $toLower convert to lowercase ?
     * @return string
     */
    function slugify($title, $sectionId=null, $space='_', $toLower=false)
    {
        if (empty($title)) return 'n-a';
        $title = preg_replace('~[^\\pL\d]+~u', $space, $title);
        $title = trim($title, $space);
        if (function_exists('iconv')) $title = iconv('utf-8', 'us-ascii//TRANSLIT', $title);
        if ($toLower) $title = strtolower($title);
        $title = preg_replace('~[^-\w]+~', '', $title);

        if (is_numeric($sectionId)) { //calculate number of occurrences of slug and add it to the end
            if (!isset($this->array[$sectionId])) return $title;
            $occurrences = 0;
            $i = 0;
            while ($i <= $sectionId) {
                if ($this->array[$i]->slug == $title) $occurrences++;
                $i++;
            }
            $title = $title . '_' . $occurrences;
        }
        return $title;
    }

    function dealWithDuplicateSlugs()
    {
        if (empty($this->array)) return false;
        $occurrences = array();
        $arr = $this->array;
        foreach ($arr as $sectionId=>$item) {
            $occurrences[$item->slug] = ( isset($occurrences[$item->slug]) ? $occurrences[$item->slug] + 1 : 0 );
            if ($occurrences[$item->slug]) {
                $this->array[$sectionId]->slug .= '_' . ( $occurrences[$item->slug] + 1 );
            }
        }
    }

    function markDownParseLinks($text)
    {
        $r =  preg_replace_callback("/
                ([<]a		# Starts with 'a' HTML tag
                .*			# Followed by any number of chars
                href[=]		# Then by href=
                [\"']?		# Optional quotes
                (.*?)		# The alias (backreference 1)
                [\"']?		# Optional quotes
                [ >])		# Ends with space or close tag
                (.*?)		# Anchor value
                [<][\/][a][>]	# Ends with a close tag
                /ix"
            , array($this, "_handle_markdown_link")
            , $text
        );
        return $r;
    }

    function _handle_markdown_link($matches)
    {
        $url = $matches[2];
        // If external URL, just return it as is
        if(preg_match("/^(https?|ftp|file)/", $url))
        {
            // return "<a href=$url$local_anchor>" . ($description ? $description : $url) . "</a>";
            return $matches[0];
        }
        // If local document that  exists, return expected link and exit
        if($alias = $this->wiki_site->documentExists($url))
        {
            $full_url = $this->wiki_site->getFullLink($alias);
            $anchor = str_replace($url, $full_url, $matches[0]);
            return $anchor;
        }
        // Else, if document does not exist
        //   If user is not allowed to create content, return plain text
        if(!$this->wiki_site->currentUserCanCreateContent())
        {
            return $url;
        }
        //   Else return link to create new page
        $full_url = $this->wiki_site->getFullLink($url);
        $description = $matches[3];
        return "<a href=$full_url class='notexist'>" . $description . "</a>";
    }

    /**
     * Splits $text into an array of items containing headings, paragraphs, offsets and lengths
     * @param $text text to be splitted
     * @return array
     */
    function split($text)
    {
        if ($this->mode == 'wikitext' || $this->mode == 'googlecode') $delimiter = '=';
        elseif ($this->mode == 'markdown') $delimiter = '[#]';
        elseif ($this->mode == 'xewiki') return $this->splitXeWiki($text);
		/*$regex = '/
		^[\s?]*([#]{1,6})(?![\2])(.+?)(?<![\2])\1[\s?]*$         # Match headings that start with #
		|
		^(.+?)[ ]*\n(=+|-+)[ ]*\n+                     # Match heading underlined by = or -
		/mx';*/
		$regex = '/^[\s?]*(' . $delimiter . '{1,6})(?![\2])(.+?)(?<![\2])\1[\s?]*$|^(.+?)[ ]*\n(=+|-+)[ ]*\n+/m';
        $paragraphs = preg_split($regex, $text, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);
        $nodes = array();
        $itemOffset = 0; $title = $wrapper = null;
        foreach ($paragraphs as $i=>$p) {
            $group = $p[0];
            $offset = $p[1];
            //if (!$group && !$offset) continue;
            $mod = $i % 3;
            if ($mod == 0) { // Content match
                $item = new WTItem();
                $item->offset = $itemOffset;
                $item->wrapper = $wrapper;
                $item->title = $title;
                $item->content = $group;
                if ($title) $item->slug = $this->slugify($title, $i);
                $nodes[] = $item;
            }
            elseif ($mod == 1) { // Delimiter match
                $wrapper = $group;
                $itemOffset = $offset;
            }
            elseif ($mod == 2) { // Title match
                $title = $group;
            }
        }
        return $nodes;
    }

    function splitXeWiki($text)
    {
        $regex = '/(<[\s?]*h[\s?]*([1-6])[\s?]*>)(.+?)(<[\s?]*\/[\s?]*h[\s?]*\2[\s?]*>)/s';
        $paragraphs = preg_split($regex, $text, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);
        $nodes = array();
        $itemOffset = 0; $itemRank = $titleLeft = $titleRight = $title = $wrapper = null;
        foreach ($paragraphs as $i=>$p) {
            $group = $p[0];
            $offset = $p[1];
            $mod = $i % 5;
            if ($mod == 0) {
                $item = new WTItem();
                $item->offset = $itemOffset;
                $item->wrapper = $itemRank ? array(
                    'h' => $itemRank,
                    'left' => $titleLeft,
                    'right' => $titleRight
                    ) : null;
                $item->title = $title;
                $item->content = $group;
                if ($title) $item->slug = $this->slugify($title, $i);
                $nodes[] = $item;
            }
            elseif ($mod == 1) {
                $titleLeft = $group;
                $itemOffset = $offset;
            }
            elseif ($mod == 2) {
                $itemRank = $group;
            }
            elseif ($mod == 3) {
                $title = $group;
            }
            elseif ($mod == 4) {
                $titleRight = $group;
            }
        }
        return $nodes;
    }

}

//$text = "a<h2>b</h2>c<h3>d</h3>e<h2>eee</h2><h1>hohoho</h1>";
/*
$text = <<<EOF
skdvbgakbsvcwa
ew
dewdwebcxquwbxyubxuewq


denuxnyqwinxyewgvcewubdyuhwedewduhewudiwe


wfcwednuyewhbwe

dnewiuneiqdwedweqdq

# Heading 1 #
ksjdfhjkshdfkjsahchdjkdshcasd

sdcjksanbcjksdbncksa

Another heading 1
======================

sdkjsadfjkshdfjasdkjfhsdkjfhajksd

asfajskdfjdfhgsjda

And a little subheading
---------------------------------------

sadkjfsahdjkfhsjakdccdsacsad
EOF;
$content = new WTParser($text, 'markdown');
//echo "<pre>$text</pre><hr>";
//echo $content->toc();
echo $content->toString();
//echo $content->getText(1);*/
//$content->setText('bla', 4);
//echo $content->toString(false);
