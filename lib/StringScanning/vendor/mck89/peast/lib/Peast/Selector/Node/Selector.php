<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Selector\Node;

use Peast\Selector\Matches;

class Selector
{
    protected $groups = array();

    public function addGroup(Group $group)
    {
        $this->groups[] = $group;
        return $this;
    }

    public function exec(Matches $matches)
    {
        $retMatches = array();
        foreach ($this->groups as $group) {
            $clonedMatches = $matches->createClone();
            $group->exec($clonedMatches);
            $retMatches[] = $clonedMatches;
        }
        if (count($retMatches) > 1) {
            $retMatches[0]->merge(array_slice($retMatches, 1));
        }
        return $retMatches[0];
    }
}