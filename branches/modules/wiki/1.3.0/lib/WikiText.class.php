<?php
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
            $text = substr_replace($this->getText(), "$text\n", $item['offset'], $len);
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
        if (!isset($this->array[$paragraph+1])) return substr($this->text, $this->array[$paragraph]['offset'], $this->array[$paragraph]['length']);
        $item = $this->array[$paragraph];
        $startPosition = $item['offset'];
        $rank = strlen($item['wrapper']);
        while (isset($this->array[++$paragraph])) {
            $slaveRank = strlen($this->array[$paragraph]['wrapper']);
			// If a heading of equal rank is encountered, stop looking for children
			if($slaveRank == $rank) break;
			// If a subheading was found, include it and its corresponding content
            if ($slaveRank > $rank) {
                $item = $this->array[$paragraph];
            }
        }
        return substr($this->text, $startPosition, $item['length'] + $item['offset'] - $startPosition);
    }

    /**
     * Returns an array of child items for item with $id keeping the keys.
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
    function toc($id=0, $chapter='')
    {
        $children = $this->getChildren($id);
        $str = "<ul>";
        $innerCount = 1;
        foreach ($children as $i=>$item)
        {
            $deeperChildren = $this->getChildren($i);
            $chapterAux = ( $chapter ? $chapter . '.' : '' ) . $innerCount++;
            $liContent = "<a href='#{$item['slug']}'><span class='toc_number'>$chapterAux</span> {$item['title']}</a>";
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
        $arr = $this->array;
        $item = current($arr);
        $tocIsInserted = false;
        $text = '';
        do {
            $section = key($arr);
            if (!is_null($item['title']) && $toc && !$tocIsInserted) {
                $text .= "<div id='wikiToc'><span id='wikiTocTitle'>Contents</span>{$this->toc()}</div>";
                $tocIsInserted = true;
            }
            $hAttributes = array('title="' . trim($item['title']) . '"');
            if ($toc) $hAttributes[] = 'id="' . $item['slug'] . '"';
            $hAttributes = implode(' ', $hAttributes);
            $edit = $baseEditLink ? "<span class='edit_link'><a href='$baseEditLink&section=$section'>edit</a></span>" : null;
            $depth = strlen($item['wrapper']);
            $text .= ( is_null($item['title']) ? '' : "<h$depth $hAttributes>$edit{$item['title']}</h$depth>" );
            if ($p = trim($item['paragraph']))
            {
                if ($this->mode == 'markdown') {
                    require_once ('markdown.php');
                    $p = Markdown($p);
                    $p = $this->markDownParseLinks($p);
                    $text .= "$p\n";
                }
                elseif ($this->mode == 'googlecode') {
                    $p = "<p>$p</p>";
                }
                elseif ($this->mode == 'wikitext') {
                    $p = "<p>$p</p>";
                }
                $text .= "$p\n";
            }
        } while ($item = next($arr));

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
                if ($this->array[$i]['slug'] == $title) $occurrences++;
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
            $occurrences[$item['slug']] = ( isset($occurrences[$item['slug']]) ? $occurrences[$item['slug']] + 1 : 0 );
            if ($occurrences[$item['slug']]) {
                $this->array[$sectionId]['slug'] .= '_' . ( $occurrences[$item['slug']] + 1 );
            }
        }
    }

    /**
     * Splits $text into an array of items containing headings, paragraphs, offsets and lengths
     * @param $text text to be splitted
     * @return array
     */
    function split($text)
    {
        if ($this->mode == 'wikitext') $regex = '/^[\s?]*(={1,6})(?!=)(?P<content>.+?)(?<!=)\1[\s?]*$/m';
        elseif ($this->mode == 'markdown') $regex = '/^[\s?]*([#=]{1,6})(?![#=])(?P<content>.+?)(?<![#=])\1[\s?]*$/m';
        elseif ($this->mode == 'googlecode') $regex = '/^[\s?]*(={1,6})(?!=)(?P<content>.+?)(?<!=)\1[\s?]*$/m';
        else return false;
        $paragraphs = preg_split($regex, $text, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);
        $nodes = array();
        foreach ($paragraphs as $i=>$p) {
            if (substr($p[0], 0, 1) === ($this->mode == 'wikitext' || $this->mode == 'googlecode' ? '=' : '#')) { //we have a title, start building
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
                $item['slug'] = $this->slugify($item['title'], $i);
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

}

/*$text = <<<EOF
boo
==hehe==
Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.
   ===ceva ceva ===
It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using 'Content here, content here', making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for 'lorem ipsum' will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).
 ===inca ceva ===
Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of "de Finibus Bonorum et Malorum" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, "Lorem ipsum dolor sit amet..", comes from a line in section 1.10.32.
=== chiar inca ===
There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don't look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn't anything embarrassing hidden in the middle of text. All the Lorem Ipsum generators on the Internet tend to repeat predefined chunks as necessary, making this the first true generator on the Internet. It uses a dictionary of over 200 Latin words, combined with a handful of model sentence structures, to generate Lorem Ipsum which looks reasonable. The generated Lorem Ipsum is therefore always free from repetition, injected humour, or non-characteristic words etc.
==== merge ?    ====
  Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?
==poate==
But I must explain to you how all this mistaken idea of denouncing pleasure and praising pain was born and I will give you a complete account of the system, and expound the actual teachings of the great explorer of the truth, the master-builder of human happiness. No one rejects, dislikes, or avoids pleasure itself, because it is pleasure, but because those who do not know how to pursue pleasure rationally encounter consequences that are extremely painful. Nor again is there anyone who loves or pursues or desires to obtain pain of itself, because it is pain, but because occasionally circumstances occur in which toil and pain can procure him some great pleasure. To take a trivial example, which of us ever undertakes laborious physical exercise, except to obtain some advantage from it? But who has any right to find fault with a man who chooses to enjoy a pleasure that has no annoying consequences, or one who avoids a pain that produces no resultant pleasure?
====niste h4====
At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat.
====inca niste h4====
At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat.
===== si h5, sigur=====
Sed a elit urna. Aliquam erat volutpat. Vivamus fringilla ligula ut massa semper quis ultrices quam aliquam. Proin venenatis fermentum tortor non ornare. Nam facilisis nibh at augue accumsan faucibus. Nulla rhoncus euismod tortor eu adipiscing. Praesent mollis quam vitae felis egestas in dictum quam vulputate. Phasellus sollicitudin iaculis ligula sit amet accumsan. Vestibulum rutrum odio quis nibh ullamcorper gravida. Donec volutpat, risus id lobortis varius, nisl leo vestibulum sem, eu porta nibh enim quis sem. Nam eu nisi eget mi cursus vehicula quis quis quam. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis lobortis lectus ut nulla elementum a dapibus elit malesuada. Morbi blandit condimentum tortor vitae ornare. Ut venenatis, massa vel accumsan elementum, felis ligula consectetur mauris, vitae lobortis ipsum lacus vel leo.
==hehe==
==hehe==
===hehe===
==hehe==
==hehe==
ooooo
EOF;
$content = new WTParser($text);
echo $content->toString();*/
