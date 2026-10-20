<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
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
 * Version information for the reservation dates element.
 *
 * @package    customcertelement_reservationdates
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026102000; // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2024100700; // Moodle 4.5.
$plugin->component = 'customcertelement_reservationdates';
$plugin->release   = '1.0.0';
$plugin->maturity  = MATURITY_STABLE;
$plugin->dependencies = ['mod_customcert' => 2024042228]; // Shared class mod_customcert\local\cargoschool\reservation.
