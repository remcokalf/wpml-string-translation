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

namespace cli;

class Tree {

    protected $_renderer;
    protected $_data = array();

    public function setRenderer(tree\Renderer $renderer) {
        $this->_renderer = $renderer;
    }

    public function setData(array $data)
    {
        $this->_data = $data;
    }

    public function render()
    {
        return $this->_renderer->render($this->_data);
    }

    public function display()
    {
        echo $this->render();
    }

}
