<?php
/**
 * MIT License <https://opensource.org/licenses/mit>
 *
 * Copyright (c) 2015 Kerem Güneş
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace froq\pager;

use froq\util\Util;

/**
 * Pager.
 * @package froq\pager
 * @object  froq\pager\Pager
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0
 */
final class Pager
{
    /**
     * Start.
     * @var int
     */
    private $start = 0;

    /**
     * Stop (limit or per page).
     * @var int
     */
    private $stop = 10;

    /**
     * Stop max.
     * @var int
     */
    private $stopMax = 1000;

    /**
     * Stop default.
     * @var int
     */
    private $stopDefault = 10;

    /**
     * Start key.
     * @var string
     */
    private $startKey = 's';

    /**
     * Stop key.
     * @var string
     */
    private $stopKey = 'ss';

    /**
     * Total pages.
     * @var int
     */
    private $totalPages = null;

    /**
     * Total records.
     * @var int
     */
    private $totalRecords = null;

    /**
     * Links.
     * @var array
     */
    private $links = [];

    /**
     * Links center.
     * @var array
     */
    private $linksCenter = [];

    /**
     * Links limit.
     * @var int
     */
    private $linksLimit = 5;

    /**
     * Links template.
     * @var array
     */
    private $linksTemplate = [
        'page'  => 'Page',
        'first' => '&laquo;',  'prev' => '&lsaquo;',
        'next'  => '&rsaquo;', 'last' => '&raquo;',
    ];

    /**
     * Links class name.
     * @var string
     */
    private $linksClassName = 'pager';

    /**
     * Autorun.
     * @var bool
     */
    private $autorun = true;

    /**
     * Numarate first last.
     * @var bool
     */
    private $numerateFirstLast = false;

    /**
     * Arg sep.
     * @var string
     */
    private $argSep;

    /**
     * Constructor.
     * @param array $properties
     */
    public function __construct(array $properties = null)
    {
        if ($properties != null) {
            foreach ($properties as $name => $value) {
                $this->setProperty($name, $value);
            }
        }

        $this->argSep = ini_get('arg_separator.output') ?: '&';
    }

    /**
     * Set magic.
     * @param  string $name
     * @param  any    $value
     * @return void
     * @since  3.0
     */
    public function __set(string $name, $value)
    {
        $this->setProperty($name, $value);
    }

    /**
     * Get magic.
     * @param  string $name
     * @return any|null
     * @since  3.0
     */
    public function __get(string $name)
    {
        return $this->getProperty($name);
    }

    /**
     * Set property.
     * @param  string $name
     * @return any    $value
     * @return self
     * @since  3.0
     */
    public function setProperty(string $name, $value): self
    {
        if (strpos($name, '_')) { // camelize
            $name = preg_replace_callback('~_([a-z])~', function($match) {
                return ucfirst($match[1]);
            }, strtolower($name));
        }

        if (!property_exists($this, $name)) {
            throw new PagerException("No property found such '{$name}'");
        }

        // forbid start & stop
        if (in_array($name, ['start', 'stop'])) {
            throw new PagerException("No allowed property '{$name}' to set");
        }

        static $intProperties = ['stopMax', 'stopDefault', 'totalPages', 'totalRecords', 'linksLimit'];
        static $boolProperties = ['autorun', 'numerateFirstLast'];

        if (in_array($name, $intProperties)) {
            $value = (int) abs($value);
        } elseif (in_array($name, $boolProperties)) {
            $value = (bool) $value;
        }

        if ($name == 'stop' && $value > $this->stopMax) {
            $value = $this->stopMax;
        }

        $this->{$name} = $value;

        return $this;
    }

    /**
     * Get property.
     * @param  string $name
     * @return any|null
     * @since  3.0
     */
    public function getProperty(string $name)
    {
        if (strpos($name, '_')) { // camelize
            $name = preg_replace_callback('~_([a-z])~', function($match) {
                return ucfirst($match[1]);
            }, strtolower($name));
        }

        // aliases
        if (in_array($name, ['limit', 'offset'])) {
            $name = ($name == 'limit') ? 'stop' : 'start';
        }

        if (!property_exists($this, $name)) {
            throw new PagerException("No property found such '{$name}'");
        }

        return $this->{$name};
    }

    /**
     * Get offset (start alias).
     * @return int
     */
    public function getOffset(): int
    {
        return $this->start;
    }

    /**
     * Get limit (stop alias).
     * @return int
     */
    public function getLimit(): int
    {
        return $this->stop;
    }

    /**
     * Run.
     * @param  int|null    $totalRecords
     * @param  int|null    $limit
     * @param  string|null $startKey
     * @param  string|null $stopKey
     * @return array
     */
    public function run(int $totalRecords = null, int $limit = null, string $startKey = null, string $stopKey = null): array
    {
        if ($totalRecords !== null) {
            $this->totalRecords = abs($totalRecords);
        }

        if ($startKey) $this->startKey = $startKey;
        if ($stopKey) $this->stopKey = $stopKey;

        $startValue = $_GET[$this->startKey] ?? null;
        if ($limit !== null) {
            $stopValue = $limit; // skip GET parameter
        } else {
            $stopValue = $_GET[$this->stopKey] ?? null;
        }

        // get params could be manipulated by developer (setting autorun false)
        if ($this->autorun) {
            $this->start = abs($startValue);
            $this->stop = abs($stopValue);
        }

        $stop = ($this->stop > 0) ? $this->stop : $this->stopDefault;
        $start = ($this->start > 1) ? ($this->start * $stop) - $stop : 0;

        $this->stop = $stop;
        $this->start = $start;
        $this->totalPages = 1;
        if ($this->totalRecords > 0) {
            $this->totalPages = abs((int) ceil($this->totalRecords / $this->stop));
        }

        // safety
        if ($startValue !== null) {
            if ($startValue > $this->totalPages) {
                $this->redirect($this->prepareQuery() . $this->startKey .'='. $this->totalPages, 307);
            } elseif ($startValue && $startValue[0] == '-') {
                $this->redirect($this->prepareQuery() . $this->startKey .'='. abs($startValue), 301);
            } elseif ($startValue === '' || $startValue === '0' || !ctype_digit((string) $startValue)) {
                $this->redirect(trim($this->prepareQuery(), $this->argSep), 301);
            }
        }
        if ($stopValue !== null) {
            if ($stopValue > $this->stopMax) {
                $this->redirect($this->prepareQuery($this->stopKey) . $this->stopKey .'='. $this->stopMax, 307);
            } elseif ($stopValue && $stopValue[0] == '-') {
                $this->redirect($this->prepareQuery($this->stopKey) . $this->stopKey .'='. abs($stopValue), 301);
            } elseif ($stopValue === '' || $stopValue === '0' || !ctype_digit((string) $stopValue)) {
                $this->redirect(trim($this->prepareQuery(), $this->argSep), 301);
            }
        }

        // fix start,stop
        if ($this->totalRecords == 1) {
            $this->stop = 1;
            $this->start = 0;
        }

        return [$this->stop, $this->start];
    }

    /**
     * Generate links.
     * @param  int|null    $linksLimit
     * @param  string|null $ignoredKeys
     * @param  string|null $linksClassName
     * @return string
     */
    public function generateLinks(int $linksLimit = null, string $ignoredKeys = null,
        string $linksClassName = null): string
    {
        $totalPages = $this->totalPages;

        // called run()?
        if ($totalPages === null) {
            throw new PagerException('No pages to generate links');
        }

        // only one page?
        if ($totalPages == 1) {
            return $this->template(['<a class="current" href="#">1</a>'], $linksClassName);
        }

        $links = (array) $this->links;
        if ($links != null) {
            return $this->template($links, $linksClassName);
        }

        $linksTemplate = $this->linksTemplate;
        $numerateFirstLast = $this->numerateFirstLast;
        if (!$numerateFirstLast) {
            $linksTemplate['first'] = 1;
            $linksTemplate['last']  = $totalPages;
        }

        $linksLimit = $linksLimit ?? $this->linksLimit;
        if ($linksLimit > $totalPages) {
            $linksLimit = $totalPages;
        }

        $s = $this->startKey;
        $query = $this->prepareQuery($ignoredKeys);
        $start = max(1, ($this->start / $this->stop) + 1);
        $stop = $start + $linksLimit;

        // calculate loop
        $sub = 1;
        $middle = ceil($linksLimit / 2);
        $middleSub = $middle - $sub;
        if ($start >= $middle) {
            $i = $start - $middleSub;
            $loop = $stop - $middleSub;
        } else {
            $i = $sub;
            $loop = $start == $middleSub ? $stop - $sub : $stop;
            if ($loop >= $linksLimit) {
                $diff = $loop - $linksLimit;
                $loop = $loop - $diff + $sub;
            }
        }

        // add first & prev links
        $prev = $start - 1;
        if ($prev >= 1) {
            $links[] = sprintf('<a class="first" rel="first" href="%s%s=1">%s</a>', $query, $s,
                $linksTemplate['first']);
            $links[] = sprintf('<a class="prev" rel="prev" href="%s%s=%s">%s</a>', $query, $s, $prev,
                $linksTemplate['prev']);
        }

        // add numbered links
        for ($i; $i < $loop; $i++) {
            if ($loop <= $totalPages) {
                if ($i == $start) {
                    $links[] = '<a class="current" href="#">'. $i .'</a>';
                } else {
                    $relPrevNext = '';
                    if ($i == $start - 1) {
                        $relPrevNext = ' rel="prev"';
                    } elseif ($i == $start + 1) {
                        $relPrevNext = ' rel="next"';
                    }
                    $links[] = sprintf('<a%s href="%s%s=%s">%s</a>', $relPrevNext, $query, $s, $i, $i);
                }
            } else {
                $j = $start;
                $extra = $totalPages - $start;
                if ($extra < $linksLimit) {
                    $j = $j - (($linksLimit - 1) - $extra);
                }

                for ($j; $j <= $totalPages; $j++) {
                    if ($j == $start) {
                        $links[] = '<a class="current" href="#">'. $j .'</a>';
                    } else {
                        $links[] = sprintf('<a rel="next" href="%s%s=%s">%s</a>', $query, $s, $j, $j);
                    }
                }
                break;
            }
        }

        // add next & last link
        $next = $start + 1;
        if ($start != $totalPages) {
            $links[] = sprintf('<a class="next" rel="next" href="%s%s=%s">%s</a>', $query, $s, $next,
                $linksTemplate['next']);
            $links[] = sprintf('<a class="last" rel="last" href="%s%s=%s">%s</a>', $query, $s, $totalPages,
                $linksTemplate['last']);
        }

        // store
        $this->links = $links;

        return $this->template($links, $linksClassName);
    }

    /**
     * Generate links center.
     * @param  string|null $page
     * @param  string|null $ignoredKeys
     * @param  string      $linksClassName
     * @return string
     */
    public function generateLinksCenter(string $page = null, string $ignoredKeys = null, $linksClassName = null): string
    {
        $totalPages = $this->totalPages;

        // called run()?
        if ($totalPages === null) {
            throw new PagerException('No pages to generate links');
        }

        // only one page?
        if ($totalPages == 1) {
            return $this->template(['<a class="current" href="#">1</a>'], $linksClassName, true);
        }

        $links = (array) $this->linksCenter;
        if ($links != null) {
            return $this->template($links, $linksClassName, true);
        }

        $linksTemplate = $this->linksTemplate;

        $s = $this->startKey;
        $query = $this->prepareQuery($ignoredKeys);
        $start = max(1, ($this->start / $this->stop) + 1);

        // add first & prev links
        $prev = $start - 1;
        if ($prev >= 1) {
            $links[] = sprintf('<a class="first" rel="first" href="%s%s=1">%s</a>', $query, $s,
                $linksTemplate['first']);
            $links[] = sprintf('<a class="prev" rel="prev" href="%s%s=%s">%s</a>', $query, $s, $prev,
                $linksTemplate['prev']);
        }

        $links[] = sprintf('<a class="current" href="#">%s %s</a>',
            $page ?? $linksTemplate['page'], $start);

        // add next & last link
        $next = $start + 1;
        if ($start < $totalPages) {
            $links[] = sprintf('<a class="next" rel="next" href="%s%s=%s">%s</a>', $query, $s, $next,
                $linksTemplate['next']);
            $links[] = sprintf('<a class="last" rel="last" href="%s%s=%s">%s</a>', $query, $s, $totalPages,
                $linksTemplate['last']);
        }

        // store
        $this->linksCenter = $links;

        return $this->template($links, $linksClassName, true);
    }

    /**
     * Template.
     * @param  array       $links
     * @param  string|null $linksClassName
     * @param  bool        $center
     * @return string
     */
    private function template(array $links, string $linksClassName = null, bool $center = false): string
    {
        $linksClassName = $linksClassName ?? $this->linksClassName;
        if ($center) {
            $linksClassName .= ' center';
        }

        $tpl  = "<ul class=\"{$linksClassName}\">";
        foreach ($links as $link) {
            $tpl .= "<li>{$link}</li>";
        }
        $tpl .= "</ul>";

        return $tpl;
    }

    /**
     * Prepare query.
     * @param  string|null $ignoredKeys
     * @return string
     */
    private function prepareQuery(string $ignoredKeys = null): string
    {
        $tmp = explode('?', $_SERVER['REQUEST_URI'], 2);
        $path = $tmp[0];
        $query = trim($tmp[1] ?? '');
        if ($query != '') {
            $query = Util::unparseQueryString(Util::parseQueryString($query, true),
                true, join(',', [$this->startKey, $ignoredKeys]));
            if ($query != '') {
                $query .= $this->argSep;
            }
            return html_encode($path) .'?'. html_encode($query);
        } else {
            return html_encode($path) .'?';
        }
    }

    /**
     * Redirect.
     * @param  string $location
     * @param  int    $code
     * @return void
     */
    private function redirect(string $location, int $code): void
    {
        if (function_exists('redirect')) {
            redirect($location, $code); // froq-http/sugars.php
        } elseif (!headers_sent()) {
            $location = trim($location);
            header('Location: '. $location, false, $code);
            $location = htmlspecialchars($location);
            die('Redirecting to <a href="'. $location .'">'. $location .'</a>'); // yes..
        }
    }
}
