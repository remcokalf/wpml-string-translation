<?php
/**
 * PHP Command Line Tools
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.
 *
 * @author    Ryan Sullivan <rsullivan@connectstudios.com>
 * @copyright 2010 James Logsdom (http://girsbrain.org)
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace cli\tree;

class Markdown extends Renderer {

    protected $_padding = 2;

    function __construct($padding = null)
    {
        if ($padding)
        {
            $this->_padding = $padding;
        }
    }

    public function render(array $tree, $level = 0)
    {
        $output = '';

        foreach ($tree as $label => $next)
        {

            if (is_string($next))
            {
                $label = $next;
            }

            $output .= sprintf("%s- %s\n", str_repeat(' ', $level * $this->_padding), $label);

            if (is_array($next))
            {
                $output .= $this->render($next, $level + 1);
            }

        }

        return $output;
    }

}
