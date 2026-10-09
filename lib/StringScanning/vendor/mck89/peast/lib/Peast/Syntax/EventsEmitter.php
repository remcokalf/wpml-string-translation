<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Syntax;

class EventsEmitter
{
    protected $eventsRegistry = array();
    
    public function addListener($event, $listener)
    {
        if (!isset($this->eventsRegistry[$event])) {
            $this->eventsRegistry[$event] = array();
        }
        $this->eventsRegistry[$event][] = $listener;
        return $this;
    }
    
    public function fire($event, $args = array())
    {
        if (isset($this->eventsRegistry[$event])) {
            foreach ($this->eventsRegistry[$event] as $listener) {
                call_user_func_array($listener, $args);
            }
        }
        return $this;
    }
}