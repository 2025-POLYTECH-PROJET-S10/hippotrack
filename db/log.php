<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Definition of log events for the hippotrack module.
 *
 * @package    mod_hippotrack
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'hippotrack', 'action'=>'add', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'update', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'view', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'report', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'attempt', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'submit', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'review', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'editquestions', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'preview', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'start attempt', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'close attempt', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'continue attempt', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'edit override', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'delete override', 'mtable'=>'hippotrack', 'field'=>'name'),
    array('module'=>'hippotrack', 'action'=>'view summary', 'mtable'=>'hippotrack', 'field'=>'name'),
);