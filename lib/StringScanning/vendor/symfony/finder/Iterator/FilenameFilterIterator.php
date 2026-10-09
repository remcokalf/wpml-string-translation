<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

use Symfony\Component\Finder\Glob;

class FilenameFilterIterator extends MultiplePcreFilterIterator
{
    public function accept()
    {
        return $this->isAccepted($this->current()->getFilename());
    }

    protected function toRegex($str)
    {
        return $this->isRegex($str) ? $str : Glob::toRegex($str);
    }
}
