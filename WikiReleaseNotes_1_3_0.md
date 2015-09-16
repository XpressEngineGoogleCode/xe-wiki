### General improvements ###
  * Added three new types of syntax you can use with wiki: Markdown, Google Code and MediaWiki ([Issue 10](https://code.google.com/p/xe-wiki/issues/detail?id=10))
  * Added history diff - now you can see exactly what changed from a version to another for documents ([Issue 9](https://code.google.com/p/xe-wiki/issues/detail?id=9))
  * Added breadcrumbs support ([Issue 22](https://code.google.com/p/xe-wiki/issues/detail?id=22))
  * Added functionality related to which pages link to "this" page, in order to make it easier to find broken links but also to navigate in between documents ([Issue 27](https://code.google.com/p/xe-wiki/issues/detail?id=27))
  * Added improved alias support - it is no longer enforced by XE to be the same as title but with underscores instead of spaces. Now, you can choose your own alias and the distinction between alias and title is much more clear ([Issue 6](https://code.google.com/p/xe-wiki/issues/detail?id=6))
  * Allow users to name the frontpage other than "Front page" ([Issue 16](https://code.google.com/p/xe-wiki/issues/detail?id=16))
  * Added possibility to also display users' avatar in the Contributors list ([Issue 12](https://code.google.com/p/xe-wiki/issues/detail?id=12))
  * Improved English language files ([Issue 11](https://code.google.com/p/xe-wiki/issues/detail?id=11))
  * Added wiki mobile support ([Issue 4](https://code.google.com/p/xe-wiki/issues/detail?id=4), [Issue 7](https://code.google.com/p/xe-wiki/issues/detail?id=7))
  * Added autofocus to page title in Edit page ([Issue 15](https://code.google.com/p/xe-wiki/issues/detail?id=15))

### Features that allow wiki to better serve as a manual ###
  * Added a whole new skin that allows the wiki to look more like a manual, with left hand tree navigation (tree or MSDN style hierarchy view) ([Issue 8](https://code.google.com/p/xe-wiki/issues/detail?id=8))
  * Display on each wiki page a list of language code in which it was translated ([Issue 17](https://code.google.com/p/xe-wiki/issues/detail?id=17))
  * Allow users to translate a page if it wasn't translated ([Issue 18](https://code.google.com/p/xe-wiki/issues/detail?id=18))
  * Allow users to search through documents ([Issue 21](https://code.google.com/p/xe-wiki/issues/detail?id=21))

### Code improvements ###
  * Formatted all code according to new XE Coding Convention
  * Updated all backend views to use ruleset instead of filter ([Issue 19](https://code.google.com/p/xe-wiki/issues/detail?id=19))
  * Updated all lang files to the new XML syntax ([Issue 20](https://code.google.com/p/xe-wiki/issues/detail?id=20))

### Bug fixes ###
  * Fixed a bug where title text was overwritten by alias ([Issue 6](https://code.google.com/p/xe-wiki/issues/detail?id=6))
  * Fixed a bug where document links were sometimes broken in the hierarchical tree view ([Issue 1](https://code.google.com/p/xe-wiki/issues/detail?id=1))
  * Fixed a bug where after creating a new wiki instance and adding a few docs, the hierarchical view was empty ([Issue 5](https://code.google.com/p/xe-wiki/issues/detail?id=5))
  * Fixed some problems with users not logged in trying to edit pages ([Issue 13](https://code.google.com/p/xe-wiki/issues/detail?id=13))

### Performance enhancements ###
  * Added document caching - content is now cached so that runtime parsing will not be done at each page load ([Issue 23](https://code.google.com/p/xe-wiki/issues/detail?id=23))
  * Retrieve comment editor via AJAX (in the new skin) ([Issue 14](https://code.google.com/p/xe-wiki/issues/detail?id=14))

### Requirements ###
  * XE Core: XE 1.5 or higher (but 1.6 is recommended).
  * PHP 5