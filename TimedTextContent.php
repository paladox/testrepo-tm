<?php
/**
 * Created on Sep 5, 2015
 *
 * Copyright © 2015 Derk-Jan Hartman "hartman.wiki@gmail.com"
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * Timed Text Content. This is basically Wikitext for now.
 *
 * @file
 * @ingroup Extensions
 * @ingroup TimedText
 * @author Derk-Jan Hartman <hartman.wiki@gmail.com>
 * @since 1.27
 */
namespace TimedMediaHandler\TimedText;

use WikitextContent;

class Content extends WikiTextContent {

	function __construct( $text ) {
		parent::__construct( $text );
		$this->model_id = CONTENT_MODEL_TIMEDTEXT;
	}
}
