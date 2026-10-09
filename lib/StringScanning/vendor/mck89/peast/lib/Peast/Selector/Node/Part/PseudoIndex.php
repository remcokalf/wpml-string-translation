<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Selector\Node\Part;

use Peast\Syntax\Node\Node;
use Peast\Syntax\Utils;

class PseudoIndex extends Pseudo
{
    protected $priority = 2;

    protected $step = 0;

    protected $offset = 0;

    public function setStep($step)
    {
        $this->step = $step;
        return $this;
    }

    public function setOffset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function check(Node $node, $parent = null)
    {
        $props = Utils::getExpandedNodeProperties($parent);
        $count = count($props);
        $reverse = $this->name === "nth-last-child";
        if ($reverse) {
            $start = $count - 1 - ($this->offset - 1);
            $step = $this->step * -1;
            if ($step > 0) {
                $reverse = false;
            }
        } else {
            $start = $this->offset - 1;
            $step = $this->step;
            if ($step < 0) {
                $reverse = true;
            }
        }
        if (!$step) {
            $step = $reverse ? -$count : $count;
        }
        for ($i = $start; ($reverse && $i >= 0)  || (!$reverse && $i < $count); $i += $step) {
            if (isset($props[$i]) && $props[$i] === $node) {
                return true;
            }
        }
        return false;
    }
}