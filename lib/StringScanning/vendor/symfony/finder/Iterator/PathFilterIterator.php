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

class PathFilterIterator extends MultiplePcreFilterIterator
{
    public function accept()
    {
        $filename = $this->current()->getRelativePathname();

        if ('\\' === DIRECTORY_SEPARATOR) {
            $filename = str_replace('\\', '/', $filename);
        }

        return $this->isAccepted($filename);
    }

    protected function toRegex($str)
    {
        return $this->isRegex($str) ? $str : '/'.preg_quote($str, '/').'/';
    }
}
